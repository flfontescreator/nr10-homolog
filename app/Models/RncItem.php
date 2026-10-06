<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Uma não conformidade dentro do RNC: o apontamento encontrado + a
 * recomendação (ação) + prazos. As evidências ancoram em
 * `evidences.rnc_item_id`; as referências normativas vêm do pivô N:N.
 */
class RncItem extends Model
{
    use BelongsToTenant;

    protected $table = 'rnc_items';

    protected $fillable = [
        'tenant_id',
        'rnc_id',
        'numero',
        'titulo',
        'descricao',
        'criticidade_id',
        'classificacao_risco_id',
        'situacao_id',
        'recomendacao',
        'prazo_adequacao',
        'data_adequacao',
    ];

    protected function casts(): array
    {
        return [
            'prazo_adequacao' => 'date',
            'data_adequacao' => 'date',
            'numero' => 'integer',
        ];
    }

    public function rnc(): BelongsTo
    {
        return $this->belongsTo(Rnc::class);
    }

    public function criticidade(): BelongsTo
    {
        return $this->belongsTo(Criticidade::class);
    }

    public function classificacaoRisco(): BelongsTo
    {
        return $this->belongsTo(ClassificacaoRisco::class);
    }

    public function situacao(): BelongsTo
    {
        return $this->belongsTo(Situacao::class);
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(Evidence::class);
    }

    public function normaItens(): BelongsToMany
    {
        // Ordem estável no relatório: norma mais antiga primeiro (NR-10) e,
        // dentro dela, a ordem do sumário.
        return $this->belongsToMany(NormaItem::class, 'rnc_item_norma_item', 'rnc_item_id', 'norma_item_id')
            ->withTimestamps()
            ->orderBy('norma_itens.norma_tecnica_id')
            ->orderBy('norma_itens.ordem');
    }

    /**
     * Depois da primeira publicação a revisão oficial é imutável: remover um
     * item ou uma evidência desmentiria o PDF já publicado. Isso trava a
     * remoção, não a edição.
     */
    public function lockedByPublication(): bool
    {
        return $this->rnc?->current_revision > 0;
    }

    public function prazoVencido(): bool
    {
        return $this->prazo_adequacao !== null
            && $this->prazo_adequacao->isPast()
            && $this->data_adequacao === null;
    }
}
