@extends('layouts.app')

@section('title', 'Usuários')

@section('content')
    <div class="page-header">
        <div>
            <h1>Usuários</h1>
            <p class="subtitle">Usuários do ambiente atual e seus papéis de acesso.</p>
        </div>
        @if(auth()->user()->isAdmin())
            <a class="btn" href="{{ route('usuarios.create') }}">+ Novo usuário</a>
        @endif
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="grid">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>Papel</th>
                        @if(auth()->user()->isSuperAdmin())
                            <th>Cliente</th>
                        @endif
                        <th>Último acesso</th>
                        <th class="text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                        <tr>
                            <td><strong>{{ $user->name }}</strong></td>
                            <td>{{ $user->email }}</td>
                            <td>
                                @php($badge = $user->role->value === 'super_admin' ? 'badge-red' : ($user->role->value === 'admin' ? 'badge-blue' : ($user->role->value === 'manager' ? 'badge-amber' : 'badge-neutral')))
                                <span class="badge {{ $badge }}">{{ $user->role->label() }}</span>
                            </td>
                            @if(auth()->user()->isSuperAdmin())
                                <td>
                                    @if($user->tenant)
                                        {{ $user->tenant->name }}
                                    @else
                                        <span class="muted">Plataforma</span>
                                    @endif
                                </td>
                            @endif
                            <td>{{ $user->last_login_at?->format('d/m/Y H:i') ?: '—' }}</td>
                            <td class="text-right" style="white-space:nowrap">
                                <a class="btn btn-sm btn-secondary" href="{{ route('usuarios.show', $user) }}">Ver</a>
                                @if(auth()->user()->isAdmin())
                                    <a class="btn btn-sm" href="{{ route('usuarios.edit', $user) }}">Editar</a>
                                    <form method="POST" action="{{ route('usuarios.reset-link', $user) }}" style="display:inline"
                                          title="Envia link de redefinição de senha por e-mail">
                                        @csrf
                                        @method('PUT')
                                        <button class="btn btn-sm btn-secondary" type="submit">Resetar senha</button>
                                    </form>
                                    @if($user->id !== auth()->id())
                                        <form method="POST" action="{{ route('usuarios.destroy', $user) }}"
                                              data-confirm="Excluir o usuário {{ $user->name }}?" style="display:inline">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-danger" type="submit">Excluir</button>
                                        </form>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $users->links() }}</div>
    </div>
@endsection