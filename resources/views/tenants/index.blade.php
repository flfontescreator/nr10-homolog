@extends('layouts.app')

@section('title', 'Clientes')

@section('content')
    <div class="page-header">
        <div>
            <h1>Clientes</h1>
            <p class="subtitle">Cada cliente (tenant) nasce com a árvore completa de itens/subitens do catálogo.</p>
        </div>
        <a class="btn" href="{{ route('tenants.create') }}">+ Novo cliente</a>
    </div>

    @if($tenants->isEmpty())
        <div class="card docs-empty">
            Nenhum cliente cadastrado. <a href="{{ route('tenants.create') }}">Crie o primeiro</a>.
        </div>
    @else
        <div class="card">
            <div class="table-wrap">
                <table class="grid">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>CNPJ</th>
                            <th>Usuários</th>
                            <th>Itens</th>
                            <th>Situação</th>
                            <th class="text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tenants as $tenant)
                            <tr>
                                <td>
                                    <strong>{{ $tenant->name }}</strong>
                                    @if($tenant->contact_name)
                                        <div class="muted small">Contato: {{ $tenant->contact_name }}</div>
                                    @endif
                                </td>
                                <td>{{ $tenant->cnpj ?: '—' }}</td>
                                <td>
                                    <span class="badge badge-blue">{{ $tenant->users_count }}</span>
                                </td>
                                <td>
                                    <span class="badge badge-neutral">{{ $tenant->items_count }}</span>
                                </td>
                                <td>
                                    <span class="badge {{ $tenant->is_active ? 'badge-green' : 'badge-red' }}">
                                        {{ $tenant->is_active ? 'Ativo' : 'Inativo' }}
                                    </span>
                                </td>
                                <td class="text-right" style="white-space:nowrap">
                                    <form method="POST" action="{{ route('tenant.switch') }}" style="display:inline">
                                        @csrf
                                        <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                                        <button class="btn btn-sm" type="submit">Operar</button>
                                    </form>
                                    <a class="btn btn-sm btn-secondary" href="{{ route('tenants.show', $tenant) }}">Ver</a>
                                    <a class="btn btn-sm btn-secondary" href="{{ route('tenants.edit', $tenant) }}">Editar</a>
                                    @if($userCanDelete ?? false)
                                        <form method="POST" action="{{ route('tenants.destroy', $tenant) }}"
                                              data-confirm="Excluir o cliente '{{ $tenant->name }}' e todos os seus dados?" style="display:inline">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-danger" type="submit">Excluir</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection