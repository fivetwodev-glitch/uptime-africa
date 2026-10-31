@extends('layouts.console')

@section('title', 'Sécurité')

@php use App\Support\Format; @endphp

@section('content')
<x-page-header title="Paramètres" subtitle="Profil, sécurité, agence et équipe."/>
@include('settings._tabs')

<div class="grid gap-6 lg:grid-cols-2">
    <section class="card h-fit">
        <div class="card-header"><h2 class="card-title">Mot de passe</h2></div>
        <form method="POST" action="{{ route('settings.password') }}" class="space-y-5 p-5 sm:p-6" novalidate>
            @csrf @method('PUT')
            <input type="hidden" name="_form" value="password">
            <x-field form="password" name="current_password" label="Mot de passe actuel" type="password" autocomplete="current-password" required/>
            <x-field form="password" name="password" label="Nouveau mot de passe" type="password" autocomplete="new-password" required
                     :hint="app()->isProduction() ? '10 caractères minimum, avec majuscule, minuscule et chiffre.' : '8 caractères minimum.'"/>
            <x-field form="password" name="password_confirmation" label="Confirmer le nouveau mot de passe" type="password" autocomplete="new-password" required/>
            <p class="text-xs text-slate-500">Vos sessions ouvertes sur d'autres appareils seront fermées.</p>
            <div class="flex justify-end"><button type="submit" class="btn-primary">Modifier le mot de passe</button></div>
        </form>
    </section>

    <section class="card">
        <div class="card-header"><h2 class="card-title">Sessions ouvertes</h2></div>
        @if (! $sessionsEnabled)
            <p class="p-5 text-sm text-slate-500">La liste des sessions nécessite SESSION_DRIVER=database.</p>
        @else
            <ul class="divide-y divide-slate-100">
                @foreach ($sessions as $session)
                    <li class="flex items-start justify-between gap-4 px-5 py-3 text-sm">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-night-900" title="{{ $session->user_agent }}">{{ \Illuminate\Support\Str::limit($session->user_agent ?: 'Navigateur inconnu', 70) }}</p>
                            <p class="text-xs text-slate-500">{{ $session->ip_address ?? 'IP inconnue' }} · active {{ Format::ago(\Illuminate\Support\Carbon::createFromTimestamp($session->last_activity)) }}</p>
                        </div>
                        @if ($session->id === $currentSession)
                            <span class="shrink-0 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">Cette session</span>
                        @endif
                    </li>
                @endforeach
            </ul>
            @if ($sessions->count() > 1)
                <form method="POST" action="{{ route('settings.sessions.destroy') }}" class="flex flex-col gap-3 border-t border-slate-100 p-5 sm:flex-row sm:items-end" novalidate>
                    @csrf @method('DELETE')
                    <div class="flex-1"><x-field form="sessions" name="password" label="Mot de passe" type="password" autocomplete="current-password" required/></div>
                    <button type="submit" class="btn-secondary">Fermer les autres sessions</button>
                </form>
            @endif
        @endif
    </section>
</div>
@endsection
