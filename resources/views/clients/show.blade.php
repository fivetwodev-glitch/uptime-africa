@extends('layouts.console')

@php use App\Support\Format; @endphp

@section('title', $client->name)

@section('content')
<x-page-header :title="$client->name" :subtitle="$sites->count().' site(s) surveillé(s)'">
    <x-slot:back>
        <a href="{{ route('clients.index') }}" class="mb-2 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-night-900">
            <x-icon name="arrow-left" class="h-4 w-4"/> Clients
        </a>
    </x-slot:back>
    @can('sites.manage')
        <a href="{{ route('sites.create', ['client' => $client->id]) }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4"/> Ajouter un site</a>
    @endcan
    @can('clients.manage')
        <a href="{{ route('clients.edit', $client) }}" class="btn-secondary"><x-icon name="pencil" class="h-4 w-4"/> Modifier</a>
        <form method="POST" action="{{ route('clients.destroy', $client) }}"
              data-confirm="Supprimer le client « {{ $client->name }} »{{ $sites->isNotEmpty() ? ' et ses '.$sites->count().' site(s) avec tout leur historique' : '' }} ? Cette action est définitive.">
            @csrf @method('DELETE')
            <button type="submit" class="btn-danger"><x-icon name="trash" class="h-4 w-4"/> Supprimer</button>
        </form>
    @endcan
</x-page-header>

<div class="grid gap-6 lg:grid-cols-3">
    <section class="card lg:col-span-1">
        <div class="card-header"><h2 class="card-title">Coordonnées</h2></div>
        <dl class="space-y-4 p-5 text-sm">
            <div><dt class="text-slate-500">Contact</dt><dd class="font-medium text-night-900">{{ $client->contact_name ?? '—' }}</dd></div>
            <div><dt class="text-slate-500">Email</dt><dd class="font-medium">@if ($client->email)<a href="mailto:{{ $client->email }}" class="link">{{ $client->email }}</a>@else — @endif</dd></div>
            <div><dt class="text-slate-500">Téléphone</dt><dd class="font-medium">@if ($client->phone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $client->phone) }}" class="link">{{ $client->phone }}</a>@else — @endif</dd></div>
            @if ($client->notes)
                <div><dt class="text-slate-500">Notes</dt><dd class="whitespace-pre-line text-night-900">{{ $client->notes }}</dd></div>
            @endif
        </dl>
    </section>

    <section class="card lg:col-span-2">
        <div class="card-header"><h2 class="card-title">Sites</h2></div>
        @if ($sites->isEmpty())
            <x-empty-state icon="globe" title="Aucun site pour ce client">
                @can('sites.manage')
                    <x-slot:action><a href="{{ route('sites.create', ['client' => $client->id]) }}" class="btn-primary"><x-icon name="plus" class="h-4 w-4"/> Ajouter un site</a></x-slot:action>
                @endcan
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Site</th><th>Statut</th><th>Temps de réponse</th><th>Dernière vérification</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @foreach ($sites as $site)
                        <tr>
                            <td class="max-w-xs">
                                <a href="{{ route('sites.show', $site) }}" class="block truncate font-semibold text-night-900 hover:text-electric-600">{{ $site->name }}</a>
                                <span class="block truncate text-xs text-slate-500">{{ $site->url }}</span>
                            </td>
                            <td><x-site-status :status="$site->status" :monitoring="$site->monitoring_status"/></td>
                            <td class="tabular-nums">{{ Format::ms($site->last_response_time_ms) }}</td>
                            <td class="whitespace-nowrap text-slate-500">{{ Format::ago($site->last_checked_at) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
@endsection
