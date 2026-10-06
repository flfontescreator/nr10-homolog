<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cadastro compartilhado de Situação (Funcionário, RNC Técnico, RNC Fotográfico).
 * Administrado por Admin/Super Admin; "Não avaliado" é o default.
 */
class Situacao extends Model
{
    protected $table = 'situacoes';

    protected $fillable = [
        'nome',
        'is_default',
        'ordem',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'ordem' => 'integer',
        ];
    }

    public function funcionarioItems(): HasMany
    {
        return $this->hasMany(FuncionarioItem::class);
    }

    public function rncItems(): HasMany
    {
        return $this->hasMany(RncItem::class);
    }

    /**
     * Situação default do sistema: "Não avaliado".
     */
    public static function default(): ?self
    {
        return static::query()->where('is_default', true)->first()
            ?? static::query()->orderBy('ordem')->first();
    }
}
