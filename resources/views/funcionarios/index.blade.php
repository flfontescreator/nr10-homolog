@extends('layouts.app')

@section('title', 'Funcionários — Prontuário NR-10')

@section('content')
    <div class="page-header">
        <div>
            <h1>Funcionários — Item 4</h1>
            <p class="subtitle">Cada funcionário possui o item 4 (documentação comprobatória) com seus sub-itens 4.1 a 4.8, cada um com seus próprios campos de controle e evidências.</p>
        </div>
        <div style="display:flex;gap:8px">
            @if($canWrite)
                <a class="btn" href="{{ route('funcionarios.create') }}">+ Novo funcionário</a>
            @endif
            <a class="btn btn-secondary" href="{{ route('prontuario.index') }}">← Voltar ao Prontuário</a>
        </div>
    </div>

    @if($funcionarios->isEmpty())
        <div class="card docs-empty">
            Nenhum funcionário cadastrado.
            @if($canWrite)
                <a href="{{ route('funcionarios.create') }}">Cadastre o primeiro</a> para abrir o item 4 do prontuário por funcionário.
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
                            <th>Sub-itens (4.x)</th>
                            <th>Evidências</th>
                            <th class="text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($funcionarios as $funcionario)
                            <tr>
                                <td><strong>{{ $funcionario->nome }}</strong></td>
                                <td>{{ $funcionario->matricula ?: '—' }}</td>
                                <td>
                                    <span class="badge badge-neutral">{{ $funcionario->items_count }}</span>
                                    <span class="badge badge-blue">item 4</span>
                                </td>
                                <td>
                                    <span class="badge {{ ($evidencesByFuncionario[$funcionario->id] ?? 0) > 0 ? 'badge-green' : 'badge-neutral' }}">
                                        {{ $evidencesByFuncionario[$funcionario->id] ?? 0 }}
                                    </span>
                                </td>
                                <td class="text-right" style="white-space:nowrap">
                                    <a class="btn btn-sm" href="{{ route('funcionarios.show', $funcionario) }}">
                                        {{ $canWrite ? 'Gerenciar' : 'Ver' }}
                                    </a>
                                    @if($canWrite)
                                        <a class="btn btn-sm btn-secondary" href="{{ route('funcionarios.edit', $funcionario) }}">Editar</a>
                                        <form method="POST" action="{{ route('funcionarios.destroy', $funcionario) }}"
                                              data-confirm="Excluir o funcionário '{{ $funcionario->nome }}' e seus sub-itens do item 4?" style="display:inline">
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