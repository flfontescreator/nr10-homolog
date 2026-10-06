<?php

namespace App\Support;

use App\Enums\ItemStatus;
use App\Enums\Source;
use App\Models\Evidence;
use App\Models\NcDocumentItem;
use App\Models\Tenant;
use App\Models\TenantItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Estatísticas consolidadas do dashboard do cliente: KPIs, séries dos
 * gráficos, alertas de prazo, previsões heurísticas e indicadores
 * secundários do plano. É a única fonte de verdade do dashboard —
 * usada por DashboardController::index() (render do HTML) e ::stats()
 * (JSON da telemetria que recalcula a cada 60s), para nunca divergirem.
 *
 * Base das métricas de NC = `nc_document_items` com `tenant_item_id`
 * preenchido (subitens; seções/capas ficam de fora) dos documentos do
 * tenant. Criticidade/seções seguem as regras das fases 1/5/7.
 */
class DashboardStats
{
    /** Ordem fixa dos status no gráfico de pizza (derivada "Atrasada" por prazo). */
    private const STATUS_LABELS = ['Pendente', 'Em andamento', 'Auditoria', 'Atrasada', 'Concluído'];

    private const CRIT_LABELS = ['Alta', 'Média', 'Baixa'];

    private const PRAZO_LABELS = ['Vencidos', 'Até 7 dias', 'Até 30 dias', 'Demais / sem prazo'];

    /** ALTA + GIR contam como criticidade alta (riscos de maior severidade). */
    private const CRIT_ALTAS = ['ALTA', 'Crítica / Grave e Iminente Risco (GIR)'];

    private const MONTHS = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

    private Collection $items;

    private function __construct(private readonly Tenant $tenant)
    {
        $this->items = NcDocumentItem::query()
            ->whereHas('document', fn ($q) => $q->where('tenant_id', $tenant->id))
            ->whereNotNull('tenant_item_id')
            ->with(['catalogItem', 'document:id,code'])
            ->get();
    }

    public static function for(Tenant $tenant): array
    {
        return (new self($tenant))->payload();
    }

    private function payload(): array
    {
        $today = Carbon::today();
        $now = Carbon::now();
        $items = $this->items;
        $open = $items->reject(fn (NcDocumentItem $i) => $this->isConcluido($i))->values();

        $total = $items->count();
        $conformidade = $total > 0 ? round($items->filter(fn (NcDocumentItem $i) => $this->isConcluido($i))->count() / $total * 100, 1) : 0.0;

        $statusCount = $items->countBy(fn (NcDocumentItem $i) => $this->statusBucket($i, $today));
        $atraso = $statusCount->get('Atrasada', 0);
        $alta = $open->filter(fn (NcDocumentItem $i) => $this->criticidadeBucket($i) === 'Alta')->count();

        [$deltaTotal, $deltaConf, $deltaAtraso, $deltaAlta] = $this->deltas($now, $total, $conformidade, $atraso, $alta);

        $evolucao = $this->evolucao($now);
        $previsao = $this->previsao($open, $conformidade, $evolucao, $today);

        return [
            // `now()` já vem em America/Sao_Paulo (config/app.php) — não converter de novo.
            'generated_at' => $now->format('d/m/Y H:i:s'),
            'kpi' => [
                'total' => $total,
                'total_delta' => $deltaTotal,
                'conformidade' => $conformidade,
                'conformidade_delta' => $deltaConf,
                'atraso' => $atraso,
                'atraso_delta' => $deltaAtraso,
                'alta' => $alta,
                'alta_delta' => $deltaAlta,
            ],
            'charts' => [
                'status' => [
                    'labels' => self::STATUS_LABELS,
                    'values' => array_map(fn (string $l) => $statusCount->get($l, 0), self::STATUS_LABELS),
                ],
                'criticidade' => [
                    'labels' => self::CRIT_LABELS,
                    'values' => array_map(
                        fn (string $l) => $open->filter(fn (NcDocumentItem $i) => $this->criticidadeBucket($i) === $l)->count(),
                        self::CRIT_LABELS
                    ),
                ],
                'evolucao' => $evolucao,
                'prazos' => [
                    'labels' => self::PRAZO_LABELS,
                    'values' => array_map(
                        fn (string $l) => $open->filter(fn (NcDocumentItem $i) => $this->prazoBucket($i, $today) === $l)->count(),
                        self::PRAZO_LABELS
                    ),
                ],
            ],
            'previsao' => $previsao,
            'alertas' => $this->alertas($open, $today),
            'setores' => $this->porSetor($items),
            'responsaveis' => $this->porResponsavel($items),
            'ncs' => [
                'pendencia' => $this->ncCounts(
                    $open->reject(fn (NcDocumentItem $i) => $i->prazo_adequacao && $i->prazo_adequacao->lt($today))->values()
                ),
                'ativas' => $this->ncCounts(
                    $open->filter(fn (NcDocumentItem $i) => $i->prazo_adequacao && $i->prazo_adequacao->lt($today))->values()
                ),
            ],
            'secundarios' => $this->secundarios(),
            'recentEvidences' => Evidence::query()
                ->where('tenant_id', $this->tenant->id)
                ->with(['tenantItem.catalogItem', 'uploader'])
                ->latest()
                ->limit(6)
                ->get(),
        ];
    }

