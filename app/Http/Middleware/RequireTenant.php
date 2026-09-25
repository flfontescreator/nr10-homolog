<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garante que um cliente esteja selecionado antes de acessar páginas de dados.
 */
class RequireTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! TenantContext::current()) {
            if (! $user->isSuperAdmin() && $user->tenant_id) {
                request()->session()->put('tenant_id', $user->tenant_id);
                TenantContext::set($user->tenant);
            }

            if (! TenantContext::current()) {
                return redirect()->route('dashboard')->with(
                    'warning',
                    'Selecione um cliente para acessar os dados.'
                );
            }
        }

        return $next($request);
    }
}