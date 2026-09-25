<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CnpjLookup;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    public function index(): View
    {
        if (! request()->user()->isSuperAdmin()) {
            abort(403);
        }

        $tenants = Tenant::withCount(['users', 'items'])->orderBy('name')->get();

        return view('tenants.index', ['tenants' => $tenants, 'userCanDelete' => true]);
    }

    public function create(): View
    {
        if (! request()->user()->isSuperAdmin()) {
            abort(403);
        }

        return view('tenants.create');
    }

    /**
     * Consulta pública e gratuita de CNPJ (BrasilAPI) para autocompletar os
     * campos de razão social e endereço no formulário de cliente.
     */
    public function lookupCnpj(Request $request, string $cnpj): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            abort(403);
        }

        try {
            $data = CnpjLookup::lookup($cnpj);
        } catch (\Throwable) {
            return response()->json([
                'message' => 'Não foi possível consultar o CNPJ agora. Tente novamente em instantes.',
            ], 503);
        }

        if (! $data) {
            return response()->json([
                'message' => 'CNPJ não encontrado. Verifique os dígitos e tente novamente.',
            ], 404);
        }

        return response()->json($data);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdmin()) {
            abort(403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cnpj' => ['nullable', 'string', 'max:18'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $tenant = Tenant::create([
            ...$data,
            'created_by' => $user->id,
        ]);

        // Todo cliente novo já nasce com a árvore completa de itens/subitens.
        $created = $tenant->bootstrapItems();

        // O e-mail de cadastro é o login do administrador do cliente.
        $temporaryPassword = Str::password(12);
        User::create([
            'name' => $data['contact_name'] ?: 'Administrador',
            'email' => $data['contact_email'],
            'password' => $temporaryPassword,
            'role' => Role::Admin,
            'tenant_id' => $tenant->id,
        ]);

        return redirect()
            ->route('tenants.show', $tenant)
            ->with('success', "Cliente criado com {$created} itens/subitens. Acesso do administrador: {$tenant->contact_email} — senha temporária: {$temporaryPassword}");
    }

    public function show(Tenant $tenant): View
    {
        $this->authorizeManage($tenant);

        $tenant->loadCount(['users', 'items', 'evidences']);

        return view('tenants.show', [
            'tenant' => $tenant,
        ]);
    }

    public function edit(Tenant $tenant): View
    {
        $this->authorizeManage($tenant);

        return view('tenants.edit', ['tenant' => $tenant]);
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $this->authorizeManage($tenant);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cnpj' => ['nullable', 'string', 'max:18'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $tenant->update($data);

        return redirect()->route('tenants.show', $tenant)
            ->with('success', 'Cliente atualizado.');
    }

    public function destroy(Request $request, Tenant $tenant): RedirectResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            abort(403);
        }

        if ($tenant->users()->exists()) {
            return back()->withErrors([
                'tenant' => 'Não é possível excluir este cliente: existem usuários vinculados. Mova ou exclua os usuários primeiro.',
            ]);
        }

        $tenant->delete();

        return redirect()->route('tenants.index')
            ->with('success', 'Cliente excluído.');
    }

    /**
     * Seletor de cliente: troca o ambiente da sessão.
     */
    public function switch(Request $request): RedirectResponse
    {
        $request->validate(['tenant_id' => ['required', 'integer']]);

        $user = $request->user();
        $tenant = Tenant::find($request->integer('tenant_id'));

        if (! $tenant) {
            return back()->withErrors(['tenant' => 'Cliente inválido.']);
        }

        if (! $user->isSuperAdmin() && $user->tenant_id !== $tenant->id) {
            abort(403);
        }

        $request->session()->put('tenant_id', $tenant->id);

        return back()->with('success', "Ambiente alterado para {$tenant->name}.");
    }

    protected function authorizeManage(Tenant $tenant): void
    {
        $user = request()->user();

        if (! $user->isSuperAdmin()) {
            abort(403);
        }
    }
}
