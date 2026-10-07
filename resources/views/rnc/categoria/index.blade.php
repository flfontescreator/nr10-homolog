@extends('layouts.app')

@section('title', 'Categorias do RNC')

@section('content')
    <div class="page-header">
        <div>
            <h1><span class="badge badge-green">Administração</span> Categorias do RNC</h1>
            <p class="subtitle">
                Listas globais usadas pelo Relatório de Não Conformidade: projetos,
                criticidades e classificações de risco. Situações e normas técnicas são
                geridas pelo sistema.
            </p>
        </div>
        <a class="btn btn-secondary" href="{{ route('rnc.index') }}">← Voltar ao RNC</a>
    </div>

    <div class="card" style="display:flex;gap:8px;flex-wrap:wrap">
        @foreach(['projetos' => 'Projetos', 'criticidades' => 'Criticidades', 'classificacoes' => 'Classificações de Risco'] as $key => $label)
            <a class="btn {{ $aba === $key ? '' : 'btn-secondary' }}"
               href="{{ route('rnc.categoria.index', ['aba' => $key]) }}">{{ $label }}</a>
        @endforeach
    </div>

    @if($aba === 'projetos')
        <div class="card">
            <h2 class="card-title">Novo projeto</h2>
            <form method="POST" action="{{ route('rnc.categoria.projeto.store') }}" class="form-grid" style="grid-template-columns:1fr auto auto">
                @csrf
                <div class="form-group" style="margin-bottom:0">
                    <input type="text" name="nome" maxlength="160" placeholder="Nome do projeto (ex.: Consultoria NR-10)" required>
                </div>
                <label class="form-check" style="align-self:center;display:flex;gap:6px;align-items:center">
                    <input type="checkbox" name="ativo" value="1" checked> Ativo
                </label>
                <button class="btn" type="submit">Criar projeto</button>
            </form>
        </div>

        <div class="card">
            <h2 class="card-title">Projetos cadastrados</h2>
            @if($projetos->isEmpty())
                <p class="muted">Nenhum projeto cadastrado.</p>
            @else
                <div class="table-wrap">
                    <table class="grid">
                        <thead>
                            <tr><th>Nome</th><th>Situação</th><th class="text-right">Ações</th></tr>
                        </thead>
                        <tbody>
                            @foreach($projetos as $projeto)
                                <tr>
                                    <td>{{ $projeto->nome }}</td>
                                    <td>
                                        <span class="badge {{ $projeto->ativo ? 'badge-green' : 'badge-red' }}">
                                            {{ $projeto->ativo ? 'Ativo' : 'Inativo' }}
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        <button type="button" class="btn btn-sm" data-toggle-form="edit-projeto-{{ $projeto->id }}">Editar</button>
                                        <form method="POST" action="{{ route('rnc.categoria.projeto.destroy', $projeto) }}" style="display:inline"
                                              data-confirm="Excluir o projeto &quot;{{ $projeto->nome }}&quot;? Esta ação não pode ser desfeita.">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-danger" type="submit">Excluir</button>
                                        </form>
                                    </td>
                                </tr>
                                <tr id="edit-projeto-{{ $projeto->id }}" style="display:none">
                                    <td colspan="3">
                                        <form method="POST" action="{{ route('rnc.categoria.projeto.update', $projeto) }}" class="form-grid" style="grid-template-columns:1fr auto auto">
                                            @csrf
                                            @method('PUT')
                                            <div class="form-group" style="margin-bottom:0">
                                                <input type="text" name="nome" maxlength="160" value="{{ $projeto->nome }}" required>
                                            </div>
                                            <label class="form-check" style="align-self:center;display:flex;gap:6px;align-items:center">
                                                <input type="checkbox" name="ativo" value="1" @checked($projeto->ativo)> Ativo
                                            </label>
                                            <button class="btn btn-sm" type="submit">Salvar</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @elseif($aba === 'criticidades')
        <div class="card">
            <h2 class="card-title">Nova criticidade</h2>
            <form method="POST" action="{{ route('rnc.categoria.criticidade.store') }}" class="form-grid" style="grid-template-columns:1fr auto auto">
                @csrf
                <div class="form-group" style="margin-bottom:0">
                    <input type="text" name="nome" maxlength="60" placeholder="Nome (ex.: Alta)" required>
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <input type="number" name="ordem" min="0" max="65535" placeholder="Ordem" style="width:110px">
                </div>
                <button class="btn" type="submit">Criar criticidade</button>
            </form>
        </div>

        @include('rnc.categoria._lista', [
            'itens' => $criticidades,
            'titulo' => 'Criticidades cadastradas',
            'prefixo' => 'criticidade',
            'rotaUpdate' => 'rnc.categoria.criticidade.update',
            'rotaDestroy' => 'rnc.categoria.criticidade.destroy',
        ])
    @else
        <div class="card">
            <h2 class="card-title">Nova classificação de risco</h2>
            <form method="POST" action="{{ route('rnc.categoria.classificacao.store') }}" class="form-grid" style="grid-template-columns:1fr auto auto">
                @csrf
                <div class="form-group" style="margin-bottom:0">
                    <input type="text" name="nome" maxlength="60" placeholder="Nome (ex.: Alto)" required>
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <input type="number" name="ordem" min="0" max="65535" placeholder="Ordem" style="width:110px">
                </div>
                <button class="btn" type="submit">Criar classificação</button>
            </form>
        </div>

        @include('rnc.categoria._lista', [
            'itens' => $classificacoes,
            'titulo' => 'Classificações de risco cadastradas',
            'prefixo' => 'classificacao',
            'rotaUpdate' => 'rnc.categoria.classificacao.update',
            'rotaDestroy' => 'rnc.categoria.classificacao.destroy',
        ])
    @endif

    @push('scripts')
        <script>
            document.querySelectorAll('[data-toggle-form]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var row = document.getElementById(btn.dataset.toggleForm);
                    if (row) {
                        row.style.display = row.style.display === 'none' ? '' : 'none';
                    }
                });
            });

            document.querySelectorAll('form[data-confirm]').forEach(function (form) {
                form.addEventListener('submit', function (e) {
                    if (! window.confirm(form.dataset.confirm)) {
                        e.preventDefault();
                    }
                });
            });
        </script>
    @endpush
@endsection
