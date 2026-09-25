<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve o cliente ativo da sessão. Clientes da Greenjob (super admins)
 * podem trocar de cliente pela chave seletora do topo.
 */
class SetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            if (! $user->isSuperAdmin()) {
                $sessionTenantId = $request->session()->get('tenant_id');
                $tenantId = $sessionTenantId ?: $user->tenant_id;

                if (! $sessionTenantId && $tenantId) {
                    $request->session()->put('tenant_id', $tenantId);
                }

                if ($tenantId) {
                    TenantContext::set(Tenant::find($tenantId));
                }
            } else {
                $tenantId = $request->session()->get('tenant_id');
                if ($tenantId) {
                    $tenant = Tenant::find($tenantId);
                    if ($tenant) {
                        TenantContext::set($tenant);
                    } else {
                        $request->session()->forget('tenant_id');
                    }
                }
            }

            $request->attributes->set('accessible_tenants', $user->accessibleTenants());
        }

        return $next($request);
    }
}