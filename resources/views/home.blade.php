@extends('layouts.app')

@section('title', 'Surveillance de sites web')

@section('content')
<section class="mx-auto max-w-6xl px-4 py-16 sm:px-6 sm:py-24">
    <p class="text-sm font-semibold uppercase tracking-wide text-brand-700">Pour les agences web et les développeurs</p>
    <h1 class="mt-3 max-w-3xl text-3xl font-bold tracking-tight sm:text-5xl">
        Tous les sites de vos clients surveillés depuis un seul tableau de bord.
    </h1>
    <p class="mt-6 max-w-2xl text-lg text-slate-600">
        Uptime Africa contrôle la disponibilité de vos sites, mesure leur temps de réponse
        et vous alerte par email et WhatsApp dès qu'un site tombe.
    </p>
    <p class="mt-8 inline-flex rounded-md bg-amber-50 px-4 py-2 text-sm text-amber-800 ring-1 ring-amber-200">
        Application en cours de développement : l'inscription n'est pas encore ouverte.
    </p>
</section>

<section id="fonctionnalites" class="bg-white">
    <div class="mx-auto grid max-w-6xl gap-6 px-4 py-16 sm:grid-cols-2 sm:px-6 lg:grid-cols-3">
        @foreach ([
            ['Disponibilité', 'Contrôle régulier de chaque site et détection des interruptions.'],
            ['Temps de réponse', 'Mesure et historique du temps de réponse et du code HTTP.'],
            ['Incidents', 'Ouverture, suivi et clôture automatique des incidents avec leur durée.'],
            ['Alertes', 'Notifications par email et WhatsApp à chaque panne et rétablissement.'],
            ['Multi-clients', 'Organisez vos sites par client, sans mélange entre agences.'],
            ['FCFA', 'Abonnements en francs CFA, adaptés au marché ivoirien.'],
        ] as [$title, $text])
            <article class="rounded-lg border border-slate-200 p-6">
                <h2 class="font-semibold text-slate-900">{{ $title }}</h2>
                <p class="mt-2 text-sm text-slate-600">{{ $text }}</p>
            </article>
        @endforeach
    </div>
</section>

<section id="agences" class="mx-auto max-w-6xl px-4 py-16 sm:px-6">
    <h2 class="text-2xl font-bold">Conçu pour les agences</h2>
    <p class="mt-4 max-w-2xl text-slate-600">
        Une agence gère plusieurs clients, chaque client plusieurs sites. Les données de chaque agence
        sont strictement isolées.
    </p>
</section>
@endsection
