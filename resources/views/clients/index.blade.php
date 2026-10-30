@extends('layouts.console')

@section('title', 'Clients')

@section('content')
<x-page-header title="Clients" :subtitle="$hasAnyClient ? $clients->total().' client(s)' : null">
    @can('clients.manage')
        <a href="{{ route('clients.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4"/> Ajouter un client</a>
    @endcan
</x-page-header>

<div class="card">
    @if (! $hasAnyClient)
        <x-empty-state icon="users" title="Aucun client">
            Ajoutez vos clients pour y rattacher les sites à surveiller.
            @can('clients.manage')
                <x-slot:action><a href="{{ route('clients.create') }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4"/> Ajouter un client</a></x-slot:action>
            @endcan
        </x-empty-state>
    @else
        <form method="GET" action="{{ route('clients.index') }}" class="flex gap-2 border-b border-slate-100 p-4" role="search">
            <div class="relative flex-1">
                <x-icon name="search" class="pointer-events-none absolute top-2.5 left-3 h-4 w-4 text-slate-400"/>
                <label for="q" class="sr-only">Rechercher</label>
                <input id="q" name="q" type="search" value="{{ $q }}" placeholder="Nom, contact ou email" class="input pl-9">
            </div>
            <button type="submit" class="btn-secondary">Rechercher</button>
            @if ($q)<a href="{{ route('clients.index') }}" class="btn-ghost">Effacer</a>@endif
        </form>

        @if ($clients->isEmpty())
            <x-empty-state icon="search" title="Aucun client ne correspond à « {{ $q }} »"/>
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Client</th><th>Contact</th><th>Sites</th><th>Hors ligne</th><th class="text-right"><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @foreach ($clients as $client)
                        <tr>
                            <td><a href="{{ route('clients.show', $client) }}" class="font-semibold text-night-900 hover:text-electric-600">{{ $client->name }}</a></td>
                            <td>
                                <span class="block">{{ $client->contact_name ?? '—' }}</span>
                                @if ($client->email)<span class="block text-xs text-slate-500">{{ $client->email }}</span>@endif
                            </td>
                            <td class="tabular-nums">{{ $client->sites_count }}</td>
                            <td>
                                @if ($client->down_sites_count > 0)
                                    <span class="inline-flex rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-semibold text-rose-700">{{ $client->down_sites_count }}</span>
                                @else
                                    <span class="text-slate-400">0</span>
                                @endif
                            </td>
                            <td class="text-right"><a href="{{ route('clients.show', $client) }}" class="btn-secondary btn-sm">Consulter</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $clients->links() }}
        @endif
    @endif
</div>
@endsection
