@extends('layouts.admin')

@section('title', 'Statistiques')

@php use App\Enums\SubscriptionStatus; use App\Support\Format; use App\Support\Money; @endphp

@section('content')
<x-page-header title="Statistiques globales" subtitle="Comptages en temps réel sur l'ensemble de la plateforme."/>

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <x-stat label="Agences" :value="$agencies['total']" :hint="$agencies['active'].' active(s)'" :href="route('admin.index')"/>
    <x-stat label="Utilisateurs" :value="$users['total']" :hint="$users['active'].' actif(s)'" :href="route('admin.users')"/>
    <x-stat label="Sites" :value="$sites['total']" :hint="$sites['monitored'].' sous surveillance'" :href="route('admin.sites')"/>
    <x-stat label="Incidents ouverts" :value="$incidents['open']" :tone="$incidents['open'] > 0 ? 'down' : 'default'" :hint="$incidents['last30'].' sur 30 jours'" :href="route('admin.incidents')"/>
</div>

<div class="mt-4 grid gap-4 sm:grid-cols-3">
    <x-stat label="Revenu mensuel récurrent" :value="Money::xof($mrr)" hint="Abonnements payants en cours, annuels ramenés au mois"/>
    <x-stat label="Encaissé ce mois-ci" :value="Money::xof($paidThisMonth)"/>
    <x-stat label="Paiements en attente" :value="$pendingPayments" :href="route('admin.subscriptions')"/>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <section class="card">
        <div class="card-header"><h2 class="card-title">Abonnements par statut</h2></div>
        <dl class="divide-y divide-slate-100 text-sm">
            @foreach (SubscriptionStatus::cases() as $status)
                <div class="flex justify-between px-5 py-3"><dt>{{ $status->label() }}</dt><dd class="font-semibold tabular-nums text-night-900">{{ $subscriptions[$status->value] ?? 0 }}</dd></div>
            @endforeach
        </dl>
    </section>
    <section class="card">
        <div class="card-header"><h2 class="card-title">Abonnements en cours par offre</h2></div>
        <dl class="divide-y divide-slate-100 text-sm">
            @foreach ($byPlan as $plan)
                <div class="flex justify-between px-5 py-3"><dt>{{ $plan->name }}</dt><dd class="font-semibold tabular-nums text-night-900">{{ $plan->current_count }}</dd></div>
            @endforeach
        </dl>
        <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500">Dernier battement du planificateur : {{ $lastHeartbeat ? Format::datetime(\Illuminate\Support\Carbon::parse($lastHeartbeat)) : 'jamais reçu' }}</p>
    </section>
</div>
@endsection
