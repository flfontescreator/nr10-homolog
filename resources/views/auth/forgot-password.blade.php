@extends('layouts.auth')

@section('title', 'Recuperar senha — Gestão de Conformidades NR-10')

@section('content')
    <h1>Esqueci minha senha</h1>
    <p class="subtitle">Informe seu e-mail e enviaremos um link para redefinir a senha.</p>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div class="form-group">
            <label for="email">E-mail</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                   autocomplete="email" placeholder="voce@empresa.com">
            @error('email')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <button class="btn btn-block" type="submit">Enviar link de redefinição</button>
    </form>

    <p class="muted small mt-4" style="text-align:center">
        <a href="{{ route('login') }}">Voltar para o login</a>
    </p>
@endsection