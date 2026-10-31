@if ($check->is_up === true)
    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Succès</span>
@elseif ($check->is_up === false)
    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-rose-700" title="{{ $check->error_message }}">
        <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>{{ $check->error_type?->label() ?? 'Échec' }}
    </span>
@else
    {{-- Ni succès ni échec : API injoignable, moniteur en pause ou panne non confirmée. --}}
    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600" title="{{ $check->error_message }}">
        <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>{{ $check->status?->label() ?? 'Inconnu' }}
    </span>
@endif
@if ($check->provider === \App\Enums\MonitoringProvider::UptimeRobot)
    <span class="block text-[11px] text-slate-400">UptimeRobot{{ $check->sync_result && $check->sync_result !== \App\Enums\SyncResult::Ok ? ' · '.$check->sync_result->label() : '' }}</span>
@endif
