@extends('layouts.app')

@section('title', 'Catálogo Operacional — Prontuário NR-10')

@section('content')
    @php($write = auth()->user()->canWrite())

    <div class="page-header">
        <div>
            <h1><span class="badge badge-green">Operacional</span> Catálogo do Prontuário NR-10</h1>
            <p class="subtitle">
                Seções (títulos) numeradas 1..N e sub-itens "X.Y". Crie, edite ou exclua
                itens deste catálogo. Itens vinculados a um documento de não conformidades
                não podem ser excluídos.
            </p>
        </div>
        <a class="btn btn-secondary" href="javascript:history.back()">← Voltar</a>
    </div>

    <div class="card">
        <h2 class="card-title">Nova seção</h2>
        <form method="POST" action="{{ route('prontuario.catalogo.section.store') }}" class="form-grid" style="grid-template-columns:1fr auto">
            @csrf
            <div class="form-group" style="margin-bottom:0">
                <input type="text" name="title" maxlength="255" placeholder="Título da nova seção (ex.: 8)">
            </div>
            <button class="btn" type="submit">Criar seção</button>
        </form>
    </div>

    @foreach($sections as $section)
        @php($children = $subitems->where('n1', $section->n1)->values())
        @php($sectionLinked = in_array($section->id, $linkedToDocument, true))
        <div class="card">
            <div class="card-toolbar" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
                <h2 class="card-title" style="margin:0;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                    <span class="badge badge-green">{{ $section->code }}</span>
                    {{ $section->title }}
                    @if($sectionLinked)
                        <span class="badge badge-red">em documento</span>
                    @endif
                </h2>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <button type="button" class="btn btn-sm" data-toggle-form="edit-section-{{ $section->id }}">Editar</button>
                    <button type="button" class="btn btn-sm" data-toggle-form="new-item-{{ $section->id }}">+ Sub-item</button>
                    @if($children->isEmpty() && $canDelete && ! $sectionLinked)
                        <form method="POST" action="{{ route('prontuario.catalogo.destroy', $section) }}" data-confirm="Excluir a seção {{ $section->code }}?">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm btn-danger" type="submit">Excluir</button>
                        </form>
                    @endif
                </div>
            </div>

            <form method="POST" action="{{ route('prontuario.catalogo.update', $section) }}" id="edit-section-{{ $section->id }}" class="form-grid" style="grid-template-columns:1fr auto;display:none;margin-top:12px">
                @csrf
                @method('PUT')
                <div class="form-group" style="margin-bottom:0">
                    <input type="text" name="title" maxlength="255" value="{{ $section->title }}" required>
                </div>
                <button class="btn btn-sm" type="submit">Salvar</button>
            </form>

            <form method="POST" action="{{ route('prontuario.catalogo.item.store') }}" id="new-item-{{ $section->id }}" class="form-grid" style="grid-template-columns:1fr auto;display:none;margin-top:12px">
                @csrf
                <input type="hidden" name="section_id" value="{{ $section->id }}">
                <div class="form-group" style="margin-bottom:0">
                    <input type="text" name="title" maxlength="255" placeholder="Título do novo sub-item (será {{ $section->code }}.{{ $children->count() ? ($children->max('n2') + 1) : 1 }})" required>
                </div>
                <button class="btn btn-sm" type="submit">Criar sub-item</button>
            </form>

            @if($children->isNotEmpty())
                <div class="table-wrap" style="margin-top:12px">
                    <table class="grid">
                        <tbody>
                            @foreach($children as $child)
                                @php($childLinked = in_array($child->id, $linkedToDocument, true))
                                <tr>
                                    <td style="width:70px"><strong>{{ $child->code }}</strong></td>
                                    <td>
                                        {{ $child->title }}
                                        @if($childLinked)
                                            <span class="badge badge-red">em documento</span>
                                        @endif
                                    </td>
                                    <td class="text-right" style="white-space:nowrap">
                                        <button type="button" class="btn btn-sm btn-secondary" data-toggle-form="edit-item-{{ $child->id }}">Editar</button>
                                        @if($canDelete && ! $childLinked)
                                            <form method="POST" action="{{ route('prontuario.catalogo.destroy', $child) }}" data-confirm="Excluir o sub-item {{ $child->code }}? " style="display:inline">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-danger" type="submit">Excluir</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="3" style="padding:0 8px 8px 76px">
                                        <form method="POST" action="{{ route('prontuario.catalogo.update', $child) }}" id="edit-item-{{ $child->id }}" class="form-grid" style="grid-template-columns:1fr auto;display:none">
                                            @csrf
                                            @method('PUT')
                                            <div class="form-group" style="margin-bottom:0">
                                                <input type="text" name="title" maxlength="255" value="{{ $child->title }}" required>
                                            </div>
                                            <button class="btn btn-sm" type="submit">Salvar</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="muted" style="margin-top:12px">Sem sub-itens ainda.</p>
            @endif
        </div>
    @endforeach

    @if($write)
        <p class="muted">
            Novo item no documento: a aba <strong>Operacional</strong> do documento de não
            conformidades usa este catálogo.
        </p>
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