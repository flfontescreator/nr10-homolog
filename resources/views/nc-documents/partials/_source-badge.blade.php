@php($isOperacional = isset($catalogItem) && $catalogItem->source === \App\Enums\Source::Prontuario)
<span class="badge {{ $isOperacional ? 'badge-green' : 'badge-blue' }}">
    {{ $isOperacional ? 'Operacional' : 'Normativa' }}
</span>