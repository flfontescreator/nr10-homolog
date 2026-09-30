@php($isOperacional = ($catalogItem->source ?? $source ?? null) === \App\Enums\Source::Prontuario)
<span class="badge {{ $isOperacional ? 'badge-green' : 'badge-blue' }}">
    {{ $isOperacional ? 'Operacional' : 'Normativa' }}
</span>