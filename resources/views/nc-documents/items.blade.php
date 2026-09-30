@extends('layouts.app')

@section('title', 'Itens de '.$document->code)

@section('content')
    <div class="page-header">
        <div>
            <h1>
                <span class="badge badge-blue">{{ $document->code }}</span>
                Gerenciar itens do documento
            </h1>
            <p class="subtitle">
                Os itens deste documento são uma <strong>cópia própria</strong> do catálogo:
                crie, edite ou exclua aqui sem afetar o catálogo operacional, e vice-versa.
                Excluir um item da base do catálogo não altera este documento.
            </p>
        </div>
        <div style="display:flex;gap:8px">
            <a class="btn btn-secondary" href="{{ route('nc-documents.show', $document) }}">← Voltar ao documento</a>
            @if(! $document->is_finalized)
                <a class="btn" href="{{ route('nc-documents.edit', $document) }}">Editar seleção</a>
            @endif
        </div>
    </div>

    <div class="card stat-row" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px">
        <span class="badge badge-blue">Itens: {{ $document->items->count() }}</span>
        <span class="badge badge-green">Seções: {{ $sections->count() }}</span>
        <span class="badge badge-neutral">Sub-itens: {{ $childrenBySection->flatten()->count() + $avulso->count() }}</span>
        <span class="badge badge-amber">Cópias preservadas (avulso): {{ $avulso->count() }}</span>
    </div>

    @if(! $document->is_finalized)
        <div class="card">
            <h2 class="card-title">Adicionar item do catálogo (como cópia)</h2>
            <form method="POST" action="{{ route('nc-documents.items.attach', $document) }}" class="form-grid" style="grid-template-columns:1fr auto;align-items:end">
                @csrf
                <div class="form-group" style="margin-bottom:0">
                    <label>Escolha no catálogo operacional — entra no documento como cópia independente</label>
                    <select name="catalog_item_ids[]" required>
                        <option value="">— escolha uma seção ou sub-item —</option>
                        @foreach($operacional as $branch)
                            @php($section = $branch->section)
                            <optgroup label="[{{ $section->code }}] {{ $section->title }}">
                                <option value="{{ $section->id }}">Toda a seção (capa + {{ $branch->children->count() }} sub-itens)</option>
                                @foreach($branch->children as $child)
                                    <option value="{{ $child->id }}">{{ $child->code }} — {{ $child->title }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
                <button class="btn" type="submit">Adicionar ao documento</button>
            </form>
        </div>
    @endif

    @forelse($sections as $section)
        @php($secChildren = $childrenBySection->get((string) $section->catalogItem?->n1, collect())->values())
        @php($isLinked = $section->catalog_item_id !== null)
        <div class="card">
            <div class="card-toolbar" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
                <h2 class="card-title" style="margin:0;display:flex;align-items:center;gap:10px;flex-wrap:wrap;overflow-wrap:anywhere;word-break:break-word;min-width:0">
                    <span class="badge badge-green">{{ $section->code }}</span>
                    {{ $section->title }}
                    @if($isLinked)
                        <span class="badge badge-neutral">no catálogo</span>
                    @else
                        <span class="badge badge-amber">cópia preservada</span>
                    @endif
                </h2>
                @if(! $document->is_finalized)
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <button type="button" class="btn btn-sm" data-toggle-form="edit-docitem-{{ $section->id }}">Editar</button>
                        <form method="POST" action="{{ route('nc-documents.items.destroy', [$document, $section]) }}"
                              data-confirm="Excluir a seção {{ $section->code }} e os {{ $secChildren->count() }} sub-itens dela deste documento? A linha de trabalho do cliente não é apagada.">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-danger" type="submit">Excluir</button>
                        </form>
                    </div>
                @endif
            </div>

            @if(! $document->is_finalized && $isLinked)
                <form method="POST" action="{{ route('nc-documents.items.update', [$document, $section]) }}" id="edit-docitem-{{ $section->id }}" class="form-grid" style="grid-template-columns:120px 1fr auto;display:none;margin-top:12px">
                    @csrf
                    @method('PUT')
                    <div class="form-group" style="margin-bottom:0">
                        <input type="text" name="code" maxlength="30" value="{{ $section->code }}" placeholder="Código" required>
                    </div>
                    <div class="form-group" style="margin-bottom:0">
                        <input type="text" name="title" maxlength="255" value="{{ $section->title }}" placeholder="Título no documento" required>
                    </div>
                    <button class="btn btn-sm" type="submit">Salvar</button>
                </form>
            @endif

            @if($secChildren->isNotEmpty())
                <div class="table-wrap" style="margin-top:12px">
                    <table class="grid">
                        <tbody>
                            @foreach($secChildren as $child)
                                <tr>
                                    <td style="width:70px"><strong>{{ $child->code }}</strong></td>
                                    <td style="overflow-wrap:anywhere;word-break:break-word;min-width:0">
                                        {{ $child->title }}
                                        @if($child->catalog_item_id === null)
                                            <span class="badge badge-amber">cópia preservada</span>
                                        @endif
                                    </td>
                                    @if(! $document->is_finalized)
                                        <td class="text-right" style="white-space:nowrap">
                                            <button type="button" class="btn btn-sm btn-secondary" data-toggle-form="edit-docitem-{{ $child->id }}">Editar</button>
                                            <form method="POST" action="{{ route('nc-documents.items.destroy', [$document, $child]) }}" data-confirm="Excluir o sub-item {{ $child->code }} deste documento? A linha de trabalho do cliente não é apagada." style="display:inline">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-danger" type="submit">Excluir</button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                                @if(! $document->is_finalized)
                                    <tr>
                                        <td colspan="3" style="padding:0 8px 8px 76px">
                                            <form method="POST" action="{{ route('nc-documents.items.update', [$document, $child]) }}" id="edit-docitem-{{ $child->id }}" class="form-grid" style="grid-template-columns:120px 1fr auto;display:none">
                                                @csrf
                                                @method('PUT')
                                                <div class="form-group" style="margin-bottom:0">
                                                    <input type="text" name="code" maxlength="30" value="{{ $child->code }}" required>
                                                </div>
                                                <div class="form-group" style="margin-bottom:0">
                                                    <input type="text" name="title" maxlength="255" value="{{ $child->title }}" required>
                                                </div>
                                                <button class="btn btn-sm" type="submit">Salvar</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="muted" style="margin-top:12px">Sem sub-itens neste documento para esta seção.</p>
            @endif
        </div>
    @empty
        @if($avulso->isEmpty())
            <div class="card docs-empty">
                Este documento ainda não tem itens operacionais.
                @if(! $document->is_finalized)
                    Use o formulário acima para adicionar uma cópia do catálogo.
                @endif
            </div>
        @endif
    @endforelse

    @if($avulso->isNotEmpty())
        <div class="card">
            <h2 class="card-title">
                <span class="badge badge-amber">Cópias preservadas</span>
                Itens avulsos (sem vínculo no catálogo atual)
            </h2>
            <p class="subtitle">
                Estes itens foram criados a partir do catálogo e permanecem registrados neste
                documento como cópia própria — o catálogo atual não os contém mais.
            </p>
            <div class="table-wrap">
                <table class="grid">
                    <tbody>
                        @foreach($avulso as $entry)
                            <tr>
                                <td style="width:70px"><strong>{{ $entry->code }}</strong></td>
                                <td style="overflow-wrap:anywhere;word-break:break-word;min-width:0">{{ $entry->title }}</td>
                                @if(! $document->is_finalized)
                                    <td class="text-right" style="white-space:nowrap">
                                        <button type="button" class="btn btn-sm btn-secondary" data-toggle-form="edit-docitem-{{ $entry->id }}">Editar</button>
                                        <form method="POST" action="{{ route('nc-documents.items.destroy', [$document, $entry]) }}" data-confirm="Excluir a cópia {{ $entry->code }} deste documento? A linha de trabalho do cliente não é apagada." style="display:inline">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-danger" type="submit">Excluir</button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                            @if(! $document->is_finalized)
                                <tr>
                                    <td colspan="3" style="padding:0 8px 8px 76px">
                                        <form method="POST" action="{{ route('nc-documents.items.update', [$document, $entry]) }}" id="edit-docitem-{{ $entry->id }}" class="form-grid" style="grid-template-columns:120px 1fr auto;display:none">
                                            @csrf
                                            @method('PUT')
                                            <div class="form-group" style="margin-bottom:0">
                                                <input type="text" name="code" maxlength="30" value="{{ $entry->code }}" required>
                                            </div>
                                            <div class="form-group" style="margin-bottom:0">
                                                <input type="text" name="title" maxlength="255" value="{{ $entry->title }}" required>
                                            </div>
                                            <button class="btn btn-sm" type="submit">Salvar</button>
                                        </form>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @push('scripts')
        <script>
            document.querySelectorAll('[data-toggle-form]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var form = document.getElementById(btn.dataset.toggleForm);
                    if (form) {
                        form.style.display = form.style.display === 'none' ? '' : 'none';
                    }
                });
            });

            document.querySelectorAll('form[data-confirm]').forEach(function (form) {
                form.addEventListener('submit', function (e) {
                    if (! window.confirm(form.dataset.confirm)) {
                        e.preventDefault();
                    }
                });
            });
        </script>
    @endpush
@endsection