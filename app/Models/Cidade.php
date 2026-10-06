<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Base de cidades por UF (dado público IBGE/Correios) usada para sugerir a
 * cidade no cadastro do cliente e para filtrar a lista de bairros.
 *
 * A tabela é compartilhada entre clientes — não é escopada por tenant.
 */
class Cidade extends Model
{
    /** Sigla => nome completo de cada unidade da federação. */
    public const UFS = [
        'AC' => 'Acre',
        'AL' => 'Alagoas',
        'AP' => 'Amapá',
        'AM' => 'Amazonas',
        'BA' => 'Bahia',
        'CE' => 'Ceará',
        'DF' => 'Distrito Federal',
        'ES' => 'Espírito Santo',
        'GO' => 'Goiás',
        'MA' => 'Maranhão',
        'MT' => 'Mato Grosso',
        'MS' => 'Mato Grosso do Sul',
        'MG' => 'Minas Gerais',
        'PA' => 'Pará',
        'PB' => 'Paraíba',
        'PR' => 'Paraná',
        'PE' => 'Pernambuco',
        'PI' => 'Piauí',
        'RJ' => 'Rio de Janeiro',
        'RN' => 'Rio Grande do Norte',
        'RS' => 'Rio Grande do Sul',
        'RO' => 'Rondônia',
        'RR' => 'Roraima',
        'SC' => 'Santa Catarina',
        'SP' => 'São Paulo',
        'SE' => 'Sergipe',
        'TO' => 'Tocantins',
    ];

    protected $table = 'cidades';

    protected $fillable = [
        'uf',
        'nome',
    ];

    public function scopeDaUf(Builder $query, string $uf): Builder
    {
        return $query->where('uf', mb_strtoupper($uf))->orderBy('nome');
    }

    /**
     * Guarda uma cidade retornada pela busca de CEP, se ainda não existir.
     */
    public static function registrar(string $uf, string $nome): ?self
    {
        $uf = mb_strtoupper(mb_substr(trim($uf), 0, 2));
        $nome = trim($nome);

        if ($uf === '' || $nome === '') {
            return null;
        }

        return static::query()->firstOrCreate(['uf' => $uf, 'nome' => $nome]);
    }
}
