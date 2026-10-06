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
        'cep',
        'numero',
        'complemento',
        'bairro',
        'cidade',
        'uf',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Endereço do cliente em uma linha só, no mesmo formato do autocompletar
     * de CNPJ: "Rua das Flores, 123, Sala 4, Centro, São Paulo — SP, CEP 01000-000".
     *
     * Em cadastros antigos `address` já guarda o endereço inteiro (logradouro
     * e número misturados): quando o número aparece no logradouro ele não é
     * repetido. Retorna null quando nada está preenchido.
     */
    public function enderecoCompleto(): ?string
    {
        $logradouro = trim((string) $this->address);
        $numero = trim((string) $this->numero);

        if ($numero !== '' && preg_match('/(?:^|[\s,])'.preg_quote($numero, '/').'(?:[\s,]|$)/u', $logradouro)) {
            $numero = '';
        }

        $localidade = implode(' — ', array_filter([
            trim((string) $this->cidade),
            strtoupper(trim((string) $this->uf)),
        ], fn ($value) => $value !== ''));

        $partes = array_filter([
            implode(', ', array_filter([$logradouro, $numero], fn ($value) => $value !== '')),
            trim((string) $this->complemento),
            trim((string) $this->bairro),
            $localidade,
            $this->cep ? 'CEP '.$this->cep : '',
        ], fn ($value) => $value !== '');

        return $partes === [] ? null : implode(', ', $partes);
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
     *
     * A documentação de cada funcionário é independente e vive em
     * `funcionario_items` — não é criada aqui.
     */
    public function bootstrapItems(): int
    {
        $subitems = CatalogItem::query()
            ->where('is_section', false)
            ->orderBy('source')
            ->orderBy('n1')
            ->orderBy('n2')
            ->orderBy('n3')
            ->orderBy('n4')
            ->get(['id', 'code', 'title', 'source']);

        $existing = DB::table('tenant_items')
            ->where('tenant_id', $this->id)
            ->pluck('catalog_item_id');

        $subitems = $subitems->whereNotIn('id', $existing);

        if ($subitems->isEmpty()) {
            return 0;
        }

        $now = now();
        $rows = $subitems->map(fn ($item) => [
            'tenant_id' => $this->id,
            'catalog_item_id' => $item->id,
            'code' => $item->code,
            'title' => $item->title,
            'source' => $item->source,
            // setores fica NULL (não tocado): o item mostra o catálogo ao vivo,
            // podendo ser zerado ([]) ou personalizado ([...]) pelo usuário.
            'setores' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        return DB::table('tenant_items')->insert($rows);
    }
}
