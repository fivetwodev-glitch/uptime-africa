@extends('layouts.console')

@php
    use App\Enums\MonitoringProvider;
    use App\Enums\MonitoringStatus;
    use App\Support\Format;

    // Graphique SVG du temps de réponse (24 h), calculé côté serveur.
    $chart = null;
    if ($series->count() >= 2) {
        $w = 720; $h = 180; $pad = 8;
        $max = max(100, (int) ceil($series->max('ms') * 1.15));
        $t0 = $series->first()['at']->getTimestamp();
        $span = max(1, $series->last()['at']->getTimestamp() - $t0);
        $points = $series->map(fn ($p) => [
            round($pad + ($p['at']->getTimestamp() - $t0) / $span * ($w - 2 * $pad), 1),
            round($h - $pad - $p['ms'] / $max * ($h - 2 * $pad), 1),
        ]);
        $line = $points->map(fn ($p) => $p[0].','.$p[1])->implode(' ');
        $area = 'M'.$points->first()[0].','.($h - $pad).' L'.$line.' L'.$points->last()[0].','.($h - $pad).' Z';
        $chart = compact('w', 'h', 'max', 'line', 'area') + [
            'from' => $series->first()['at'], 'to' => $series->last()['at'],
            'min' => $series->min('ms'), 'avg' => (int) round($series->avg('ms')), 'peak' => $series->max('ms'),
        ];
    }
@endphp

@section('title', $site->name)

@section('content')
<x-page-header :title="$site->name">
    <x-slot:back>
        <a href="{{ route('sites.index') }}" class="mb-2 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-night-900">
            <x-icon name="arrow-left" class="h-4 w-4"/> Sites surveillés
        </a>
    </x-slot:back>
    @can('sites.manage')
        <form method="POST" action="{{ route('sites.toggle', $site) }}">
            @csrf @method('PATCH')
            @if ($site->monitoring_status === MonitoringStatus::Active)
                <button type="submit" class="btn-secondary"><x-icon name="pause" class="h-4 w-4"/> Mettre en pause</button>
            @else
                <button type="submit" class="btn-secondary"><x-icon name="play" class="h-4 w-4"/> Reprendre</button>
            @endif
        </form>
        <a href="{{ route('sites.edit', $site) }}" class="btn-secondary"><x-icon name="pencil" class="h-4 w-4"/> Modifier</a>
        <form method="POST" action="{{ route('sites.destroy', $site) }}" data-confirm="Supprimer « {{ $site->name }} » et tout son historique ? Cette action est définitive.">
            @csrf @method('DELETE')
            <button type="submit" class="btn-danger"><x-icon name="trash" class="h-4 w-4"/> Supprimer</button>
        </form>
    @endcan
</x-page-header>

<div class="-mt-3 mb-6 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-slate-500">
    <x-site-status :status="$site->status" :monitoring="$site->monitoring_status"/>
    <a href="{{ $site->url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 link">{{ $site->url }} <x-icon name="external" class="h-3.5 w-3.5"/></a>
    <span>Client : <a href="{{ route('clients.show', $site->client_id) }}" class="link">{{ $site->client->name }}</a></span>
</div>

<div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
    <x-stat label="Statut actuel" :value="$site->status->label()" :tone="$site->status->value"
            :hint="$site->consecutive_failures > 0 ? $site->consecutive_failures.' échec(s) consécutif(s)' : null"/>
    <x-stat label="Dernière vérification" :value="Format::ago($site->last_checked_at)" :hint="$site->last_checked_at ? Format::datetime($site->last_checked_at) : 'Aucun contrôle effectué'"/>
    <x-stat label="Temps de réponse" :value="Format::ms($site->last_response_time_ms)" hint="Dernier contrôle"/>
    <x-stat label="Code HTTP" :value="$site->last_http_status ?? '—'" hint="Dernier contrôle"/>
</div>

