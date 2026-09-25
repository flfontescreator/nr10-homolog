@extends('layouts.app')

@section('title', 'Não foi possível concluir')

@section('content')
    <div class="card">
        <h1 style="margin:0">Não foi possível concluir a operação</h1>
        <p>
            Esta ação depende de outros cadastros e não pode ser executada no momento.
            Veja o caminho de resolução abaixo e tente novamente.
        </p>
        <ul class="pill-list">
            <li>O registro pode ter <strong>usuários, itens ou evidências vinculados</strong>.</li>
            <li><strong>Cliente com usuários:</strong> mova ou exclua os usuários antes de excluir o cliente.</li>
            <li><strong>Usuário com registros:</strong> transfira os registros antes de excluir o usuário.</li>
            <li><strong>Evidência com arquivo:</strong> exclua o arquivo na tela do item antes de remover o cadastro pai.</li>
        </ul>
        <p class="muted small">
            Referência técnica do erro: integridade referencial (FOREIGN KEY) — registros dependentes bloqueiam a exclusão.
        </p>
        <p style="margin-top:16px">
            <a class="btn" href="{{ url()->previous() }}">← Voltar e corrigir</a>
        </p>
    </div>
@endsection