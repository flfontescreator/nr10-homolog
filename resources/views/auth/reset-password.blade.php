@extends('layouts.auth')

@section('title', 'Redefinir senha — Gestão de Conformidades NR-10')

@section('content')
    <h1>Redefinir senha</h1>
    <p class="subtitle">Defina uma nova senha forte para sua conta.</p>

    <form method="POST" action="{{ route('password.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="form-group">
            <label for="email">E-mail</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                   autocomplete="email">
            @error('email')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label for="password">Nova senha</label>
            <div class="password-wrap">
                <input id="password" type="password" name="password" required
                       autocomplete="new-password" placeholder="••••••••">
                <button type="button" class="password-toggle" data-show="1" tabindex="-1" aria-label="Mostrar senha"></button>
            </div>
            <div class="field-hint">Mínimo 8 caracteres: maiúscula, minúscula, número e símbolo.</div>
            @error('password')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label for="password_confirmation">Confirmar nova senha</label>
            <div class="password-wrap">
                <input id="password_confirmation" type="password" name="password_confirmation" required
                       autocomplete="new-password" placeholder="••••••••">
                <button type="button" class="password-toggle" data-show="1" tabindex="-1" aria-label="Mostrar senha"></button>
            </div>
        </div>

        <button class="btn btn-block" type="submit">Salvar nova senha</button>
    </form>

    <p class="muted small mt-4" style="text-align:center">
        <a href="{{ route('login') }}">Voltar para o login</a>
    </p>
@endsection