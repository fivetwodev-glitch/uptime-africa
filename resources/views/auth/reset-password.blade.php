@extends('layouts.auth')

@section('title', $invitation ? 'Activer mon compte' : 'Nouveau mot de passe')

@section('content')
<h1 class="text-2xl font-bold tracking-tight text-night-900">{{ $invitation ? 'Activer mon compte' : 'Nouveau mot de passe' }}</h1>
<p class="mt-2 text-sm text-slate-500">{{ $invitation ? 'Choisissez le mot de passe de votre compte.' : 'Choisissez un nouveau mot de passe. Vos sessions ouvertes seront fermées.' }}</p>

<form method="POST" action="{{ route('password.store') }}" class="mt-8 space-y-5" novalidate>
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <x-field name="email" label="Adresse email" type="email" :value="$email" autocomplete="email" required maxlength="191"/>
    <x-field name="password" label="Mot de passe" type="password" autocomplete="new-password" required autofocus
             :hint="app()->isProduction() ? '10 caractères minimum, avec majuscule, minuscule et chiffre.' : '8 caractères minimum.'"/>
    <x-field name="password_confirmation" label="Confirmer le mot de passe" type="password" autocomplete="new-password" required/>
    <button type="submit" class="btn-primary w-full py-2.5">Enregistrer le mot de passe</button>
</form>
@endsection
