@extends('layouts.app')

@section('title', 'Check-list Prontuário NR-10')

@section('content')
    <div class="page-header">
        <div>
            <h1>Check-list Prontuário NR-10</h1>
            <p class="subtitle">Itens do prontuário (fixos) com situação das evidências, percentual e média geral calculada.</p>
        </div>
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
                <table class="grid">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Não conformidade</th>
                            <th>Evidências</th>
                            <th>Realização</th>
                            <th>Validade</th>
                            <th>Percentual</th>
                            <th>Arquivos</th>
                            <th class="text-right">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($branch->children as $child)
                            @php($row = $map->get($child->id))
                            @if($section->n1 === 4)
                                {{-- Item 4 é gerenciado por funcionário; sub-itens 4.x por funcionário não aparecem aqui. --}}
                                @continue
                            @endif
                            <tr>
                                <td><strong>{{ $child->code }}</strong></td>
                                <td style="max-width:320px">{{ $child->title }}</td>
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
                                <td>{{ $row?->data_realizacao?->format('d/m/Y') ?: '—' }}</td>
                                <td>{{ $row?->data_validade?->format('d/m/Y') ?: '—' }}</td>
                                <td>
                                    @if($row?->percentual !== null)
                                        <div style="display:flex;align-items:center;gap:8px">
                                            <div class="progress" style="flex:1"><div class="progress-bar" style="width:{{ min($row->percentual, 100) }}%"></div></div>
                                            <span class="small">{{ number_format($row->percentual, 0, ',', '.') }}%</span>
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
                        @if($section->n1 === 4)
                            <tr>
                                <td colspan="8">
                                    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                                        <span class="badge badge-blue">{{ $funcionariosTotal }}</span>
                                        <span>funcionário(s) com item 4 (4.1 a 4.8) do prontuário.</span>
                                        <a class="btn btn-sm" href="{{ route('funcionarios.index') }}">Gerenciar funcionários</a>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
            </div>
        @endforeach
    @endif
@endsection