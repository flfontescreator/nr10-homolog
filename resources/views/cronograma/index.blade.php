@extends('layouts.app')

@section('title', 'Cronograma de Adequação')

@section('content')
    <div class="page-header">
        <div>
            <h1>Cronograma de Adequação NR-10</h1>
            <p class="subtitle">Itens e subitens do catálogo (fixos) com os campos de controle por cliente.</p>
        </div>
    </div>

    @if($tree->isEmpty())
        <div class="card docs-empty">Catálogo ainda não importado.</div>
    @else
        @foreach($tree as $branch)
            <div class="card">
                <h2 class="card-title" style="display:flex;align-items:baseline;gap:10px">
                    <span class="badge badge-blue">{{ $branch->section->code }}</span>
                    {{ $branch->section->title }}
                </h2>

                <div class="table-wrap">
                    <table class="grid">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Item / Requisito</th>
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
                            @foreach($branch->children as $child)
                                @php($row = $map->get($child->id))
                                <tr>
                                    <td><strong>{{ $child->code }}</strong></td>
                                    <td style="max-width:340px">{{ \Illuminate\Support\Str::limit($child->title, 110) }}</td>
                                    <td>@include('partials.criticidade', ['criticidade' => $row?->criticidade_atual ?: $child->criticidade])</td>
                                    <td>
                                        <div class="setores-mini">
                                            @forelse($row?->setores_list ?: $child->setores_list as $setor)
                                                <span class="badge badge-setor">{{ $setor }}</span>
                                            @empty
                                                <span class="muted">—</span>
                                            @endforelse
                                        </div>
                                    </td>
                                    <td>{{ $row?->condicao_inicial ?: '—' }}</td>
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
                                            <a class="btn btn-sm" href="{{ route('cronograma.show', $row) }}">
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
@endsection