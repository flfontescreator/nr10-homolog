@php($evidences = $evidences ?? ($item->evidences ?? collect()))
@php($canWrite = $canWrite ?? false)
@php($canDeleteEvidence = $canDeleteEvidence ?? false)
@php($destroyRouteModel = $destroyRouteModel ?? null)
@php($destroyRouteParams = $destroyRouteParams ?? [])
@php($libraryAvailable = $libraryAvailable ?? collect())
@php($libraryAttachRoute = $libraryAttachRoute ?? null)

<div class="card">
    <h2 class="card-title">Evidências</h2>

    @if($canWrite)
        <form method="POST" action="{{ $uploadRoute }}" enctype="multipart/form-data" class="mb-4">
            @csrf
            <div class="form-group" style="margin-bottom:0">
                <label for="evidence">Anexar arquivo (foto, PDF, documento)</label>
                <div class="form-grid" style="grid-template-columns:1fr auto">
                    <input type="file" name="evidence" id="evidence" required>
                    <button class="btn" type="submit">Anexar</button>
                </div>
                <div class="field-hint">Formatos de imagem e documentos. Executáveis (exe, php, bat, etc.) são bloqueados.</div>
            </div>
        </form>

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
                    <div class="muted small">
                        {{ $evidence->humanSize() }} · por {{ $evidence->uploader?->name ?? '—' }} · {{ $evidence->created_at->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }}
                    </div>
                </div>
                @if($canDeleteEvidence)
                    @php($destroyAction = $destroyRouteModel
                        ? route($destroyRoute, array_merge([$destroyRouteModel, $evidence], $destroyRouteParams))
                        : route($destroyRoute, array_merge([$evidence], $destroyRouteParams)))
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