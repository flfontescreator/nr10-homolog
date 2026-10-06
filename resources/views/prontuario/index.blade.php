@extends('layouts.app')

@section('title', 'Check-list Prontuário NR-10')

@section('content')
    <div class="page-header">
        <div>
            <h1>Check-list Prontuário NR-10</h1>
            <p class="subtitle">Itens do prontuário (fixos) com situação das evidências, percentual e média geral calculada.</p>
        </div>
        @if($canWrite)
            <a class="btn btn-secondary" href="{{ route('funcionarios.index') }}">Funcionários</a>
            <a class="btn btn-secondary" href="{{ route('prontuario.catalogo.index') }}">Gerenciar catálogo operacional</a>
        @endif
    </div>

    @if($tree->isEmpty())
        <div class="card docs-empty">Catálogo ainda não importado.</div>
    @else
        @foreach($tree as $branch)
            @php($section = $branch->section)
            @php($media = $averages[$section->n1] ?? null)
            <div class="card">
                <h2 class="card-title" style="display:flex;align-items:baseline;gap:10px;justify-content:space-between;flex-wrap:wrap">
                    <span style="display:flex;align-items:baseline;gap:10px">
                        <span class="badge badge-green">{{ $section->code }}</span>
                        {{ \Illuminate\Support\Str::limit($section->title, 90) }}
                    </span>
                    <span style="font-size:13px">
                        Média Geral:
                        <strong>{{ $media !== null ? number_format($media, 0, ',', '.') . '%' : '—' }}</strong>
                    </span>
                </h2>

<div class="table-wrap">
                <table class="grid docs-grid" style="table-layout:fixed">
                    <colgroup>
                        <col style="width:7%">
                        <col style="width:20%">
                        <col style="width:10%">
                        <col style="width:11%">
                        <col style="width:10%">
                        <col style="width:10%">
                        <col style="width:14%">
                        <col style="width:10%">
                        <col style="width:8%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Não conformidade</th>
                            <th>Arquivos</th>
                            <th style="white-space:normal">Condição inicial</th>
                            <th>Verificação</th>
                            <th style="white-space:normal">Validade do documento</th>
                            <th>Percentual</th>
                            <th>Evidência</th>
                            <th class="text-right">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($branch->children as $child)
                            @php($row = $map->get($child->id))
                            <tr>
                                <td><strong style="white-space:nowrap">{{ $child->code }}</strong></td>
                                <td style="max-width:320px;overflow-wrap:anywhere;word-break:break-word">{{ $child->title }}</td>
                                <td>
                                    @php($status = $row?->evidencias_status)
                                    @if($status)
                                        <span class="badge {{ $status === 'Digital' ? 'badge-green' : ($status === 'Pendente' ? 'badge-amber' : 'badge-neutral') }}">
                                            {{ $status }}
                                        </span>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                <td style="overflow-wrap:anywhere;word-break:break-word">{{ $row?->condicao_inicial ?? '—' }}</td>
                                <td style="white-space:nowrap">{{ $row?->data_realizacao?->format('d/m/Y') ?: '—' }}</td>
                                <td style="white-space:nowrap">{{ $row?->data_validade?->format('d/m/Y') ?: '—' }}</td>
                                <td>
                                    @if($row?->percentual !== null)
                                        <div style="display:flex;align-items:center;gap:8px">
                                            <div class="progress" style="flex:1;min-width:0"><div class="progress-bar" style="width:{{ min($row->percentual, 100) }}%"></div></div>
                                            <span class="small" style="white-space:nowrap">{{ number_format($row->percentual, 0, ',', '.') }}%</span>
                                        </div>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ ($row->evidences_count ?? 0) > 0 ? 'badge-green' : 'badge-neutral' }}">
                                        {{ $row->evidences_count ?? 0 }}
                                    </span>
                                </td>
                                <td class="text-right" style="white-space:nowrap">
                                    @if($row)
                                        <a class="btn btn-sm" href="{{ route('prontuario.show', $row) }}">
                                            {{ $canWrite ? 'Editar' : 'Ver' }}
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            </div>
        @endforeach
    @endif

    @if($avulso->isNotEmpty())
        <div class="card">
            <h2 class="card-title">
                <span class="badge badge-green">Avulso</span>
                Itens avulsos (fora do catálogo operacional)
            </h2>
            <p class="subtitle">
                Estes itens foram criados a partir do catálogo e permanecem registrados
                — o catálogo atual não os contém mais, mas os dados continuam preservados.
            </p>
            <div class="table-wrap">
                <table class="grid docs-grid" style="table-layout:fixed">
                    <colgroup>
                        <col style="width:10%">
                        <col style="width:34%">
                        <col style="width:14%">
                        <col style="width:14%">
                        <col style="width:14%">
                        <col style="width:8%">
                        <col style="width:6%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Não conformidade</th>
                            <th>Arquivos</th>
                            <th style="white-space:normal">Validade do documento</th>
                            <th>Percentual</th>
                            <th>Evidência</th>
                            <th class="text-right">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($avulso as $row)
                            <tr>
                                <td><strong style="white-space:nowrap">{{ $row->code }}</strong></td>
                                <td style="max-width:320px;overflow-wrap:anywhere;word-break:break-word">{{ $row->title }}</td>
                                <td>
                                    @php($status = $row->evidencias_status)
                                    @if($status)
                                        <span class="badge {{ $status === 'Digital' ? 'badge-green' : ($status === 'Pendente' ? 'badge-amber' : 'badge-neutral') }}">
                                            {{ $status }}
                                        </span>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                <td style="white-space:nowrap">{{ $row->data_validade?->format('d/m/Y') ?: '—' }}</td>
                                <td>
                                    @if($row->percentual !== null)
                                        {{ number_format($row->percentual, 0, ',', '.') }}%
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $row->evidences_count > 0 ? 'badge-green' : 'badge-neutral' }}">
                                        {{ $row->evidences_count }}
                                    </span>
                                </td>
                                <td class="text-right" style="white-space:nowrap">
                                    <a class="btn btn-sm" href="{{ route('prontuario.show', $row) }}">
                                        {{ $canWrite ? 'Editar' : 'Ver' }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