    /**
     * Deltas dos KPIs vs. o mês anterior (null quando não há base:
     * nenhum item criado até o fim do mês anterior).
     *
     * @return array{0: ?int, 1: ?float, 2: ?int, 3: ?int}
     */
    private function deltas(Carbon $now, int $total, float $conformidade, int $atraso, int $alta): array
    {
        $prevEnd = $now->copy()->subMonthNoOverflow()->endOfMonth();
        $prevTotal = $this->items->filter(fn (NcDocumentItem $i) => $i->created_at && $i->created_at->lte($prevEnd))->count();

        if ($prevTotal === 0) {
            return [null, null, null, null];
        }

        $prevConcluidas = $this->items->filter(fn (NcDocumentItem $i) => ($c = $this->concludedAt($i)) && $c->lte($prevEnd))->count();
        $prevConf = round($prevConcluidas / $prevTotal * 100, 1);
        $prevOpen = $this->openAsOf($prevEnd);
        $prevAtraso = $prevOpen->filter(fn (NcDocumentItem $i) => $i->prazo_adequacao && $i->prazo_adequacao->lte($prevEnd))->count();
        $prevAlta = $prevOpen->filter(fn (NcDocumentItem $i) => $this->criticidadeBucket($i) === 'Alta')->count();

        return [
            $total - $prevTotal,
            round($conformidade - $prevConf, 1),
            $atraso - $prevAtraso,
            $alta - $prevAlta,
        ];
    }

    /**
     * Série de conformidade (%) dos últimos 6 meses, reconstruída por
     * timestamps (não há histórico de status): para o fim de cada mês,
     * concluídos até lá / criados até lá.
     *
     * @return array{labels: array<int, string>, values: array<int, float>}
     */
    private function evolucao(Carbon $now): array
    {
        $labels = [];
        $values = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = $now->copy()->subMonthsNoOverflow($i);
            $end = $month->copy()->endOfMonth();
            $labels[] = self::MONTHS[(int) $month->format('n') - 1];

            $total = $this->items->filter(fn (NcDocumentItem $it) => $it->created_at && $it->created_at->lte($end))->count();
            $done = $this->items->filter(fn (NcDocumentItem $it) => ($c = $this->concludedAt($it)) && $c->lte($end))->count();

            $values[] = $total > 0 ? round($done / $total * 100, 1) : 0.0;
        }

        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * Previsões heurísticas (não estatísticas): projeção de conformidade
     * pela velocidade dos últimos 90 dias, NCs em risco nos próximos 7
     * dias e tendência da série de 6 meses (menor quadrados).
     *
     * @return array{projecao30: float, projecao60: float, projecao90: float, risco7: int, tendencia: string}
     */
    private function previsao(Collection $open, float $conformidade, array $evolucao, Carbon $today): array
    {
        $total = $this->items->count();
        $limit90 = $today->copy()->subDays(90);
        $concl90 = $this->items->filter(fn (NcDocumentItem $i) => ($c = $this->concludedAt($i)) && $c->gte($limit90))->count();
        $ppMes = $total > 0 ? ($concl90 / 3) / $total * 100 : 0.0;

        $proj = fn (int $m): float => $total > 0 ? round(min(100, $conformidade + $ppMes * $m), 1) : 0.0;

        $risco7 = $open->filter(fn (NcDocumentItem $i) => $i->prazo_adequacao && $i->prazo_adequacao->lte($today->copy()->addDays(7)))->count();

        $slope = $this->slope($evolucao['values']);

        return [
            'projecao30' => $proj(1),
            'projecao60' => $proj(2),
            'projecao90' => $proj(3),
            'risco7' => $risco7,
            'tendencia' => $slope > 0.5 ? 'alta' : ($slope < -0.5 ? 'queda' : 'estavel'),
        ];
    }

