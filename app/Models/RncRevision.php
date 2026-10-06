<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Uma revisão oficial do RNC, criada apenas ao "Publicar". É imutável:
 * guarda o snapshot do relatório naquele instante, o Markdown gerado, o PDF em
 * disco e o link público (token + validade). `rnc.current_revision` aponta
 * para a última publicada.
 */
class RncRevision extends Model
{
    use BelongsToTenant;

    protected $table = 'rnc_revisions';

    /** Validade do link público a partir da publicação. */
    public const PUBLIC_LINK_DAYS = 7;

    protected $fillable = [
        'tenant_id',
        'rnc_id',
        'revision',
        'label',
        'snapshot',
        'markdown',
        'pdf_path',
        'notas',
        'public_token',
        'public_expires_at',
        'published_by',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'revision' => 'integer',
            'public_expires_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    protected $hidden = [
        'public_token',
    ];

    public function rnc(): BelongsTo
    {
        return $this->belongsTo(Rnc::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function publicUrl(): ?string
    {
        return $this->public_token
            ? route('rnc.public.show', $this->public_token)
            : null;
    }

    public function publicLinkExpired(): bool
    {
        return $this->public_expires_at === null || $this->public_expires_at->isPast();
    }

    public function publicLinkExpiringSoon(): bool
    {
        return $this->public_expires_at !== null
            && ! $this->publicLinkExpired()
            && $this->public_expires_at->lessThanOrEqualTo(Carbon::now()->addDay());
    }

    public function renewPublicLink(): void
    {
        $this->forceFill([
            'public_token' => (string) Str::uuid(),
            'public_expires_at' => Carbon::now()->addDays(self::PUBLIC_LINK_DAYS),
        ])->save();
    }

    public static function makeLabel(int $revision): string
    {
        return Rnc::makeRevisionLabel($revision);
    }
}
