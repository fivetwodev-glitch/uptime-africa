@extends('layouts.console')

@php
    use App\Enums\MonitoringProvider;
    $editing = $site->exists;
    $codes = old('expected_status_codes', $site->expected_status_codes);
    $codes = is_array($codes) ? implode(', ', $codes) : $codes;
    $provider = old('monitoring_provider', $site->monitoring_provider?->value ?? 'internal');
@endphp

@section('title', $editing ? 'Modifier '.$site->name : 'Ajouter un site')

@section('content')
<x-page-header :title="$editing ? 'Modifier le site' : 'Ajouter un site'" :subtitle="$editing ? $site->name : 'Le site sera contrôlé à l\'intervalle choisi.'">
    <x-slot:back>
        <a href="{{ $editing ? route('sites.show', $site) : route('sites.index') }}" class="mb-2 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-night-900">
            <x-icon name="arrow-left" class="h-4 w-4"/> Retour
        </a>
    </x-slot:back>
</x-page-header>

@if ($clients->isEmpty())
    <div class="card">
        <x-empty-state icon="users" title="Aucun client">
            Un site doit être rattaché à un client.
            @can('clients.manage')
                <x-slot:action><a href="{{ route('clients.create') }}" class="btn-primary">Ajouter un client</a></x-slot:action>
            @endcan
        </x-empty-state>
    </div>
@else
<form method="POST" action="{{ $editing ? route('sites.update', $site) : route('sites.store') }}" class="card max-w-3xl" novalidate>
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="space-y-5 p-5 sm:p-6">
        <div class="grid gap-5 sm:grid-cols-2">
            <x-field name="name" label="Nom du site" :value="$site->name" required maxlength="120" placeholder="Boutique en ligne"/>
            <div>
                <label for="client_id" class="label">Client <span class="text-rose-500">*</span></label>
                <select id="client_id" name="client_id" required class="input @error('client_id') input-error @enderror">
                    <option value="">Choisir un client</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected(old('client_id', $site->client_id) === $client->id)>{{ $client->name }}</option>
                    @endforeach
                </select>
                @error('client_id')<p class="error">{{ $message }}</p>@enderror
            </div>
        </div>

        <x-field name="url" label="Adresse (URL)" type="url" :value="$site->url" required placeholder="https://www.exemple.ci"
                 hint="Adresse publique en http ou https. Les adresses internes ou privées sont refusées."/>

        <div class="grid gap-5 sm:grid-cols-3">
            <div>
                <label for="interval_minutes" class="label">Intervalle <span class="text-rose-500">*</span></label>
                <select id="interval_minutes" name="interval_minutes" class="input @error('interval_minutes') input-error @enderror">
                    @foreach ($intervals as $minutes)
                        <option value="{{ $minutes }}" @selected((int) old('interval_minutes', $site->interval_minutes) === $minutes)>
                            Toutes les {{ $minutes === 60 ? 'heures' : $minutes.' minutes' }}
                        </option>
                    @endforeach
                </select>
                @error('interval_minutes')<p class="error">{{ $message }}</p>@enderror
            </div>
            <x-field name="timeout_seconds" label="Délai maximal (s)" type="number" min="2" max="30" :value="$site->timeout_seconds" required/>
            <div>
                <label for="expected_status_codes" class="label">Codes HTTP attendus</label>
                <input id="expected_status_codes" name="expected_status_codes" value="{{ $codes }}" placeholder="200, 301"
                       class="input @if ($errors->has('expected_status_codes') || $errors->has('expected_status_codes.*')) input-error @endif">
                @if ($errors->has('expected_status_codes') || $errors->has('expected_status_codes.*'))
                    <p class="error">{{ $errors->first('expected_status_codes') ?: $errors->first('expected_status_codes.*') }}</p>
                @else
                    <p class="hint">Vide = tout code 2xx ou 3xx.</p>
                @endif
            </div>
        </div>

        <fieldset class="rounded-lg border border-slate-200 p-4">
            <legend class="px-1 text-sm font-semibold text-night-900">Fournisseur de surveillance</legend>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="monitoring_provider" class="label">Fournisseur</label>
                    <select id="monitoring_provider" name="monitoring_provider" class="input" data-provider-select>
                        @foreach (MonitoringProvider::cases() as $option)
                            <option value="{{ $option->value }}" @selected($provider === $option->value)>{{ $option->label() }}</option>
                        @endforeach
                    </select>
                    <p class="hint">Un fournisseur externe nécessite l'identifiant de son moniteur.</p>
                </div>
                <div data-external-field @class(['hidden' => $provider === 'internal'])>
                    <x-field name="external_monitor_id" label="Identifiant du moniteur externe" :value="$site->external_monitor_id" maxlength="100"/>
                </div>
            </div>
        </fieldset>
    </div>

    <div class="flex flex-col-reverse gap-2 border-t border-slate-100 bg-slate-50 px-5 py-4 sm:flex-row sm:justify-end sm:px-6">
        <a href="{{ $editing ? route('sites.show', $site) : route('sites.index') }}" class="btn-secondary">Annuler</a>
        <button type="submit" class="btn-primary">{{ $editing ? 'Enregistrer' : 'Ajouter le site' }}</button>
    </div>
</form>
@endif
@endsection
