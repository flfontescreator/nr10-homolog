<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

class UsuarioController extends Controller
{
    public function index(): View
    {
        $user = request()->user();

        $query = User::with('tenant');

        if (! $user->isSuperAdmin()) {
            $query->where('tenant_id', TenantContext::id());
        }

        $users = $query->orderBy('name')->paginate(25);

        return view('usuarios.index', [
            'users' => $users,
            'roles' => Role::options(),
        ]);
    }

    public function create(): View
    {
        $this->authorizeCreate();

        return view('usuarios.create', ['roles' => Role::options()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeCreate();

        $isSuper = $request->user()->isSuperAdmin();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'in:'.implode(',', array_keys(Role::options()))],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->mixedCase()->numbers()->symbols()],
            'tenant_id' => ['nullable', 'exists:tenants,id'],
            'two_step_enabled' => ['sometimes', 'boolean'],
        ]);

        $data['role'] = Role::from($data['role']);

        if (! $isSuper) {
            // Admin do cliente cria usuários do próprio ambiente, nunca super admins.
            if ($data['role'] === Role::SuperAdmin) {
                abort(403);
            }

            $data['tenant_id'] = TenantContext::id();
        } else {
            // Super admin: usuário de cliente exige um cliente; super admin é de plataforma.
            if ($data['role'] === Role::SuperAdmin) {
                $data['tenant_id'] = null;
            } elseif (empty($data['tenant_id'] ?? null)) {
                abort(422, 'Selecione o cliente para criar o usuário.');
            }
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'], // hashed pelo cast
            'role' => $data['role']->value,
            'tenant_id' => $data['tenant_id'],
            'two_step_enabled' => $request->boolean('two_step_enabled', true),
        ]);

        return redirect()->route('usuarios.index')
            ->with('success', "Usuário {$user->name} criado.");
    }

    public function show(User $user): View
    {
        if ($user->id !== request()->user()->id && ! $this->canManage($user)) {
            abort(403);
        }

        return view('usuarios.show', ['user' => $user->load('tenant')]);
    }

    public function edit(User $user): View
    {
        $this->authorizeManage($user);

        return view('usuarios.edit', ['user' => $user, 'roles' => Role::options()]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeManage($user);

        $actor = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'role' => ['required', 'in:'.implode(',', array_keys(Role::options()))],
            'two_step_enabled' => ['sometimes', 'boolean'],
        ]);

        if (! $actor->isSuperAdmin()) {
            // Admin do cliente não promove/demite super admins nem troca o próprio papel.
            if ($data['role'] === Role::SuperAdmin->value || ($user->id === $actor->id && $data['role'] !== $user->role->value)) {
                abort(403);
            }
        }

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'two_step_enabled' => $request->boolean('two_step_enabled', true),
        ]);

        // Ao desativar o 2FA, dispositivos confiáveis antigos deixam de valer
        // para quando a verificação for religada.
        if (! $user->twoStepEnabled()) {
            $user->trustedDevices()->delete();
        }

        return back()->with('success', 'Usuário atualizado.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'Você não pode excluir a si mesmo.']);
        }

        $this->authorizeManage($user);

        $user->delete();

        return back()->with('success', 'Usuário excluído.');
    }

    /**
     * Envia o link de redefinição de senha por e-mail (requisito do Bruno).
     */
    public function sendResetLink(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();

        if ($actor->isSuperAdmin()) {
            $allowed = true;
        } else {
            $allowed = $user->id !== $actor->id
                && $actor->role === Role::Admin
                && $user->tenant_id === TenantContext::id();
        }

        if (! $allowed) {
            abort(403);
        }

        $status = Password::sendResetLink(['email' => $user->email]);

        return back()->with(
            $status === Password::RESET_LINK_SENT ? 'success' : 'error',
            $status === Password::RESET_LINK_SENT
                ? 'Link de redefinição de senha enviado para '.$user->email.'.'
                : 'Não foi possível enviar o link. Verifique o e-mail cadastrado.'
        );
    }

    protected function canManage(User $target): bool
    {
        $actor = request()->user();

        if ($actor->isSuperAdmin()) {
            return true;
        }

        // Admin do cliente gerencia usuários do próprio ambiente, exceto super admins.
        return $actor->role === Role::Admin
            && $target->tenant_id === TenantContext::id()
            && ! $target->isSuperAdmin();
    }

    protected function authorizeManage(User $target): void
    {
        if (! $this->canManage($target)) {
            abort(403);
        }
    }

    protected function authorizeCreate(): void
    {
        $actor = request()->user();

        if (! $actor->isSuperAdmin() && $actor->role !== Role::Admin) {
            abort(403);
        }
    }
}
