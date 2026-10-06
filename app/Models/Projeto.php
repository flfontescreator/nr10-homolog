<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Projeto do relatório RNC — catálogo GLOBAL, mesmo espírito do `catalog_items`.
 * Por ora apenas o registro semeado ("Consultoria NR-10") é cadastrado.
 */
class Projeto extends Model
{
    protected $table = 'projetos';

    protected $fillable = [
        'nome',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function rncs(): HasMany
    {
        return $this->hasMany(Rnc::class);
    }

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }
}
