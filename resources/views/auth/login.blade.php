@extends('layouts.auth')

@section('title', 'Connexion')

@section('content')
<h1 class="text-2xl font-bold tracking-tight text-night-900">Connexion</h1>
<p class="mt-2 text-sm text-slate-500">
    Pas encore de compte ? <a href="{{ route('register') }}" class="link">Créer votre agence</a>
</p>

@if (session('status'))
    <div class="mt-6 rounded-lg border border-electric-100 bg-electric-50 px-4 py-3 text-sm text-night-800" role="status">{{ session('status') }}</div>
@endif

<form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5" novalidate>
    @csrf
    <x-field name="email" label="Adresse email" type="email" autocomplete="email" required autofocus/>
    <x-field name="password" label="Mot de passe" type="password" autocomplete="current-password" required/>
    <div class="flex items-center justify-between">
        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-slate-300 text-electric-500" @checked(old('remember'))>
            Rester connecté
        </label>
        <a href="{{ route('password.request') }}" class="link text-sm">Mot de passe oublié ?</a>
    </div>
    <button type="submit" class="btn-primary w-full py-2.5">Se connecter</button>
</form>
@endsection
