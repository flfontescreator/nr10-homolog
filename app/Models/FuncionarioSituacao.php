<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo da "Situação" do FUNCIONÁRIO (vínculo empregatício: Ativo/Inativo).
 *
 * É um catálogo próprio e separado de `Situacao` — aquele descreve a avaliação
 * de ITENS de documentação ("Não avaliado", "Conforme"…). Aqui são apenas dois
 * valores fixos, seedados pela migration e lidos do banco.
 *
 * Convive com o fluxo de desativar/reativar (`Funcionario::isAtivo()`): são
 * coisas diferentes. A desativação é operacional (somente leitura + janela de
 * 48h); esta situação é um dado cadastral.
 */
class FuncionarioSituacao extends Model
{
    protected $table = 'funcionario_situacoes';

    /** Slug do estado de vínculo ativo — default do cadastro. */
    public const ATIVO = 'ativo';

    /** Slug do estado inativo — exibido em cinza. */
    public const INATIVO = 'inativo';

    protected $fillable = [
        'nome',
        'slug',
        'ordem',
    ];

    protected function casts(): array
    {
        return [
            'ordem' => 'integer',
        ];
    }

    public function funcionarios(): HasMany
    {
        return $this->hasMany(Funcionario::class, 'situacao_id');
    }

    public function isAtivo(): bool
    {
        return $this->slug === self::ATIVO;
    }

    public function isInativo(): bool
    {
        return $this->slug === self::INATIVO;
    }

    /**
     * Situação default do cadastro. Cai para o menor `ordem` se o seed não
     * tiver marcado nada (robustez em base migrada à mão).
     */
    public static function default(): ?self
    {
        return static::query()->where('slug', self::ATIVO)->first()
            ?? static::query()->orderBy('ordem')->first();
    }
}
