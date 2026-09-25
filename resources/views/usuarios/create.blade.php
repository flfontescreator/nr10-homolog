@extends('layouts.app')

@section('title', 'Novo usuário')

@section('content')
    @php($isSuper = auth()->user()->isSuperAdmin())
    @php($tenants = $isSuper ? \App\Models\Tenant::orderBy('name')->get() : collect())

    <div class="page-header">
        <div>
            <h1>Novo usuário</h1>
            <p class="subtitle">A senha deve ter mínimo 8 caracteres com maiúscula, minúscula, número e símbolo.</p>
        </div>
        <a class="btn btn-secondary" href="{{ route('usuarios.index') }}">← Voltar</a>
    </div>

    <div class="card" style="max-width:640px">
        <form method="POST" action="{{ route('usuarios.store') }}">
            @csrf

            <div class="form-group">
                <label for="name">Nome *</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required>
            </div>

            <div class="form-group">
                <label for="email">E-mail *</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="role">Papel *</label>
                    <select id="role" name="role" required>
                        @foreach($roles as $value => $label)
                            @if($value === 'super_admin' && ! $isSuper)
                                @continue
                            @endif
                            <option value="{{ $value }}" @selected(old('role') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @if($isSuper)
                        <div class="field-hint">Super Admin: sem cliente (plataforma). Demais papéis: exige cliente.</div>
                    @endif
                </div>

                @if($isSuper)
                    <div class="form-group">
                        <label for="tenant_id">Cliente</label>
                        <select id="tenant_id" name="tenant_id">
                            <option value="">— Cliente —</option>
                            @foreach($tenants as $tenant)
                                <option value="{{ $tenant->id }}" @selected(old('tenant_id') == $tenant->id)>{{ $tenant->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label for="password">Senha *</label>
                    <div class="password-wrap">
                        <input id="password" name="password" type="password" required>
                        <button type="button" class="password-toggle" data-show="1" tabindex="-1" aria-label="Mostrar senha"></button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="password_confirmation">Confirmar senha *</label>
                    <div class="password-wrap">
                        <input id="password_confirmation" name="password_confirmation" type="password" required>
                        <button type="button" class="password-toggle" data-show="1" tabindex="-1" aria-label="Mostrar senha"></button>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;font-weight:500">
                    <input type="hidden" name="two_step_enabled" value="0">
                    <input id="two_step_enabled" name="two_step_enabled" type="checkbox" value="1"
                           style="width:auto" @checked(old('two_step_enabled', true))>
                    Verificação em 2 etapas no login
                </label>
                <div class="field-hint">Novos usuários já nascem com a verificação em 2 etapas ativada por padrão.</div>
            </div>

            <button class="btn" type="submit">Criar usuário</button>
        </form>
    </div>
@endsection