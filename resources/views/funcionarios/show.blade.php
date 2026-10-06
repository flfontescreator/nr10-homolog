@extends('layouts.app')

@section('title', 'Funcionário — '.$funcionario->nome)

@section('content')
    <div class="page-header">
        <div>
            <h1>
                {{ $funcionario->nome }}
                @if($funcionario->matricula)
                    <span class="badge badge-neutral">{{ $funcionario->matricula }}</span>
                @endif
                @if($funcionario->cpf)
                    <span class="badge badge-blue">{{ \App\Support\Cpf::mask($funcionario->cpf) }}</span>
                @endif
            </h1>
            <p class="subtitle">Itens de documentação — cada item pode ou não ter evidência anexada.</p>
        </div>
        <div style="display:flex;gap:8px">
            @if($canWrite)
                <a class="btn btn-secondary" href="{{ route('funcionarios.edit', $funcionario) }}">Editar</a>
            @endif
            @if($canHardDelete)
                <form method="POST" action="{{ route('funcionarios.destroy', $funcionario) }}"
                      data-confirm="Excluir definitivamente {{ $funcionario->nome }} e seus itens? As evidências permanecem na Gestão de Documentos.">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger" type="submit">Excluir</button>
                </form>
            @endif
            <a class="btn btn-secondary" href="{{ $backUrl ?? route('funcionarios.index') }}">← Voltar</a>
        </div>
    </div>

    <div class="muted small" style="display:flex;gap:20px;flex-wrap:wrap;margin-bottom:16px">
        <span>
            <strong>Data de admissão:</strong>
            {{ $funcionario->data_admissao?->format('d/m/Y') ?? '-' }}
        </span>
        <span>
            <strong>Situação:</strong>
            @if($funcionario->situacao)
                <span class="badge {{ $funcionario->situacao->isInativo() ? 'badge-neutral' : 'badge-blue' }}">
                    {{ $funcionario->situacao->nome }}
                </span>
            @else
                -
            @endif
        </span>
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="pill-list">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card" style="margin-bottom:16px">
        <h2 class="card-title" style="margin-top:0">Adicionar item</h2>
        @if($canWrite)
            <form method="POST" action="{{ route('funcionarios.items.store', $funcionario) }}">
                @csrf
                <div class="form-grid" style="grid-template-columns:1fr 1fr;gap:12px">
                    <div class="form-group" style="margin-bottom:0">
                        <label for="item-titulo">Título do item *</label>
                        <input type="text" name="titulo" id="item-titulo" required maxlength="255"
                               value="{{ old('titulo') }}"
                               placeholder="Ex.: Certificado de Aptidão Física">
                    </div>
                    <div class="form-group" style="margin-bottom:0">
                        <label for="item-descricao">Descrição</label>
                        <input type="text" name="descricao" id="item-descricao" maxlength="5000"
                               value="{{ old('descricao') }}"
                               placeholder="Opcional">
                    </div>
                </div>
                <button class="btn" type="submit" style="margin-top:12px">Criar item</button>
            </form>
        @else
            <p class="muted">Você não tem permissão para adicionar itens.</p>
        @endif
    </div>

    <div class="card">
        <h2 class="card-title" style="margin-top:0">Itens de documentação</h2>

        @if($items->isEmpty())
            <div class="docs-empty">Nenhum item criado para este funcionário.</div>
        @else
            <div class="table-wrap">
                <table class="grid">
                    <thead>
                        <tr>
                            <th style="width:60px">Nº</th>
                            <th>Item</th>
                            <th style="width:130px">Situação</th>
                            <th style="width:110px">Evidências</th>
                            <th class="text-right" style="width:150px">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $item)
                            <tr>
                                <td><strong>{{ $item->numero }}</strong></td>
                                <td>
                                    <strong>{{ $item->titulo }}</strong>
                                    @if($item->descricao)
                                        <div class="muted small">{{ \Illuminate\Support\Str::limit($item->descricao, 90) }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if($item->situacao)
                                        <span class="badge badge-blue-alt">{{ $item->situacao->nome }}</span>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $item->evidences_count > 0 ? 'badge-green' : 'badge-neutral' }}">
                                        {{ $item->evidences_count }}
                                    </span>
                                </td>
                                <td class="text-right" style="white-space:nowrap">
                                    <a class="btn btn-sm" href="{{ route('funcionarios.item.show', [$funcionario, $item]) }}">
                                        {{ $canWrite ? 'Editar' : 'Ver' }}
                                    </a>
                                    @if($canWrite)
                                        <form method="POST" action="{{ route('funcionarios.items.destroy', [$funcionario, $item]) }}"
                                              data-confirm="Excluir o item {{ $item->numero }} deste funcionário?" style="display:inline">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-danger" type="submit" title="Excluir item">Excluir</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection