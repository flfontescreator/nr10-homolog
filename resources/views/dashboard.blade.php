@extends('layouts.app')

@section('title', 'Dashboard — '.$tenant->name)

@section('content')
    @php($currentUser = auth()->user())
    @php($kpi = $stats['kpi'])
    @php($sec = $stats['secundarios'])
    @php($pred = $stats['previsao'])

    <div class="page-header">
        <div>
            <h1>Olá, {{ Str::before($currentUser->name, ' (') }}</h1>
            <p class="subtitle">Painel do cliente <strong>{{ $tenant->name }}</strong></p>
            <p class="small muted" style="margin:6px 0 0">
                <span id="telemetry-dot" style="color:#16a34a">●</span>
                Atualização automática a cada 60s · última atualização:
                <span id="telemetry-time">{{ $stats['generated_at'] }}</span>
                <span id="telemetry-msg"></span>
            </p>
        </div>
        @if($currentUser->canWrite())
            <div style="display:flex;gap:8px">
                <a class="btn" href="{{ route('cronograma.index') }}">Cronograma</a>
                <a class="btn btn-secondary" href="{{ route('prontuario.index') }}">Prontuário</a>
                <a class="btn btn-secondary" href="{{ route('checklist.index') }}">Não Conformidades</a>
            </div>
        @endif
    </div>

    {{-- KPIs principais (telemetria) --}}
    <div class="stat-grid">
        @include('dashboard.partials._kpi', [
            'kpiKey' => 'total',
            'label' => 'Total de NCs',
            'value' => number_format($kpi['total'], 0, ',', '.'),
            'delta' => $kpi['total_delta'],
            'decimals' => 0,
            'suffix' => '',
            'tone' => 'neutral',
        ])
        @include('dashboard.partials._kpi', [
            'kpiKey' => 'conformidade',
            'label' => '% Conformidade',
            'value' => number_format($kpi['conformidade'], 1, ',', '.').'%',
            'delta' => $kpi['conformidade_delta'],
            'decimals' => 1,
            'suffix' => ' p.p.',
            'tone' => 'good',
        ])
        @include('dashboard.partials._kpi', [
            'kpiKey' => 'atraso',
            'label' => 'NCs em atraso',
            'value' => number_format($kpi['atraso'], 0, ',', '.'),
            'delta' => $kpi['atraso_delta'],
            'decimals' => 0,
            'suffix' => '',
            'tone' => 'bad',
        ])
        @include('dashboard.partials._kpi', [
            'kpiKey' => 'alta',
            'label' => 'Criticidade alta (abertas)',
            'value' => number_format($kpi['alta'], 0, ',', '.'),
            'delta' => $kpi['alta_delta'],
            'decimals' => 0,
            'suffix' => '',
            'tone' => 'bad',
        ])
    </div>

    {{-- Gráficos --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));column-gap:16px">
        <div class="card">
            <h2 class="card-title">Status das NCs</h2>
            <div style="position:relative;height:300px">
                <canvas id="chart-status"></canvas>
            </div>
        </div>
        <div class="card">
            <h2 class="card-title">NCs por criticidade (abertas)</h2>
            <div style="position:relative;height:300px">
                <canvas id="chart-criticidade"></canvas>
            </div>
        </div>
        <div class="card">
            <h2 class="card-title">Evolução da conformidade (6 meses)</h2>
            <div style="position:relative;height:300px">
                <canvas id="chart-evolucao"></canvas>
            </div>
        </div>
        <div class="card">
            <h2 class="card-title">Prazos de adequação (abertas)</h2>
            <div style="position:relative;height:300px">
                <canvas id="chart-prazos"></canvas>
            </div>
        </div>
    </div>

    {{-- Previsões / riscos --}}
    <div class="card">
        <h2 class="card-title">Previsões e riscos</h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px">
            <div class="stat-card">
                <div class="stat-value" data-pred="projecao30" style="font-size:22px">{{ number_format($pred['projecao30'], 1, ',', '.') }}%</div>
                <div class="stat-label">Projeção de conformidade (30 dias)</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" data-pred="projecao60" style="font-size:22px">{{ number_format($pred['projecao60'], 1, ',', '.') }}%</div>
                <div class="stat-label">Projeção de conformidade (60 dias)</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" data-pred="projecao90" style="font-size:22px">{{ number_format($pred['projecao90'], 1, ',', '.') }}%</div>
                <div class="stat-label">Projeção de conformidade (90 dias)</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" data-pred="risco7">{{ number_format($pred['risco7'], 0, ',', '.') }}</div>
                <div class="stat-label">NCs em risco (prazo em até 7 dias)</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" data-pred="tendencia" style="font-size:20px">
                    {{ match ($pred['tendencia']) {
                        'alta' => 'Em alta',
                        'queda' => 'Em queda',
                        default => 'Estável',
                    } }}
                </div>
                <div class="stat-label">Tendência (6 meses)</div>
            </div>
        </div>
        <p class="small muted" style="margin:12px 0 0">
            Projeção pela velocidade dos últimos 90 dias e tendência da série histórica —
            indicadores automáticos (heurística), não substituem análise técnica.
        </p>
    </div>

    {{-- Alertas e agregações --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));column-gap:16px;align-items:start">
        <div class="card">
            <h2 class="card-title">Alertas de prazo</h2>
            <div id="alerts-panel">
                @include('dashboard.partials._alerts', ['alertas' => $stats['alertas']])
            </div>
        </div>
        <div class="card">
            <h2 class="card-title">NCs por setor</h2>
            <div id="setores-panel">
                @include('dashboard.partials._agregado', ['rows' => $stats['setores'], 'coluna' => 'Setor'])
            </div>
        </div>
        <div class="card">
            <h2 class="card-title">NCs por responsável</h2>
            <div id="responsaveis-panel">
                @include('dashboard.partials._agregado', ['rows' => $stats['responsaveis'], 'coluna' => 'Responsável'])
            </div>
        </div>
    </div>

    {{-- Indicadores secundários (cards originais) --}}
    <h2 class="card-title" style="margin-top:8px">Indicadores do plano e da biblioteca</h2>
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-value" data-sec="cronogramaTotal">{{ $sec['cronogramaTotal'] }}</div>
            <div class="stat-label">Subitens no Cronograma</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" data-sec="cronogramaComAcao">{{ $sec['cronogramaComAcao'] }}</div>
            <div class="stat-label">Cronograma com ação definida</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" data-sec="prontuarioTotal">{{ $sec['prontuarioTotal'] }}</div>
            <div class="stat-label">Itens no Prontuário</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" data-sec="prontuarioMedia">
                {{ $sec['prontuarioMedia'] !== null ? number_format($sec['prontuarioMedia'], 0, ',', '.').'%' : '—' }}
            </div>
            <div class="stat-label">Média Geral do Prontuário</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" data-sec="checklistTotal">{{ $sec['checklistTotal'] }}</div>
            <div class="stat-label">Itens em Não Conformidades</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" data-sec="checklistComAcao">{{ $sec['checklistComAcao'] }}</div>
            <div class="stat-label">Não Conformidades com ação definida</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" data-sec="evidenciasCount">{{ $sec['evidenciasCount'] }}</div>
            <div class="stat-label">Evidências anexadas</div>
        </div>
    </div>

    <div class="card">
        <h2 class="card-title">Evidências recentes</h2>
        <div id="evidencias-panel">
            @include('dashboard.partials._evidencias', ['evidences' => $stats['recentEvidences']])
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        window.DASHBOARD = {
            statsUrl: @json(route('dashboard.stats')),
            initial: {
                generated_at: @json($stats['generated_at']),
                kpi: @json($stats['kpi']),
                charts: @json($stats['charts']),
                previsao: @json($stats['previsao']),
                secundarios: @json($stats['secundarios']),
            },
        };
    </script>
    <script src="{{ asset('js/vendor/chart.umd.min.js') }}"></script>
    <script src="{{ asset('js/dashboard.js') }}"></script>
@endpush
