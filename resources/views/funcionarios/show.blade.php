@extends('layouts.app')

@section('title', 'Funcionário — '.$funcionario->nome)

@section('content')
    @php($average = \App\Http\Controllers\FuncionarioController::averagePercent($funcionario))

    <div class="page-header">
        <div>
            <h1>
                {{ $funcionario->nome }}
                @if($funcionario->matricula)
                    <span class="badge badge-neutral">{{ $funcionario->matricula }}</span>
                @endif
            </h1>
            <p class="subtitle">Item 4 do prontuário — documentação comprobatória da qualificação, habilitação e capacitação.</p>
        </div>
        <div style="display:flex;gap:8px">
            @if($canWrite)
                <a class="btn btn-secondary" href="{{ route('funcionarios.edit', $funcionario) }}">Editar</a>
            @endif
            <a class="btn btn-secondary" href="{{ route('funcionarios.index') }}">← Voltar</a>
        </div>
    </div>

    <div class="card" style="margin-bottom:16px">
        <div style="display:flex;align-items:baseline;gap:10px;justify-content:space-between;flex-wrap:wrap">
            <span style="font-size:14px">Média Geral deste funcionário:</span>
            <strong>{{ $average !== null ? number_format($average, 0, ',', '.') . '%' : '—' }}</strong>
        </div>
    </div>

    <div class="card">
        <h2 class="card-title">Sub-itens 4.1 a 4.8</h2>

        @if($items->isEmpty())
            <div class="docs-empty">Nenhum sub-item disponível para este funcionário.</div>
        @else
            <div class="table-wrap">
                <table class="grid">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Documento / não conformidade</th>
                            <th>Evidências</th>
                            <th>Status</th>
                            <th>Percentual</th>
                            <th class="text-right">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $item)
                            <tr>
                                <td><strong>{{ $item->catalogItem?->code }}</strong></td>
                                <td style="max-width:340px">{{ $item->catalogItem?->title }}</td>
                                <td>
                                    <span class="badge {{ $item->evidences_count > 0 ? 'badge-green' : 'badge-neutral' }}">
                                        {{ $item->evidences_count }}
                                    </span>
                                </td>
                                <td>
                                    @php($status = $item->evidencias_status)
                                    @if($status)
                                        <span class="badge {{ $status === 'Digital' ? 'badge-green' : ($status === 'Pendente' ? 'badge-amber' : 'badge-neutral') }}">
                                            {{ $status }}
                                        </span>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($item->percentual !== null)
                                        <div style="display:flex;align-items:center;gap:8px">
                                            <div class="progress" style="flex:1"><div class="progress-bar" style="width:{{ min($item->percentual, 100) }}%"></div></div>
                                            <span class="small">{{ number_format($item->percentual, 0, ',', '.') }}%</span>
                                        </div>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                <td class="text-right" style="white-space:nowrap">
                                    <a class="btn btn-sm" href="{{ route('prontuario.show', $item) }}">
                                        {{ $canWrite ? 'Editar' : 'Ver' }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection