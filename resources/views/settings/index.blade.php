@extends('layouts.console')

@section('title', 'Paramètres')

@php use App\Support\Format; @endphp

@section('content')
<x-page-header title="Paramètres" subtitle="Agence, profil, sécurité et équipe."/>

<div class="space-y-6">
    <section class="card">
        <div class="card-header">
            <h2 class="card-title">Agence</h2>
            @cannot('agency.update')<span class="text-xs text-slate-500">Lecture seule</span>@endcannot
        </div>
        <form method="POST" action="{{ route('settings.agency') }}" class="p-5 sm:p-6" novalidate>
            @csrf @method('PUT')
            <input type="hidden" name="_form" value="agency">
            <fieldset class="grid gap-5 sm:grid-cols-2" @cannot('agency.update') disabled @endcannot>
                <x-field form="agency" name="name" label="Nom de l'agence" :value="$agency->name" required maxlength="120"/>
                <div>
                    <label for="timezone" class="label">Fuseau horaire</label>
                    <select id="timezone" name="timezone" class="input @error('timezone', 'agency') input-error @enderror">
                        @foreach ($timezones as $value => $label)
                            <option value="{{ $value }}" @selected(old('timezone', $agency->timezone) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('timezone', 'agency')<p class="error">{{ $message }}</p>@enderror
                </div>
                <x-field form="agency" name="contact_email" label="Email de contact" type="email" :value="$agency->contact_email" maxlength="191"/>
                <x-field form="agency" name="contact_phone" label="Téléphone" type="tel" :value="$agency->contact_phone" maxlength="32"/>
            </fieldset>
            @can('agency.update')
                <div class="mt-5 flex justify-end"><button type="submit" class="btn-primary">Enregistrer</button></div>
            @endcan
        </form>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="card">
            <div class="card-header"><h2 class="card-title">Mon profil</h2></div>
            <form method="POST" action="{{ route('settings.profile') }}" class="space-y-5 p-5 sm:p-6" novalidate>
                @csrf @method('PUT')
                <input type="hidden" name="_form" value="profile">
                <x-field form="profile" name="name" label="Nom" :value="$user->name" required maxlength="120"/>
                <x-field form="profile" name="email" label="Adresse email" type="email" :value="$user->email" required maxlength="191"/>
                <div class="flex justify-end"><button type="submit" class="btn-primary">Enregistrer</button></div>
            </form>
        </section>

        <section class="card">
            <div class="card-header"><h2 class="card-title">Mot de passe</h2></div>
            <form method="POST" action="{{ route('settings.password') }}" class="space-y-5 p-5 sm:p-6" novalidate>
                @csrf @method('PUT')
                <x-field form="password" name="current_password" label="Mot de passe actuel" type="password" autocomplete="current-password" required/>
                <x-field form="password" name="password" label="Nouveau mot de passe" type="password" autocomplete="new-password" required/>
                <x-field form="password" name="password_confirmation" label="Confirmer le nouveau mot de passe" type="password" autocomplete="new-password" required/>
                <div class="flex justify-end"><button type="submit" class="btn-primary">Modifier le mot de passe</button></div>
            </form>
        </section>
    </div>

    <section class="card">
        <div class="card-header">
            <h2 class="card-title">Équipe</h2>
            <span class="text-xs text-slate-500">{{ $team->count() }} membre(s)</span>
        </div>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Nom</th><th>Email</th><th>Rôle</th><th>Statut</th><th>Dernière connexion</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @foreach ($team as $member)
                    <tr>
                        <td class="font-medium text-night-900">{{ $member->name }}@if ($member->is($user)) <span class="text-xs text-slate-400">(vous)</span>@endif</td>
                        <td>{{ $member->email }}</td>
                        <td>{{ $member->role?->name }}</td>
                        <td>{{ $member->is_active ? 'Actif' : 'Désactivé' }}</td>
                        <td class="whitespace-nowrap text-slate-500">{{ Format::ago($member->last_login_at) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500">L'invitation de nouveaux membres sera disponible dans une prochaine version.</p>
    </section>
</div>
@endsection
