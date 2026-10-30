@props(['label', 'value', 'hint' => null, 'tone' => 'default', 'href' => null])
@php
    $valueClass = match ($tone) {
        'up' => 'text-emerald-600',
        'down' => 'text-rose-600',
        'degraded' => 'text-amber-600',
        default => 'text-night-900',
    };
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'card block p-5'.($href ? ' transition hover:border-electric-200 hover:shadow-md' : '')]) }}>
    <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">{{ $label }}</p>
    <p class="mt-2 text-2xl font-bold tabular-nums {{ $valueClass }}">{{ $value }}</p>
    @if ($hint)
        <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
    @endif
</{{ $tag }}>
