@if($evidences->isEmpty())
    <div class="docs-empty">Nenhuma evidência anexada ainda.</div>
@else
    <div class="table-wrap">
        <table class="grid">
            <thead>
                <tr>
                    <th>Arquivo</th>
                    <th>Módulo</th>
                    <th>Item</th>
                    <th>Enviado por</th>
                    <th>Data</th>
                </tr>
            </thead>
            <tbody>
                @foreach($evidences as $ev)
                    <tr>
                        <td>
                            <a href="{{ route('documentos.download', $ev) }}">{{ $ev->original_name }}</a>
                            <div class="muted small">{{ $ev->humanSize() }}</div>
                        </td>
                        <td>
                            @if(isset($ev->tenantItem->catalogItem->source))
                                <span class="badge {{ $ev->tenantItem->catalogItem->source->value === 'cronograma' ? 'badge-blue' : ($ev->tenantItem->catalogItem->source->value === 'prontuario' ? 'badge-green' : 'badge-neutral') }}">
                                    {{ $ev->tenantItem->catalogItem->source->label() }}
                                </span>
                            @endif
                        </td>
                        <td>{{ $ev->tenantItem->catalogItem->code ?? '—' }}</td>
                        <td>{{ $ev->uploader?->name ?? '—' }}</td>
                        <td>{{ $ev->created_at->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
