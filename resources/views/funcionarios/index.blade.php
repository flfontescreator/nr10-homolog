@extends('layouts.app')

@section('title', 'Funcionários')

@section('content')
    <div class="page-header">
        <div>
            <h1>Funcionários</h1>
            <p class="subtitle">
                Cada funcionário tem seus próprios itens de documentação, numerados em sequência
                (1, 2, 3…), e cada item pode ou não ter evidência anexada.
            </p>
        </div>
        @if($canWrite)
            <a class="btn" href="{{ route('funcionarios.create') }}">+ Novo funcionário</a>
        @endif
    </div>

    @if($funcionarios->isEmpty())
        <div class="card docs-empty">
            Nenhum funcionário cadastrado.
            @if($canWrite)
                <a href="{{ route('funcionarios.create') }}">Cadastre o primeiro</a> para começar a documentação.
            @endif
        </div>
    @else
        <div class="card">
            <div class="table-wrap">
                <table class="grid">
                    <thead>
                        <tr>
                            <th>Funcionário</th>
                            <th>Matrícula</th>
                            <th class="text-right">Itens</th>
                            <th class="text-right">Evidências</th>
                            <th>Situação</th>
                            <th class="text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($funcionarios as $funcionario)
                            <tr>
                                <td><strong>{{ $funcionario->nome }}</strong></td>
                                <td>{{ $funcionario->matricula ?: '—' }}</td>
                                <td class="text-right">
                                    <span class="badge badge-neutral">{{ $funcionario->items_count }}</span>
                                </td>
                                <td class="text-right">
                                    <span class="badge {{ ($evidencesByFuncionario[$funcionario->id] ?? 0) > 0 ? 'badge-green' : 'badge-neutral' }}">
                                        {{ $evidencesByFuncionario[$funcionario->id] ?? 0 }}
                                    </span>
                                </td>
                                <td>
                                    @if($funcionario->situacao)
                                        <span class="badge {{ $funcionario->situacao->isInativo() ? 'badge-neutral' : 'badge-blue' }}">
                                            {{ $funcionario->situacao->nome }}
                                        </span>
                                    @else
                                        <span class="muted">-</span>
                                    @endif
                                </td>
                                <td class="text-right" style="white-space:nowrap">
                                    <a class="btn btn-sm" href="{{ route('funcionarios.show', $funcionario) }}">
                                        {{ $canWrite ? 'Gerenciar' : 'Ver' }}
                                    </a>
                                    @if($canWrite)
                                        <a class="btn btn-sm btn-secondary" href="{{ route('funcionarios.edit', $funcionario) }}">Editar</a>
                                    @endif
                                    @if($canHardDelete)
                                        <form method="POST" action="{{ route('funcionarios.destroy', $funcionario) }}"
                                              data-confirm="Excluir definitivamente o funcionário {{ $funcionario->nome }} e seus itens? As evidências permanecem na Gestão de Documentos." style="display:inline">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-danger" type="submit" title="Exclusão definitiva">Excluir</button>
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
