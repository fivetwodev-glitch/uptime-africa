@extends('layouts.console')

@section('title', 'Incidents')

@php
    use App\Enums\IncidentStatus;
    use App\Enums\IncidentType;
    use App\Support\Format;
    $filtered = collect($filters)->filter()->isNotEmpty();
@endphp

@section('content')
<x-page-header title="Incidents" :subtitle="$hasAnyIncident ? $incidents->total().' incident(s)'.($filtered ? ' correspondant aux filtres' : '') : null"/>

<div class="card">
    @if (! $hasAnyIncident)
        <x-empty-state icon="check" title="Aucun incident enregistré">
            Un incident est ouvert lorsqu'une panne est confirmée par plusieurs contrôles successifs.
            Il apparaîtra ici avec sa durée et son historique d'alertes.
        </x-empty-state>
    @else
        <form method="GET" action="{{ route('incidents.index') }}" class="grid gap-3 border-b border-slate-100 p-4 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_auto]">
            <label class="sr-only" for="statut">Statut</label>
            <select id="statut" name="statut" class="input" data-autosubmit>
                <option value="">Tous les statuts</option>
                @foreach (IncidentStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(($filters['statut'] ?? null) === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            <label class="sr-only" for="type">Type</label>
            <select id="type" name="type" class="input" data-autosubmit>
                <option value="">Tous les types</option>
                @foreach (IncidentType::cases() as $type)
                    <option value="{{ $type->value }}" @selected(($filters['type'] ?? null) === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
            <label class="sr-only" for="site">Site</label>
            <select id="site" name="site" class="input" data-autosubmit>
                <option value="">Tous les sites</option>
                @foreach ($sites as $site)
                    <option value="{{ $site->id }}" @selected(($filters['site'] ?? null) === $site->id)>{{ $site->name }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <button type="submit" class="btn-secondary">Filtrer</button>
                @if ($filtered)<a href="{{ route('incidents.index') }}" class="btn-ghost">Réinitialiser</a>@endif
            </div>
        </form>

        @if ($incidents->isEmpty())
            <x-empty-state icon="search" title="Aucun incident ne correspond à ces filtres">
                <x-slot:action><a href="{{ route('incidents.index') }}" class="btn-secondary">Réinitialiser les filtres</a></x-slot:action>
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Site</th><th>Statut</th><th>Type</th><th>Début</th><th>Durée</th><th class="text-right"><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @foreach ($incidents as $incident)
                        <tr>
                            <td class="max-w-xs">
                                <span class="block truncate font-semibold text-night-900">{{ $incident->site->name }}</span>
                                <span class="block truncate text-xs text-slate-500">{{ $incident->site->url }}</span>
                            </td>
                            <td><x-incident-status :status="$incident->status"/></td>
                            <td>{{ $incident->type->label() }}</td>
                            <td class="whitespace-nowrap text-slate-500">{{ Format::datetime($incident->started_at) }}</td>
                            <td class="whitespace-nowrap">@include('incidents._duration')</td>
                            <td class="text-right"><a href="{{ route('incidents.show', $incident) }}" class="btn-secondary btn-sm whitespace-nowrap">Consulter les détails</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $incidents->links() }}
        @endif
    @endif
</div>
@endsection
