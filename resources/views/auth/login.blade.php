@extends('layouts.auth')

@section('title', 'Entrar — Gestão de Conformidades')

@section('content')
    <h1>Entrar no sistema</h1>
    <p class="subtitle">Acesse com o e-mail e a senha cadastrados.</p>

    <form method="POST" action="{{ route('login.store') }}">
        @csrf

        <div class="form-group">
            <label for="email">E-mail</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                   autocomplete="email" placeholder="voce@empresa.com">
            @error('email')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label for="password">Senha</label>
            <div class="password-wrap">
                <input id="password" type="password" name="password" required
                       autocomplete="current-password" placeholder="••••••••">
                <button type="button" class="password-toggle" data-show="1" tabindex="-1" aria-label="Mostrar senha"></button>
            </div>
            @error('password')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;font-weight:500">
                <input type="checkbox" name="remember" value="1" style="width:auto" {{ old('remember') ? 'checked' : '' }}>
                Manter-me conectado por 7 dias
            </label>
        </div>

        <button class="btn btn-block" type="submit">Entrar</button>
    </form>

    <p class="muted small mt-4" style="text-align:center">
        <a href="{{ route('password.request') }}">Esqueci minha senha</a>
    </p>
@endsection