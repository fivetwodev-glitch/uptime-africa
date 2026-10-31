@extends('layouts.console')

@section('title', 'Équipe')

@php use App\Support\Format; @endphp

@section('content')
<x-page-header title="Paramètres" subtitle="Profil, sécurité, agence et équipe.">
    @can('users.manage')
        <a href="{{ route('team.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4"/> Inviter un utilisateur</a>
    @endcan
</x-page-header>
@include('settings._tabs')

@error('member')
    <div class="mb-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ $message }}</div>
@enderror

<section class="card">
    <div class="card-header">
        <h2 class="card-title">Utilisateurs de l'agence</h2>
        <span class="text-xs text-slate-500">{{ $members->count() }}{{ $limit ? ' / '.$limit : '' }} utilisateur(s)</span>
    </div>
    <div class="overflow-x-auto">
        <table class="data-table">
            <thead><tr><th>Nom</th><th>Rôle</th><th>Statut</th><th>Dernière connexion</th>@can('users.manage')<th class="text-right"><span class="sr-only">Actions</span></th>@endcan</tr></thead>
            <tbody class="divide-y divide-slate-100">
            @foreach ($members as $member)
                @php $pending = $member->last_login_at === null; $self = $member->is($me); @endphp
                <tr>
                    <td>
                        <span class="font-semibold text-night-900">{{ $member->name }}</span>@if ($self) <span class="text-xs text-slate-400">(vous)</span>@endif
                        <span class="block text-xs text-slate-500">{{ $member->email }}</span>
                    </td>
                    <td>{{ $member->role?->name }}</td>
                    <td>
                        @if (! $member->is_active)
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">Désactivé</span>
                        @elseif ($pending)
                            <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700">Invitation en attente</span>
                        @else
                            <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">Actif</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap text-slate-500">{{ Format::ago($member->last_login_at) }}</td>
                    @can('users.manage')
                        <td class="text-right">
                            <div class="flex justify-end gap-2">
                                <a href="{{ route('team.edit', $member) }}" class="btn-secondary btn-sm">Modifier</a>
                                @unless ($self)
                                    @if ($pending && $member->is_active)
                                        <form method="POST" action="{{ route('team.invitation', $member) }}">
                                            @csrf
                                            <button type="submit" class="btn-secondary btn-sm">Renvoyer l'invitation</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('team.toggle', $member) }}"
                                          @if ($member->is_active) data-confirm="Désactiver {{ $member->name }} ? Ses sessions seront fermées immédiatement." @endif>
                                        @csrf @method('PATCH')
                                        <button type="submit" class="btn-secondary btn-sm">{{ $member->is_active ? 'Désactiver' : 'Réactiver' }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('team.destroy', $member) }}" data-confirm="Supprimer définitivement le compte de {{ $member->name }} ?">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn-danger btn-sm"><span class="sr-only">Supprimer</span><x-icon name="trash" class="h-4 w-4"/></button>
                                    </form>
                                @endunless
                            </div>
                        </td>
                    @endcan
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</section>

<section class="card mt-6">
    <div class="card-header"><h2 class="card-title">Rôles</h2></div>
    <dl class="grid gap-4 p-5 text-sm sm:grid-cols-2">
        <div><dt class="font-semibold text-night-900">Administrateur d'agence</dt><dd class="mt-1 text-slate-500">Tout gérer dans l'agence : équipe, clients, sites, alertes, abonnement et paramètres.</dd></div>
        <div><dt class="font-semibold text-night-900">Collaborateur</dt><dd class="mt-1 text-slate-500">Gérer les clients, les sites et prendre en charge les incidents. Pas d'accès à l'équipe, aux alertes ni à l'abonnement.</dd></div>
    </dl>
</section>
@endsection
