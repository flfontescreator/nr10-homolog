<?php

namespace Database\Seeders;

use App\Enums\Source;
use App\Models\CatalogItem;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    protected array $codes = [];

    /**
     * Códigos importados por fonte (para limpeza de linhas obsoletas sem
     * invalidar os IDs — os tenant_items dependem dos IDs do catálogo).
     */
    protected array $sourceCodes = [];

    /**
     * Importa o conteúdo FIXO das planilhas (itens/subitens + termos técnicos + criticidade).
     * As colunas de controle (datas, status, percentuais, evidências...) não entram no catálogo.
     *
     * A matriz 2026 (matriz_nr10_2026.csv) completa o cronograma: cria itens/seções que
     * faltam, preenche norma/interpretação/sugestão/status e reclassifica criticidade e
     * setor pela versão vigente da NR-10.
     *
     * NÃO apaga a tabela antes de importar: os registros são atualizados pela chave
     * (source, code), preservando os IDs. Assim, os tenant_items que apontam para o
     * catálogo permanecem íntegros após reimportações. Apenas códigos que deixaram de
     * existir na planilha são removidos ao final.
     */
    public function run(): void
    {
        $this->importProntuario();
        $this->importCronograma();
        $this->importChecklist();
        $this->importMatrizCronograma();

        $this->removeStaleCodes();

        $this->markSections();
    }

    protected function importProntuario(): void
    {
        $rows = $this->readCsv(storage_path('app/imports/prontuario.csv'));

        foreach ($rows as $row) {
            // Pula título/preamble (linhas sem código no topo).
            $this->normalizeRow($row);
            $code = trim((string) $row[0]);

            if (! $this->validCode($code)) {
                continue;
            }

            $this->upsertItem(Source::Prontuario, $code, [
                'title' => $this->clean((string) ($row[1] ?? '')),
            ]);
        }
    }

    protected function importCronograma(): void
    {
        $rows = $this->readCsv(storage_path('app/imports/cronograma.csv'));

        // A planilha agora tem UM setor por linha; subitens com vários setores
        // aparecem em linhas repetidas. Agrupamos por código antes de gravar.
        $groups = [];

        // Linha 0: título; linha 1: cabeçalho; dados a partir da linha 2.
        foreach ($rows as $index => $row) {
            if ($index < 2) {
                continue;
            }

            $this->normalizeRow($row);
            $cell = $this->clean((string) ($row[1] ?? ''));

            // O código vem junto com a descrição na planilha: "10.3.1 No processo de ..."
            if (! preg_match('/^([\d]+(?:\.[\d]+)*)\s*(.*)$/s', $cell, $m)) {
                continue;
            }

            $code = $m[1];
            $setor = $this->clean((string) ($row[3] ?? ''));

            $groups[$code]['title'] ??= $this->clean($m[2]);
            $groups[$code]['criticidade'] ??= $this->clean((string) ($row[2] ?? ''));
            $groups[$code]['detalhamento'] ??= $this->clean((string) ($row[4] ?? ''));
            $groups[$code]['setores'][] = $setor;
        }

        foreach ($groups as $code => $group) {
            $group['setores'] = array_values(array_filter(array_unique(array_map(
                fn ($setor) => $this->clean((string) $setor),
                $group['setores'],
            ))));

            $this->upsertItem(Source::Cronograma, $code, $group);
        }
    }

    protected function importChecklist(): void
    {
        $rows = $this->readCsv(storage_path('app/imports/checklist.csv'));

        // A planilha agora tem UM setor por linha; itens com vários setores
        // aparecem em linhas repetidas. Agrupamos por código antes de gravar.
        $groups = [];

        // Linha 0: título; linha 1: cabeçalho; dados a partir da linha 2.
        foreach ($rows as $index => $row) {
            if ($index < 2) {
                continue;
            }

            $this->normalizeRow($row);
            $code = trim((string) ($row[0] ?? ''));

            if (! $this->validCode($code)) {
                continue;
            }

            $setor = $this->clean((string) ($row[3] ?? ''));

            $groups[$code]['title'] ??= $this->clean((string) ($row[1] ?? ''));
            $groups[$code]['criticidade'] ??= $this->clean((string) ($row[2] ?? ''));
            $groups[$code]['detalhamento'] ??= $this->clean((string) ($row[4] ?? ''));
            $groups[$code]['setores'][] = $setor;
        }

        foreach ($groups as $code => $group) {
            $group['setores'] = array_values(array_filter(array_unique(array_map(
                fn ($setor) => $this->clean((string) $setor),
                $group['setores'],
            ))));

            $this->upsertItem(Source::Checklist, $code, $group);
        }
    }

    /**
     * Matriz NR-10 2026 (Portaria MTE nº 737/2026, vigência 01/06/2027).
     *
     * Colunas: 0 capítulo/seção, 1 item, 2 norma técnica, 3 interpretação técnica,
     * 4 setor responsável sugerido, 5 criticidade, 6 sugestão de ação, 7 status.
     *
     * Regras do de-para:
     * - Itens/seções ausentes são criados (IDs novos; os existentes nunca são recriados).
     * - Norma técnica: a planilha prevalece (norma_tecnica literal + description do banco
     *   converge quando o texto normalizado difere — corrige textos contaminados/abreviados).
     * - Interpretação/sugestão/status: preenchem apenas o que está vazio (status inicial
     *   "Não iniciado"; seções ficam sem status).
     * - Criticidade e setor: a planilha 2026 prevalece (criticidade convertida para o
     *   vocabulário do sistema; setor sugerido dividido em setores[]).
     */
    protected function importMatrizCronograma(): void
    {
        $path = storage_path('app/imports/matriz_nr10_2026.csv');

        if (! is_file($path)) {
            return;
        }

        $rows = $this->readCsv($path);
        $sections = [];

        // Linha 0: cabeçalho; dados a partir da linha 1.
        foreach ($rows as $index => $row) {
            if ($index === 0) {
                continue;
            }

            $this->normalizeRow($row);
            $secao = $this->clean((string) ($row[0] ?? ''));

            if (preg_match('/^([\d]+(?:\.[\d]+)*)\s+(.+)$/u', $secao, $m)) {
                $sections[$m[1]] = $this->clean($m[2]);
            }

            $code = $this->clean((string) ($row[1] ?? ''));

            if (! $this->validCode($code)) {
                continue;
            }

            $this->upsertMatrizItem($code, [
                'norma' => trim((string) ($row[2] ?? '')),
                'interpretacao' => $this->clean((string) ($row[3] ?? '')),
                'setor' => $this->clean((string) ($row[4] ?? '')),
                'criticidade' => $this->clean((string) ($row[5] ?? '')),
                'sugestao' => $this->clean((string) ($row[6] ?? '')),
                'status' => $this->clean((string) ($row[7] ?? '')),
            ]);
        }

        // Seções que existem só na matriz (ex.: 10.1, 10.2) precisam de linha na
        // árvore; as já existentes são preservadas.
        foreach ($sections as $secCode => $secTitle) {
            $exists = CatalogItem::query()
                ->where('source', Source::Cronograma->value)
                ->where('code', $secCode)
                ->exists();

            if (! $exists) {
                $this->upsertItem(Source::Cronograma, $secCode, [
                    'title' => $secTitle,
                    'setores' => [],
                ]);
            } else {
                // Seção já existe: registra o código para removeStaleCodes()
                // não removê-la no reseed (cronograma.csv não a contém).
                $this->sourceCodes[Source::Cronograma->value][] = $secCode;
            }
        }
    }

    /**
     * Upsert de um item da matriz 2026 (ver regras em importMatrizCronograma()).
     */
    protected function upsertMatrizItem(string $code, array $matrix): void
    {
        $item = CatalogItem::firstOrNew([
            'source' => Source::Cronograma->value,
            'code' => $code,
        ]);

        $norma = $matrix['norma'];

        // Norma: planilha prevalece — description converge só quando o texto
        // normalizado difere (preserva o texto atual se apenas a formatação muda).
        if (! $item->exists || $this->normText((string) $item->description) !== $this->normText($norma)) {
            $item->description = $this->descFromNorma($norma);
            $item->title = mb_substr($item->description, 0, 250);
        }

        $item->norma_tecnica = $norma;

        // Preenche apenas o que está vazio (preserva edições posteriores).
        if (empty($item->interpretacao_tecnica)) {
            $item->interpretacao_tecnica = $matrix['interpretacao'] ?: null;
        }

        if (empty($item->sugestao_acao)) {
            $item->sugestao_acao = $matrix['sugestao'] ?: null;
        }

        if (empty($item->status)) {
            $item->status = $matrix['status'] ?: 'Não iniciado';
        }

        // Criticidade e setor: planilha 2026 prevalece.
        $item->criticidade = $this->mapCriticidade($matrix['criticidade']);

        $setores = $this->splitSetores($matrix['setor']);
        $item->setores = $setores ?: null;
        $item->setor = $setores[0] ?? null;

        $parts = array_slice(array_merge(explode('.', $code), [0, 0, 0, 0]), 0, 4);
        $item->n1 = (int) $parts[0];
        $item->n2 = (int) $parts[1];
        $item->n3 = (int) $parts[2];
        $item->n4 = (int) $parts[3];

        // sort não é tocado (linhas existentes mantêm o valor; novas usam o default 0,
        // igual ao restante do cronograma — os testes ordenam por sort).

        $this->sourceCodes[Source::Cronograma->value][] = $code;

        $item->save();
    }

    /**
     * Converte a criticidade da matriz para o vocabulário do sistema
     * (CronogramaOptions::criticidades): Crítica → GIR, Alta → ALTA, etc.
     */
    protected function mapCriticidade(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        return match (mb_strtolower($value)) {
            'crítica', 'critica' => 'Crítica / Grave e Iminente Risco (GIR)',
            'alta' => 'ALTA',
            'média', 'media' => 'MÉDIA',
            'baixa' => 'BAIXA',
            default => $value,
        };
    }

    /**
     * "SESMT / Engenharia Elétrica / SGI" → ['SESMT', 'Engenharia Elétrica', 'SGI'].
     */
    protected function splitSetores(string $setor): array
    {
        if ($setor === '') {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            fn (string $part) => $this->clean($part),
            explode('/', $setor),
        ))));
    }

    /**
     * Texto da norma sem o código inicial e com espaços normalizados (estilo do description).
     */
    protected function descFromNorma(string $norma): string
    {
        $text = trim($norma);
        $text = preg_replace('/^[\d]+(?:\.[\d]+)*\s*/u', '', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * Normalização para comparar textos da norma (código inicial, caixa e espaços).
     */
    protected function normText(string $value): string
    {
        return trim($this->descFromNorma(mb_strtolower($value)), " \t\n\r\0\x0B.,;:()");
    }

    protected function upsertItem(Source $source, string $code, array $extra): void
    {
        $parts = array_slice(array_merge(explode('.', $code), [0, 0, 0, 0]), 0, 4);

        $description = (string) ($extra['title'] ?? '');
        $setores = array_values(array_filter((array) ($extra['setores'] ?? [])));
        $setor = (string) ($extra['setor'] ?? '');

        if ($setor === '' && $setores !== []) {
            $setor = (string) ($setores[0] ?? '');
        }

        CatalogItem::updateOrCreate(
            ['source' => $source->value, 'code' => $code],
            [
                'n1' => (int) $parts[0],
                'n2' => (int) $parts[1],
                'n3' => (int) $parts[2],
                'n4' => (int) $parts[3],
                'title' => mb_substr($description, 0, 250),
                'description' => $description,
                'criticidade' => $extra['criticidade'] ?? null,
                'setor' => $setor ?: null,
                'setores' => $setores ?: null,
                'detalhamento' => $extra['detalhamento'] ?? null,
                'sort' => $this->codes[$source->value.'.'.$code] ?? 0,
            ]
        );

        $this->codes[$source->value.'.'.$code] = ($this->codes[$source->value.'.'.$code] ?? 0) + 1;
        $this->sourceCodes[$source->value][] = $code;
    }

    /**
     * Remove do catálogo apenas códigos que deixaram de existir nas planilhas.
     * Mantém íntegros os IDs das linhas que continuam na fonte (requisito para
     * não quebrar a ligação com os tenant_items).
     */
    protected function removeStaleCodes(): void
    {
        foreach (array_keys($this->sourceCodes) as $source) {
            $currentCodes = array_values(array_unique($this->sourceCodes[$source]));

            CatalogItem::query()
                ->where('source', $source)
                ->whereNotIn('code', $currentCodes)
                ->delete();
        }
    }

    /**
     * Sincroniza APENAS o catálogo NORMATIVO (Cronograma de Adequação + Matriz
     * NR-10 2026) a partir dos CSVs, sem tocar no catálogo operacional (Prontuário)
     * nem no checklist. Usado em produção para propagar a normativa atualizada sem
     * o risco de recriar itens operacionais que foram excluídos pelos clientes.
     */
    public function syncNormativa(): void
    {
        $this->importCronograma();
        $this->importMatrizCronograma();

        $codes = array_values(array_unique($this->sourceCodes[Source::Cronograma->value] ?? []));

        if ($codes !== []) {
            CatalogItem::query()
                ->where('source', Source::Cronograma->value)
                ->whereNotIn('code', $codes)
                ->delete();
        }

        $this->markSectionsFor(Source::Cronograma);
    }

    /**
     * Seção = nó que contém filhos. Ex.: "10.3" (cronograma) e "1..7" (prontuário)
     * agrupam subitens; um nó intermediário como "10.4.3" também agrupa "10.4.3.1".
     */
    protected function markSections(): void
    {
        foreach (Source::cases() as $source) {
            $this->markSectionsFor($source);
        }
    }

    protected function markSectionsFor(Source $source): void
    {
        $items = CatalogItem::query()->where('source', $source->value)->get();

        $codes = $items->pluck('code', 'id');

        $sections = $codes->filter(function (string $code) use ($codes) {
            return $codes->contains(
                fn (string $other) => $other !== $code && str_starts_with($other, $code.'.')
            );
        });

        $sections = $sections->mapWithKeys(fn ($c) => [$c => true]);

        foreach ($items as $item) {
            $segments = substr_count($item->code, '.') + 1;
            $isSection = ($sections[$item->code] ?? false) && in_array($segments, [1, 2], true);

            if ($item->is_section !== $isSection) {
                $item->is_section = $isSection;
                $item->save();
            }
        }
    }

    protected function readCsv(string $path): array
    {
        return self::csvRows($path);
    }

    public static function csvRows(string $path): array
    {
        $handle = fopen($path, 'r');
        $rows = [];

        while (($row = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    protected function normalizeRow(array &$row): void
    {
        // Remove BOM (assinatura UTF-8) do primeiro campo.
        $row[0] = str_replace("\xEF\xBB\xBF", '', (string) $row[0]);
    }

    protected function validCode(string $code): bool
    {
        return (bool) preg_match('/^[\d]+(?:\.[\d]+)*$/', $code);
    }

    protected function clean(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }
}
