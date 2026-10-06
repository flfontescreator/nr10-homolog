<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cadastro do funcionário e seus itens de documentação. Módulo próprio: não
 * tem nenhuma ligação com item de catálogo — a numeração dos itens é
 * sequencial por funcionário (1, 2, 3…).
 *
 * Demissão = desativação. O registro e todo o histórico permanecem; apenas a
 * edição é desligada. Reativação é uma janela temporária de 48h e só Admin
 * ou SuperAdmin conseguem fazê-la (senha + justificativa).
 */
class Funcionario extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'nome',
        'matricula',
        'cpf',
        'data_admissao',
        'situacao_id',
    ];

    /**
     * Janela de reativação concedida ao administrador, em horas.
     */
    public const REATIVACAO_HORAS = 48;

    protected function casts(): array
    {
        return [
            'cpf' => 'string',
            'data_admissao' => 'date',
            'ativo' => 'boolean',
            'desativado_em' => 'datetime',
            'desativacao_justificativa' => 'string',
            'reativado_em' => 'datetime',
            'reativacao_justificativa' => 'string',
            'reativacao_expira_em' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Situação de vínculo (Ativo/Inativo) — catálogo `funcionario_situacoes`.
     * Nada a ver com o fluxo de desativar/reativar (`isAtivo()`).
     */
    public function situacao(): BelongsTo
    {
        return $this->belongsTo(FuncionarioSituacao::class, 'situacao_id');
    }

    /**
     * Itens de documentação, em numeração sequencial (1, 2, 3…).
     */
    public function items(): HasMany
    {
        return $this->hasMany(FuncionarioItem::class)->orderBy('numero');
    }

    /**
     * Quem desligou o funcionário no registro atual.
     */
    public function desativador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'desativado_por');
    }

    /**
     * Quem reativou o funcionário na janela atual.
     */
    public function reativador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reativado_por');
    }

    public function evidenciasCount(): int
    {
        return $this->items()->withCount('evidences')->get()->sum('evidences_count');
    }

    /**
     * Próxima numeração disponível para este funcionário.
     */
    public function nextItemNumber(): int
    {
        return ((int) $this->items()->max('numero')) + 1;
    }

    /**
     * Registro apagado nunca: "desativado" é o estado de negócio.
     *
     * A janela de reativação é respeitada na LEITURA (`reativacao_expira_em`),
     * para que o funcionário já apareça inativo mesmo antes de o comando
     * agendado rodar.
     */
    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where('ativo', true)
            ->where(fn (Builder $q) => $q->whereNull('reativacao_expira_em')
                ->orWhere('reativacao_expira_em', '>', now()));
    }

    public function scopeInativos(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->where('ativo', false)
            ->orWhere(fn (Builder $inner) => $inner
                ->where('ativo', true)
                ->whereNotNull('reativacao_expira_em')
                ->where('reativacao_expira_em', '<=', now())));
    }

    /**
     * Só está ativo se a marcação bater com a janela de reativação.
     */
    public function isAtivo(): bool
    {
        return $this->ativo && ! $this->reativacaoExpirada();
    }

    public function reativacaoExpirada(): bool
    {
        return $this->reativacao_expira_em !== null && $this->reativacao_expira_em->isPast();
    }

    /**
     * situation badge: ativo = azul, inativo = cinza.
     */
    public function situacaoLabel(): string
    {
        return $this->isAtivo() ? 'Ativo' : 'Inativo';
    }

    public function auditLabel(): string
    {
        return $this->nome;
    }
}
