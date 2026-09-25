@extends('layouts.app')

@section('title', 'Editar usuário — '.$user->name)

@section('content')
    @php($isSuper = auth()->user()->isSuperAdmin())

    <div class="page-header">
        <div>
            <h1>Editar usuário</h1>
            <p class="subtitle">{{ $user->name }}</p>
        </div>
        <a class="btn btn-secondary" href="{{ route('usuarios.show', $user) }}">← Voltar</a>
    </div>

    <div class="card" style="max-width:640px">
        <form method="POST" action="{{ route('usuarios.update', $user) }}">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label for="name">Nome *</label>
                <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required>
            </div>

            <div class="form-group">
                <label for="email">E-mail *</label>
                <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required>
            </div>

            <div class="form-group">
                <label for="role">Papel *</label>
                <select id="role" name="role" required {{ $user->id === auth()->id() ? 'disabled' : '' }}>
                    @foreach($roles as $value => $label)
                        @if($value === 'super_admin' && ! $isSuper)
                            @continue
                        @endif
                        <option value="{{ $value }}" @selected($user->role->value === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @if($user->id === auth()->id())
                    <div class="field-hint">Seu próprio papel não pode ser alterado nesta tela.</div>
                    <input type="hidden" name="role" value="{{ $user->role->value }}">
                @endif
            </div>

            <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;font-weight:500">
                    <input type="hidden" name="two_step_enabled" value="0">
                    <input id="two_step_enabled" name="two_step_enabled" type="checkbox" value="1"
                           style="width:auto" @checked(old('two_step_enabled', $user->twoStepEnabled()))>
                    Verificação em 2 etapas no login
                </label>
                <div class="field-hint">Desativar remove os dispositivos confiáveis deste usuário.</div>
            </div>

            <button class="btn" type="submit">Salvar alterações</button>
        </form>
    </div>
@endsection