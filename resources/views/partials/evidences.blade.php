@php($evidences = $item->evidences ?? collect())
@php($canWrite = $canWrite ?? false)
@php($canDeleteEvidence = $canDeleteEvidence ?? false)

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
                        {{ $evidence->humanSize() }} · por {{ $evidence->uploader?->name ?? '—' }} · {{ $evidence->created_at->format('d/m/Y H:i') }}
                    </div>
                </div>
                @if($canDeleteEvidence)
                    <form method="POST" action="{{ route($destroyRoute, $evidence) }}"
                          data-confirm="Excluir esta evidência? A ação não pode ser desfeita.">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-danger" type="submit">Excluir</button>
                    </form>
                @endif
            </div>
        @endforeach
    @endif
</div>