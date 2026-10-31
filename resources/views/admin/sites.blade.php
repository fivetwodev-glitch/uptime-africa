@extends('layouts.admin')

@section('title', 'Sites')

@section('content')
<x-page-header title="Sites surveillés" :subtitle="$sites->total().' site(s), toutes agences'"/>
@include('admin._filters', ['search' => 'Nom ou URL'])

<div class="card">
    @if ($sites->isEmpty())
        <x-empty-state icon="globe" title="Aucun site"/>
    @else
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Site</th><th>Agence</th><th>Client</th><th>État</th><th>Surveillance</th><th>Fournisseur</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                @foreach ($sites as $site)
                    <tr>
                        <td><span class="font-semibold text-night-900">{{ $site->name }}</span><span class="block max-w-xs truncate text-xs text-slate-500">{{ $site->url }}</span></td>
                        <td>{{ $site->agency->name }}</td>
                        <td>{{ $site->client?->name }}</td>
                        <td><x-site-status :status="$site->status"/></td>
                        <td>{{ $site->monitoring_status->label() }}</td>
                        <td>{{ $site->monitoring_provider->label() }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $sites->links() }}
    @endif
</div>
@endsection
