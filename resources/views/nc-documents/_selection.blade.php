{{-- Seleção de itens (seções/títulos e sub-itens do Cronograma de Adequação) com dados fixos do catálogo. --}}
@php($oldSelected = old('catalog_item_ids', $selected ?? []))
@php($oldSelected = is_array($oldSelected) ? $oldSelected : [])
@php($library = $library ?? collect())
@php($oldEvidenceIds = old('evidence_ids', $selectedEvidenceIds ?? []))
@php($oldEvidenceIds = is_array($oldEvidenceIds) ? $oldEvidenceIds : [])

<div class="form-group">
    <label>Título do documento</label>
    <input type="text" name="title" maxlength="255" value="{{ old('title', $title ?? '') }}" placeholder="Ex.: Documento de Não Conformidades 01">
</div>

<div class="form-group">
    <label>Descrição</label>
    <textarea name="description" rows="3">{{ old('description', $description ?? '') }}</textarea>
</div>

<div class="card">
    <div class="card-toolbar" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
        <h2 class="card-title" style="margin:0">Itens selecionados do Cronograma de Adequação</h2>
        <div style="display:flex;gap:8px">
            <button type="button" class="btn btn-sm" data-check-all>Marcar todos</button>
            <button type="button" class="btn btn-sm btn-secondary" data-check-none>Limpar</button>
        </div>
    </div>
    <p class="muted">
        Selecione as seções que se aplicam a este documento. Ao marcar a seção (título),
        os sub-itens dela são marcados automaticamente.
    </p>

    <div data-item-picker>
        @foreach($items as $branch)
            @php($section = $branch->section)
            <div class="pick-section" style="margin-top:14px">
                <label style="display:flex;gap:10px;align-items:center;padding:8px;border:1px solid #c9c9c9;border-radius:6px;background:#f4f6f8">
                    <input type="checkbox" name="catalog_item_ids[]" value="{{ $section->id }}"
                           data-check-section data-section-target="section-{{ $section->id }}"
                           @checked(in_array($section->id, $oldSelected, true))>
                    <span>
                        <strong>{{ $section->code }}</strong> — {{ $section->title }}
                        <span class="badge badge-neutral">{{ $branch->children->count() }} sub-itens</span>
                    </span>
                </label>

                <div id="section-{{ $section->id }}" class="pick-children" style="display:grid;gap:6px;margin:6px 0 0 26px">
                    @foreach($branch->children as $child)
                        <label style="display:flex;gap:10px;align-items:flex-start;padding:6px 8px;border:1px solid #d9d9d9;border-radius:6px">
                            <input type="checkbox" name="catalog_item_ids[]" value="{{ $child->id }}"
                                   data-section-group="section-{{ $section->id }}"
                                   @checked(in_array($child->id, $oldSelected, true))>
                            <span>
                                <strong>{{ $child->code }}</strong> — {{ $child->title }}
                                @if($child->criticidade)
                                    <span class="badge {{ $child->criticidade === 'ALTA' ? 'badge-red' : 'badge-amber' }}">{{ $child->criticidade }}</span>
                                @endif
                                <span class="setores-mini" style="margin-top:4px">
                                    @forelse($child->setores_list as $setor)
                                        <span class="badge badge-setor">{{ $setor }}</span>
                                    @empty
                                        <span class="muted">—</span>
                                    @endforelse
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>

@if($library->isNotEmpty())
    <div class="card">
        <div class="card-toolbar" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
            <h2 class="card-title" style="margin:0">Biblioteca de documentos</h2>
            <span class="badge badge-neutral">{{ $library->count() }} arquivo(s)</span>
        </div>
        <p class="muted">
            Reutilize arquivos já anexados: marque os que devem ficar vinculados a este
            documento. O vínculo aparece em Gestão de Documentos na coluna
            <strong>Documento de referência</strong>. Nenhuma cópia é feita — o arquivo é o mesmo.
        </p>

        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:10px">
            @foreach($library as $evidence)
                <label style="display:flex;gap:8px;align-items:center;padding:8px;border:1px solid #d9d9d9;border-radius:6px;background:{{ in_array($evidence->id, $oldEvidenceIds, true) ? '#eff7ff' : '#fff' }}">
                    <input type="checkbox" name="evidence_ids[]" value="{{ $evidence->id }}"
                           @checked(in_array($evidence->id, $oldEvidenceIds, true))>
                    @php($isImage = str_starts_with((string) $evidence->mime_type, 'image/'))
                    @if($isImage)
                        <img src="{{ route('documentos.preview', $evidence) }}" alt=""
                             loading="lazy" style="width:44px;height:44px;object-fit:cover;border-radius:4px;flex:none">
                    @else
                        <span style="width:44px;height:44px;display:flex;align-items:center;justify-content:center;background:#f4f6f8;border-radius:4px;flex:none">
                            @include('partials.icon', ['name' => 'file'])
                        </span>
                    @endif
                    <span style="flex:1;min-width:0">
                        <span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13px">
                            {{ \Illuminate\Support\Str::limit($evidence->original_name, 30) }}
                        </span>
                        <span class="setores-mini">
                            @forelse($evidence->documents as $ref)
                                <span class="badge badge-blue">{{ $ref->code }}</span>
                            @empty
                                <span class="muted small">sem vínculo</span>
                            @endforelse
                        </span>
                    </span>
                </label>
            @endforeach
        </div>
    </div>
@endif

<button class="btn" type="submit">{{ $submitLabel }}</button>

@push('scripts')
    {{-- Sem dependência do bundle: marcação em lote e sincronização seção <-> sub-itens. --}}
    <script>
        document.querySelectorAll('[data-check-all], [data-check-none]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var picker = btn.closest('.card').querySelector('[data-item-picker]');
                var check = btn.dataset.checkAll !== undefined;
                picker.querySelectorAll('input[type="checkbox"]').forEach(function (cb) { cb.checked = check; });
            });
        });

        function subitemsOf(section) {
            return document.querySelectorAll('[data-section-group="' + section.dataset.sectionTarget + '"]');
        }

        function syncSection(section) {
            var anyChecked = Array.prototype.some.call(subitemsOf(section), function (cb) { return cb.checked; });
            section.checked = anyChecked;
        }

        document.querySelectorAll('[data-check-section]').forEach(function (section) {
            section.addEventListener('change', function () {
                subitemsOf(section).forEach(function (cb) { cb.checked = section.checked; });
            });
        });

        document.querySelectorAll('[data-section-group]').forEach(function (cb) {
            cb.addEventListener('change', function () {
                var section = document.querySelector('[data-check-section][data-section-target="' + cb.dataset.sectionGroup + '"]');
                if (section) {
                    syncSection(section);
                }
            });
        });
    </script>
@endpush