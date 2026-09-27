@if(count($alertas) === 0)
    <div class="docs-empty">Nenhuma NC com prazo definido.</div>
@else
    @foreach($alertas as $alerta)
        @php
            $alertClass = match ($alerta['nivel']) {
                'vencido' => 'alert alert-danger',
                'critico' => 'alert alert-warning',
                'atencao' => 'alert',
                default => 'alert',
            };
            $alertStyle = match ($alerta['nivel']) {
                'atencao' => 'background:#eff6ff;border-color:#bfdbfe;color:#1e40af',
                'ok' => 'background:#f8fafc;border-color:#e2e8f0;color:#334155',
                default => '',
            };
            $nivelLabel = match ($alerta['nivel']) {
                'vencido' => 'Vencido',
                'critico' => 'Vence em até 7 dias',
                'atencao' => 'Vence em até 30 dias',
                default => 'Prazo futuro',
            };
        @endphp
        <div class="{{ $alertClass }}" style="margin-bottom:10px;{{ $alertStyle }}">
            <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start">
                <div>
                    <span class="badge badge-blue">{{ $alerta['codigo'] }}</span>
                    <span class="badge badge-neutral">{{ $alerta['documento'] }}</span>
                    <span class="small">{{ $nivelLabel }}</span>
                    <div style="margin-top:4px">{{ $alerta['descricao'] }}</div>
                    <div style="margin-top:4px">@include('partials.criticidade', ['criticidade' => $alerta['criticidade']])</div>
                </div>
                <div class="text-right" style="white-space:nowrap">
                    <div class="small muted">Prazo</div>
                    <strong>{{ $alerta['prazo'] }}</strong>
                </div>
            </div>
        </div>
    @endforeach
@endif
