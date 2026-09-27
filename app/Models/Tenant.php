<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'cnpj',
        'contact_name',
        'contact_email',
        'contact_phone',
        'address',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TenantItem::class);
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }

    /**
     * Cria o ambiente completo: todos os subitens do catalogo nascem
     * vinculados a este cliente com os campos de controle vazios.
     * Exceção: os subitens do item 4 do prontuário (4.1..4.8) são por
     * funcionário e não são criados aqui — veja Funcionario::bootstrapProntuarioItems().
     */
    public function bootstrapItems(): int
    {
        $subitems = CatalogItem::query()
            ->where('is_section', false)
            ->whereNot(function ($q) {
                $q->where('source', 'prontuario')->where('n1', 4);
            })
            ->orderBy('source')
            ->orderBy('n1')
            ->orderBy('n2')
            ->orderBy('n3')
            ->orderBy('n4')
            ->get(['id']);

        // A unique key inclui funcionario_id; com NULL o MySQL não deduplica,
        // então a exclusividade das linhas gerais é garantida aqui na aplicação.
        $existing = DB::table('tenant_items')
            ->where('tenant_id', $this->id)
            ->whereNull('funcionario_id')
            ->pluck('catalog_item_id');

        $subitems = $subitems->whereNotIn('id', $existing);

        if ($subitems->isEmpty()) {
            return 0;
        }

        $now = now();
        $rows = $subitems->map(fn ($item) => [
            'tenant_id' => $this->id,
            'catalog_item_id' => $item->id,
            // setores fica NULL (não tocado): o item mostra o catálogo ao vivo,
            // podendo ser zerado ([]) ou personalizado ([...]) pelo usuário.
            'setores' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        return DB::table('tenant_items')->insert($rows);
    }
}
