@extends('layouts.app')

@section('title', $user->name)

@section('content')
    <div class="page-header">
        <div>
            <h1>{{ $user->name }}</h1>
            <p class="subtitle">{{ $user->email }}</p>
        </div>
        <div style="display:flex;gap:8px">
            @if(auth()->user()->isAdmin())
                <a class="btn" href="{{ route('usuarios.edit', $user) }}">Editar</a>
            @endif
            <a class="btn btn-secondary" href="{{ route('usuarios.index') }}">← Voltar</a>
        </div>
    </div>

    <div class="card" style="max-width:640px">
        <dl class="detail-grid">
            <dt>Nome</dt><dd>{{ $user->name }}</dd>
            <dt>E-mail</dt><dd>{{ $user->email }}</dd>
            <dt>Papel</dt><dd>{{ $user->role->label() }}</dd>
            <dt>Cliente</dt><dd>{{ $user->tenant?->name ?? 'Plataforma (super admin)' }}</dd>
            <dt>Último acesso</dt><dd>{{ $user->last_login_at?->format('d/m/Y H:i') ?: 'Nunca' }}</dd>
            <dt>Verificação em 2 etapas</dt>
            <dd>
                @if($user->twoStepEnabled())
                    <span class="badge badge-blue">Ativada</span>
                @else
                    <span class="badge badge-neutral">Desativada</span>
                @endif
            </dd>
        </dl>
    </div>
@endsection