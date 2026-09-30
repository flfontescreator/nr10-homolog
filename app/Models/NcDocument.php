<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Documento de Não Conformidades: uma seleção de itens do Cronograma de Adequação
 * que passam a ser as não conformidades do cliente. Cada cliente pode ter
 * vários documentos (RNC-00001, RNC-00002, ...), cada um com histórico de versões.
 */
class NcDocument extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'number',
        'code',
        'title',
        'description',
        'status',
        'finalized_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'finalized_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(NcDocumentItem::class, 'document_id')
            ->orderBy(CatalogItem::select('source')->whereColumn('catalog_items.id', 'nc_document_items.catalog_item_id'))
            ->orderBy(CatalogItem::select('n1')->whereColumn('catalog_items.id', 'nc_document_items.catalog_item_id'))
            ->orderBy(CatalogItem::select('n2')->whereColumn('catalog_items.id', 'nc_document_items.catalog_item_id'))
            ->orderBy(CatalogItem::select('n3')->whereColumn('catalog_items.id', 'nc_document_items.catalog_item_id'))
            ->orderBy(CatalogItem::select('n4')->whereColumn('catalog_items.id', 'nc_document_items.catalog_item_id'))
            ->orderBy('sort_order');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(NcDocumentVersion::class, 'document_id')->latest('version');
    }

    /**
     * Arquivos da biblioteca vinculados a este documento (vínculo N:N via
     * evidence_document). Usado tanto no picker de edição quanto na listagem
     * de "arquivos vinculados" — junto com o contexto do subitem quando a
     * evidência também foi anexada a um item do documento.
     */
    public function libraryFiles(): BelongsToMany
    {
        return $this->belongsToMany(Evidence::class, 'evidence_document', 'document_id', 'evidence_id')
            ->using(EvidenceDocument::class)
            ->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isFinalized(): bool
    {
        return $this->status->isFinalized();
    }

    public function getIsFinalizedAttribute(): bool
    {
        return $this->isFinalized();
    }

    public static function makeCode(int $number): string
    {
        return 'RNC-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
    }

    public static function nextNumber(int $tenantId): int
    {
        return (int) self::query()->where('tenant_id', $tenantId)->max('number') + 1;
    }

    /**
     * Snapshot completo do documento em um ponto no tempo: a lista de itens
     * selecionados com os dados fixos do catálogo e o estado operacional
     * POR DOCUMENTO daquele momento (independente do cronograma).
     */
    public function buildSnapshot(): array
    {
        return $this->items()
            ->with(['catalogItem'])
            ->orderBy('sort_order')
            ->get()
            ->map(function (NcDocumentItem $entry) {
                $catalog = $entry->catalogItem;

                return [
                    'code' => $entry->code ?: ($catalog?->code ?? '—'),
                    'title' => $entry->title ?: ($catalog?->title ?? '—'),
                    'criticidade' => $entry->criticidade ?: $catalog?->criticidade,
                    'setores' => $entry->setores_list,
                    'status' => $entry->status?->value,
                    'condicao_inicial' => $entry->condicao_inicial,
                    'data_inspecao' => $entry->data_inspecao?->format('Y-m-d'),
                    'descricao_nc' => $entry->descricao_nc,
                    'id_relatorio' => $entry->id_relatorio,
                    'prazo_adequacao' => $entry->prazo_adequacao?->format('Y-m-d'),
                    'acao' => $entry->acao,
                    'acao_realizada' => $entry->acao_realizada,
                    'data_realizacao' => $entry->data_realizacao?->format('Y-m-d'),
                    'responsavel' => $entry->responsavel,
                ];
            })
            ->all();
    }

    /**
     * Grava a próxima versão do histórico com o estado atual da seleção.
     */
    public function recordVersion(?string $summary = null, ?int $userId = null): NcDocumentVersion
    {
        $next = (int) $this->versions()->max('version') + 1;

        return NcDocumentVersion::create([
            'document_id' => $this->id,
            'version' => $next,
            'selection' => $this->buildSnapshot(),
            'summary' => $summary,
            'created_by' => $userId,
        ]);
    }
}
