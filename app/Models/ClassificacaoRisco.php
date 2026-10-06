<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Classificação de risco de uma não conformidade — catálogo GLOBAL
 * (Alto, Médio, Baixo). Só é exibida no RNC Técnico.
 */
class ClassificacaoRisco extends Model
{
    protected $table = 'classificacoes_risco';

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
