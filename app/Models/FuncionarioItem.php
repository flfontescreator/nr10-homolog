<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Item de documentação de um Funcionário. Módulo próprio, sem relação com
 * item de catálogo: a numeração é sequencial por funcionário (1, 2, 3…) e
 * cada item pode ou não ter evidência anexada.
 *
 * A validade do documento NÃO fica aqui — pertence à evidência anexada
 * (box de anexo com "Se aplica").
 */
class FuncionarioItem extends Model
{
    use BelongsToTenant;

    protected $table = 'funcionario_items';

    protected $fillable = [
        'tenant_id',
        'funcionario_id',
        'numero',
        'titulo',
        'descricao',
        'situacao_id',
        'prazo_adequacao',
        'data_adequacao',
        'data_verificacao',
        'comentario',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'prazo_adequacao' => 'date',
            'data_adequacao' => 'date',
            'data_verificacao' => 'date',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class);
    }

    public function situacao(): BelongsTo
    {
        return $this->belongsTo(Situacao::class);
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Identificação do item na interface: "Item 3 —Título".
     */
    public function getDisplayLabelAttribute(): string
    {
        return 'Item '.$this->numero.' — '.$this->titulo;
    }

    /**
     * Rótulo amigável usado na auditoria.
     */
    public function auditLabel(): string
    {
        return $this->getDisplayLabelAttribute();
    }
}
