@extends('layouts.console')

@section('title', 'Paramètres')

@section('content')
<x-page-header title="Paramètres" subtitle="Profil, sécurité, agence et équipe."/>
@include('settings._tabs')

<div class="grid gap-6 lg:grid-cols-2">
    <section class="card">
        <div class="card-header">
            <h2 class="card-title">Mon profil</h2>
            <span class="text-xs text-slate-500">{{ $user->role?->name }}</span>
        </div>
        <form method="POST" action="{{ route('settings.profile') }}" class="space-y-5 p-5 sm:p-6" novalidate>
            @csrf @method('PUT')
            <input type="hidden" name="_form" value="profile">
            <x-field form="profile" name="name" label="Nom" :value="$user->name" required maxlength="120"/>
            <x-field form="profile" name="email" label="Adresse email" type="email" :value="$user->email" required maxlength="191"/>
            <x-field form="profile" name="current_password" label="Mot de passe actuel" type="password" autocomplete="current-password"
                     hint="Obligatoire uniquement pour changer d'adresse email."/>
            <p class="text-xs text-slate-500">Votre rôle ne peut être modifié que par un autre administrateur de l'agence.</p>
            <div class="flex justify-end"><button type="submit" class="btn-primary">Enregistrer</button></div>
        </form>
    </section>

    <section class="card">
        <div class="card-header">
            <h2 class="card-title">Agence</h2>
            @cannot('agency.update')<span class="text-xs text-slate-500">Lecture seule</span>@endcannot
        </div>
        <form method="POST" action="{{ route('settings.agency') }}" class="p-5 sm:p-6" novalidate>
            @csrf @method('PUT')
            <input type="hidden" name="_form" value="agency">
            <fieldset class="space-y-5" @cannot('agency.update') disabled @endcannot>
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
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-field form="agency" name="contact_email" label="Email de contact" type="email" :value="$agency->contact_email" maxlength="191"/>
                    <x-field form="agency" name="contact_phone" label="Téléphone" type="tel" :value="$agency->contact_phone" maxlength="32"/>
                </div>
            </fieldset>
            @can('agency.update')
                <div class="mt-5 flex justify-end"><button type="submit" class="btn-primary">Enregistrer</button></div>
            @endcan
        </form>
    </section>
</div>
@endsection
