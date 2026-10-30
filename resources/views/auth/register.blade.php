@extends('layouts.auth')

@section('title', 'Créer votre agence')

@section('content')
<h1 class="text-2xl font-bold tracking-tight text-night-900">Créer votre agence</h1>
<p class="mt-2 text-sm text-slate-500">
    Déjà inscrit ? <a href="{{ route('login') }}" class="link">Se connecter</a>
</p>

<form method="POST" action="{{ route('register') }}" class="mt-8 space-y-5" novalidate>
    @csrf
    <x-field name="agency_name" label="Nom de l'agence" autocomplete="organization" required autofocus/>
    <x-field name="name" label="Votre nom" autocomplete="name" required/>
    <x-field name="email" label="Adresse email professionnelle" type="email" autocomplete="email" required/>
    <x-field name="password" label="Mot de passe" type="password" autocomplete="new-password" required hint="8 caractères minimum."/>
    <x-field name="password_confirmation" label="Confirmer le mot de passe" type="password" autocomplete="new-password" required/>
    <button type="submit" class="btn-primary w-full py-2.5">Créer mon agence</button>
    <p class="text-xs text-slate-500">Vous serez le propriétaire de l'agence et pourrez ensuite ajouter vos clients et leurs sites.</p>
</form>
@endsection
