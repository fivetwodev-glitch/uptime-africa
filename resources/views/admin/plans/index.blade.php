@extends('layouts.admin')

@section('title', 'Offres')

@php use App\Enums\PlanFeature; use App\Support\Money; @endphp

@section('content')
<x-page-header title="Offres" subtitle="Limites, fonctionnalités et tarifs. Un tarif non validé ne peut pas être souscrit."/>

<div class="grid gap-4 lg:grid-cols-3">
    @foreach ($plans as $plan)
        <article class="card flex flex-col p-5">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <h2 class="text-lg font-bold text-night-900">{{ $plan->name }} <span class="font-mono text-xs font-normal text-slate-400">{{ $plan->code }}</span></h2>
                    <p class="text-sm text-slate-500">{{ $plan->audience }}</p>
                </div>
                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $plan->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $plan->is_active ? ($plan->is_public ? 'Publique' : 'Masquée') : 'Inactive' }}</span>
            </div>
            <p class="mt-3 text-sm">
                @if ($plan->price_validated)
                    <strong class="text-night-900">{{ Money::xof($plan->monthly_price_xof) }}</strong> / mois{{ $plan->yearly_price_xof ? ' · '.Money::xof($plan->yearly_price_xof).' / an' : '' }}
                @else
                    <span class="rounded bg-amber-100 px-1.5 py-0.5 text-xs font-semibold text-amber-800">Tarif à valider</span>
                @endif
            </p>
            <dl class="mt-4 grid grid-cols-2 gap-2 text-sm">
                <dt class="text-slate-500">Sites</dt><dd class="tabular-nums">{{ $plan->max_sites }}</dd>
                <dt class="text-slate-500">Clients</dt><dd class="tabular-nums">{{ $plan->max_clients ?? 'Illimité' }}</dd>
                <dt class="text-slate-500">Utilisateurs</dt><dd class="tabular-nums">{{ $plan->max_users }}</dd>
                <dt class="text-slate-500">Intervalle min.</dt><dd class="tabular-nums">{{ $plan->min_interval_minutes }} min</dd>
                <dt class="text-slate-500">Historique</dt><dd class="tabular-nums">{{ $plan->retention_days }} j</dd>
                <dt class="text-slate-500">WhatsApp</dt><dd>{{ $plan->includes_whatsapp ? $plan->whatsapp_monthly_quota.' / mois' : 'Non' }}</dd>
                <dt class="text-slate-500">En cours</dt><dd class="tabular-nums">{{ $plan->current_count }} abonnement(s)</dd>
            </dl>
            <ul class="mt-4 flex-1 space-y-1 text-xs text-slate-600">
                @foreach (PlanFeature::cases() as $feature)
                    <li class="flex gap-2"><x-icon :name="$plan->hasFeature($feature) ? 'check' : 'x'" class="h-4 w-4 {{ $plan->hasFeature($feature) ? 'text-electric-500' : 'text-slate-300' }}"/>{{ $feature->label() }}</li>
                @endforeach
            </ul>
            <a href="{{ route('admin.plans.edit', $plan) }}" class="btn-secondary mt-5"><x-icon name="pencil" class="h-4 w-4"/> Modifier</a>
        </article>
    @endforeach
</div>
@endsection
