<?php

namespace App\Support\Rnc;

use App\Enums\RncStatus;
use App\Models\Rnc;
use App\Models\RncRevision;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Publicação do RNC. Existem duas ações:
 *
 * - `publish()`    → cria a PRÓXIMA revisão (`Rev:0002`, `Rev:0003`, ...) com
 *                    link próprio, congelando a anterior. É o "Publicar nova
 *                    revisão", para quando realmente se quer versionar.
 * - `republish()`  → REEMITE a revisão corrente (mesmo número, mesmo token de
 *                    link), é o "Atualizar publicação": a edição é livre e o
 *                    relatório público acompanha sem inflar a numeração.
 *
 * As duas geram Markdown, PDF e link público válido por 7 dias.
 */
class RncPublicationService
{
    public function __construct(
        protected RncMarkdownBuilder $markdown,
        protected RncPdfRenderer $pdf,
    ) {}

    public function publish(Rnc $rnc, ?User $user, ?string $notas = null): RncRevision
    {
        return $this->gravar($rnc, $user, $notas, novaRevisao: true);
    }

    public function republish(Rnc $rnc, ?User $user, ?string $notas = null): RncRevision
    {
        return $this->gravar($rnc, $user, $notas, novaRevisao: false);
    }

    private function gravar(Rnc $rnc, ?User $user, ?string $notas, bool $novaRevisao): RncRevision
    {
        return DB::transaction(function () use ($rnc, $user, $notas, $novaRevisao) {
            $existente = $rnc->revisions()->orderByDesc('revision')->first();
            $reemissao = ! $novaRevisao && $existente !== null;

            $revisao = $reemissao
                ? (int) $existente->revision
                : (int) $rnc->revisions()->max('revision') + 1;
            $label = Rnc::makeRevisionLabel($revisao);

            // Precisa estar no modelo ANTES de montar: o Markdown assina com o
            // rótulo da revisão corrente (senão a 1ª saía como "Rascunho").
            $rnc->current_revision = $revisao;

            $snapshot = $rnc->buildSnapshot();
            $markdown = $this->markdown->build($rnc, $snapshot);
            // Sem dompdf no servidor store() devolve null: a revisão nasce
            // com markdown + link + tela de impressão, sem PDF pré-gravado.
            $pdfPath = $this->pdf->store($rnc, $snapshot, $label, $revisao);

            if ($reemissao) {
                $existente->forceFill([
                    'snapshot' => $snapshot,
                    'markdown' => $markdown,
                    'pdf_path' => $pdfPath ?? $existente->pdf_path,
                    'notas' => $notas ?? $existente->notas,
                    'published_at' => now(),
                    'published_by' => $user?->id ?? $existente->published_by,
                    'public_expires_at' => Carbon::now()->addDays(RncRevision::PUBLIC_LINK_DAYS),
                ])->save();

                $publicada = $existente->fresh();
                $acao = 'rnc.republish';
                $descricao = sprintf('RNC %s reemitido como %s', $rnc->code, $label);
            } else {
                $publicada = $rnc->revisions()->create([
                    'tenant_id' => $rnc->tenant_id,
                    'rnc_id' => $rnc->id,
                    'revision' => $revisao,
                    'label' => $label,
                    'snapshot' => $snapshot,
                    'markdown' => $markdown,
                    'pdf_path' => $pdfPath,
                    'notas' => $notas,
                    'public_token' => (string) Str::uuid(),
                    'public_expires_at' => Carbon::now()->addDays(RncRevision::PUBLIC_LINK_DAYS),
                    'published_by' => $user?->id,
                    'published_at' => now(),
                ]);

                $acao = 'rnc.publish';
                $descricao = sprintf('RNC %s publicado como %s', $rnc->code, $label);
            }

            $rnc->forceFill([
                'status' => RncStatus::Publicado,
                'current_revision' => $revisao,
                'published_at' => now(),
                'published_by' => $user?->id ?? $rnc->published_by,
                'updated_by' => $user?->id ?? $rnc->updated_by,
            ])->save();

            Audit::record(
                $acao,
                $descricao,
                $rnc,
                null,
                [],
                ['revision' => $revisao, 'label' => $label],
                $user
            );

            return $publicada->fresh();
        });
    }

    /**
     * TRUE quando o relatório publicado está atrás do conteúdo atual: o que o
     * cliente vê no link/PDF só muda ao chamar `publish()` de novo.
     */
    public function hasPendingChanges(Rnc $rnc): bool
    {
        $revisao = $rnc->latestRevision();

        if (! $revisao) {
            return false;
        }

        return $this->markdown->build($rnc, $rnc->buildSnapshot()) !== $revisao->markdown;
    }
}
