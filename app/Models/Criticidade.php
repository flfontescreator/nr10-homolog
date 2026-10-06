<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Criticidade de uma não conformidade — catálogo GLOBAL
 * (Alta, Média, Baixa, Não aplicada). Não aparece no relatório; alimenta
 * indicadores/dashboards.
 */
class Criticidade extends Model
{
    protected $table = 'criticidades';

    protected $fillable = [
        'nome',
        'ordem',
    ];

    protected function casts(): array
    {
        return [
            'ordem' => 'integer',
        ];
    }

    public function rncItems(): HasMany
    {
        return $this->hasMany(RncItem::class);
    }
}
