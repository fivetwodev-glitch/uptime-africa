@extends('layouts.admin')

@section('title', 'Abonnement · '.$subscription->agency->name)

@php use App\Support\Format; use App\Support\Money; $s = $subscription; @endphp

@section('content')
<x-page-header :title="$s->agency->name" :subtitle="$s->plan->name.' · '.$s->status->label()">
    <a href="{{ route('admin.subscriptions') }}" class="btn-secondary"><x-icon name="arrow-left" class="h-4 w-4"/> Abonnements</a>
</x-page-header>
@if ($errors->any())<div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" role="alert">{{ $errors->first() }}</div>@endif

<div class="grid gap-6 lg:grid-cols-3">
    <section class="card p-5">
        <h2 class="card-title mb-4">Détail</h2>
        <dl class="space-y-2 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">Offre</dt><dd class="font-semibold">{{ $s->plan->name }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Statut</dt><dd>{{ $s->status->label() }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Période</dt><dd>{{ $s->billing_period->label() }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Prix figé</dt><dd class="tabular-nums">{{ Money::xof($s->price_xof) }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Début</dt><dd>{{ Format::datetime($s->starts_at) }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Expiration</dt><dd>{{ Format::datetime($s->accessEndsAt()) }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Renouvelé le</dt><dd>{{ Format::datetime($s->renewed_at) }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Annulé le</dt><dd>{{ Format::datetime($s->cancelled_at) }}</dd></div>
            @if ($s->previous)<div class="flex justify-between"><dt class="text-slate-500">Remplace</dt><dd><a class="link" href="{{ route('admin.subscriptions.show', $s->previous) }}">{{ $s->previous->plan->name }}</a></dd></div>@endif
        </dl>
    </section>

    @if ($s->status->isCurrent())
        <section class="card p-5 lg:col-span-2">
            <h2 class="card-title mb-4">Actions</h2>
            <form method="POST" action="{{ route('admin.subscriptions.change', $s) }}" class="flex flex-wrap items-end gap-3" data-confirm="Changer l'offre immédiatement, sans paiement ? L'échéance est conservée.">
                @csrf
                <div><label for="plan_id" class="label">Changer d'offre (immédiat, sans paiement)</label>
                    <select id="plan_id" name="plan_id" class="input">@foreach ($plans->where('id', '!=', $s->plan_id) as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div>
                <button type="submit" class="btn-secondary">Changer</button>
            </form>

            @if ($s->price_xof > 0 && $s->status !== \App\Enums\SubscriptionStatus::Trialing)
                <form method="POST" action="{{ route('admin.subscriptions.renew', $s) }}" class="mt-6 space-y-3 border-t border-slate-100 pt-5" data-confirm="Enregistrer un renouvellement payé de {{ Money::xof($s->price_xof) }} ?">
                    @csrf
                    <p class="text-sm font-semibold text-night-900">Renouvellement payé hors ligne ({{ Money::xof($s->price_xof) }} · {{ $s->billing_period->label() }})</p>
                    @include('admin.subscriptions._payment-form', ['key' => 'renew'])
                    <button type="submit" class="btn-primary">Enregistrer le renouvellement</button>
                </form>
            @endif

            <form method="POST" action="{{ route('admin.subscriptions.cancel', $s) }}" class="mt-6 border-t border-slate-100 pt-5" data-confirm="Annuler cet abonnement immédiatement ? L'agence ne pourra plus ajouter de sites.">
                @csrf @method('DELETE')
                <button type="submit" class="btn-danger">Annuler l'abonnement</button>
            </form>
        </section>
    @endif
</div>

<section class="card mt-6">
    <div class="card-header"><h2 class="card-title">Paiements de l'agence</h2></div>
    @if ($payments->isEmpty())
        <p class="px-5 py-4 text-sm text-slate-500">Aucun paiement.</p>
    @else
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Créé le</th><th>Objet</th><th>Montant</th><th>Statut</th><th>Prestataire</th><th>Référence</th><th>Moyen</th><th>Payé le</th><th>Saisi par</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @foreach ($payments as $payment)
                    <tr>
                        <td class="whitespace-nowrap">{{ Format::datetime($payment->created_at) }}</td>
                        <td>{{ $payment->purpose->label() }}</td>
                        <td class="tabular-nums">{{ Money::xof($payment->amount_xof) }}</td>
                        <td>{{ $payment->status->label() }}</td>
                        <td>{{ $payment->provider }}</td>
                        <td>{{ $payment->provider_reference ?? '—' }}</td>
                        <td>{{ \App\Http\Controllers\Admin\SubscriptionController::METHODS[$payment->method] ?? '—' }}</td>
                        <td class="whitespace-nowrap">{{ Format::datetime($payment->paid_at) }}</td>
                        <td class="text-slate-500">{{ $payment->recorder?->email ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

<section class="card mt-6">
    <div class="card-header"><h2 class="card-title">Historique des abonnements</h2></div>
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>Offre</th><th>Statut</th><th>Prix</th><th>Début</th><th>Fin</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
            @foreach ($history as $row)
                <tr @if ($row->is($s)) class="bg-electric-50/40" @endif>
                    <td><a class="link" href="{{ route('admin.subscriptions.show', $row) }}">{{ $row->plan->name }}</a></td>
                    <td>{{ $row->status->label() }}</td>
                    <td class="tabular-nums">{{ Money::xof($row->price_xof) }}</td>
                    <td class="whitespace-nowrap">{{ Format::datetime($row->starts_at) }}</td>
                    <td class="whitespace-nowrap">{{ Format::datetime($row->accessEndsAt()) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>
@endsection
