@props(['status'])
@php
    $classes = match ($status) {
        \App\Enums\IncidentStatus::Open => 'bg-rose-50 text-rose-700 ring-rose-200',
        \App\Enums\IncidentStatus::Acknowledged => 'bg-amber-50 text-amber-700 ring-amber-200',
        \App\Enums\IncidentStatus::Resolved => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    };
@endphp
<span {{ $attributes->merge(['class' => "inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap ring-1 ring-inset {$classes}"]) }}>{{ $status->label() }}</span>