    /**
     * NCs abertas com prazo, da mais urgente para a menos (máx. 8).
     *
     * @return array<int, array<string, mixed>>
     */
    private function alertas(Collection $open, Carbon $today): array
    {
        return $open
            ->filter(fn (NcDocumentItem $i) => $i->prazo_adequacao)
            ->sortBy('prazo_adequacao')
            ->take(8)
            ->map(function (NcDocumentItem $i) use ($today) {
                $prazo = $i->prazo_adequacao;
                $nivel = match (true) {
                    $prazo->lt($today) => 'vencido',
                    $prazo->lte($today->copy()->addDays(7)) => 'critico',
                    $prazo->lte($today->copy()->addDays(30)) => 'atencao',
                    default => 'ok',
                };

                return [
                    'id' => $i->id,
                    'documento' => $i->document?->code ?? '—',
                    'codigo' => $i->catalogItem?->code ?? '—',
                    'descricao' => $i->descricao_nc ?: $i->catalogItem?->title_or_code,
                    'prazo' => $prazo->format('d/m/Y'),
                    'criticidade' => $i->criticidade_atual,
                    'nivel' => $nivel,
                ];
            })
            ->all();
    }

    /**
     * Agregação por setor (item pode ter vários setores).
     *
     * @return array<int, array<string, int|float|string>>
     */
    private function porSetor(Collection $items): array
    {
        $rows = [];

        foreach ($items as $item) {
            $setores = $item->setores_list ?: ['Não definido'];

            foreach ($setores as $setor) {
                $nome = trim((string) $setor) ?: 'Não definido';
                $rows[$nome] ??= ['nome' => $nome, 'total' => 0, 'concluidas' => 0, 'percentual' => 0.0];
                $rows[$nome]['total']++;

                if ($this->isConcluido($item)) {
                    $rows[$nome]['concluidas']++;
                }
            }
        }

        return $this->finishRows($rows);
    }

    /**
     * Agregação por responsável (sem responsável = "Não atribuído").
     *
     * @return array<int, array<string, int|float|string>>
     */
    private function porResponsavel(Collection $items): array
    {
        $rows = [];

        foreach ($items as $item) {
            $nome = trim((string) ($item->responsavel ?? '')) ?: 'Não atribuído';
            $rows[$nome] ??= ['nome' => $nome, 'total' => 0, 'concluidas' => 0, 'percentual' => 0.0];
            $rows[$nome]['total']++;

            if ($this->isConcluido($item)) {
                $rows[$nome]['concluidas']++;
            }
        }

        return $this->finishRows($rows);
    }

    /**
     * @param  array<string, array{nome: string, total: int, concluidas: int, percentual: float}>  $rows
     * @return array<int, array{nome: string, total: int, concluidas: int, percentual: float}>
     */
    private function finishRows(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['percentual'] = $row['total'] > 0 ? round($row['concluidas'] / $row['total'] * 100, 1) : 0.0;
        }
        unset($row);

        usort($rows, fn (array $a, array $b) => $b['total'] <=> $a['total'] ?: strcmp($a['nome'], $b['nome']));

