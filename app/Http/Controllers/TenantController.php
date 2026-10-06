<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Bairro;
use App\Models\Cidade;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CepLookup;
use App\Support\CnpjLookup;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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

        return view('tenants.create', ['ufs' => Cidade::UFS]);
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

    /**
     * Consulta pública e gratuita de CEP (ViaCEP) para autocompletar o
     * endereço no formulário de cliente.
     *
     * O bairro não consta do cadastro da Receita Federal, então a resposta
     * é aproveitada para alimentar a base de bairros da cidade — da próxima
     * vez o campo já nasce preenchido, mesmo sem o CEP ter sido digitado.
     */
    public function lookupCep(Request $request, string $cep): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            abort(403);
        }

        try {
            $data = CepLookup::lookup($cep);
        } catch (\Throwable) {
            return response()->json([
                'message' => 'Não foi possível consultar o CEP agora. Tente novamente em instantes.',
            ], 503);
        }

        if (! $data) {
            return response()->json([
                'message' => 'CEP não encontrado. Verifique os dígitos e tente novamente.',
            ], 404);
        }

        Cidade::registrar($data['uf'], $data['cidade']);
        Bairro::registrar($data['uf'], $data['cidade'], $data['bairro']);

        return response()->json($data);
    }

    /**
     * Cidades de uma UF, para o autocompletar do cadastro de cliente.
     */
    public function cidades(Request $request): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            abort(403);
        }

        $data = $request->validate([
            'uf' => ['required', 'string', 'size:2'],
        ]);

        $nomes = Cidade::query()->daUf($data['uf'])->pluck('nome');

        return response()->json($nomes->values());
    }

    /**
     * Bairros de uma cidade, para o autocompletar do cadastro de cliente:
     * só aparecem os bairros da cidade selecionada.
     */
    public function bairros(Request $request): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            abort(403);
        }

        $data = $request->validate([
            'uf' => ['required', 'string', 'size:2'],
            'cidade' => ['required', 'string', 'max:150'],
        ]);

        $nomes = Bairro::query()->daCidade($data['uf'], $data['cidade'])->pluck('nome');

        return response()->json($nomes->values());
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user->isSuperAdmin()) {
            abort(403);
        }

        $this->prepararEndereco($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cnpj' => ['nullable', 'string', 'max:18'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            ...$this->enderecoRules(),
        ]);

        $tenant = Tenant::create([
            ...$this->normalizeEndereco($data),
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

        return view('tenants.edit', ['tenant' => $tenant, 'ufs' => Cidade::UFS]);
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        $this->authorizeManage($tenant);

        $this->prepararEndereco($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cnpj' => ['nullable', 'string', 'max:18'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            ...$this->enderecoRules(),
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $tenant->update($this->normalizeEndereco($data));

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

    /**
     * Antes da validação: UF em maiúsculas, para que a regra de sigla aceite
     * tanto o <select> do formulário quanto uma entrada programática.
     */
    protected function prepararEndereco(Request $request): void
    {
        $uf = $request->input('uf');

        if (is_string($uf)) {
            $request->merge(['uf' => strtoupper(trim($uf))]);
        }
    }

    /**
     * Endereço estruturado do cliente (logradouro + número + complemento +
     * bairro/cidade/UF + CEP). `address` guarda o logradouro; a string exibida
     * é montada por Tenant::enderecoCompleto().
     */
    protected function enderecoRules(): array
    {
        return [
            'address' => ['nullable', 'string', 'max:255'],
            'cep' => ['nullable', 'string', 'regex:/^[0-9]{5}-?[0-9]{3}$/'],
            'numero' => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:120'],
            'bairro' => ['nullable', 'string', 'max:120'],
            'cidade' => ['nullable', 'string', 'max:120'],
            'uf' => ['nullable', 'string', Rule::in(array_keys(Cidade::UFS))],
        ];
    }

    /**
     * Normaliza o endereço antes de gravar: CEP sem máscara vira 00000-000,
     * UF em maiúsculas e textos aparados (string vazia vira null).
     */
    protected function normalizeEndereco(array $data): array
    {
        foreach (['address', 'numero', 'complemento', 'bairro', 'cidade'] as $campo) {
            if (array_key_exists($campo, $data) && is_string($data[$campo])) {
                $data[$campo] = trim($data[$campo]) ?: null;
            }
        }

        if (array_key_exists('cep', $data)) {
            $digits = CepLookup::normalize((string) $data['cep']);
            $data['cep'] = $digits ? CepLookup::mask($digits) : null;
        }

        if (array_key_exists('uf', $data) && is_string($data['uf'])) {
            $data['uf'] = strtoupper(trim($data['uf'])) ?: null;
        }

        return $data;
    }

    protected function authorizeManage(Tenant $tenant): void
    {
        $user = request()->user();

        if (! $user->isSuperAdmin()) {
            abort(403);
        }
    }
}
