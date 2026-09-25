@extends('layouts.app')

@section('title', 'Painel — Plataforma')

@section('content')
    @php($currentUser = auth()->user())

    <div class="page-header">
        <div>
            <h1>Painel da plataforma</h1>
            <p class="subtitle">
                @if($currentUser->isSuperAdmin())
                    Você tem acesso a todos os clientes. Selecione um para operar.
                @else
                    Selecione o cliente para operar.
                @endif
            </p>
        </div>
        @if($currentUser->isSuperAdmin())
            <a class="btn" href="{{ route('tenants.create') }}">+ Novo cliente</a>
        @endif
    </div>

    @if($tenants->isEmpty())
        <div class="card docs-empty">
            Nenhum cliente cadastrado.
            @if($currentUser->isSuperAdmin())
                <a href="{{ route('tenants.create') }}">Crie o primeiro cliente</a>.
            @endif
        </div>
    @else
        <div class="card">
            <div class="table-wrap">
                <table class="grid">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Itens ativos</th>
                            <th>Situação</th>
                            <th class="text-right">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tenants as $tenant)
                            <tr>
                                <td>
                                    <strong>{{ $tenant->name }}</strong>
                                    @if($tenant->cnpj)
                                        <div class="muted small">{{ $tenant->cnpj }}</div>
                                    @endif
                                </td>
                                <td>{{ number_format($tenant->itemsCount, 0, ',', '.') }}</td>
                                <td>
                                    <span class="badge {{ $tenant->is_active ? 'badge-green' : 'badge-neutral' }}">
                                        {{ $tenant->is_active ? 'Ativo' : 'Inativo' }}
                                    </span>
                                </td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('tenant.switch') }}" style="display:inline">
                                        @csrf
                                        <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                                        <button class="btn btn-sm" type="submit">Operar</button>
                                    </form>
                                    @if($currentUser->isSuperAdmin())
                                        <a class="btn btn-sm btn-secondary" href="{{ route('tenants.show', $tenant) }}">Ver</a>
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