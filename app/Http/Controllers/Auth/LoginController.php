<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = 'login:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => "Muitas tentativas. Aguarde {$seconds} segundos.",
            ]);
        }

        $remember = $request->boolean('remember');

        // "Manter-me conectado por 7 dias" = sessão de 7 dias (10080 minutos).
        if ($remember) {
            config(['session.lifetime' => 10080]);
        }

        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials, $remember)) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'email' => 'Credenciais inválidas.',
            ]);
        }

        RateLimiter::clear($key);

        /** @var User $user */
        $user = Auth::user();
        $user->forceFill(['last_login_at' => now()])->save();

        // Define o ambiente do cliente automaticamente, se o usuário pertencer a um.
        if (! $user->isSuperAdmin() && $user->tenant_id) {
            $request->session()->put('tenant_id', $user->tenant_id);
        }

        $request->session()->regenerate();

        // Usuário com verificação em 2 etapas desativada entra direto.
        if (! $user->twoStepEnabled()) {
            $request->session()->put('two_step_verified', true);

            return redirect()->intended(route('dashboard'))
                ->with('success', 'Bem-vindo(a), '.$user->name.'!');
        }

        // Se o dispositivo for confiável (7 dias), pula a verificação em 2 etapas.
        if ($user->hasTrustedDevice($request->cookie('nr10_trusted_device'))) {
            $request->session()->put('two_step_verified', true);

            return redirect()->intended(route('dashboard'))
                ->with('success', 'Bem-vindo(a), '.$user->name.'!');
        }

        return redirect()->route('verificar')
            ->with('info', 'Confirme seu acesso com o código enviado por e-mail.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        // Logout explícito revoga o dispositivo confiável.
        $user->revokeTrustedDevice($request->cookie('nr10_trusted_device'));

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->withCookie(cookie()->forget('nr10_trusted_device'));
    }
}