<section class="card mt-6">
    <div class="card-header">
        <h2 class="card-title">Disponibilité</h2>
        <span class="text-xs text-slate-500">30 derniers jours</span>
    </div>
    <div class="grid grid-cols-3 divide-x divide-slate-100 border-b border-slate-100">
        @foreach (['24 heures' => $uptime24h, '7 jours' => $uptime7d, '30 jours' => $uptime30d] as $label => $summary)
            <div class="px-5 py-4">
                <p class="text-xs text-slate-500">{{ $label }}</p>
                <p class="mt-1 text-lg font-bold tabular-nums text-night-900">{{ Format::percent($summary['uptime']) }}</p>
                <p class="text-xs text-slate-400">{{ $summary['total'] > 0 ? $summary['total'].' contrôle(s)' : 'Aucune donnée' }}</p>
            </div>
        @endforeach
    </div>
    <div class="px-5 py-5">
        <div class="flex h-10 items-stretch gap-[3px]" role="img" aria-label="Disponibilité quotidienne sur 30 jours">
            @foreach ($bars as $bar)
                @php
                    $color = match (true) {
                        $bar['uptime'] === null => 'bg-slate-200',
                        $bar['uptime'] >= 99.5 => 'bg-emerald-500',
                        $bar['uptime'] >= 95 => 'bg-amber-400',
                        default => 'bg-rose-500',
                    };
                @endphp
                <span class="flex-1 rounded-sm {{ $color }}"
                      title="{{ $bar['date']->translatedFormat('d M Y') }} : {{ $bar['uptime'] === null ? 'aucune donnée' : Format::percent($bar['uptime']).' sur '.$bar['total'].' contrôle(s)' }}"></span>
            @endforeach
        </div>
        <div class="mt-2 flex justify-between text-xs text-slate-400">
            <span>Il y a 30 jours</span>
            <span class="hidden gap-3 sm:flex">
                <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-sm bg-emerald-500"></span>≥ 99,5 %</span>
                <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-sm bg-amber-400"></span>≥ 95 %</span>
                <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-sm bg-rose-500"></span>&lt; 95 %</span>
                <span class="inline-flex items-center gap-1"><span class="h-2 w-2 rounded-sm bg-slate-200"></span>Aucune donnée</span>
            </span>
            <span>Aujourd'hui</span>
        </div>
    </div>
</section>

<section class="card mt-6">
    <div class="card-header">
        <h2 class="card-title">Temps de réponse</h2>
        <span class="text-xs text-slate-500">24 dernières heures</span>
    </div>
    @if ($chart)
        <div class="grid grid-cols-3 divide-x divide-slate-100 border-b border-slate-100 text-center sm:text-left">
            <div class="px-5 py-3"><p class="text-xs text-slate-500">Moyenne</p><p class="font-bold tabular-nums text-night-900">{{ Format::ms($chart['avg']) }}</p></div>
            <div class="px-5 py-3"><p class="text-xs text-slate-500">Minimum</p><p class="font-bold tabular-nums text-emerald-600">{{ Format::ms($chart['min']) }}</p></div>
            <div class="px-5 py-3"><p class="text-xs text-slate-500">Maximum</p><p class="font-bold tabular-nums text-rose-600">{{ Format::ms($chart['peak']) }}</p></div>
        </div>
        <div class="p-5">
            <svg viewBox="0 0 {{ $chart['w'] }} {{ $chart['h'] }}" class="h-48 w-full" preserveAspectRatio="none" role="img" aria-label="Temps de réponse sur 24 heures">
                <defs>
                    <linearGradient id="rt-fill" x1="0" x2="0" y1="0" y2="1">
                        <stop offset="0" stop-color="#2f6bff" stop-opacity="0.25"/>
                        <stop offset="1" stop-color="#2f6bff" stop-opacity="0"/>
                    </linearGradient>
                </defs>
                @foreach ([0.25, 0.5, 0.75] as $g)
                    <line x1="0" x2="{{ $chart['w'] }}" y1="{{ $chart['h'] * $g }}" y2="{{ $chart['h'] * $g }}" stroke="#e2e8f0" stroke-dasharray="4 4" vector-effect="non-scaling-stroke"/>
                @endforeach
                <path d="{{ $chart['area'] }}" fill="url(#rt-fill)"/>
                <polyline points="{{ $chart['line'] }}" fill="none" stroke="#2f6bff" stroke-width="2" stroke-linejoin="round" vector-effect="non-scaling-stroke"/>
            </svg>
            <div class="mt-1 flex justify-between text-xs text-slate-400">
                <span>{{ Format::datetime($chart['from']) }}</span>
                <span>Échelle : 0 à {{ Format::ms($chart['max']) }}</span>
                <span>{{ Format::datetime($chart['to']) }}</span>
            </div>
        </div>
    @else
        <x-empty-state icon="clock" title="Pas encore assez de mesures">
            Le graphique s'affiche dès que deux contrôles réussis ont été enregistrés sur les dernières 24 heures.
        </x-empty-state>
    @endif
