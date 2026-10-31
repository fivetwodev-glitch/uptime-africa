@extends('layouts.console')

@section('title', 'Abonnement')

@php
    use App\Enums\BillingPeriod;
    use App\Enums\PlanFeature;
    use App\Enums\SubscriptionStatus;
    use App\Support\Format;
    use App\Support\Money;
    $plan = $subscription?->plan;
    $canManage = auth()->user()->can('subscriptions.manage');
@endphp

@section('content')
<x-page-header title="Abonnement" subtitle="Formule, limites, échéance et paiements de votre agence."/>

@error('plan_id')<div class="mb-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" role="alert">{{ $message }}</div>@enderror
@error('subscription')<div class="mb-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" role="alert">{{ $message }}</div>@enderror

@foreach ($pending as $request)
    <div class="mb-6 flex flex-col gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 sm:flex-row sm:items-center sm:justify-between">
        <p>
            <strong>{{ $request->purpose->label() }} en attente de paiement</strong> :
            {{ $request->subscription->plan->name }} · {{ Money::xof($request->amount_xof) }}.
            @if ($gateway->checkoutUrl($request) === null)
                L'équipe Uptime Africa vous contacte pour le règlement{{ $supportEmail ? ' ('.$supportEmail.')' : '' }}, puis l'active.
            @endif
        </p>
        @if ($canManage)
            <form method="POST" action="{{ route('subscription.request.cancel', $request) }}" data-confirm="Annuler cette demande ?">
                @csrf @method('DELETE')
                <button type="submit" class="btn-secondary btn-sm">Annuler la demande</button>
            </form>
        @endif
    </div>
@endforeach

@if (! $subscription)
    <div class="card">
        <x-empty-state icon="card" title="Aucun abonnement actif">
            Votre agence n'a pas de formule en cours : l'ajout de sites, de clients et d'utilisateurs est bloqué. La surveillance des sites existants continue.
            Choisissez une formule ci-dessous.
        </x-empty-state>
    </div>
