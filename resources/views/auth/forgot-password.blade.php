@extends('layouts.auth')

@section('title', 'Mot de passe oublié')

@section('content')
<h1 class="text-2xl font-bold tracking-tight text-night-900">Mot de passe oublié</h1>
<p class="mt-2 text-sm text-slate-500">Indiquez l'adresse de votre compte : vous recevrez un lien pour choisir un nouveau mot de passe.</p>

@if (session('status'))
    <div class="mt-6 rounded-lg border border-electric-100 bg-electric-50 px-4 py-3 text-sm text-night-800" role="status">{{ session('status') }}</div>
@endif

<form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5" novalidate>
    @csrf
    <x-field name="email" label="Adresse email" type="email" autocomplete="email" required autofocus maxlength="191"/>
    <button type="submit" class="btn-primary w-full py-2.5">Envoyer le lien</button>
</form>
<p class="mt-6 text-sm"><a href="{{ route('login') }}" class="link">Retour à la connexion</a></p>
@endsection
