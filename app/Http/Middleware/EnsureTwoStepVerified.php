<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoStepVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        // Usuário com verificação em 2 etapas desativada entra direto.
        if (! $user->twoStepEnabled()) {
            $request->session()->put('two_step_verified', true);

            return $next($request);
        }

        if ($request->session()->get('two_step_verified')) {
            return $next($request);
        }

        // Dispositivo confiável (7 dias) não exige novo código.
        if ($user->hasTrustedDevice($request->cookie('nr10_trusted_device'))) {
            $request->session()->put('two_step_verified', true);

            return $next($request);
        }

        return redirect()->route('verificar')
            ->with('warning', 'Confirme seu acesso com o código enviado por e-mail.');
    }
}
