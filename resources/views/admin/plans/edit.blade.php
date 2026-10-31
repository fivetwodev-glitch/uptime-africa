@extends('layouts.admin')

@section('title', 'Offre '.$plan->name)

@section('content')
<x-page-header :title="'Offre '.$plan->name" subtitle="Les abonnements déjà souscrits gardent leur prix ; les limites s'appliquent immédiatement à tous.">
    <a href="{{ route('admin.plans') }}" class="btn-secondary"><x-icon name="arrow-left" class="h-4 w-4"/> Offres</a>
</x-page-header>

<form method="POST" action="{{ route('admin.plans.update', $plan) }}" class="space-y-6">
    @csrf @method('PUT')

    <section class="card p-5">
        <h2 class="card-title mb-4">Présentation</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            <x-field name="name" label="Nom" :value="$plan->name" required/>
            <x-field name="audience" label="Public visé" :value="$plan->audience"/>
            <x-field name="sort_order" label="Ordre d'affichage" type="number" min="0" :value="$plan->sort_order" required/>
        </div>
        <div class="mt-4"><x-field name="tagline" label="Accroche" :value="$plan->tagline"/></div>
        <div class="mt-4 flex flex-wrap gap-6 text-sm">
            <label class="flex items-center gap-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $plan->is_active))> Offre active</label>
            <label class="flex items-center gap-2"><input type="hidden" name="is_public" value="0"><input type="checkbox" name="is_public" value="1" @checked(old('is_public', $plan->is_public))> Visible par les agences</label>
        </div>
    </section>

    <section class="card p-5">
        <h2 class="card-title mb-1">Tarifs (FCFA)</h2>
        <p class="mb-4 text-sm text-slate-500">Tant que la case « Tarif validé » n'est pas cochée, l'offre affiche « Tarif à venir » et ne peut être souscrite qu'en essai.</p>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="monthly_price_xof" label="Prix mensuel" type="number" min="0" step="1" :value="$plan->monthly_price_xof" required/>
            <x-field name="yearly_price_xof" label="Prix annuel (vide = pas d'offre annuelle)" type="number" min="0" step="1" :value="$plan->yearly_price_xof"/>
        </div>
        <label class="mt-4 flex items-center gap-2 text-sm font-semibold text-night-900"><input type="hidden" name="price_validated" value="0"><input type="checkbox" name="price_validated" value="1" @checked(old('price_validated', $plan->price_validated))> Tarif validé (souscription payante ouverte)</label>
    </section>

    <section class="card p-5">
        <h2 class="card-title mb-4">Limites</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            <x-field name="max_sites" label="Sites" type="number" min="1" :value="$plan->max_sites" required/>
            <x-field name="max_clients" label="Clients (vide = illimité)" type="number" min="1" :value="$plan->max_clients"/>
            <x-field name="max_users" label="Utilisateurs" type="number" min="1" :value="$plan->max_users" required/>
            <div>
                <label for="min_interval_minutes" class="label">Intervalle minimal</label>
                <select id="min_interval_minutes" name="min_interval_minutes" class="input">
                    @foreach ($intervals as $minutes)
                        <option value="{{ $minutes }}" @selected((int) old('min_interval_minutes', $plan->min_interval_minutes) === $minutes)>{{ $minutes }} min</option>
                    @endforeach
                </select>
            </div>
            <x-field name="retention_days" label="Historique (jours)" type="number" min="1" :value="$plan->retention_days" required/>
            <x-field name="whatsapp_monthly_quota" label="Quota WhatsApp / mois" type="number" min="0" :value="$plan->whatsapp_monthly_quota" required/>
        </div>
        <label class="mt-4 flex items-center gap-2 text-sm"><input type="hidden" name="includes_whatsapp" value="0"><input type="checkbox" name="includes_whatsapp" value="1" @checked(old('includes_whatsapp', $plan->includes_whatsapp))> Alertes WhatsApp incluses</label>
    </section>

    <section class="card p-5">
        <h2 class="card-title mb-4">Fonctionnalités</h2>
        <div class="grid gap-2 sm:grid-cols-2">
            @foreach ($features as $feature)
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="features[]" value="{{ $feature->value }}" @checked(in_array($feature->value, old('features', $plan->features ?? []), true))> {{ $feature->label() }}</label>
            @endforeach
        </div>
        @error('features.*')<p class="error">{{ $message }}</p>@enderror
    </section>

    <div class="flex justify-end"><button type="submit" class="btn-primary">Enregistrer l'offre</button></div>
</form>
@endsection