</section>

<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <section class="card">
        <div class="card-header">
            <h2 class="card-title">Incidents récents</h2>
            <a href="{{ route('incidents.index', ['site' => $site->id]) }}" class="link text-xs">Tous les incidents du site</a>
        </div>
        @forelse ($incidents as $incident)
            <a href="{{ route('incidents.show', $incident) }}" class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-3 last:border-0 hover:bg-slate-50">
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-night-900">{{ $incident->type->label() }}</p>
                    <p class="truncate text-xs text-slate-500">{{ Format::datetime($incident->started_at) }} · durée {{ Format::duration($incident->duration_seconds) }}</p>
                </div>
                <x-incident-status :status="$incident->status"/>
            </a>
        @empty
            <x-empty-state icon="check" title="Aucun incident enregistré pour ce site"/>
        @endforelse
    </section>

    <section class="card">
        <div class="card-header">
            <h2 class="card-title">Derniers contrôles</h2>
            <a href="{{ route('checks.index', ['site' => $site->id]) }}" class="link text-xs">Historique complet</a>
        </div>
        @if ($checks->isEmpty())
            <x-empty-state icon="pulse" title="Aucun contrôle enregistré">
                @if ($site->monitoring_status !== MonitoringStatus::Active)
                    La surveillance de ce site est en pause.
                @else
                    Les contrôles apparaîtront ici dès que le moteur de surveillance aura vérifié ce site.
                @endif
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Date</th><th>Résultat</th><th>Code</th><th>Temps</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @foreach ($checks as $check)
                        <tr>
                            <td class="whitespace-nowrap text-slate-500">{{ Format::datetime($check->checked_at) }}</td>
                            <td>@include('checks._result', ['check' => $check])</td>
                            <td class="tabular-nums">{{ $check->http_status ?? '—' }}</td>
                            <td class="tabular-nums">{{ Format::ms($check->response_time_ms) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>

<section class="card mt-6">
    <div class="card-header"><h2 class="card-title">Configuration</h2></div>
    <dl class="grid gap-x-6 gap-y-4 p-5 text-sm sm:grid-cols-2 lg:grid-cols-3">
        <div><dt class="text-slate-500">Surveillance</dt><dd class="font-medium text-night-900">{{ $site->monitoring_status->label() }}</dd></div>
        <div><dt class="text-slate-500">Intervalle</dt><dd class="font-medium text-night-900">Toutes les {{ $site->interval_minutes }} min</dd></div>
        <div><dt class="text-slate-500">Délai maximal</dt><dd class="font-medium text-night-900">{{ $site->timeout_seconds }} s</dd></div>
        <div><dt class="text-slate-500">Codes HTTP attendus</dt><dd class="font-medium text-night-900">{{ $site->expected_status_codes ? implode(', ', $site->expected_status_codes) : '2xx ou 3xx' }}</dd></div>
        <div><dt class="text-slate-500">Fournisseur</dt><dd class="font-medium text-night-900">{{ $site->monitoring_provider->label() }}</dd></div>
        @if ($site->monitoring_provider !== MonitoringProvider::Internal)
            <div><dt class="text-slate-500">Moniteur externe</dt><dd class="font-medium text-night-900">{{ $site->external_monitor_id ?? 'Non associé' }}</dd></div>
            <div><dt class="text-slate-500">Synchronisation</dt><dd class="font-medium text-night-900">{{ $site->sync_status->label() }}@if ($site->last_synced_at) <span class="block text-xs text-slate-500">Dernière : {{ \App\Support\Format::datetime($site->last_synced_at) }}</span>@endif @if ($site->sync_error) <span class="block text-xs text-rose-600">{{ $site->sync_error }}</span>@endif</dd></div>
            @if ($site->monitoring_provider === MonitoringProvider::UptimeRobot)
                @can('sites.manage')
                    <div class="self-end"><form method="POST" action="{{ route('sites.sync', $site) }}">@csrf<button type="submit" class="btn-secondary btn-sm">{{ $site->external_monitor_id ? 'Synchroniser avec UptimeRobot' : 'Créer le moniteur UptimeRobot' }}</button></form></div>
                @endcan
            @endif
        @endif
        <div><dt class="text-slate-500">Ajouté le</dt><dd class="font-medium text-night-900">{{ Format::datetime($site->created_at) }}</dd></div>
    </dl>
</section>
@endsection
