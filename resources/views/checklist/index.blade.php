@extends('layouts.app')

@section('title', 'Não Conformidades')

@section('content')
    <div class="page-header">
        <div>
            <h1>Não Conformidades</h1>
            <p class="subtitle">Itens de não conformidade das instalações elétricas, com campos de controle por cliente.</p>
        </div>
    </div>

    <div class="card stat-row" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px">
        <span class="badge badge-red">Criticidade ALTA: {{ $critCounts['alta'] }}</span>
        <span class="badge badge-amber">Criticidade MÉDIA: {{ $critCounts['media'] }}</span>
        <span class="badge badge-neutral">Status Pendente: {{ $critCounts['pendente'] }}</span>
    </div>

    @if($items->isEmpty())
        <div class="card docs-empty">Catálogo ainda não importado.</div>
    @else
        <div class="card">
            <div class="table-wrap">
                <table class="grid">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item / Não conformidade</th>
                            <th>Criticidade</th>
                            <th>Setor</th>
                            <th>Condição inicial</th>
                            <th>Inspeção</th>
                            <th>Status</th>
                            <th>Evidências</th>
                            <th class="text-right">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $child)
                            @php($row = $map->get($child->id))
                            <tr>
                                <td><strong>{{ $child->code }}</strong></td>
                                <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $child->title }}">{{ $child->title }}</td>
                                <td>@include('partials.criticidade', ['criticidade' => $child->criticidade])</td>
                                <td>
                                    <div class="setores-mini">
                                        @forelse($row?->setores_list ?: $child->setores_list as $setor)
                                            <span class="badge badge-setor">{{ $setor }}</span>
                                        @empty
                                            <span class="muted">—</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $row?->condicao_inicial }}">{{ $row?->condicao_inicial ?: '—' }}</td>
                                <td>{{ $row?->data_inspecao?->format('d/m/Y') ?: '—' }}</td>
                                <td>
                                    @if($row?->status)
                                        <span class="badge {{ match($row->status->value) {
                                            'Concluído' => 'badge-green',
                                            'Em andamento' => 'badge-amber',
                                            'Auditoria' => 'badge-blue',
                                            default => 'badge-neutral',
                                        } }}">
                                            {{ $row->status->label() }}
                                        </span>
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
                                        <a class="btn btn-sm" href="{{ route('checklist.show', $row) }}">
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
    @endif
@endsection