@else
    <div class="grid gap-6 lg:grid-cols-3">
        <section class="card relative overflow-hidden bg-night-900 p-6 text-white lg:col-span-1">
            <div class="pointer-events-none absolute -top-20 -right-20 h-56 w-56 rounded-full bg-electric-500/30 blur-2xl"></div>
            <p class="relative text-xs font-semibold tracking-wide text-electric-200 uppercase">Formule actuelle</p>
            <p class="relative mt-2 text-3xl font-bold">{{ $plan->name }}</p>
            <p class="relative mt-1 text-slate-300">
                @if ($subscription->status === SubscriptionStatus::Trialing) Essai gratuit @else {{ Money::xof($subscription->price_xof) }} · {{ $subscription->billing_period->label() }} @endif
            </p>
            <dl class="relative mt-6 space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-300">Statut</dt><dd class="font-semibold">{{ $subscription->status->label() }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-300">Début</dt><dd>{{ Format::datetime($subscription->starts_at) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-300">{{ $subscription->status === SubscriptionStatus::Trialing ? 'Fin de l\'essai' : 'Expiration' }}</dt><dd>{{ $subscription->accessEndsAt() ? Format::datetime($subscription->accessEndsAt()) : 'Sans échéance' }}</dd></div>
                @if ($subscription->renewed_at)
                    <div class="flex justify-between"><dt class="text-slate-300">Dernier renouvellement</dt><dd>{{ Format::datetime($subscription->renewed_at) }}</dd></div>
                @endif
            </dl>
            @if ($subscription->status === SubscriptionStatus::PastDue)
                <p class="relative mt-4 rounded-lg bg-rose-500/20 px-3 py-2 text-xs text-rose-100">Échéance dépassée : renouvelez sous {{ config('billing.grace_days') }} jours pour éviter l'expiration.</p>
            @endif
            @if ($canManage && $subscription->price_xof > 0 && $subscription->status !== SubscriptionStatus::Trialing)
                <form method="POST" action="{{ route('subscription.renew') }}" class="relative mt-6">
                    @csrf
                    <button type="submit" class="btn w-full bg-white text-night-900 hover:bg-slate-100">Renouveler ({{ Money::xof($subscription->price_xof) }})</button>
                </form>
            @endif
        </section>

        <section class="card lg:col-span-2">
            <div class="card-header"><h2 class="card-title">Utilisation</h2></div>
            <div class="grid gap-6 p-5 sm:grid-cols-3">
                @foreach ([['Sites surveillés', $usage['sites'], $plan->max_sites], ['Clients', $usage['clients'], $plan->max_clients], ['Utilisateurs', $usage['users'], $plan->max_users]] as [$label, $used, $max])
                    @php $ratio = $max ? min(100, round($used / $max * 100)) : 0; @endphp
                    <div>
                        <div class="flex justify-between text-sm"><span class="font-medium text-night-900">{{ $label }}</span><span class="tabular-nums text-slate-500">{{ $used }} / {{ $max ?? '∞' }}</span></div>
                        <div class="mt-2 h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full {{ $ratio >= 100 ? 'bg-rose-500' : ($ratio >= 80 ? 'bg-amber-400' : 'bg-electric-500') }}" style="width: {{ $ratio }}%"></div></div>
                    </div>
                @endforeach
            </div>
            <dl class="grid gap-4 border-t border-slate-100 p-5 text-sm sm:grid-cols-3">
                <div><dt class="text-slate-500">Intervalle minimal</dt><dd class="font-medium text-night-900">{{ $plan->min_interval_minutes }} min</dd></div>
                <div><dt class="text-slate-500">Historique consultable</dt><dd class="font-medium text-night-900">{{ $plan->retention_days }} jours</dd></div>
                <div><dt class="text-slate-500">WhatsApp</dt><dd class="font-medium text-night-900">{{ $plan->includes_whatsapp ? $plan->whatsapp_monthly_quota.' messages / mois' : 'Non inclus' }}</dd></div>
            </dl>
        </section>
    </div>
@endif

@if ($plans->isNotEmpty())
    <section class="mt-8">
        <h2 class="text-lg font-bold text-night-900">Formules</h2>
        <p class="mt-1 text-sm text-slate-500">
            Paiement : {{ $gateway->label() }}. Un changement de formule prend effet à la confirmation du paiement ; la formule actuelle reste active d'ici là.
        </p>
        <div class="mt-4 grid gap-4 md:grid-cols-3">
            @foreach ($plans as $option)
                @php $isCurrent = $plan && $plan->id === $option->id; @endphp
                <article class="card flex flex-col p-5 {{ $isCurrent ? 'ring-2 ring-electric-500' : '' }}">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-bold text-night-900">{{ $option->name }}</h3>
                        @if ($isCurrent)<span class="rounded-full bg-electric-50 px-2 py-0.5 text-xs font-semibold text-electric-700">Actuelle</span>@endif
                    </div>
                    @if ($option->audience)<p class="text-sm text-slate-500">{{ $option->audience }}</p>@endif
                    <p class="mt-3">
                        @if ($option->price_validated)
                            <span class="text-2xl font-bold text-night-900">{{ Money::xof($option->monthly_price_xof) }}</span> <span class="text-sm text-slate-500">/ mois</span>
                            @if ($option->yearly_price_xof)<span class="block text-xs text-slate-500">ou {{ Money::xof($option->yearly_price_xof) }} / an</span>@endif
                        @else
                            <span class="text-base font-semibold text-slate-500">Tarif à venir</span>
                        @endif
                    </p>
                    <ul class="mt-4 flex-1 space-y-2 text-sm text-slate-600">
                        <li class="flex gap-2"><x-icon name="check" class="h-4 w-4 shrink-0 text-electric-500"/>{{ $option->max_sites }} sites</li>
                        <li class="flex gap-2"><x-icon name="check" class="h-4 w-4 shrink-0 text-electric-500"/>{{ $option->max_clients ? $option->max_clients.' clients' : 'Clients illimités' }}</li>
                        <li class="flex gap-2"><x-icon name="check" class="h-4 w-4 shrink-0 text-electric-500"/>{{ $option->max_users }} utilisateur(s)</li>
                        <li class="flex gap-2"><x-icon name="check" class="h-4 w-4 shrink-0 text-electric-500"/>Contrôle toutes les {{ $option->min_interval_minutes }} min</li>
                        <li class="flex gap-2"><x-icon name="check" class="h-4 w-4 shrink-0 text-electric-500"/>Historique {{ $option->retention_days }} jours</li>
                        @foreach (PlanFeature::cases() as $feature)
                            @if ($option->hasFeature($feature))
                                <li class="flex gap-2"><x-icon name="check" class="h-4 w-4 shrink-0 text-electric-500"/>{{ $feature->label() }}</li>
                            @endif
                        @endforeach
                    </ul>
                    @if ($canManage && $option->price_validated)
                        <form method="POST" action="{{ route('subscription.request') }}" class="mt-5 flex gap-2">
                            @csrf
                            <input type="hidden" name="plan_id" value="{{ $option->id }}">
                            <label class="sr-only" for="period-{{ $option->id }}">Période</label>
                            <select id="period-{{ $option->id }}" name="billing_period" class="input">
                                <option value="{{ BillingPeriod::Monthly->value }}">Mensuel</option>
                                @if ($option->yearly_price_xof)<option value="{{ BillingPeriod::Yearly->value }}">Annuel</option>@endif
                            </select>
                            <button type="submit" class="btn-primary shrink-0">Choisir</button>
                        </form>
                    @elseif (! $option->price_validated)
                        <p class="mt-5 text-xs text-slate-500">Souscription disponible dès la publication du tarif.</p>
                    @endif
                </article>
            @endforeach
        </div>
    </section>
@endif

<section class="card mt-8">
    <div class="card-header"><h2 class="card-title">Historique des paiements</h2></div>
    @if ($payments->isEmpty())
        <x-empty-state icon="card" title="Aucun paiement"/>
    @else
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Date</th><th>Objet</th><th>Formule</th><th>Montant</th><th>Statut</th><th>Référence</th><th>Période couverte</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @foreach ($payments as $payment)
                    <tr>
                        <td class="whitespace-nowrap">{{ Format::datetime($payment->paid_at ?? $payment->created_at) }}</td>
                        <td>{{ $payment->purpose->label() }}</td>
                        <td>{{ $payment->subscription->plan->name }}</td>
                        <td class="tabular-nums">{{ Money::xof($payment->amount_xof) }}</td>
                        <td>{{ $payment->status->label() }}</td>
                        <td class="text-slate-500">{{ $payment->provider_reference ?? '—' }}</td>
                        <td class="whitespace-nowrap text-slate-500">{{ $payment->period_ends_at ? Format::datetime($payment->period_starts_at).' → '.Format::datetime($payment->period_ends_at) : '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

@if ($history->count() > 1)
    <section class="card mt-8">
        <div class="card-header"><h2 class="card-title">Historique des abonnements</h2></div>
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
                        <td class="whitespace-nowrap">{{ Format::datetime($row->accessEndsAt()) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif
@endsection
