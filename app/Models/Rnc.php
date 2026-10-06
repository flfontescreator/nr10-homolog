<?php

namespace App\Models;

use App\Enums\RncModelo;
use App\Enums\RncStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * RNC = relatório formal de não conformidade (cabeçalho + não conformidades +
 * evidências + assinatura). Dois modelos de layout: Técnico e Fotográfico.
 *
 * IMPORTANTE: este módulo é NOVO e independente de "Não Conformidades"
 * (`App\Models\NcDocument`). Não existe vínculo entre eles: aqui a numeração é
 * `RNC_0001`, sequencial por tenant. O relatório é um rascunho editável até
 * alguém "Publicar": cada publicação gera uma `RncRevision` imutável
 * (`Rev:0001`, `Rev:0002`, ...) com snapshot, Markdown, PDF e link público.
 */
class Rnc extends Model
{
    use BelongsToTenant;

    /** Fixo de propósito: evita depender do pluralizador do Laravel ("Rnc" → "rncs"). */
    protected $table = 'rncs';

    protected $fillable = [
        'tenant_id',
        'number',
        'code',
        'titulo',
        'descricao',
        'projeto_id',
        'modelo',
        'recomendacoes',
        'resumo',
        'conclusao',
        'data_inspecao',
        'responsavel_nome',
        'responsavel_cargo',
        'status',
        'current_revision',
        'published_at',
        'published_by',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => RncStatus::class,
            'modelo' => RncModelo::class,
            'data_inspecao' => 'date',
            'published_at' => 'datetime',
            'current_revision' => 'integer',
            'number' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RncItem::class)->orderBy('numero');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(RncRevision::class)->orderByDesc('revision');
    }

    public function projeto(): BelongsTo
    {
        return $this->belongsTo(Projeto::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function isFotografica(): bool
    {
        return $this->modelo === RncModelo::Fotografica;
    }

    public function isPublished(): bool
    {
        return $this->status->isPublished();
    }

    public function getIsPublishedAttribute(): bool
    {
        return $this->isPublished();
    }

    public function latestRevision(): ?RncRevision
    {
        return $this->revisions()->first();
    }

    public function nextItemNumber(): int
    {
        return (int) $this->items()->max('numero') + 1;
    }

    public static function makeCode(int $number): string
    {
        return 'RNC_'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }

    public static function makeRevisionLabel(int $revision): string
    {
        return 'Rev:'.str_pad((string) $revision, 4, '0', STR_PAD_LEFT);
    }

    public static function nextNumber(int $tenantId): int
    {
        return (int) self::query()->where('tenant_id', $tenantId)->max('number') + 1;
    }

    /**
     * Estado completo do relatório num ponto no tempo. É o que a revisão
     * congela no momento da publicação.
     */
    public function buildSnapshot(): array
    {
        $this->loadMissing([
            'tenant',
            'projeto',
            'items.criticidade',
            'items.classificacaoRisco',
            'items.situacao',
            'items.normaItens',
            'items.evidences',
        ]);

        return [
            'codigo' => $this->code,
            'numero' => $this->number,
            'modelo' => $this->modelo?->value,
            'modelo_label' => $this->modelo?->label(),
            'titulo' => $this->titulo,
            'descricao' => $this->descricao,
            'projeto' => $this->projeto?->nome,
            'data_inspecao' => $this->data_inspecao?->format('Y-m-d'),
            'responsavel_nome' => $this->responsavel_nome,
            'responsavel_cargo' => $this->responsavel_cargo,
            'recomendacoes' => $this->recomendacoes,
            'resumo' => $this->resumo,
            'conclusao' => $this->conclusao,
            'cliente' => $this->tenant?->name,
            'cliente_documento' => $this->tenant?->cnpj,
            'cliente_endereco' => $this->tenant?->enderecoCompleto(),
            'cliente_contato' => $this->tenant?->contact_name,
            'cliente_email' => $this->tenant?->contact_email,
            'itens' => $this->items
                ->map(fn (RncItem $item) => [
                    'numero' => $item->numero,
                    'titulo' => $item->titulo,
                    'descricao' => $item->descricao,
                    'criticidade' => $item->criticidade?->nome,
                    'classificacao_risco' => $item->classificacaoRisco?->nome,
                    'recomendacao' => $item->recomendacao,
                    'prazo_adequacao' => $item->prazo_adequacao?->format('Y-m-d'),
                    'data_adequacao' => $item->data_adequacao?->format('Y-m-d'),
                    'situacao' => $item->situacao?->nome,
                    'referencias' => $item->normaItens
                        ->map(fn (NormaItem $normaItem) => [
                            'codigo' => $normaItem->codigo,
                            'descricao' => $normaItem->descricao,
                        ])
                        ->all(),
                    'evidencias' => $item->evidences
                        ->map(fn (Evidence $evidence) => [
                            'id' => $evidence->id,
                            'nome' => $evidence->original_name,
                            'descricao' => $evidence->description,
                            'validade' => $evidence->validade?->format('Y-m-d'),
                            'path' => $evidence->stored_path,
                            'mime' => $evidence->mime_type,
                        ])
                        ->all(),
                ])
                ->all(),
        ];
    }
}
