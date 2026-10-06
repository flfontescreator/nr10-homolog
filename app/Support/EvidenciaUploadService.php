<?php

namespace App\Support;

use App\Models\Evidence;
use App\Models\EvidenceSequence;
use App\Models\FuncionarioItem;
use App\Models\RncItem;
use App\Models\TenantItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Criação e remoção de evidências. Concentra as regras comuns a todos os
 * módulos: bloqueio de executáveis, nomenclatura do arquivo, caminho de
 * armazenamento por tenant, validade ("Se aplica") e metadados.
 */
class EvidenciaUploadService
{
    /**
     * Extensões bloqueadas por segurança (código executável).
     *
     * @var array<int, string>
     */
    protected const BLOCKED_EXTENSIONS = [
        'php', 'phar', 'exe', 'bat', 'cmd', 'com', 'msi', 'sh', 'bin',
        'dll', 'scr', 'pif', 'cpl', 'js', 'jsp',
    ];

    /**
     * Abertura de 3 letras do módulo no nome do arquivo.
     *
     * O prefixo é o da TELA do upload: `cronograma` em contexto de documento
     * (`?from=document`) usa `rnc`, no plano usa `crn`.
     *
     * `rnc` pertence ao NOVO módulo RNC (módulo independente de Não
     * Conformidades). Os documentos de Não Conformidades usavam o mesmo prefixo
     * antes da criação do RNC; uploads novos naquele módulo herdam `rnc` até que
     * aquele fluxo seja desativado — ver Fase 12 de `.ai/rules/decisions.md`.
     */
    public const MODULO_RNC = 'rnc';

    /** Documents de Não Conformidades (fluxo legado, em desativação). */
    public const MODULO_NC_LEGACY = 'rnc';

    public const MODULO_FUNCIONARIO = 'fun';

    public const MODULO_CRONOGRAMA = 'crn';

    public const MODULO_PRONTUARIO = 'prt';

    public static function guardSafeFile(UploadedFile $file): void
    {
        $ext = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));

        if ($ext !== '' && in_array($ext, self::BLOCKED_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'evidence' => 'Arquivos executáveis não são permitidos por segurança.',
            ]);
        }
    }

    /**
     * O checkbox "Se aplica" (campo `validade_aplica`) é a fonte da verdade da
     * validade da evidência em TODOS os módulos: desmarcado = documento sem
     * prazo (`null`); marcado = data obrigatória. É o mesmo comportamento do
     * box de upload (`partials/evidences`), que já nasce desmarcado.
     */
    public static function resolveValidade(Request $request, string $field = 'validade'): ?string
    {
        if (! $request->boolean('validade_aplica')) {
            return null;
        }

        $validade = $request->input('validade');

        if (! is_string($validade) || $validade === '') {
            throw ValidationException::withMessages([
                $field => 'Informe a data de validade ou desmarque "Se aplica".',
            ]);
        }

        return $validade;
    }

    /**
     * Nomenclatura do arquivo anexado — regra GLOBAL do sistema:
     *
     *   imagem            → img_{módulo}_{ddmmaaaa}_{sequencial}
     *   documento/PDF     → doc_{módulo}_{ddmmaaaa}_{sequencial}
     *
     * Ex.: `img_rnc_01102026_000000001.png`, `doc_prt_01102026_000000002.pdf`.
     *
     * O `sequencial` tem 9 dígitos e é GLOBAL: existe uma única sequência para
     * todas as imagens do sistema e outra para todos os documentos/PDF,
     * independentes do módulo e do cliente (tabela `evidence_sequences`).
     * Isso torna a ordem alfabética do nome equivalente à ordem de upload em
     * todo o sistema e impede que duas telas gerem o mesmo nome.
     *
     * O nome enviado pelo usuário é DESCARTADO de propósito: quem anexa apenas
     * escolhe o arquivo, quem nomeia é o sistema.
     */
    public static function storedFilename(UploadedFile $file, string $modulo): string
    {
        $categoria = str_starts_with((string) $file->getMimeType(), 'image/')
            ? EvidenceSequence::IMAGEM
            : EvidenceSequence::DOCUMENTO;

        $ext = strtolower($file->getClientOriginalExtension());

        if ($ext === '') {
            $ext = 'bin';
        }

        return sprintf(
            '%s_%s_%s_%09d.%s',
            $categoria,
            $modulo,
            now()->format('dmY'),
            EvidenceSequence::proximo($categoria),
            $ext,
        );
    }

    /**
     * @param  self::MODULO_*  $modulo  Abreviação de 3 letras do módulo de origem
     *                                  (a tela onde o anexo foi feito).
     */
    public static function storeForTenantItem(
        UploadedFile $file,
        TenantItem $item,
        User $user,
        ?string $description = null,
        ?string $validade = null,
        string $modulo = self::MODULO_CRONOGRAMA
    ): Evidence {
        self::guardSafeFile($file);

        $directory = 'evidences/tenant-'.$item->tenant_id;
        $filename = self::storedFilename($file, $modulo);

        $stored = $file->storeAs($directory, $filename, ['disk' => 'local']);

        return Evidence::create([
            'tenant_id' => $item->tenant_id,
            'tenant_item_id' => $item->id,
            'uploaded_by' => $user->id,
            'original_name' => $filename,
            'description' => $description,
            'validade' => $validade ?: null,
            'stored_path' => $stored,
            'disk' => 'local',
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
        ]);
    }

    public static function storeForFuncionarioItem(
        UploadedFile $file,
        FuncionarioItem $item,
        User $user,
        string $description,
        ?string $validade = null
    ): Evidence {
        self::guardSafeFile($file);

        $directory = 'evidences/tenant-'.$item->tenant_id;
        $filename = self::storedFilename($file, self::MODULO_FUNCIONARIO);

        $stored = $file->storeAs($directory, $filename, ['disk' => 'local']);

        return Evidence::create([
            'tenant_id' => $item->tenant_id,
            'funcionario_item_id' => $item->id,
            'uploaded_by' => $user->id,
            'original_name' => $filename,
            'description' => $description,
            'validade' => $validade ?: null,
            'stored_path' => $stored,
            'disk' => 'local',
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
        ]);
    }

    /**
     * Anexo de um item do RNC (módulo novo). A âncora é `evidences.rnc_item_id`
     * e o prefixo do arquivo é `rnc`.
     */
    public static function storeForRncItem(
        UploadedFile $file,
        RncItem $item,
        User $user,
        ?string $description = null,
        ?string $validade = null
    ): Evidence {
        self::guardSafeFile($file);

        $directory = 'evidences/tenant-'.$item->tenant_id;
        $filename = self::storedFilename($file, self::MODULO_RNC);

        $stored = $file->storeAs($directory, $filename, ['disk' => 'local']);

        return Evidence::create([
            'tenant_id' => $item->tenant_id,
            'rnc_item_id' => $item->id,
            'uploaded_by' => $user->id,
            'original_name' => $filename,
            'description' => $description,
            'validade' => $validade ?: null,
            'stored_path' => $stored,
            'disk' => 'local',
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
        ]);
    }

    /**
     * Remove o arquivo do disco e a linha. O chamador é responsável por
     * autorizar e checar vínculos que impedem a exclusão.
     */
    public static function deleteEvidence(Evidence $evidence): void
    {
        Storage::disk($evidence->disk ?? 'local')->delete($evidence->stored_path);

        $evidence->delete();
    }
}
