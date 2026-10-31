@extends('layouts.admin')

@section('title', 'Abonnements')

@php use App\Enums\BillingPeriod; use App\Enums\SubscriptionStatus; use App\Support\Format; use App\Support\Money; @endphp

@section('content')
<x-page-header title="Abonnements" :subtitle="$subscriptions->total().' abonnement(s)'"/>
@if ($errors->any())<div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" role="alert">{{ $errors->first() }}</div>@endif

<section class="card mb-6">
    <div class="card-header"><h2 class="card-title">Paiements en attente ({{ $pendingPayments->count() }})</h2></div>
    @if ($pendingPayments->isEmpty())
        <p class="px-5 py-4 text-sm text-slate-500">Aucune demande en attente.</p>
    @else
        <div class="divide-y divide-slate-100">
            @foreach ($pendingPayments as $payment)
                <div class="p-5">
                    <p class="text-sm">
                        <strong class="text-night-900">{{ $payment->agency->name }}</strong> · {{ $payment->purpose->label() }} · {{ $payment->subscription->plan->name }} ({{ $payment->subscription->billing_period->label() }})
                        · <strong>{{ Money::xof($payment->amount_xof) }}</strong> · demandé le {{ Format::datetime($payment->created_at) }}
                    </p>
                    <form method="POST" action="{{ route('admin.payments.confirm', $payment) }}" class="mt-3 space-y-3" data-confirm="Confirmer la réception de {{ Money::xof($payment->amount_xof) }} de {{ $payment->agency->name }} ?">
                        @csrf
                        @include('admin.subscriptions._payment-form', ['key' => 'p'.$payment->id])
                        <div class="flex gap-2">
                            <button type="submit" class="btn-primary btn-sm">Confirmer le paiement reçu</button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('admin.payments.cancel', $payment) }}" class="mt-2" data-confirm="Annuler cette demande ?">
                        @csrf
                        <button type="submit" class="btn-ghost btn-sm">Annuler la demande</button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif
</section>

@if ($agenciesWithout->isNotEmpty())
    <section class="card mb-6 p-5">
        <h2 class="card-title mb-4">Créer un abonnement</h2>
        <form method="POST" action="{{ route('admin.subscriptions.store') }}" class="grid gap-3 sm:grid-cols-5 sm:items-end">
            @csrf
            <div><label for="agency_id" class="label">Agence sans abonnement</label>
                <select id="agency_id" name="agency_id" class="input">@foreach ($agenciesWithout as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach</select></div>
            <div><label for="plan_id" class="label">Offre</label>
                <select id="plan_id" name="plan_id" class="input">@foreach ($plans as $p)<option value="{{ $p->id }}">{{ $p->name }}{{ $p->price_validated ? '' : ' (tarif à valider)' }}</option>@endforeach</select></div>
            <div><label for="status" class="label">Type</label>
                <select id="status" name="status" class="input"><option value="trialing">Essai</option><option value="active">Actif (tarif validé)</option></select></div>
            <div><label for="billing_period" class="label">Période</label>
                <select id="billing_period" name="billing_period" class="input">@foreach (BillingPeriod::cases() as $period)<option value="{{ $period->value }}">{{ $period->label() }}</option>@endforeach</select></div>
            <div class="flex gap-2"><div class="w-24"><label for="trial_days" class="label">Jours d'essai</label><input id="trial_days" name="trial_days" type="number" min="1" value="14" class="input"></div>
                <button type="submit" class="btn-primary">Créer</button></div>
        </form>
    </section>
@endif

<form method="GET" class="mb-4 flex flex-wrap items-end gap-3">
    <div><label for="statut" class="label">Statut</label>
        <select id="statut" name="statut" class="input"><option value="">Tous</option>@foreach (SubscriptionStatus::cases() as $s)<option value="{{ $s->value }}" @selected(($filters['statut'] ?? null) === $s->value)>{{ $s->label() }}</option>@endforeach</select></div>
    <div><label for="offre" class="label">Offre</label>
        <select id="offre" name="offre" class="input"><option value="">Toutes</option>@foreach ($plans as $p)<option value="{{ $p->id }}" @selected((int) ($filters['offre'] ?? 0) === $p->id)>{{ $p->name }}</option>@endforeach</select></div>
    <button type="submit" class="btn-secondary">Filtrer</button>
</form>

<div class="card">
    @if ($subscriptions->isEmpty())
        <x-empty-state icon="card" title="Aucun abonnement"/>
    @else
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Agence</th><th>Offre</th><th>Statut</th><th>Période</th><th>Prix figé</th><th>Début</th><th>Expiration</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @foreach ($subscriptions as $subscription)
                    <tr>
                        <td><a href="{{ route('admin.subscriptions.show', $subscription) }}" class="link">{{ $subscription->agency->name }}</a></td>
                        <td>{{ $subscription->plan->name }}</td>
                        <td>{{ $subscription->status->label() }}</td>
                        <td>{{ $subscription->billing_period->label() }}</td>
                        <td class="tabular-nums">{{ Money::xof($subscription->price_xof) }}</td>
                        <td class="whitespace-nowrap">{{ Format::datetime($subscription->starts_at) }}</td>
                        <td class="whitespace-nowrap">{{ Format::datetime($subscription->accessEndsAt()) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $subscriptions->links() }}
    @endif
</div>
@endsection
