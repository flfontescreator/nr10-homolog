<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Norma técnica aplicável ao RNC — catálogo GLOBAL, mesma lista para todos os
 * clientes. Seus itens (`norma_itens`) alimentam as referências normativas das
 * não conformidades; a matriz NR-10 do `catalog_items` é a origem do seed.
 */
class NormaTecnica extends Model
{
    /**
     * Fixo de propósito: o pluralizador do Laravel não acerta
     * "NormaTecnica" → "norma_tecnicas".
     */
    protected $table = 'normas_tecnicas';

    protected $fillable = [
        'codigo',
        'nome',
        'descricao',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function itens(): HasMany
    {
        return $this->hasMany(NormaItem::class);
    }

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }
}
