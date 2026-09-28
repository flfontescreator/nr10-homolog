@extends('layouts.app')

@section('title', 'Gestão de Documentos')

@section('content')
    <div class="page-header">
        <div>
            <h1>Gestão de Documentos</h1>
            <p class="subtitle">Todos os arquivos anexados como evidências do cliente ativo.</p>
        </div>
    </div>

    <div class="card">
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px">
            <a class="btn btn-sm {{ ! $activeSource ? 'btn' : 'btn-secondary' }}" href="{{ route('documentos.index') }}">Todos</a>
            @foreach($sources as $source)
                <a class="btn btn-sm {{ $activeSource === $source->value ? 'btn' : 'btn-secondary' }}"
                   href="{{ route('documentos.index', ['source' => $source->value]) }}">
                    {{ $source->label() }}
                </a>
            @endforeach
        </div>

        @if($documents->isEmpty())
            <div class="docs-empty">Nenhum documento anexado ainda.</div>
        @else
            <div class="table-wrap">
                <table class="grid docs-grid" style="table-layout:fixed">
                    <colgroup>
                        <col style="width:16%">
                        <col style="width:10%">
                        <col style="width:17%">
                        <col style="width:13%">
                        <col style="width:10%">
                        <col style="width:12%">
                        <col style="width:6%">
                        <col style="width:16%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Arquivo</th>
                            <th>Módulo</th>
                            <th>Item</th>
                            <th style="white-space:normal">Documento de referência</th>
                            <th>Enviado por</th>
                            <th>Enviado em</th>
                            <th>Tamanho</th>
                            <th class="text-right">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($documents as $doc)
                            <tr>
                                <td style="max-width:170px">
                                    <a href="{{ route('documentos.download', $doc) }}" title="{{ $doc->original_name }}"
                                       style="display:block;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                        {{ $doc->displayName() }}
                                    </a>
                                </td>
                                <td>
                                    @php($source = $doc->tenantItem->catalogItem->source ?? null)
                                    @if($source)
                                        @php($moduleLabels = ['cronograma' => 'Cronograma', 'prontuario' => 'Prontuário', 'checklist' => 'Checklist'])
                                        <span class="badge {{ $source->value === 'cronograma' ? 'badge-blue' : ($source->value === 'prontuario' ? 'badge-green' : 'badge-neutral') }}">
                                            {{ $moduleLabels[$source->value] ?? $source->label() }}
                                        </span>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($doc->relatedItems()->isNotEmpty())
                                        <div style="display:flex;flex-wrap:wrap;gap:4px">
                                            @foreach($doc->relatedItems() as $item)
                                                <span class="badge {{ ['badge-green', 'badge-amber', 'badge-setor', 'badge-neutral'][$loop->index % 4] }}"
                                                      title="{{ $item->code }} — {{ $item->description ?? '' }}">
                                                    {{ $item->code }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($doc->documents->isNotEmpty())
                                        <div class="setores-mini">
                                            @foreach($doc->documents as $ref)
                                                <a class="badge badge-blue" style="text-decoration:none"
                                                   href="{{ route('nc-documents.show', $ref) }}"
                                                   title="Abrir {{ $ref->code }}">
                                                    {{ $ref->code }}
                                                </a>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                <td>{{ $doc->uploader?->name ?? '—' }}</td>
                                <td>{{ $doc->created_at->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</td>
                                <td>{{ $doc->humanSize() }}</td>
                                <td class="text-right">
                                    <a class="btn btn-sm btn-secondary" href="{{ route('documentos.download', $doc) }}">Baixar</a>
                                    @if(auth()->user()->canDeleteEvidence())
                                        <form method="POST" action="{{ route('documentos.destroy', $doc) }}"
                                              data-confirm="Excluir este documento?" style="display:inline">
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

            <div class="mt-4">{{ $documents->links() }}</div>
        @endif
    </div>
@endsection