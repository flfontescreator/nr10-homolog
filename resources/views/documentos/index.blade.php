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
            <a class="btn btn-sm {{ $activeSource === 'funcionarios' ? 'btn' : 'btn-secondary' }}"
               href="{{ route('documentos.index', ['source' => 'funcionarios']) }}">
                Funcionários
            </a>
        </div>

        @if($documents->isEmpty())
            <div class="docs-empty">Nenhum documento anexado ainda.</div>
        @else
            <div class="table-wrap">
                <table class="grid docs-grid" style="table-layout:fixed">
                    <colgroup>
                        <col style="width:24%">
                        <col style="width:12%">
                        <col style="width:12%">
                        <col style="width:15%">
                        <col style="width:11%">
                        <col style="width:8%">
                        <col style="width:11%">
                        @if($canHardDelete)
                            <col style="width:7%">
                        @endif
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Arquivo</th>
                            <th>Módulo</th>
                            <th>Item</th>
                            <th style="white-space:normal">Documento</th>
                            <th>Enviado</th>
                            <th>Situação</th>
                            <th>Validade</th>
                            @if($canHardDelete)
                                <th></th>
                            @endif
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
                                    @if($doc->description)
                                        <span class="muted small" style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                                              title="{{ $doc->description }}">{{ $doc->description }}</span>
                                    @endif
                                </td>
                                <td>
                                    @php($moduleLabels = ['cronograma' => 'Cronograma', 'prontuario' => 'Prontuário', 'checklist' => 'Checklist', 'funcionarios' => 'Funcionários'])
                                    <span class="badge badge-blue">
                                        {{ $moduleLabels[$doc->modulo()] ?? $doc->modulo() }}
                                    </span>
                                </td>
                                <td>
                                    @php($relatedItems = $doc->relatedItems())
                                    @php($relatedFuncionario = $doc->funcionarioItem)
                                    @if($relatedItems->isNotEmpty() || $relatedFuncionario)
                                        <div style="display:flex;flex-wrap:wrap;gap:4px">
                                            @foreach($relatedItems as $item)
                                                <span class="badge badge-blue-alt"
                                                      title="{{ $item->code }} — {{ $item->description ?? '' }}">
                                                    {{ $item->code }}
                                                </span>
                                            @endforeach
                                            @if($relatedFuncionario)
                                                <span class="badge badge-blue-alt"
                                                      title="{{ $relatedFuncionario->funcionario?->nome }} — {{ $relatedFuncionario->titulo }}">
                                                    {{ $relatedFuncionario->funcionario?->nome }}
                                                </span>
                                            @endif
                                        </div>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @php($traceable = $doc->documents
                                        ->merge($referencing->get($doc->tenant_item_id, collect()))
                                        ->unique('id')
                                        ->sortBy('code'))
                                    @if($traceable->isNotEmpty())
                                        <div class="setores-mini">
                                            @foreach($traceable as $ref)
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
                                <td>{{ $doc->created_at->format('d/m/Y H:i') }}</td>
                                <td>
                                    @php($situacoes = $doc->documents
                                        ->merge($referencing->get($doc->tenant_item_id, collect()))
                                        ->unique('id')
                                        ->pluck('status')
                                        ->filter()
                                        ->unique()
                                        ->map(fn ($status) => $status instanceof \App\Enums\DocumentStatus
                                            ? $status->label()
                                            : (\App\Enums\DocumentStatus::tryFrom($status)?->label() ?? $status)))
                                    @if($situacoes->isNotEmpty())
                                        <div class="setores-mini">
                                            @foreach($situacoes as $situacao)
                                                <span class="badge badge-blue-alt">{{ $situacao }}</span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($doc->validade)
                                        @php($days = $doc->daysUntilExpiry())
                                        <span class="badge {{ $days !== null && $days <= 30 ? 'badge-red' : 'badge-blue-alt' }}">
                                            {{ $doc->validade->format('d/m/Y') }}
                                            @if($days !== null)
                                                <span class="muted">· {{ $days >= 0 ? $days.' dias' : 'vencido' }}</span>
                                            @endif
                                        </span>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                @if($canHardDelete)
                                    <td class="text-right" style="white-space:nowrap">
                                        <form method="POST" action="{{ route('documentos.destroy', $doc) }}"
                                              data-confirm="Excluir definitivamente o arquivo {{ $doc->displayName() }}? Esta ação não pode ser desfeita.">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-danger" type="submit">Excluir</button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $documents->links() }}</div>
        @endif
    </div>
@endsection