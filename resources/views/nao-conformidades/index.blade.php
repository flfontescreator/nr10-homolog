@extends('layouts.app')

@section('title', 'Não Conformidades')

@section('content')
    <div class="page-header">
        <div>
            <h1>Não Conformidades</h1>
            <p class="subtitle">
                Agregado somente leitura das não conformidades registradas nos módulos —
                uma linha por NC. Fonte atual: <strong>RNC</strong>. O filtro padrão mostra
                apenas <strong>Não conformidade</strong>.
            </p>
        </div>
    </div>

    <div class="card">
        <form method="GET" action="{{ route('checklist.index') }}" class="form-grid">
            <div class="form-group">
                <label>Classificação</label>
                <select name="classificacao">
                    <option value="" @selected($classificacao === '')>Todos</option>
                    <option value="nao_conformidade" @selected($classificacao === 'nao_conformidade')>Não conformidade</option>
                    <option value="em_conformidade" @selected($classificacao === 'em_conformidade')>Em conformidade</option>
                </select>
            </div>
            <div class="form-group">
                <label>Inspeção de</label>
                <input type="date" name="de" value="{{ $de }}">
            </div>
            <div class="form-group">
                <label>Inspeção até</label>
                <input type="date" name="ate" value="{{ $ate }}">
            </div>
            <div class="form-group">
                <label>Ordenação</label>
                <select name="ordem">
                    <option value="desc" @selected($ordem === 'desc')>Mais recentes primeiro</option>
                    <option value="asc" @selected($ordem === 'asc')>Mais antigas primeiro</option>
                </select>
            </div>
            <div class="form-group" style="align-self:flex-end">
                <button class="btn" type="submit">Filtrar</button>
                <a class="btn btn-secondary" href="{{ route('checklist.index') }}">Limpar</a>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="table-wrap">
            <table class="grid">
                <thead>
                    <tr>
                        <th>NC</th>
                        <th>Descrição</th>
                        <th>Situação</th>
                        <th>Classificação</th>
                        <th>Tags</th>
                        <th>Prazo</th>
                        <th>Adequada em</th>
                        <th>Inspeção</th>
                        <th>RNC</th>
                        <th class="text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($itens as $item)
                        @php
                            $tags = [];
                            if ($item->rnc->status->isArchived()) {
                                $tags[] = [\App\Http\Controllers\NaoConformidadeController::TAG_ARQUIVADA, 'badge-archived'];
                            }
                            if ($item->situacao_id === null) {
                                $tags[] = [\App\Http\Controllers\NaoConformidadeController::TAG_NAO_PREENCHIDA, 'badge-amber'];
                            } elseif ($item->situacao?->nome === 'Conforme' && $item->prazo_adequacao === null) {
                                $tags[] = [\App\Http\Controllers\NaoConformidadeController::TAG_SEM_PRAZO, 'badge-amber'];
                            }
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $item->rnc->code }} · NC {{ $item->numero }}</strong>
                                <div class="muted small">{{ $item->rnc->modelo?->label() ?: '—' }}</div>
                            </td>
                            <td style="max-width:320px">
                                {{ \Illuminate\Support\Str::limit($item->titulo, 80) }}
                                @if($item->descricao)
                                    <div class="muted small">{{ \Illuminate\Support\Str::limit($item->descricao, 100) }}</div>
                                @endif
                            </td>
                            <td>
                                @if($item->situacao)
                                    <span class="badge badge-neutral">{{ $item->situacao->nome }}</span>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if($item->classificacao === 'nao_conformidade')
                                    <span class="badge badge-red">Não conformidade</span>
                                @else
                                    <span class="badge badge-green">Em conformidade</span>
                                @endif
                            </td>
                            <td>
                                @foreach($tags as [$rotulo, $classe])
                                    <span class="badge {{ $classe }}">{{ $rotulo }}</span>
                                @endforeach
                                @if($tags === [])
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td style="white-space:nowrap">
                                @if($item->prazo_adequacao)
                                    @if($item->prazoVencido())
                                        <strong style="color:#b91c1c">{{ $item->prazo_adequacao->format('d/m/Y') }}</strong>
                                    @else
                                        {{ $item->prazo_adequacao->format('d/m/Y') }}
                                    @endif
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td style="white-space:nowrap">
                                {{ $item->data_adequacao?->format('d/m/Y') ?: '—' }}
                            </td>
                            <td style="white-space:nowrap">
                                {{ $item->rnc->data_inspecao?->format('d/m/Y') ?: '—' }}
                            </td>
                            <td>
                                <span class="badge {{ $item->rnc->status->isPublished() ? 'badge-green' : ($item->rnc->status->isArchived() ? 'badge-archived' : 'badge-neutral') }}">
                                    {{ $item->rnc->status->label() }}
                                </span>
                            </td>
                            <td class="text-right">
                                @if($podeAbrir)
                                    <a class="btn btn-sm" href="{{ route('rnc.show', $item->rnc) }}">Abrir</a>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="muted">
                                Nenhuma não conformidade encontrada com os filtros atuais.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:16px">
            {{ $itens->links() }}
        </div>
    </div>
@endsection
