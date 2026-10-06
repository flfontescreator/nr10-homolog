<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Base de bairros por cidade (dado público IBGE/Correios), complementada
 * com os bairros que a busca de CEP devolve — esses não vêm da base.
 *
 * A tabela é compartilhada entre clientes — não é escopada por tenant.
 */
class Bairro extends Model
{
    protected $table = 'bairros';

    protected $fillable = [
        'uf',
        'cidade',
        'nome',
    ];

    public function scopeDaCidade(Builder $query, string $uf, string $cidade): Builder
    {
        return $query
            ->where('uf', mb_strtoupper($uf))
            ->where('cidade', trim($cidade))
            ->orderBy('nome');
    }

    /**
     * Guarda um bairro retornado pela busca de CEP, se ainda não existir.
     */
    public static function registrar(string $uf, string $cidade, string $nome): ?self
    {
        $uf = mb_strtoupper(mb_substr(trim($uf), 0, 2));
        $cidade = trim($cidade);
        $nome = trim($nome);

        if ($uf === '' || $cidade === '' || $nome === '') {
            return null;
        }

        return static::query()->firstOrCreate([
            'uf' => $uf,
            'cidade' => $cidade,
            'nome' => $nome,
        ]);
    }
}
