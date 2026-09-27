<div class="stat-card">
    <div class="stat-value" data-kpi="{{ $kpiKey }}">{{ $value }}</div>
    <div class="stat-label">{{ $label }}</div>
    <div class="small" data-kpi-delta="{{ $kpiKey }}" data-tone="{{ $tone }}" data-decimals="{{ $decimals }}" data-suffix="{{ $suffix }}" style="margin-top:6px">
        @if($delta === null)
            <span class="muted">sem base anterior</span>
        @else
            @php
                $deltaColor = $tone === 'neutral'
                    ? '#475569'
                    : ($delta > 0
                        ? ($tone === 'good' ? '#15803d' : '#b91c1c')
                        : ($delta < 0 ? ($tone === 'good' ? '#b91c1c' : '#15803d') : '#64748b'));
            @endphp
            <span style="color:{{ $deltaColor }}">{{ ($delta > 0 ? '+' : '') . number_format($delta, $decimals, ',', '.') . $suffix }}</span>
            <span class="muted">vs mês anterior</span>
        @endif
    </div>
</div>
