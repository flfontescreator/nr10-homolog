@extends('layouts.app')

@section('title', $tenant->name)

@section('content')
    <div class="page-header">
        <div>
            <h1>{{ $tenant->name }}</h1>
            <p class="subtitle">
                Cliente
                <span class="badge {{ $tenant->is_active ? 'badge-green' : 'badge-red' }}">
                    {{ $tenant->is_active ? 'Ativo' : 'Inativo' }}
                </span>
            </p>
        </div>
        <div style="display:flex;gap:8px">
            <form method="POST" action="{{ route('tenant.switch') }}">
                @csrf
                <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                <button class="btn" type="submit">Operar neste cliente</button>
            </form>
            <a class="btn btn-secondary" href="{{ route('tenants.edit', $tenant) }}">Editar</a>
            <a class="btn btn-secondary" href="{{ route('tenants.index') }}">← Voltar</a>
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-value">{{ $tenant->users_count }}</div>
            <div class="stat-label">Usuários</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ number_format($tenant->items_count, 0, ',', '.') }}</div>
            <div class="stat-label">Itens/subitens carregados</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ $tenant->evidences_count }}</div>
            <div class="stat-label">Evidências</div>
        </div>
    </div>

    <div class="card">
        <h2 class="card-title">Dados cadastrais</h2>
        <dl class="detail-grid">
            <dt>CNPJ</dt><dd>{{ $tenant->cnpj ?: '—' }}</dd>
            <dt>Contato</dt><dd>{{ $tenant->contact_name ?: '—' }}</dd>
            <dt>E-mail do administrador</dt><dd>{{ $tenant->contact_email ?: '—' }}</dd>
            <dt>Telefone</dt><dd>{{ $tenant->contact_phone ?: '—' }}</dd>
            <dt>Endereço</dt><dd>{{ $tenant->address ?: '—' }}</dd>
        </dl>
    </div>
@endsection