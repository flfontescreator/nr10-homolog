@extends('layouts.auth')

@section('title', 'Verificação em 2 etapas — Gestão de Conformidades NR-10')

@section('content')
    <h1>Verificação em 2 etapas</h1>
    <p class="subtitle">Enviamos um código de 6 dígitos para <strong>{{ $email }}</strong>.</p>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('verificar.store') }}" autocomplete="off">
        @csrf

        <div class="form-group">
            <label for="code">Código de verificação</label>
            <input id="code" type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                   required autofocus autocomplete="one-time-code" placeholder="123456">
            @error('code')
                <div class="form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label style="display:flex;align-items:center;gap:8px;font-weight:500">
                <input type="checkbox" name="trust_device" value="1" style="width:auto">
                Confiar neste dispositivo por 7 dias
            </label>
        </div>

        <button class="btn btn-block" type="submit">Verificar e entrar</button>
    </form>

    <form method="POST" action="{{ route('verificar.resend') }}" class="mt-4" style="text-align:center">
        @csrf
        <button class="btn btn-link text-link" type="submit">Reenviar código</button>
    </form>

    <p class="muted small mt-4" style="text-align:center">
        O código expira em 10 minutos.
    </p>
@endsection