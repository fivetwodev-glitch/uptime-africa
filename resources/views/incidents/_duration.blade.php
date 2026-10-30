@if ($incident->resolved_at)
    {{ \App\Support\Format::duration($incident->duration_seconds) }}
@else
    <span class="text-rose-600">{{ \App\Support\Format::duration((int) $incident->started_at->diffInSeconds(now())) }} (en cours)</span>
@endif
