@extends('layouts.console')

@section('title', 'Vue générale')

@php use App\Support\Format; @endphp

@section('content')
<x-page-header title="Vue générale" subtitle="État de tous les sites surveillés par votre agence.">
    @can('sites.manage')
        @if ($clientsCount > 0)
            <a href="{{ route('sites.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4"/> Ajouter un site</a>
        @endif
    @endcan
</x-page-header>

@if ($totalSites === 0)
    <div class="card">
        @if ($clientsCount === 0)
            <x-empty-state icon="users" title="Aucun site surveillé pour le moment">
                Commencez par créer un client : chaque site surveillé est rattaché à l'un de vos clients.
                @can('clients.manage')
                    <x-slot:action><a href="{{ route('clients.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4"/> Ajouter un client</a></x-slot:action>
                @endcan
            </x-empty-state>
        @else
            <x-empty-state icon="globe" title="Aucun site surveillé pour le moment">
                Vous avez {{ $clientsCount }} client(s). Ajoutez le premier site à surveiller.
                @can('sites.manage')
                    <x-slot:action><a href="{{ route('sites.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4"/> Ajouter un site</a></x-slot:action>
                @endcan
            </x-empty-state>
        @endif
    </div>
@else
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat label="Sites surveillés" :value="$totalSites" :hint="$unknownSites > 0 ? $unknownSites.' pas encore contrôlé(s)' : null" :href="route('sites.index')"/>
        <x-stat label="En ligne" :value="$upSites" tone="up" :href="route('sites.index', ['statut' => 'up'])"/>
        <x-stat label="Hors ligne" :value="$downSites" :tone="$downSites > 0 ? 'down' : 'default'" :href="route('sites.index', ['statut' => 'down'])"/>
        <x-stat label="Dégradés" :value="$degradedSites" :tone="$degradedSites > 0 ? 'degraded' : 'default'" :href="route('sites.index', ['statut' => 'degraded'])"/>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-stat label="Disponibilité moyenne (24 h)" :value="Format::percent($last24h['uptime'])"
                :hint="$last24h['total'] > 0 ? 'Sur '.number_format($last24h['total'], 0, ',', ' ').' contrôle(s)' : 'Aucun contrôle sur les dernières 24 h'"/>
        <x-stat label="Temps de réponse moyen (24 h)" :value="Format::ms($last24h['avg_ms'])"
                :hint="$last24h['avg_ms'] === null ? 'Aucune mesure sur les dernières 24 h' : 'Contrôles réussis uniquement'"/>
        <x-stat label="Incidents en cours" :value="$openIncidentsCount" :tone="$openIncidentsCount > 0 ? 'down' : 'default'" :href="route('incidents.index', ['statut' => 'open'])"/>
    </div>
@endif

@if ($totalSites > 0)
<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <section class="card">
        <div class="card-header">
            <h2 class="card-title">Incidents en cours</h2>
            <a href="{{ route('incidents.index') }}" class="link text-xs">Tous les incidents</a>
        </div>
        @forelse ($openIncidents as $incident)
            <a href="{{ route('incidents.show', $incident) }}" class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-3 last:border-0 hover:bg-slate-50">
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-night-900">{{ $incident->site->name }}</p>
                    <p class="truncate text-xs text-slate-500">{{ $incident->type->label() }} · depuis {{ Format::ago($incident->started_at) }}</p>
                </div>
                <x-incident-status :status="$incident->status"/>
            </a>
        @empty
            <x-empty-state icon="check" title="Aucun incident en cours">Tous les incidents enregistrés sont résolus, ou aucun n'a encore été détecté.</x-empty-state>
        @endforelse
    </section>

    <section class="card">
        <div class="card-header">
            <h2 class="card-title">Derniers incidents</h2>
        </div>
        @forelse ($latestIncidents as $incident)
            <a href="{{ route('incidents.show', $incident) }}" class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-3 last:border-0 hover:bg-slate-50">
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-night-900">{{ $incident->site->name }}</p>
                    <p class="truncate text-xs text-slate-500">{{ Format::datetime($incident->started_at) }} · durée {{ Format::duration($incident->duration_seconds) }}</p>
                </div>
                <x-incident-status :status="$incident->status"/>
            </a>
        @empty
            <x-empty-state icon="alert" title="Aucun incident enregistré">Les incidents apparaîtront ici dès qu'une panne sera confirmée.</x-empty-state>
        @endforelse
    </section>
</div>

<section class="card mt-6">
    <div class="card-header">
        <h2 class="card-title">Dernières vérifications</h2>
        <a href="{{ route('checks.index') }}" class="link text-xs">Tous les contrôles</a>
    </div>
    @if ($latestChecks->isEmpty())
        <x-empty-state icon="pulse" title="Aucune vérification enregistrée">
            Les résultats des contrôles s'afficheront ici dès que le moteur de surveillance aura effectué ses premières vérifications.
        </x-empty-state>
    @else
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Site</th><th>Résultat</th><th>Code HTTP</th><th>Temps de réponse</th><th>Date</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @foreach ($latestChecks as $check)
                    <tr>
                        <td><a href="{{ route('sites.show', $check->site_id) }}" class="font-medium text-night-900 hover:text-electric-600">{{ $check->site->name }}</a></td>
                        <td>@include('checks._result', ['check' => $check])</td>
                        <td class="tabular-nums">{{ $check->http_status ?? '—' }}</td>
                        <td class="tabular-nums">{{ Format::ms($check->response_time_ms) }}</td>
                        <td class="whitespace-nowrap text-slate-500">{{ Format::datetime($check->checked_at) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endif
@endsection
