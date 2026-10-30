@props(['icon' => 'pulse', 'title'])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center px-6 py-12 text-center']) }}>
    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-electric-50 text-electric-600">
        <x-icon :name="$icon" class="h-6 w-6"/>
    </span>
    <h3 class="mt-4 text-sm font-semibold text-night-900">{{ $title }}</h3>
    @if ($slot->isNotEmpty())
        <div class="mt-1.5 max-w-md text-sm text-slate-500">{{ $slot }}</div>
    @endif
    @isset($action)
        <div class="mt-5">{{ $action }}</div>
    @endisset
</div>
