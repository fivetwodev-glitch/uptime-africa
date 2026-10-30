@extends('layouts.console')

@section('title', 'Sites surveillés')

@php
    use App\Enums\MonitoringStatus;
    use App\Enums\SiteStatus;
    use App\Support\Format;
    $filtered = collect($filters)->filter()->isNotEmpty();
@endphp

@section('content')
<x-page-header title="Sites surveillés" :subtitle="$hasAnySite ? $sites->total().' site(s)'.($filtered ? ' correspondant aux filtres' : '') : null">
    @can('sites.manage')
        @if ($clients->isNotEmpty())
            <a href="{{ route('sites.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4"/> Ajouter un site</a>
        @endif
    @endcan
</x-page-header>

@if (! $hasAnySite)
    <div class="card">
        @if ($clients->isEmpty())
            <x-empty-state icon="users" title="Aucun site surveillé">
                Chaque site est rattaché à un client. Créez d'abord un client.
                @can('clients.manage')
                    <x-slot:action><a href="{{ route('clients.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4"/> Ajouter un client</a></x-slot:action>
                @endcan
            </x-empty-state>
        @else
            <x-empty-state icon="globe" title="Aucun site surveillé">
                Ajoutez l'adresse d'un site pour commencer à suivre sa disponibilité.
                @can('sites.manage')
                    <x-slot:action><a href="{{ route('sites.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4"/> Ajouter un site</a></x-slot:action>
                @endcan
            </x-empty-state>
        @endif
    </div>
@else
    <div class="card">
        <form method="GET" action="{{ route('sites.index') }}" class="grid gap-3 border-b border-slate-100 p-4 sm:grid-cols-2 lg:grid-cols-[1fr_auto_auto_auto_auto]" role="search">
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute top-2.5 left-3 h-4 w-4 text-slate-400"/>
                <label for="q" class="sr-only">Rechercher</label>
                <input id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="Nom ou adresse du site" class="input pl-9">
            </div>
            <label class="sr-only" for="statut">Statut</label>
            <select id="statut" name="statut" class="input" data-autosubmit>
                <option value="">Tous les statuts</option>
                @foreach (SiteStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(($filters['statut'] ?? null) === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            <label class="sr-only" for="surveillance">Surveillance</label>
            <select id="surveillance" name="surveillance" class="input" data-autosubmit>
                <option value="">Toute surveillance</option>
                @foreach (MonitoringStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(($filters['surveillance'] ?? null) === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            <label class="sr-only" for="client">Client</label>
            <select id="client" name="client" class="input" data-autosubmit>
                <option value="">Tous les clients</option>
                @foreach ($clients as $client)
                    <option value="{{ $client->id }}" @selected(($filters['client'] ?? null) === $client->id)>{{ $client->name }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <button type="submit" class="btn-secondary">Filtrer</button>
                @if ($filtered)
                    <a href="{{ route('sites.index') }}" class="btn-ghost">Réinitialiser</a>
                @endif
            </div>
        </form>

        @if ($sites->isEmpty())
            <x-empty-state icon="search" title="Aucun site ne correspond à ces filtres">
                <x-slot:action><a href="{{ route('sites.index') }}" class="btn-secondary">Réinitialiser les filtres</a></x-slot:action>
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                    <tr><th>Site</th><th>Statut</th><th>Client</th><th>Temps de réponse</th><th>Dernière vérification</th><th class="text-right"><span class="sr-only">Actions</span></th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                    @foreach ($sites as $site)
                        <tr>
                            <td class="max-w-xs">
                                <a href="{{ route('sites.show', $site) }}" class="block truncate font-semibold text-night-900 hover:text-electric-600">{{ $site->name }}</a>
                                <span class="block truncate text-xs text-slate-500">{{ $site->url }}</span>
                            </td>
                            <td><x-site-status :status="$site->status" :monitoring="$site->monitoring_status"/></td>
                            <td><a href="{{ route('clients.show', $site->client_id) }}" class="text-slate-700 hover:text-electric-600">{{ $site->client->name }}</a></td>
                            <td class="tabular-nums">{{ Format::ms($site->last_response_time_ms) }}</td>
                            <td class="whitespace-nowrap text-slate-500" title="{{ Format::datetime($site->last_checked_at) }}">{{ Format::ago($site->last_checked_at) }}</td>
                            <td class="text-right"><a href="{{ route('sites.show', $site) }}" class="btn-secondary btn-sm whitespace-nowrap">Consulter les détails</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $sites->links() }}
        @endif
    </div>
@endif
@endsection
