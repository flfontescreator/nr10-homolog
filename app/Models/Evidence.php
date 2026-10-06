<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\Source;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

class Evidence extends Model
{
    use BelongsToTenant;

    protected $table = 'evidences';

    protected $fillable = [
        'tenant_id',
        'tenant_item_id',
        'funcionario_item_id',
        'rnc_item_id',
        'uploaded_by',
        'original_name',
        'description',
        'validade',
        'stored_path',
        'disk',
        'mime_type',
        'size_bytes',
    ];

    protected function casts(): array
    {
        return [
            'validade' => 'date',
        ];
    }

    /**
     * Dias que faltam para a validade vencer. Negativo quando já venceu, null
     * quando o arquivo não tem validade (nem todo documento tem).
     */
    public function daysUntilExpiry(): ?int
    {
        if (! $this->validade) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->validade->startOfDay(), false);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function tenantItem(): BelongsTo
    {
        return $this->belongsTo(TenantItem::class);
    }

    /**
     * Item de documentation do Funcionário (módulo próprio). Exatamente uma das
     * âncoras — tenantItem ou funcionarioItem — está preenchida.
     */
    public function funcionarioItem(): BelongsTo
    {
        return $this->belongsTo(FuncionarioItem::class);
    }

    /**
     * Item do RNC (módulo próprio e independente de Não Conformidades). É a
     * terceira âncora possível; exatamente uma das três fica preenchida.
     */
    public function rncItem(): BelongsTo
    {
        return $this->belongsTo(RncItem::class);
    }

    /**
     * Sub-itens de documentos aos quais este arquivo está vinculado na BIBLIOTECA
     * (pivô N:N evidence_document_item). O mesmo arquivo pode estar em vários
     * sub-itens, de um ou vários documentos.
     */
    public function documentItems(): BelongsToMany
    {
        return $this->belongsToMany(NcDocumentItem::class, 'evidence_document_item', 'evidence_id', 'nc_document_item_id')
            ->using(EvidenceDocumentItem::class)
            ->withTimestamps();
    }

    /**
     * Sub-itens do cronograma/plano aos quais este arquivo está vinculado na
     * BIBLIOTECA (pivô N:N evidence_tenant_item). A âncora tenant_item_id é
     * apenas a origem; o card do plano é definido por este vínculo.
     */
    public function tenantItems(): BelongsToMany
    {
        return $this->belongsToMany(TenantItem::class, 'evidence_tenant_item', 'evidence_id', 'tenant_item_id')
            ->using(EvidenceTenantItem::class)
            ->withTimestamps();
    }

    /**
     * Documentos DN aos quais este arquivo está vinculado na BIBLIOTECA
     * (vínculo N:N via evidence_document). É a fonte dos badges de
     * "Documento de referência" em Gestão de Documentos.
     */
    public function documents(): BelongsToMany
    {
        return $this->belongsToMany(NcDocument::class, 'evidence_document', 'evidence_id', 'document_id')
            ->using(EvidenceDocument::class)
            ->withTimestamps();
    }

    /**
     * Uma evidência vinculada a documento finalizado não pode ser removida:
     * checa o badge do documento (evidence_document) ou qualquer sub-item do
     * documento (evidence_document_item) cujo DN esteja finalizado.
     */
    public function linkedToFinalizedDocument(): bool
    {
        if ($this->documents()->where('status', DocumentStatus::Finalized->value)->exists()) {
            return true;
        }

        return $this->documentItems()
            ->whereHas('document', fn ($q) => $q->where('status', DocumentStatus::Finalized->value))
            ->exists();
    }

    /**
     * Evidência ancorada em um RNC já publicado também não pode ser removida:
     * a revisão oficial é imutável e o PDF publicado ao cliente já cita o
     * arquivo. Mesma regra de `RncItem::lockedByPublication()`.
     */
    public function linkedToPublishedRnc(): bool
    {
        return (bool) $this->rncItem?->lockedByPublication();
    }

    /**
     * O arquivo ainda tem algum vínculo (badge de DN, sub-item de documento ou
     * sub-item do plano)? Quando falso após um desvínculo, o arquivo pode ser
     * apagado (órfão) sem risco.
     */
    public function hasAnyLink(): bool
    {
        return EvidenceDocument::query()
            ->where('evidence_id', $this->id)
            ->exists()
            || $this->documentItems()->exists()
            || $this->tenantItems()->exists();
    }

    /**
     * Itens do cronograma/plano (catalog items) aos quais este arquivo está
     * relacionado, para os badges da coluna "Item" em Gestão de Documentos.
     * Começa pela âncora e recebe os vínculos dos pivôs, sem duplicar.
     */
    public function relatedItems(): Collection
    {
        return collect()
            ->merge($this->documentItems->map(fn ($di) => $di->tenantItem?->catalogItem))
            ->merge($this->tenantItems->map(fn ($ti) => $ti->catalogItem))
            ->push($this->tenantItem?->catalogItem)
            ->filter()
            ->unique('id')
            ->values();
    }

    /**
     * Módulo de origem do arquivo, para a coluna "Módulo" da Gestão de
     * Documentos. Itens de funcionário pertencem ao módulo Funcionários e os itens
     * de RNC ao módulo RNC.
     */
    public function modulo(): string
    {
        if ($this->rnc_item_id !== null) {
            return 'rnc';
        }

        if ($this->funcionario_item_id !== null) {
            return 'funcionarios';
        }

        $source = $this->tenantItem?->catalogItem?->source?->value;

        return $source === Source::Prontuario->value ? 'prontuario' : (string) $source;
    }

    /**
     * Rótulos dos itens relacionados, cobrindo catálogo, itens de funcionário e
     * itens de RNC.
     */
    public function relatedItemLabels(): Collection
    {
        if ($this->rnc_item_id !== null) {
            return collect()
                ->merge([$this->rncItem?->titulo])
                ->filter();
        }

        if ($this->funcionario_item_id !== null) {
            return collect()
                ->merge([$this->funcionarioItem?->getDisplayLabelAttribute()])
                ->filter();
        }

        return $this->relatedItems()->map(fn ($item) => $item->code);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->size_bytes;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }

        return round($bytes / 1024, 1).' KB';
    }

    /**
     * Nome de exibição para listagens compactas: preserva o começo do nome,
     * acrescenta reticências e mantém a extensão original, ex.:
     * "relatorio-tecnico-ade....pdf". Nomes curtos retornam intactos.
     */
    public function displayName(int $maxLength = 40): string
    {
        if (empty($this->original_name)) {
            return '';
        }

        if (mb_strlen($this->original_name) <= $maxLength) {
            return $this->original_name;
        }

        $extension = pathinfo($this->original_name, PATHINFO_EXTENSION);
        $suffix = $extension !== '' ? ".{$extension}" : '';
        $maxStem = max(1, $maxLength - mb_strlen('...') - mb_strlen($suffix));
        $stem = mb_substr($this->original_name, 0, $maxStem);

        return rtrim($stem, ' .').'...'.$suffix;
    }
}
