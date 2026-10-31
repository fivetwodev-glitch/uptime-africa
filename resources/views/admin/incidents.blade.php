@extends('layouts.admin')

@section('title', 'Incidents')

@php use App\Support\Format; @endphp

@section('content')
<x-page-header title="Incidents" :subtitle="$incidents->total().' incident(s), toutes agences'"/>
@component('admin._filters', ['agencies' => $agencies, 'filters' => $filters])
    <div>
        <label for="etat" class="label">État</label>
        <select id="etat" name="etat" class="input">
            <option value="">Tous</option>
            <option value="ouverts" @selected(($filters['etat'] ?? null) === 'ouverts')>Ouverts</option>
            <option value="resolus" @selected(($filters['etat'] ?? null) === 'resolus')>Résolus</option>
        </select>
    </div>
@endcomponent

<div class="card">
    @if ($incidents->isEmpty())
        <x-empty-state icon="alert" title="Aucun incident"/>
    @else
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Site</th><th>Agence</th><th>Statut</th><th>Début</th><th>Fin</th><th>Cause</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @foreach ($incidents as $incident)
                    <tr>
                        <td><span class="font-semibold text-night-900">{{ $incident->site->name }}</span><span class="block max-w-xs truncate text-xs text-slate-500">{{ $incident->site->url }}</span></td>
                        <td>{{ $incident->site->agency->name }}</td>
                        <td><x-incident-status :status="$incident->status"/></td>
                        <td class="whitespace-nowrap">{{ Format::datetime($incident->started_at) }}</td>
                        <td class="whitespace-nowrap">{{ Format::datetime($incident->resolved_at) }}</td>
                        <td class="max-w-xs truncate text-slate-500">{{ $incident->last_error_message ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $incidents->links() }}
    @endif
</div>
@endsection
