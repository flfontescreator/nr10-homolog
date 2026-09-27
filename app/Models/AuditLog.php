<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Registro de auditoria do sistema.
 * O escopo de tenant fecha automaticamente a leitura: um super admin na
 * plataforma (nenhum cliente selecionado) vê tudo; um admin dentro do seu
 * cliente vê apenas o que pertence àquele cliente.
 */
class AuditLog extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'summary',
        'data_old',
        'data_new',
        'ip',
    ];

    protected function casts(): array
    {
        return [
            'data_old' => 'array',
            'data_new' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
