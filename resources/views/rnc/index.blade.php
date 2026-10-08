@extends('layouts.app')

@section('title', 'RNC')

@section('content')
    <div class="page-header">
        <div>
            <h1>RNC — Relatórios de Não Conformidade</h1>
            <p class="subtitle">
                Cada relatório tem numeração própria por cliente (<code>RNC_0001</code>) e só ganha
                valor oficial quando é <strong>publicado</strong> — a publicação cria uma revisão
                (<code>Rev:0001</code>), gera o PDF e um link público válido por 7 dias.
            </p>
        </div>
        <a class="btn" href="{{ route('rnc.create') }}">+ Novo RNC</a>
    </div>

    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:14px">
        <form method="GET" action="{{ route('rnc.index') }}" style="display:flex;gap:8px;margin-left:auto;align-items:center;flex-wrap:wrap">
            <select name="situacao" aria-label="Situação">
                <option value="todos" @selected($situacao === 'todos')>Todos</option>
                <option value="rascunho" @selected($situacao === 'rascunho')>Rascunho</option>
                <option value="publicado" @selected($situacao === 'publicado')>Publicado</option>
                <option value="arquivado" @selected($situacao === 'arquivado')>Arquivado</option>
            </select>
            <input type="search" name="q" value="{{ $busca }}" placeholder="Buscar por código ou título"
                   style="min-width:240px">
            <button class="btn btn-sm" type="submit">Buscar</button>
            @if($busca || $situacao !== 'todos')
                <a class="btn btn-sm btn-secondary" href="{{ route('rnc.index') }}">Limpar</a>
            @endif
        </form>
    </div>

    @if($rncs->isEmpty())
        <div class="card docs-empty">
            @if($busca)
                Nenhum RNC encontrado para “{{ $busca }}”.
            @elseif($situacao !== 'todos')
                Nenhum RNC no filtro selecionado.
            @else
                Nenhum relatório criado ainda.
                <a href="{{ route('rnc.create') }}">Crie o primeiro RNC</a> para começar.
            @endif
        </div>
    @else
        <div class="card">
            <div class="table-wrap">
                <table class="grid">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Título</th>
                            <th>Projeto</th>
                            <th class="text-right">NCs</th>
                            <th>Revisão</th>
                            <th>Situação</th>
                            <th class="text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rncs as $rnc)
                            <tr>
                                <td>
                                    <strong>{{ $rnc->code }}</strong>
                                    @if($rnc->data_inspecao)
                                        <div class="muted small">{{ $rnc->data_inspecao->format('d/m/Y') }}</div>
                                    @endif
                                </td>
                                <td>
                                    {{ \Illuminate\Support\Str::limit($rnc->titulo, 70) }}
                                    <div class="muted small">{{ $rnc->modelo?->label() ?? '—' }}</div>
                                </td>
                                <td class="small">{{ $rnc->projeto?->nome ?: '—' }}</td>
                                <td class="text-right">
                                    <span class="badge badge-neutral">{{ $rnc->items_count }}</span>
                                </td>
                                <td>
                                    @if($rnc->current_revision > 0)
                                        <span class="badge badge-blue">{{ $rnc->makeRevisionLabel($rnc->current_revision) }}</span>
                                    @else
                                        <span class="muted small">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $rnc->status->isPublished() ? 'badge-green' : ($rnc->status->isArchived() ? 'badge-archived' : 'badge-neutral') }}">
                                        {{ $rnc->status->label() }}
                                    </span>
                                </td>
                                <td class="text-right" style="white-space:nowrap">
                                    <a class="btn btn-sm" href="{{ route('rnc.show', $rnc) }}">Abrir</a>
                                    @unless($rnc->status->isArchived())
                                        <a class="btn btn-sm btn-secondary" href="{{ route('rnc.edit', $rnc) }}">Editar</a>
                                        @if(auth()->user()?->canDelete())
                                            <form method="POST" action="{{ route('rnc.destroy', $rnc) }}"
                                                  style="display:inline"
                                                  data-confirm="Excluir o RNC {{ $rnc->code }}? Isso apaga as não conformidades, as evidências, as revisões/PDF e o link público.">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-danger" type="submit">Excluir</button>
                                            </form>
                                        @endif
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @unless($pdfAvailable)
        <div class="alert alert-warning" style="margin-top:14px">
            O gerador de PDF não está disponível neste servidor. Os relatórios continuam sendo
            gerados em Markdown e impressos pelo navegador (“Imprimir / Salvar como PDF”).
        </div>
    @endunless
@endsection
