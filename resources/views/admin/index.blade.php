@extends('layouts.admin')

@section('title', 'Agences')

@section('content')
    <x-page-header title="Agences" :subtitle="$agencies->total().' agence(s) inscrite(s)'"/>

    <div class="mb-6 card flex items-center gap-3 p-4 text-sm">
        <x-icon name="shield" class="h-5 w-5 text-electric-600"/>
        <span>Dernier battement du planificateur :
            <strong class="text-night-900">{{ $lastHeartbeat ? \App\Support\Format::datetime(\Illuminate\Support\Carbon::parse($lastHeartbeat)) : 'jamais reçu' }}</strong>
        </span>
    </div>

    <div class="card">
        @if ($agencies->isEmpty())
            <x-empty-state icon="users" title="Aucune agence inscrite"/>
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Agence</th><th>Statut</th><th>Abonnement</th><th>Utilisateurs</th><th>Clients</th><th>Sites</th><th>Inscrite le</th><th class="text-right"><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @foreach ($agencies as $agency)
                        <tr>
                            <td>
                                <span class="font-semibold text-night-900">{{ $agency->name }}</span>
                                @if ($agency->is_test_data)<span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[11px] font-semibold text-amber-800">TEST</span>@endif
                                <span class="block text-xs text-slate-500">{{ $agency->contact_email ?? $agency->slug }}</span>
                            </td>
                            <td>{{ $agency->is_active ? 'Active' : 'Suspendue' }}</td>
                            <td>
                                @if ($agency->currentSubscription)
                                    <a href="{{ route('admin.subscriptions.show', $agency->currentSubscription) }}" class="link">{{ $agency->currentSubscription->plan->name }}</a>
                                    <span class="block text-xs text-slate-500">{{ $agency->currentSubscription->status->label() }}</span>
                                @else
                                    <span class="text-slate-400">Aucun</span>
                                @endif
                            </td>
                            <td class="tabular-nums">{{ $agency->users_count }}</td>
                            <td class="tabular-nums">{{ $agency->clients_count }}</td>
                            <td class="tabular-nums">{{ $agency->sites_count }}</td>
                            <td class="whitespace-nowrap text-slate-500">{{ \App\Support\Format::datetime($agency->created_at) }}</td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('admin.agencies.toggle', $agency) }}"
                                      data-confirm="{{ $agency->is_active ? 'Suspendre' : 'Réactiver' }} l'agence {{ $agency->name }} ?{{ $agency->is_active ? ' Tous ses utilisateurs seront déconnectés.' : '' }}">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="{{ $agency->is_active ? 'btn-danger' : 'btn-secondary' }} btn-sm">{{ $agency->is_active ? 'Suspendre' : 'Réactiver' }}</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $agencies->links() }}
        @endif
    </div>
@endsection