        return $rows;
    }

    /** Indicadores secundários do plano e da biblioteca (cards antigos). */
    private function secundarios(): array
    {
        $porSource = fn (Source $source): int => TenantItem::query()
            ->where('tenant_items.tenant_id', $this->tenant->id)
            ->whereHas('catalogItem', fn ($q) => $q->where('source', $source->value))
            ->count();

        $comAcao = fn (Source $source): int => TenantItem::query()
            ->where('tenant_items.tenant_id', $this->tenant->id)
            ->whereHas('catalogItem', fn ($q) => $q->where('source', $source->value))
            ->whereNotNull('acao')
            ->count();

        $media = TenantItem::query()
            ->where('tenant_items.tenant_id', $this->tenant->id)
            ->join('catalog_items', 'catalog_items.id', '=', 'tenant_items.catalog_item_id')
            ->where('catalog_items.source', Source::Prontuario->value)
            ->whereNotNull('tenant_items.percentual')
            ->avg('tenant_items.percentual');

        return [
            'cronogramaTotal' => $porSource(Source::Cronograma),
            'cronogramaComAcao' => $comAcao(Source::Cronograma),
            'prontuarioTotal' => $porSource(Source::Prontuario),
            'prontuarioMedia' => $media === null ? null : round((float) $media, 1),
            'checklistTotal' => $porSource(Source::Checklist),
            'checklistComAcao' => $comAcao(Source::Checklist),
            'evidenciasCount' => Evidence::query()->where('tenant_id', $this->tenant->id)->count(),
        ];
    }

    private function isConcluido(NcDocumentItem $item): bool
    {
        return $item->status === ItemStatus::Concluido;
    }

    /**
     * Contagem de NCs em aberto por catálogo para o bloco "pré-NC vs NC
     * ativa" do dashboard. A comparação com o enum só ocorre quando o
     * item é subitem (tem catálogo vinculado).
     *
     * @param  Collection<int, NcDocumentItem>  $items
     * @return array{total: int, normativa: int, operacional: int}
     */
    private function ncCounts(Collection $items): array
    {
        return [
            'total' => $items->count(),
            'normativa' => $items->filter(fn (NcDocumentItem $i) => ($i->catalogItem?->source ?? $i->source) === Source::Cronograma)->count(),
            'operacional' => $items->filter(fn (NcDocumentItem $i) => ($i->catalogItem?->source ?? $i->source) === Source::Prontuario)->count(),
        ];
    }

    /** Data de conclusão efetiva (para reconstruir séries históricas). */
    private function concludedAt(NcDocumentItem $item): ?Carbon
    {
        if (! $this->isConcluido($item)) {
            return null;
        }

        return $item->data_realizacao ?? $item->updated_at;
    }

    /**
     * Bucket do status: concluído > atrasada (prazo vencido) > status
     * próprio (nulo = Pendente).
     */
    private function statusBucket(NcDocumentItem $item, Carbon $today): string
    {
        if ($this->isConcluido($item)) {
            return 'Concluído';
        }

        if ($item->prazo_adequacao && $item->prazo_adequacao->lt($today)) {
            return 'Atrasada';
        }

        return $item->status?->value ?? 'Pendente';
    }

    private function criticidadeBucket(NcDocumentItem $item): string
    {
        $criticidade = $item->criticidade_atual;

        if (in_array($criticidade, self::CRIT_ALTAS, true)) {
            return 'Alta';
        }

        if ($criticidade === 'MÉDIA') {
            return 'Média';
        }

        return 'Baixa';
    }

    private function prazoBucket(NcDocumentItem $item, Carbon $today): string
    {
        $prazo = $item->prazo_adequacao;

        if (! $prazo) {
            return 'Demais / sem prazo';
        }

        if ($prazo->lt($today)) {
            return 'Vencidos';
        }

        if ($prazo->lte($today->copy()->addDays(7))) {
            return 'Até 7 dias';
        }

        if ($prazo->lte($today->copy()->addDays(30))) {
            return 'Até 30 dias';
        }

        return 'Demais / sem prazo';
    }

    /** Itens ainda em aberto num momento passado (para deltas históricos). */
    private function openAsOf(Carbon $moment): Collection
    {
        return $this->items->filter(function (NcDocumentItem $item) use ($moment) {
            if (! $item->created_at || $item->created_at->gt($moment)) {
                return false;
            }

            $done = $this->concludedAt($item);

            return ! ($done && $done->lte($moment));
        });
    }

    /** Inclinação da reta (menor quadrados) da série; > 0 = crescendo. */
    private function slope(array $values): float
    {
        $n = count($values);

        if ($n < 2) {
            return 0.0;
        }

        $xMean = ($n - 1) / 2;
        $yMean = array_sum($values) / $n;
        $sxy = 0.0;
        $sxx = 0.0;

        foreach ($values as $x => $y) {
            $sxy += ($x - $xMean) * ($y - $yMean);
            $sxx += ($x - $xMean) ** 2;
        }

        return $sxx > 0 ? $sxy / $sxx : 0.0;
    }
}
