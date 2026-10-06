@php($evidences = $evidences ?? ($item->evidences ?? collect()))
@php($canWrite = $canWrite ?? false)
@php($canDeleteEvidence = $canDeleteEvidence ?? false)
@php($destroyRouteModel = $destroyRouteModel ?? null)
@php($destroyRouteParams = $destroyRouteParams ?? [])
@php($destroyRouteResolver = $destroyRouteResolver ?? null)
@php($libraryAvailable = $libraryAvailable ?? collect())
@php($libraryAttachRoute = $libraryAttachRoute ?? null)
@php($validadeMarcada = (bool) old('validade_aplica', false))

<div class="card">
    <h2 class="card-title">Evidências</h2>

    @if($canWrite)
        <form method="POST" action="{{ $uploadRoute }}" enctype="multipart/form-data" class="mb-4">
            @csrf
            <div class="form-group" style="margin-bottom:0">
                <label for="evidence">Anexar arquivo (foto, PDF, documento) *</label>
                <input type="file" name="evidence" id="evidence" required>
                <div class="field-hint">Formatos de imagem e documentos. Executáveis (exe, php, bat, etc.) são bloqueados.</div>
            </div>
            <div class="form-grid" style="grid-template-columns:1fr 1fr;gap:12px;margin-top:12px">
                <div class="form-group" style="margin-bottom:0">
                    <label for="evidence-description">Descrição do arquivo</label>
                    <input type="text" name="description" id="evidence-description" maxlength="255"
                           value="{{ old('description') }}"
                           placeholder="Ex.: Certificado de Aptidão Física">
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <label for="evidence-validade">Validade do documento</label>
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                        <label style="display:flex;align-items:center;gap:6px;white-space:nowrap;margin:0">
                            <input type="checkbox" name="validade_aplica" value="1" id="validade-aplica"
                                   @checked($validadeMarcada)
                                   onchange="toggleEvidenceValidade(this)">
                            Se aplica
                        </label>
                        <input type="date" name="validade" id="evidence-validade" style="max-width:220px"
                               value="{{ old('validade') }}"
                               @disabled(! $validadeMarcada)>
                    </div>
                    <div class="field-hint">Padrão: não se aplica. Marque para habilitar a data.</div>
                </div>
            </div>
            <button class="btn" type="submit" style="margin-top:12px">Anexar</button>
        </form>

        <script>
            // "Se aplica" habilita/desabilita a data de validade do anexo
            // (unchecked por padrão; marcado exige data no controller).
            function toggleEvidenceValidade(checkbox) {
                var box = checkbox || document.getElementById('validade-aplica');
                var campo = document.getElementById('evidence-validade');

                if (!box || !campo) {
                    return;
                }

                campo.disabled = !box.checked;

                if (!box.checked) {
                    campo.value = '';
                }
            }

            if (document.getElementById('validade-aplica')) {
                toggleEvidenceValidade();
            }
        </script>

        @if($libraryAttachRoute && $libraryAvailable->isNotEmpty())
            <div style="border-top:1px solid var(--border);padding-top:14px;margin-top:14px">
                <h2 class="card-title" style="font-size:14px">Anexar da biblioteca (reutilizar arquivo)</h2>
                <p class="muted small">
                    Vincula um arquivo já existente a este subitem, sem copiar. O mesmo arquivo
                    pode aparecer em vários subitens e documentos.
                </p>
                <form method="POST" action="{{ $libraryAttachRoute }}">
                    @csrf
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:8px">
                        @foreach($libraryAvailable as $libraryEvidence)
                            <label style="display:flex;gap:8px;align-items:center;padding:8px;border:1px solid #d9d9d9;border-radius:6px;background:#fff;cursor:pointer">
                                <input type="checkbox" name="evidence_ids[]" value="{{ $libraryEvidence->id }}">
                                @php($isImage = str_starts_with((string) $libraryEvidence->mime_type, 'image/'))
                                @if($isImage)
                                    <img src="{{ route('documentos.preview', $libraryEvidence) }}" alt=""
                                         loading="lazy" style="width:44px;height:44px;object-fit:cover;border-radius:4px;flex:none">
                                @else
                                    <span style="width:44px;height:44px;display:flex;align-items:center;justify-content:center;background:#f4f6f8;border-radius:4px;flex:none">
                                        @include('partials.icon', ['name' => 'file'])
                                    </span>
                                @endif
                                <span style="flex:1;min-width:0">
                                    <span style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:13px">
                                        {{ \Illuminate\Support\Str::limit($libraryEvidence->original_name, 30) }}
                                    </span>
                                    <span class="setores-mini">
                                        @forelse($libraryEvidence->documents as $ref)
                                            <span class="badge badge-blue">{{ $ref->code }}</span>
                                        @empty
                                            <span class="muted small">sem vínculo</span>
                                        @endforelse
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <button class="btn btn-sm" type="submit" style="margin-top:10px">Vincular selecionados</button>
                </form>
            </div>
        @endif
    @endif

    @if($evidences->isEmpty())
        <div class="docs-empty">Nenhuma evidência anexada para este item.</div>
    @else
        @foreach($evidences as $evidence)
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
                    @if($evidence->description)
                        <div style="font-size:13px;margin-top:2px">{{ $evidence->description }}</div>
                    @endif
                    <div class="muted small">
                        {{ $evidence->humanSize() }} · por {{ $evidence->uploader?->name ?? '—' }} · {{ $evidence->created_at->format('d/m/Y H:i') }}
                        @if($evidence->validade)
                            · <span class="badge {{ ($evidence->daysUntilExpiry() ?? 0) <= 30 ? 'badge-red' : 'badge-blue' }}">
                                Vence em {{ $evidence->daysUntilExpiry() >= 0 ? $evidence->daysUntilExpiry().' dias' : abs($evidence->daysUntilExpiry()).' dias' }}
                            </span>
                        @endif
                    </div>
                </div>
                @if($canDeleteEvidence)
                    @php($destroyAction = $destroyRouteResolver
                        ? $destroyRouteResolver($evidence)
                        : ($destroyRouteModel
                            ? route($destroyRoute, array_merge([$destroyRouteModel, $evidence], $destroyRouteParams))
                            : route($destroyRoute, array_merge([$evidence], $destroyRouteParams))))
                    <form method="POST" action="{{ $destroyAction }}"
                          data-confirm="Excluir o vínculo deste subitem? O arquivo continua na biblioteca se estiver vinculado em outro lugar.">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-danger" type="submit">Excluir</button>
                    </form>
                @endif
            </div>
        @endforeach
    @endif
</div>