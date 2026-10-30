@extends('layouts.console')

@php
    use App\Enums\IncidentStatus;
    use App\Support\Format;
@endphp

@section('title', 'Incident · '.$incident->site->name)

@section('content')
<x-page-header :title="$incident->type->label()" :subtitle="$incident->site->name">
    <x-slot:back>
        <a href="{{ route('incidents.index') }}" class="mb-2 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-night-900">
            <x-icon name="arrow-left" class="h-4 w-4"/> Incidents
        </a>
    </x-slot:back>
    @if ($incident->status === IncidentStatus::Open)
        @can('incidents.acknowledge')
            <form method="POST" action="{{ route('incidents.acknowledge', $incident) }}">
                @csrf
                <button type="submit" class="btn-primary"><x-icon name="check" class="h-4 w-4"/> Prendre en charge</button>
            </form>
        @endcan
    @endif
    <a href="{{ route('sites.show', $incident->site_id) }}" class="btn-secondary">Voir le site</a>
</x-page-header>

<div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
    <div class="card p-5"><p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Statut</p><div class="mt-3"><x-incident-status :status="$incident->status"/></div></div>
    <x-stat label="Début" :value="Format::datetime($incident->started_at)"/>
    <x-stat label="Résolution" :value="$incident->resolved_at ? Format::datetime($incident->resolved_at) : 'Non résolu'"/>
    <div class="card p-5"><p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Durée</p><p class="mt-2 text-2xl font-bold text-night-900">@include('incidents._duration')</p></div>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-3">
    <section class="card lg:col-span-1">
        <div class="card-header"><h2 class="card-title">Détails</h2></div>
        <dl class="space-y-4 p-5 text-sm">
            <div><dt class="text-slate-500">Site</dt><dd><a href="{{ route('sites.show', $incident->site_id) }}" class="link">{{ $incident->site->name }}</a><span class="block truncate text-xs text-slate-500">{{ $incident->site->url }}</span></dd></div>
            <div><dt class="text-slate-500">Client</dt><dd><a href="{{ route('clients.show', $incident->site->client_id) }}" class="link">{{ $incident->site->client->name }}</a></dd></div>
            <div><dt class="text-slate-500">Échecs confirmés</dt><dd class="font-medium text-night-900">{{ $incident->confirmed_failures }}</dd></div>
            <div><dt class="text-slate-500">Dernier code HTTP</dt><dd class="font-medium text-night-900">{{ $incident->last_http_status ?? '—' }}</dd></div>
            @if ($incident->last_error_message)
                <div><dt class="text-slate-500">Dernière erreur</dt><dd class="font-mono text-xs break-words text-rose-700">{{ $incident->last_error_message }}</dd></div>
            @endif
            @if ($incident->acknowledged_at)
                <div><dt class="text-slate-500">Pris en charge</dt><dd class="font-medium text-night-900">{{ $incident->acknowledgedBy?->name ?? 'Utilisateur supprimé' }}<span class="block text-xs font-normal text-slate-500">{{ Format::datetime($incident->acknowledged_at) }}</span></dd></div>
            @endif
            @if ($incident->description)
                <div><dt class="text-slate-500">Description</dt><dd class="whitespace-pre-line text-night-900">{{ $incident->description }}</dd></div>
            @endif
        </dl>
    </section>

    <section class="card lg:col-span-2">
        <div class="card-header"><h2 class="card-title">Alertes envoyées</h2></div>
        @if ($notifications->isEmpty())
            <x-empty-state icon="bell" title="Aucune alerte enregistrée pour cet incident">
                Les alertes email et WhatsApp envoyées pour cet incident apparaîtront ici.
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Date</th><th>Événement</th><th>Canal</th><th>Destinataire</th><th>Statut</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @foreach ($notifications as $log)
                        <tr>
                            <td class="whitespace-nowrap text-slate-500">{{ Format::datetime($log->sent_at ?? $log->created_at) }}</td>
                            <td>{{ $log->event->label() }}</td>
                            <td>{{ $log->channel_type->label() }}</td>
                            <td class="max-w-[12rem] truncate">{{ $log->recipient }}</td>
                            <td>@include('notifications._status', ['log' => $log])</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
@endsection
