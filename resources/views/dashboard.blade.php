@extends('layouts.app')

@section('title', 'Dashboard — '.$tenant->name)

@section('content')
    @php($currentUser = auth()->user())

    <div class="page-header">
        <div>
            <h1>Olá, {{ Str::before($currentUser->name, ' (') }}</h1>
            <p class="subtitle">Painel do cliente <strong>{{ $tenant->name }}</strong></p>
        </div>
        @if($currentUser->canWrite())
            <div style="display:flex;gap:8px">
                <a class="btn" href="{{ route('cronograma.index') }}">Cronograma</a>
                <a class="btn btn-secondary" href="{{ route('prontuario.index') }}">Prontuário</a>
                <a class="btn btn-secondary" href="{{ route('checklist.index') }}">Não Conformidades</a>
            </div>
        @endif
    </div>

    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-value">{{ $cronogramaTotal }}</div>
            <div class="stat-label">Subitens no Cronograma</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ $cronogramaComAcao }}</div>
            <div class="stat-label">Cronograma com ação definida</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ $prontuarioTotal }}</div>
            <div class="stat-label">Itens no Prontuário</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">
                {{ $prontuarioMedia !== null ? number_format($prontuarioMedia, 0, ',', '.') . '%' : '—' }}
            </div>
            <div class="stat-label">Média Geral do Prontuário</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ $checklistTotal }}</div>
            <div class="stat-label">Itens em Não Conformidades</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ $checklistComAcao }}</div>
            <div class="stat-label">Não Conformidades com ação definida</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ $evidenciasCount }}</div>
            <div class="stat-label">Evidências anexadas</div>
        </div>
    </div>

    <div class="card">
        <h2 class="card-title">Evidências recentes</h2>
        @if($recentEvidences->isEmpty())
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
                        @foreach($recentEvidences as $ev)
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
                                <td>{{ $ev->created_at->format('d/m/Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection