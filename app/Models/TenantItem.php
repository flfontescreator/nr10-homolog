<?php

namespace App\Models;

use App\Enums\ItemStatus as Status;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Registro de controle de um subitem do catálogo para um cliente (tenant).
 * Uma linha por (tenant_id, catalog_item_id) — criada automaticamente na criação do cliente.
 */
class TenantItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'funcionario_id',
        'catalog_item_id',
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
        'evidencias_status',
        'data_validade',
        'percentual',
        'comentarios',
        'prazo_execucao',
    ];

    protected function casts(): array
    {
        return [
            'data_inspecao' => 'date',
            'prazo_adequacao' => 'date',
            'data_realizacao' => 'date',
            'prazo_execucao' => 'date',
            'data_validade' => 'date',
            'percentual' => 'decimal:2',
            'setores' => 'array',
            'status' => Status::class,
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class, 'catalog_item_id');
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class);
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Rótulo amigável usado na auditoria.
     */
    public function auditLabel(): string
    {
        $catalog = $this->catalogItem;

        return $catalog
            ? $catalog->code.' — '.$catalog->title
            : 'Item #'.$this->id;
    }

    /**
     * Setores efetivos do subitem, em três estados:
     *  - `[...]` personalizados pelo cliente (usar como está);
     *  - `[]` zerado de propósito (mostrar vazio, NÃO voltar ao catálogo);
     *  - `null` não tocado → padrão de exibição: o setor fixo do catálogo.
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
     * Criticidade efetiva do subitem: a definida pelo cliente ou, na ausência,
     * a criticidade fixa herdada do catálogo.
     */
    public function getCriticidadeAtualAttribute(): ?string
    {
        return $this->criticidade ?: $this->catalogItem?->criticidade;
    }

    /**
     * Média Geral: média dos percentuais dos subitens filhos (renda calculada, não armazenada).
     * $funcionarioScope: null = todos; 'none' = só itens por tenant (funcionario_id nulo);
     * 'any' = só itens de funcionários (item 4 do prontuário).
     */
    public static function averagePercent(int $tenantId, string $source, int $n1, ?string $funcionarioScope = null): ?float
    {
        $values = self::query()
            ->where('tenant_items.tenant_id', $tenantId)
            ->join('catalog_items', 'catalog_items.id', '=', 'tenant_items.catalog_item_id')
            ->where('catalog_items.source', $source)
            ->where('catalog_items.n1', $n1)
            ->where('catalog_items.is_section', false)
            ->whereNotNull('tenant_items.percentual')
            ->when($funcionarioScope === 'none', fn ($q) => $q->whereNull('tenant_items.funcionario_id'))
            ->when($funcionarioScope === 'any', fn ($q) => $q->whereNotNull('tenant_items.funcionario_id'))
            ->pluck('tenant_items.percentual');

        if ($values->isEmpty()) {
            return null;
        }

        return round($values->avg(), 2);
    }
}
