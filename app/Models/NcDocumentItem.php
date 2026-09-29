<?php

namespace App\Models;

use App\Enums\ItemStatus as Status;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Um item selecionado de um documento de não conformidades.
 * Mantém o estado de trabalho POR DOCUMENTO (status, datas, setores,
 * criticidade...) — independente do TenantItem compartilhado, que é a âncora
 * do cronograma. Assim cada documento pode trabalhar o mesmo subitem em
 * ciclos distintos sem se afetarem.
 */
class NcDocumentItem extends Model
{
    protected $fillable = [
        'document_id',
        'catalog_item_id',
        'tenant_item_id',
        'sort_order',
        'updated_by',
        'data_inspecao',
        'condicao_inicial',
        'setor',
        'setores',
        'criticidade',
        'descricao_nc',
        'id_relatorio',
        'prazo_adequacao',
        'acao',
        'acao_realizada',
        'data_realizacao',
        'responsavel',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'data_inspecao' => 'date',
            'prazo_adequacao' => 'date',
            'data_realizacao' => 'date',
            'setores' => 'array',
            'status' => Status::class,
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(NcDocument::class);
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    public function tenantItem(): BelongsTo
    {
        return $this->belongsTo(TenantItem::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function evidences(): BelongsToMany
    {
        return $this->belongsToMany(Evidence::class, 'evidence_document_item', 'nc_document_item_id', 'evidence_id')
            ->using(EvidenceDocumentItem::class)
            ->withTimestamps();
    }

    /**
     * Setores efetivos do item no documento, em três estados:
     *  - `[...]` trabalhados no documento (usar como está);
     *  - `[]` zerado de propósito (mostrar vazio, NÃO voltar ao catálogo);
     *  - `null` não tocado → padrão: os do catálogo (ou o setor simples).
     */
    public function getSetoresListAttribute(): array
    {
        if (! is_null($this->setores)) {
            return $this->setores;
        }

        if (! empty($this->setor)) {
            return [$this->setor];
        }

        return $this->catalogItem?->setores_list ?: [];
    }

    /**
     * Criticidade efetiva do item no documento: a trabalhada ou, na ausência,
     * a fixa do catálogo.
     */
    public function getCriticidadeAtualAttribute(): ?string
    {
        return $this->criticidade ?: $this->catalogItem?->criticidade;
    }

    /**
     * Criticidade para os grids (documento de não conformidades): a trabalhada
     * neste documento; na ausência, a fixada no plano (TenantItem); por último,
     * a do catálogo. Espelha a regra do cronograma — o badge reflete o valor
     * efetivo em vez do fixo do catálogo.
     */
    public function getCriticidadeEfetivaAttribute(): ?string
    {
        return $this->criticidade ?: ($this->tenantItem?->criticidade ?: $this->catalogItem?->criticidade);
    }

    public function auditLabel(): string
    {
        $catalog = $this->catalogItem;

        return $catalog
            ? $catalog->code.' — '.$catalog->title
            : 'Item #'.$this->id;
    }
}
