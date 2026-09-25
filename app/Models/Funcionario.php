<?php

namespace App\Models;

use App\Enums\Source;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Funcionario extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'nome',
        'matricula',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TenantItem::class);
    }

    /**
     * Sub-itens do item 4 do prontuário (4.1..4.8) que pertencem a este funcionário.
     */
    public function prontuarioItems(): HasMany
    {
        return $this->items()
            ->whereHas('catalogItem', fn ($q) => $q->where('source', Source::Prontuario->value)->where('n1', 4))
            ->with('catalogItem');
    }

    /**
     * Cria/garante as linhas tenant_items 4.1..4.8 deste funcionário.
     */
    public function bootstrapProntuarioItems(): int
    {
        $subitems = CatalogItem::query()
            ->where('source', Source::Prontuario->value)
            ->where('n1', 4)
            ->where('is_section', false)
            ->orderBy('n1')
            ->orderBy('n2')
            ->orderBy('n3')
            ->pluck('id');

        $existing = $this->items()->pluck('catalog_item_id');

        $now = now();
        $rows = $subitems
            ->reject(fn ($id) => $existing->contains($id))
            ->map(fn ($id) => [
                'tenant_id' => $this->tenant_id,
                'funcionario_id' => $this->id,
                'catalog_item_id' => $id,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if ($rows === []) {
            return 0;
        }

        return DB::table('tenant_items')->insert($rows);
    }
}
