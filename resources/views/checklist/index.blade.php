@extends('layouts.app')

@section('title', 'Não Conformidades')

@section('content')
    @php
        $finalized = $documents->where('is_finalized', true);
        $totalItems = $documents->sum('items_count');
    @endphp

    <div class="page-header">
        <div>
            <h1>Não Conformidades</h1>
            <p class="subtitle">
                Documentos de não conformidades personalizados por cliente, com histórico de versões (snapshots).
            </p>
        </div>
        @if($canWrite)
            <a class="btn" href="{{ route('nc-documents.create') }}">+ Novo Documento</a>
        @endif
    </div>

    <div class="card stat-row" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px">
        <span class="badge badge-blue">Documentos: {{ $documents->count() }}</span>
        <span class="badge badge-green">Finalizados: {{ $finalized->count() }}</span>
        <span class="badge badge-neutral">Itens: {{ $totalItems }}</span>
        <span class="badge badge-amber">Pendentes: {{ $pendingTotal }}</span>
    </div>

    @if($documents->isEmpty())
        <div class="card docs-empty">
            Nenhum documento de não conformidade ainda.
            @if($canWrite)
                <a href="{{ route('nc-documents.create') }}">Criar o primeiro documento</a>.
            @endif
        </div>
    @else
        <div class="card">
            <div class="table-wrap">
                <table class="grid">
                    <thead>
                        <tr>
                            <th>Documento</th>
                            <th>Título</th>
                            <th>Itens</th>
                            <th>Status</th>
                            <th>Criado por</th>
                            <th>Última atualização</th>
                            <th class="text-right">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($documents as $document)
                            <tr>
                                <td><strong>{{ $document->code }}</strong></td>
                                <td style="max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $document->title }}">
                                    {{ $document->title }}
                                </td>
                                <td>{{ $document->items_count }}</td>
                                <td>
                                    @if($document->is_finalized)
                                        <span class="badge badge-green">Finalizado</span>
                                    @else
                                        <span class="badge badge-amber">Rascunho</span>
                                    @endif
                                </td>
                                <td>{{ $document->creator?->name ?: '—' }}</td>
                                <td>{{ $document->updated_at?->format('d/m/Y H:i') ?: '—' }}</td>
                                <td class="text-right" style="white-space:nowrap">
                                    <button class="btn btn-sm" form="doc-open-{{ $document->id }}">Abrir</button>
                                    <form id="doc-open-{{ $document->id }}" method="GET" action="{{ route('nc-documents.show', $document) }}" class="inline">
                                    </form>
                                    @if($canWrite && ! $document->is_finalized)
                                        <button class="btn btn-sm btn-secondary" form="doc-edit-{{ $document->id }}">Editar</button>
                                        <form id="doc-edit-{{ $document->id }}" method="GET" action="{{ route('nc-documents.edit', $document) }}" class="inline">
                                        </form>
                                        <button class="btn btn-sm btn-secondary" form="doc-finalize-{{ $document->id }}"
                                                onclick="return confirm('Finalizar o {{ $document->code }}? A edição será bloqueada.')">Finalizar</button>
                                        <form id="doc-finalize-{{ $document->id }}" method="POST" action="{{ route('nc-documents.finalize', $document) }}" class="inline">
                                            @csrf
                                        </form>
                                    @endif
                                    @if($canWrite && $document->is_finalized)
                                        <button class="btn btn-sm" form="doc-reopen-{{ $document->id }}"
                                                onclick="return confirm('Reabrir o {{ $document->code }} para edição?')">Reabrir</button>
                                        <form id="doc-reopen-{{ $document->id }}" method="POST" action="{{ route('nc-documents.reopen', $document) }}" class="inline">
                                            @csrf
                                        </form>
                                    @endif
                                    @if($canDelete)
                                        <button class="btn btn-sm btn-danger" form="doc-delete-{{ $document->id }}"
                                                onclick="return confirm('Excluir o {{ $document->code }}? O histórico e os vínculos serão removidos.')">Excluir</button>
                                        <form id="doc-delete-{{ $document->id }}" method="POST" action="{{ route('nc-documents.destroy', $document) }}" class="inline">
                                            @csrf
                                            @method('DELETE')
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