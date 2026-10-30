@extends('layouts.console')

@section('title', 'Abonnement')

@php
    use App\Support\Format;
    use App\Support\Money;
    $plan = $subscription?->plan;
@endphp

@section('content')
<x-page-header title="Abonnement" subtitle="Formule, limites et historique de votre agence."/>

@if (! $subscription)
    <div class="card">
        <x-empty-state icon="card" title="Aucun abonnement actif">
            Votre agence n'a pas encore de formule active.
            @if ($supportEmail)
                Contactez-nous pour l'activer.
                <x-slot:action><a href="mailto:{{ $supportEmail }}?subject={{ rawurlencode('Activation abonnement Uptime Africa') }}" class="btn-primary"><x-icon name="mail" class="h-4 w-4"/> Contacter l'équipe</a></x-slot:action>
            @else
                Le paiement en ligne n'est pas encore disponible.
            @endif
        </x-empty-state>
    </div>
@else
    <div class="grid gap-6 lg:grid-cols-3">
        <section class="card relative overflow-hidden bg-night-900 p-6 text-white lg:col-span-1">
            <div class="pointer-events-none absolute -top-20 -right-20 h-56 w-56 rounded-full bg-electric-500/30 blur-2xl"></div>
            <p class="relative text-xs font-semibold tracking-wide text-electric-200 uppercase">Formule actuelle</p>
            <p class="relative mt-2 text-3xl font-bold">{{ $plan->name }}</p>
            <p class="relative mt-1 text-slate-300">{{ Money::xof($subscription->price_xof) }} · {{ $subscription->billing_period->label() }}</p>
            <dl class="relative mt-6 space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-300">Statut</dt><dd class="font-semibold">{{ $subscription->status->label() }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-300">Début</dt><dd>{{ Format::datetime($subscription->starts_at) }}</dd></div>
                @if ($subscription->trial_ends_at)
                    <div class="flex justify-between"><dt class="text-slate-300">Fin de l'essai</dt><dd>{{ Format::datetime($subscription->trial_ends_at) }}</dd></div>
                @endif
                <div class="flex justify-between"><dt class="text-slate-300">Échéance</dt><dd>{{ $subscription->ends_at ? Format::datetime($subscription->ends_at) : '—' }}</dd></div>
            </dl>
        </section>

        <section class="card lg:col-span-2">
            <div class="card-header"><h2 class="card-title">Utilisation</h2></div>
            <div class="grid gap-6 p-5 sm:grid-cols-2">
                @foreach ([['Sites surveillés', $sitesCount, $plan->max_sites], ['Utilisateurs', $usersCount, $plan->max_users]] as [$label, $used, $max])
                    @php $ratio = $max > 0 ? min(100, round($used / $max * 100)) : 0; @endphp
                    <div>
                        <div class="flex justify-between text-sm"><span class="font-medium text-night-900">{{ $label }}</span><span class="tabular-nums text-slate-500">{{ $used }} / {{ $max }}</span></div>
                        <div class="mt-2 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full {{ $ratio >= 100 ? 'bg-rose-500' : ($ratio >= 80 ? 'bg-amber-400' : 'bg-electric-500') }}" style="width: {{ $ratio }}%"></div></div>
                    </div>
                @endforeach
            </div>
            <dl class="grid gap-4 border-t border-slate-100 p-5 text-sm sm:grid-cols-3">
                <div><dt class="text-slate-500">Intervalle minimal</dt><dd class="font-medium text-night-900">{{ $plan->min_interval_minutes }} min</dd></div>
                <div><dt class="text-slate-500">Conservation</dt><dd class="font-medium text-night-900">{{ $plan->retention_days }} jours</dd></div>
                <div><dt class="text-slate-500">WhatsApp</dt><dd class="font-medium text-night-900">{{ $plan->includes_whatsapp ? $plan->whatsapp_monthly_quota.' messages / mois' : 'Non inclus' }}</dd></div>
            </dl>
        </section>
    </div>
@endif

@if ($plans->isNotEmpty())
    <section class="mt-8">
        <h2 class="text-lg font-bold text-night-900">Formules disponibles</h2>
        <p class="mt-1 text-sm text-slate-500">
            Le paiement en ligne (Mobile Money, carte) n'est pas encore ouvert.
            @if ($supportEmail) Pour changer de formule, <a href="mailto:{{ $supportEmail }}?subject={{ rawurlencode('Changement de formule Uptime Africa') }}" class="link">écrivez-nous</a>.@endif
        </p>
        <div class="mt-4 grid gap-4 md:grid-cols-2 {{ [1 => 'xl:grid-cols-1', 2 => 'xl:grid-cols-2', 3 => 'xl:grid-cols-3'][$plans->count()] ?? 'xl:grid-cols-4' }}">
            @foreach ($plans as $option)
                @php $current = $plan && $plan->id === $option->id; @endphp
                <article class="card flex flex-col p-5 {{ $current ? 'ring-2 ring-electric-500' : '' }}">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-night-900">{{ $option->name }}</h3>
                        @if ($current)<span class="rounded-full bg-electric-50 px-2 py-0.5 text-xs font-semibold text-electric-700">Actuelle</span>@endif
                    </div>
                    <p class="mt-3"><span class="text-2xl font-bold text-night-900">{{ Money::xof($option->monthly_price_xof) }}</span> <span class="text-sm text-slate-500">/ mois</span></p>
                    @if ($option->yearly_price_xof)<p class="text-xs text-slate-500">ou {{ Money::xof($option->yearly_price_xof) }} / an</p>@endif
                    <ul class="mt-4 space-y-2 text-sm text-slate-600">
                        <li class="flex gap-2"><x-icon name="check" class="h-4 w-4 text-electric-500"/>{{ $option->max_sites }} sites</li>
                        <li class="flex gap-2"><x-icon name="check" class="h-4 w-4 text-electric-500"/>{{ $option->max_users }} utilisateur(s)</li>
                        <li class="flex gap-2"><x-icon name="check" class="h-4 w-4 text-electric-500"/>Contrôle toutes les {{ $option->min_interval_minutes }} min</li>
                        <li class="flex gap-2"><x-icon name="check" class="h-4 w-4 text-electric-500"/>Historique {{ $option->retention_days }} jours</li>
                        <li class="flex gap-2"><x-icon :name="$option->includes_whatsapp ? 'check' : 'x'" class="h-4 w-4 {{ $option->includes_whatsapp ? 'text-electric-500' : 'text-slate-300' }}"/>Alertes WhatsApp</li>
                    </ul>
                </article>
            @endforeach
        </div>
    </section>
@endif

@if ($history->count() > 1)
    <section class="card mt-8">
        <div class="card-header"><h2 class="card-title">Historique</h2></div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Formule</th><th>Statut</th><th>Période</th><th>Montant</th><th>Début</th><th>Fin</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @foreach ($history as $row)
                    <tr>
                        <td class="font-medium text-night-900">{{ $row->plan->name }}</td>
                        <td>{{ $row->status->label() }}</td>
                        <td>{{ $row->billing_period->label() }}</td>
                        <td class="tabular-nums">{{ Money::xof($row->price_xof) }}</td>
                        <td class="whitespace-nowrap">{{ Format::datetime($row->starts_at) }}</td>
                        <td class="whitespace-nowrap">{{ Format::datetime($row->ends_at) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif
@endsection
