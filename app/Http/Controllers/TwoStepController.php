<?php

namespace App\Http\Controllers;

use App\Mail\TwoStepCodeMail;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class TwoStepController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user->twoStepEnabled()) {
            $request->session()->put('two_step_verified', true);

            return redirect()->route('dashboard');
        }

        if ($request->session()->get('two_step_verified')) {
            return redirect()->route('dashboard');
        }

        $this->ensureCodeIssued($user);

        return view('auth.verify', [
            'email' => $user->email,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $key = 'twostep:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'code' => "Muitas tentativas. Aguarde {$seconds} segundos.",
            ]);
        }

        if ($user->twoStepCodeExpired() || ! $user->twoStepCodeMatches($request->string('code'))) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'code' => 'Código inválido ou expirado.',
            ]);
        }

        RateLimiter::clear($key);

        $user->forceFill([
            'two_step_code_hash' => null,
            'two_step_code_expires_at' => null,
        ])->save();

        $request->session()->put('two_step_verified', true);

        $deviceToken = $request->boolean('trust_device')
            ? $user->trustCurrentDevice(null)
            : null;

        return redirect()->intended(route('dashboard'))
            ->withCookie(cookie('nr10_trusted_device', $deviceToken ?? '', 10080))
            ->with('success', 'Acesso verificado com sucesso.');
    }

    public function resend(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $key = 'twostep-resend:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'code' => "Aguarde {$seconds} segundos para reenviar.",
            ]);
        }

        RateLimiter::hit($key, 60);

        $code = $user->issueTwoStepCode();
        Mail::to($user->email)->send(new TwoStepCodeMail($code, $user->name));

        return back()->with('success', 'Um novo código foi enviado para o seu e-mail.');
    }

    protected function ensureCodeIssued(User $user): void
    {
        if ($user->two_step_code_hash !== null && ! $user->twoStepCodeExpired()) {
            return;
        }

        $code = $user->issueTwoStepCode();
        Mail::to($user->email)->send(new TwoStepCodeMail($code, $user->name));
    }
}
