<?php

namespace App\Support\Rnc;

use App\Models\Rnc;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Geração do PDF do RNC a partir de um Blade próprio (dompdf). O arquivo fica
 * em `rnc/tenant-{id}/` com o código e a revisão no nome, ex.:
 * `RNC_0001_Rev0001.pdf`.
 *
 * Se o dompdf não estiver disponível no servidor, a tela `rnc.revision.print`
 * entrega a mesma página em HTML com CSS de impressão (`window.print()`).
 */
class RncPdfRenderer
{
    protected const PAPER = 'a4';

    public function available(): bool
    {
        return class_exists(Pdf::class);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function render(Rnc $rnc, array $snapshot, string $revisionLabel): string
    {
        return Pdf::loadView('rnc.pdf', [
            'rnc' => $rnc,
            'snapshot' => $snapshot,
            'revisionLabel' => $revisionLabel,
            'embedImages' => true,
        ])
            ->setPaper(self::PAPER)
            ->setOption('isRemoteEnabled', false)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isPhpEnabled', true)
            ->output();
    }

    /**
     * Grava o PDF da revisão e devolve o caminho relativo no disco `local`.
     * Sem dompdf disponível no servidor devolve `null`: o `pdf_path` da
     * revisão fica nulo e a publicação segue com markdown, link público e a
     * tela de impressão (que gera o PDF pelo navegador).
     *
     * @param  array<string, mixed>  $snapshot
     */
    public function store(Rnc $rnc, array $snapshot, string $revisionLabel, int $revision): ?string
    {
        if (! $this->available()) {
            return null;
        }

        $path = self::pathFor($rnc, $revision);

        Storage::disk('local')->put($path, $this->render($rnc, $snapshot, $revisionLabel));

        return $path;
    }

    public static function filenameFor(Rnc $rnc, int $revision): string
    {
        return sprintf('%s_Rev%04d.pdf', $rnc->code, $revision);
    }

    public static function pathFor(Rnc $rnc, int $revision): string
    {
        return sprintf('rnc/tenant-%d/%s', $rnc->tenant_id, self::filenameFor($rnc, $revision));
    }
}
