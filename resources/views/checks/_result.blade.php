@if ($check->is_up)
    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Succès</span>
@else
    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-rose-700" title="{{ $check->error_message }}">
        <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>{{ $check->error_type?->label() ?? 'Échec' }}
    </span>
@endif
