@php
    $crit = mb_strtoupper(trim((string) $criticidade));
    $class = 'badge-neutral';
    if (str_contains($crit, 'ALTA')) {
        $class = 'crit-alta';
    } elseif (str_contains($crit, 'MÉDIA') || str_contains($crit, 'MEDIA')) {
        $class = 'crit-media';
    } elseif ($crit !== '' && (str_contains($crit, 'GIR') || str_contains($crit, 'CRÍTICA'))) {
        $class = 'crit-gir';
    } elseif (str_contains($crit, 'EM PARTES')) {
        $class = 'crit-em-partes';
    } elseif (str_contains($crit, 'NÃO APLICADA') || str_contains($crit, 'NAO APLICADA') || str_contains($crit, 'NÃO APLICADO') || str_contains($crit, 'NÃO APLICÁVEL')) {
        $class = 'crit-nao-aplicada';
    }
@endphp
<span class="badge {{ $class }}">{{ $criticidade ?: '—' }}</span>