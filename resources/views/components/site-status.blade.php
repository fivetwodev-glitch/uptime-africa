@props(['status', 'monitoring' => null])
@php
    $paused = $monitoring !== null && $monitoring !== \App\Enums\MonitoringStatus::Active;
    [$dot, $text, $bg] = $paused
        ? ['bg-slate-400', 'text-slate-600', 'bg-slate-100']
        : match ($status) {
            \App\Enums\SiteStatus::Up => ['bg-emerald-500', 'text-emerald-700', 'bg-emerald-50'],
            \App\Enums\SiteStatus::Down => ['bg-rose-500', 'text-rose-700', 'bg-rose-50'],
            \App\Enums\SiteStatus::Degraded => ['bg-amber-500', 'text-amber-700', 'bg-amber-50'],
            default => ['bg-slate-400', 'text-slate-600', 'bg-slate-100'],
        };
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap {$bg} {$text}"]) }}>
    <span class="h-1.5 w-1.5 rounded-full {{ $dot }}"></span>
    {{ $paused ? $monitoring->label() : $status->label() }}
</span>
