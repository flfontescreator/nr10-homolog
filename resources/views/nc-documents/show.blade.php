@extends('layouts.app')

@section('title', $document->code.' — Não Conformidades')

@section('content')
    @php
        $canEditSelection = $canWrite && ! $document->is_finalized;
    @endphp

    <div class="page-header">
        <div>
            <h1>
                <span class="badge badge-blue">{{ $document->code }}</span>
                {{ $document->title }}
                @if($document->is_finalized)
                    <span class="badge badge-green">Finalizado</span>
                @else
                    <span class="badge badge-amber">Rascunho</span>
                @endif
            </h1>
            <p class="subtitle">{{ $document->description ?: 'Documento de não conformidades do cliente.' }}</p>
        </div>
        <div style="display:flex;gap:8px">
            <a class="btn btn-secondary" href="{{ route('checklist.index') }}">← Voltar</a>
            @if($canEditSelection)
                <a class="btn" href="{{ route('nc-documents.edit', $document) }}">Editar seleção</a>
            @endif
            @if($canWrite && ! $document->is_finalized)
                <form method="POST" action="{{ route('nc-documents.finalize', $document) }}"
                      onsubmit="return confirm('Finalizar o {{ $document->code }}? A edição será bloqueada.')">
                    @csrf
                    <button class="btn btn-sm" type="submit">Finalizar</button>
                </form>
            @endif
            @if($canWrite && $document->is_finalized)
                <form method="POST" action="{{ route('nc-documents.reopen', $document) }}"
                      onsubmit="return confirm('Reabrir o {{ $document->code }} para edição?')">
                    @csrf
                    <button class="btn btn-sm" type="submit">Reabrir edição</button>
                </form>
            @endif
            @if($canDelete)
                <form method="POST" action="{{ route('nc-documents.destroy', $document) }}"
                      onsubmit="return confirm('Excluir o {{ $document->code }}? O histórico e os vínculos serão removidos.')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm" type="submit">Excluir</button>
                </form>
            @endif
        </div>
    </div>

    <div class="card stat-row" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px">
        <span class="badge badge-blue">Itens: {{ $document->items_count ?? $document->items->count() }}</span>
        <span class="badge badge-amber">Não concluídos: {{ $pending }}</span>
        <span class="badge badge-neutral">Versões: {{ $document->versions->count() }}</span>
        <span class="muted">Finalizado em: {{ $document->finalized_at?->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') ?: '—' }}</span>
    </div>

    <div class="card">
        <h2 class="card-title">Itens do documento</h2>
        <div class="table-wrap">
            <table class="grid">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Não conformidade</th>
                        <th>Criticidade</th>
                        <th>Setor</th>
                        <th>Status</th>
                        <th>Evidências</th>
                        <th class="text-right">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($document->items as $entry)
                        @if($entry->catalogItem->is_section)
                            <tr style="background:#f4f6f8">
                                <td><span class="badge badge-blue">{{ $entry->catalogItem->code }}</span></td>
                                <td colspan="6" style="font-weight:700">{{ $entry->catalogItem->title }}</td>
                            </tr>
                        @else
                        <tr>
                            <td><strong>{{ $entry->catalogItem->code }}</strong></td>
                            <td style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $entry->catalogItem->title }}">
                                {{ $entry->catalogItem->title }}
                            </td>
                            <td>@include('partials.criticidade', ['criticidade' => $entry->catalogItem->criticidade])</td>
                            <td>
                                <div class="setores-mini">
                                    @forelse($entry->setores_list as $setor)
                                        <span class="badge badge-setor">{{ $setor }}</span>
                                    @empty
                                        <span class="muted">—</span>
                                    @endforelse
                                </div>
                            </td>
                            <td>
                                @if($entry->status)
                                    <span class="badge {{ match($entry->status->value) {
                                        'Concluído' => 'badge-green',
                                        'Em andamento' => 'badge-amber',
                                        'Auditoria' => 'badge-blue',
                                        default => 'badge-neutral',
                                    } }}">
                                        {{ $entry->status->label() }}
                                    </span>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ ($evidenceCounts->get($entry->id) ?? 0) > 0 ? 'badge-green' : 'badge-neutral' }}">
                                    {{ $evidenceCounts->get($entry->id) ?? 0 }}
                                </span>
                            </td>
                            <td class="text-right" style="white-space:nowrap">
                                <a class="btn {{ $canWrite && ! $document->is_finalized ? 'btn-sm' : 'btn-sm btn-secondary' }}"
                                   href="{{ route('cronograma.show', [$entry->tenant_item_id, 'from' => 'document', 'document_id' => $document->id]) }}">
                                    {{ $canWrite && ! $document->is_finalized ? 'Trabalhar' : 'Ver' }}
                                </a>
                            </td>
                        </tr>
                        @endif
                    @empty
                        <tr><td colspan="7" class="muted">Este documento ainda não tem itens.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2 class="card-title">Arquivos vinculados (biblioteca)</h2>
        @if($libraryFiles->isEmpty())
            <p class="muted">Nenhum arquivo da biblioteca vinculado a este documento.</p>
        @else
            @foreach($libraryFiles as $evidence)
                <div class="evidence-item">
                    @php($isImage = str_starts_with((string) $evidence->mime_type, 'image/'))
                    @if($isImage)
                        <a href="{{ route('documentos.download', $evidence) }}" title="{{ $evidence->original_name }}" class="evidence-thumb">
                            <img src="{{ route('documentos.preview', $evidence) }}" alt="{{ $evidence->original_name }}" loading="lazy">
                        </a>
                    @else
                        @include('partials.icon', ['name' => 'file'])
                    @endif
                    <div style="flex:1;min-width:0">
                        <a href="{{ route('documentos.download', $evidence) }}" title="{{ $evidence->original_name }}">
                            {{ \Illuminate\Support\Str::limit($evidence->original_name, 60) }}
                        </a>
                        <div class="muted small">
                            {{ $evidence->humanSize() }} · por {{ $evidence->uploader?->name ?? '—' }} · {{ $evidence->created_at->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }}
                        </div>
                        <div class="setores-mini">
                            @foreach($evidence->documents as $ref)
                                <a class="badge badge-blue" style="text-decoration:none"
                                   href="{{ route('nc-documents.show', $ref) }}" title="Abrir {{ $ref->code }}">
                                    {{ $ref->code }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                    @if($canWrite && ! $document->is_finalized)
                        <form method="POST" action="{{ route('nc-documents.evidence.detach', [$document, $evidence]) }}"
                              data-confirm="Desvincular este arquivo do {{ $document->code }}? Ele permanece na biblioteca.">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-danger" type="submit">Desvincular</button>
                        </form>
                    @endif
                </div>
            @endforeach
        @endif
    </div>

    <div class="card">
        <h2 class="card-title">Histórico de versões (snapshots)</h2>
        @if($document->versions->isEmpty())
            <p class="muted">Sem versões registradas.</p>
        @else
            @foreach($document->versions as $version)
                <details style="margin-bottom:8px">
                    <summary style="cursor:pointer">
                        <strong>v{{ $version->version }}</strong>
                        — {{ $version->summary ?: 'Atualização' }}
                        <span class="muted">· {{ $version->created_at?->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }} · {{ $version->creator?->name ?: '—' }}</span>
                        <span class="badge badge-neutral">{{ count($version->selection) }} itens</span>
                    </summary>
                    <div class="table-wrap" style="margin-top:8px">
                        <table class="grid">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Não conformidade</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($version->selection as $snap)
                                    <tr>
                                        <td><strong>{{ $snap['code'] }}</strong></td>
                                        <td>{{ $snap['title'] }}</td>
                                        <td>{{ $snap['status'] ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            @endforeach
        @endif
    </div>
@endsection