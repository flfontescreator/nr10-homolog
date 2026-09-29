<?php

namespace App\Models;

use App\Enums\Source;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class CatalogItem extends Model
{
    protected $fillable = [
        'parent_id',
        'source',
        'code',
        'n1',
        'n2',
        'n3',
        'n4',
        'is_section',
        'title',
        'description',
        'norma_tecnica',
        'interpretacao_tecnica',
        'sugestao_acao',
        'status',
        'criticidade',
        'setor',
        'setores',
        'detalhamento',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'source' => Source::class,
            'is_section' => 'boolean',
            'setores' => 'array',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('n1')->orderBy('n2')->orderBy('n3')->orderBy('n4');
    }

    public function tenantItems(): HasMany
    {
        return $this->hasMany(TenantItem::class, 'catalog_item_id');
    }

    public function getTitleOrCodeAttribute(): string
    {
        return $this->title ?? $this->code;
    }

    /**
     * Setores do subitem: a lista individual (um por linha na planilha) ou,
     * na ausência, o setor único legado.
     */
    public function getSetoresListAttribute(): array
    {
        if (! empty($this->setores)) {
            return $this->setores;
        }

        if (! empty($this->setor)) {
            return [$this->setor];
        }

        return [];
    }

    /**
     * Constrói a árvore (seções + subitens) para exibição em listas.
     * Cada seção agrupa os subitens cujo código inicie com o código da seção (ex.: "10.4" -> "10.4.13", "1" -> "1.1").
     */
    public static function tree(Source $source): Collection
    {
        $all = self::where('source', $source->value)
            ->orderBy('n1')
            ->orderBy('n2')
            ->orderBy('n3')
            ->orderBy('n4')
            ->get();

        $sections = $all->where('is_section', true)->values();
        $subitems = $all->where('is_section', false)->values();

        return $sections->map(function ($section) use ($subitems) {
            $prefix = $section->code.'.';
            $children = $subitems
                ->filter(fn ($item) => str_starts_with($item->code, $prefix))
                ->values();

            return (object) [
                'section' => $section,
                'children' => $children,
            ];
        });
    }
}
