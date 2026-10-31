@extends('layouts.admin')

@section('title', 'Utilisateurs')

@section('content')
<x-page-header title="Utilisateurs" :subtitle="$users->total().' compte(s)'"/>
@error('user')<div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" role="alert">{{ $message }}</div>@enderror
@include('admin._filters', ['search' => 'Nom ou email'])

<div class="card">
    @if ($users->isEmpty())
        <x-empty-state icon="users" title="Aucun utilisateur"/>
    @else
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Utilisateur</th><th>Agence</th><th>Rôle</th><th>Statut</th><th>Dernière connexion</th><th class="text-right"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @foreach ($users as $user)
                    <tr>
                        <td><span class="font-semibold text-night-900">{{ $user->name }}</span><span class="block text-xs text-slate-500">{{ $user->email }}</span></td>
                        <td>{{ $user->agency?->name ?? '—' }}</td>
                        <td>{{ $user->role?->name ?? '—' }}</td>
                        <td>{{ $user->is_active ? 'Actif' : 'Désactivé' }}</td>
                        <td class="whitespace-nowrap text-slate-500">{{ \App\Support\Format::datetime($user->last_login_at) }}</td>
                        <td class="text-right">
                            @unless ($user->is($me))
                                <form method="POST" action="{{ route('admin.users.toggle', $user) }}" data-confirm="{{ $user->is_active ? 'Désactiver' : 'Réactiver' }} le compte {{ $user->email }} ?">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="{{ $user->is_active ? 'btn-danger' : 'btn-secondary' }} btn-sm">{{ $user->is_active ? 'Désactiver' : 'Réactiver' }}</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $users->links() }}
    @endif
</div>
@endsection
