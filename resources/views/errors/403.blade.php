@extends('layouts.app')

@section('title', 'Acesso negado')

@section('content')
    <div class="card docs-empty">
        <h1 style="font-size:40px;margin:0">403</h1>
        <p>Acesso negado. Seu perfil não permite esta ação neste contexto.</p>
        <p class="muted">
            Permissões: <strong>Super Admin</strong> (tudo) · <strong>Admin</strong> (usuários e registros do cliente) ·
            <strong>Manager</strong> (altera, não exclui) · <strong>Viewer</strong> (somente leitura).
        </p>
    </div>
@endsection