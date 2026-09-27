<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Grava um registro de auditoria. Durante a linha de comando fora de testes
 * (seeds, tinker, migrações) é ignorado para não poluir o histórico — em
 * requisições web e em testes a gravação acontece normalmente.
 */
class Audit
{
    public static function record(
        string $action,
        string $summary,
        ?Model $auditable = null,
        ?int $tenantId = null,
        array $old = [],
        array $new = [],
        ?User $user = null,
    ): void {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return;
        }

        // O tenant vem do parâmetro; sem ele, do próprio modelo auditado
        // (null no modelo significa "evento de plataforma" e deve ser respeitado);
        // por fim, do cliente ativo da sessão.
        $fromModel = null;
        $modelHasTenant = $auditable !== null && array_key_exists('tenant_id', $auditable->getAttributes());

        if ($modelHasTenant) {
            $fromModel = $auditable->getAttribute('tenant_id');
        }

        $tenant = $tenantId ?? ($modelHasTenant ? $fromModel : TenantContext::id());

        AuditLog::create([
            'tenant_id' => $tenant,
            'user_id' => $user?->id,
            'action' => $action,
            'auditable_type' => $auditable ? $auditable->getMorphClass() : null,
            'auditable_id' => $auditable?->getKey(),
            'summary' => $summary,
            'data_old' => $old ?: null,
            'data_new' => $new ?: null,
            'ip' => request()->ip(),
        ]);
    }
}
