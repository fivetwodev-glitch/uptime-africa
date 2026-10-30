@extends('layouts.console')

@section('title', 'Contrôles')

@php use App\Support\Format; @endphp

@section('content')
<x-page-header title="Contrôles" subtitle="Historique des vérifications effectuées sur vos sites."/>

<div class="card">
    @if (! $hasAnyCheck)
        <x-empty-state icon="pulse" title="Aucun contrôle enregistré">
            Chaque vérification d'un site (code HTTP, temps de réponse, erreur éventuelle) sera listée ici
            dès que le moteur de surveillance aura effectué ses premiers contrôles.
        </x-empty-state>
    @else
        <form method="GET" action="{{ route('checks.index') }}" class="grid gap-3 border-b border-slate-100 p-4 sm:grid-cols-3">
            <label class="sr-only" for="site">Site</label>
            <select id="site" name="site" class="input" data-autosubmit>
                <option value="">Tous les sites</option>
                @foreach ($sites as $site)
                    <option value="{{ $site->id }}" @selected(($filters['site'] ?? null) === $site->id)>{{ $site->name }}</option>
                @endforeach
            </select>
            <label class="sr-only" for="resultat">Résultat</label>
            <select id="resultat" name="resultat" class="input" data-autosubmit>
                <option value="">Tous les résultats</option>
                <option value="succes" @selected(($filters['resultat'] ?? null) === 'succes')>Succès</option>
                <option value="echec" @selected(($filters['resultat'] ?? null) === 'echec')>Échecs</option>
            </select>
            <label class="sr-only" for="periode">Période</label>
            <select id="periode" name="periode" class="input" data-autosubmit>
                <option value="24h" @selected($filters['periode'] === '24h')>24 dernières heures</option>
                <option value="7j" @selected($filters['periode'] === '7j')>7 derniers jours</option>
                <option value="30j" @selected($filters['periode'] === '30j')>30 derniers jours</option>
            </select>
            <noscript><button type="submit" class="btn-secondary">Filtrer</button></noscript>
        </form>

        @if ($checks->isEmpty())
            <x-empty-state icon="search" title="Aucun contrôle sur cette période ou avec ces filtres">
                <x-slot:action><a href="{{ route('checks.index') }}" class="btn-secondary">Réinitialiser les filtres</a></x-slot:action>
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Date</th><th>Site</th><th>Résultat</th><th>Code HTTP</th><th>Temps de réponse</th><th>Détail</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @foreach ($checks as $check)
                        <tr>
                            <td class="whitespace-nowrap text-slate-500">{{ Format::datetime($check->checked_at) }}</td>
                            <td><a href="{{ route('sites.show', $check->site_id) }}" class="font-medium text-night-900 hover:text-electric-600">{{ $check->site->name }}</a></td>
                            <td>@include('checks._result', ['check' => $check])</td>
                            <td class="tabular-nums">{{ $check->http_status ?? '—' }}</td>
                            <td class="tabular-nums">{{ Format::ms($check->response_time_ms) }}</td>
                            <td class="max-w-xs truncate text-xs text-slate-500" title="{{ $check->error_message }}">{{ $check->error_message ?? '' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $checks->links() }}
        @endif
    @endif
</div>
@endsection
