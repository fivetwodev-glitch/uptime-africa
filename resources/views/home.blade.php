@extends('layouts.app')

@section('title', 'Surveillance de sites web')

@section('content')
<section class="relative overflow-hidden bg-night-900">
    <div class="pointer-events-none absolute -top-32 right-0 h-[30rem] w-[30rem] rounded-full bg-electric-500/25 blur-3xl"></div>
    <div class="relative mx-auto max-w-6xl px-4 pt-14 pb-20 sm:px-6 sm:pt-20 sm:pb-28">
        <p class="text-sm font-semibold tracking-wide text-electric-400 uppercase">Pour les agences web, freelances et prestataires</p>
        <h1 class="mt-4 max-w-3xl text-4xl leading-tight font-extrabold tracking-tight text-white sm:text-5xl">
            Tous les sites de vos clients surveillés depuis <span class="text-electric-400">un seul tableau de bord</span>.
        </h1>
        <p class="mt-6 max-w-2xl text-lg text-slate-300">
            Uptime Africa contrôle la disponibilité de vos sites, mesure leur temps de réponse
            et vous alerte par email et WhatsApp dès qu'un site tombe.
        </p>
        <div class="mt-10 flex flex-wrap gap-3">
            @auth
                <a href="{{ route('dashboard') }}" class="btn-primary px-6 py-3 text-base">Ouvrir mon tableau de bord</a>
            @else
                <a href="{{ route('register') }}" class="btn-primary px-6 py-3 text-base">Créer mon agence</a>
                <a href="{{ route('login') }}" class="btn px-6 py-3 text-base text-white ring-1 ring-white/25 hover:bg-white/10">Se connecter</a>
            @endauth
        </div>
    </div>
</section>

<section id="fonctionnalites" class="bg-white">
    <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
        <h2 class="text-3xl font-bold tracking-tight text-night-900">Ce que surveille Uptime Africa</h2>
        <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['globe', 'Disponibilité', 'Contrôle régulier de chaque site et détection des interruptions.'],
                ['clock', 'Temps de réponse', 'Mesure et historique du temps de réponse et du code HTTP.'],
                ['alert', 'Incidents', 'Ouverture, suivi et clôture des incidents avec leur durée.'],
                ['bell', 'Alertes', 'Notifications par email et WhatsApp à chaque panne et rétablissement.'],
                ['users', 'Multi-clients', 'Organisez vos sites par client, sans mélange entre agences.'],
                ['card', 'Francs CFA', 'Abonnements en FCFA, adaptés au marché ivoirien.'],
            ] as [$icon, $title, $text])
                <article class="card p-6">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-electric-50 text-electric-600"><x-icon :name="$icon"/></span>
                    <h3 class="mt-4 font-semibold text-night-900">{{ $title }}</h3>
                    <p class="mt-2 text-sm text-slate-600">{{ $text }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

<section id="agences" class="bg-slate-50">
    <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6">
        <h2 class="text-3xl font-bold tracking-tight text-night-900">Conçu pour les agences</h2>
        <p class="mt-4 max-w-2xl text-slate-600">
            Une agence gère plusieurs clients, chaque client plusieurs sites. Les données de chaque agence
            sont strictement isolées.
        </p>
    </div>
</section>
@endsection
