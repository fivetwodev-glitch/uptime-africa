<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Administration · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-slate-50 font-sans text-slate-800 antialiased">
<header class="bg-night-900">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
        <div class="flex items-center gap-3"><x-logo dark/><span class="rounded-full bg-electric-500 px-2 py-0.5 text-xs font-semibold text-white">Administration</span></div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn text-slate-300 hover:bg-night-800 hover:text-white"><x-icon name="logout" class="h-4 w-4"/> Déconnexion</button>
        </form>
    </div>
</header>
<nav class="mx-auto flex max-w-7xl gap-1 px-4 pt-6 sm:px-6" aria-label="Administration">
    @foreach ([['admin.index', 'Agences'], ['admin.uptimerobot', 'UptimeRobot']] as [$route, $label])
        <a href="{{ route($route) }}" @if (request()->routeIs($route)) aria-current="page" @endif
           class="rounded-lg px-3 py-2 text-sm font-semibold {{ request()->routeIs($route) ? 'bg-white text-electric-600 shadow-sm' : 'text-slate-500 hover:text-night-900' }}">{{ $label }}</a>
    @endforeach
</nav>
<main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
    <x-flash/>
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
                    <thead><tr><th>Agence</th><th>Statut</th><th>Utilisateurs</th><th>Clients</th><th>Sites</th><th>Inscrite le</th><th class="text-right"><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                    @foreach ($agencies as $agency)
                        <tr>
                            <td>
                                <span class="font-semibold text-night-900">{{ $agency->name }}</span>
                                @if ($agency->is_test_data)<span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 text-[11px] font-semibold text-amber-800">TEST</span>@endif
                                <span class="block text-xs text-slate-500">{{ $agency->contact_email ?? $agency->slug }}</span>
                            </td>
                            <td>{{ $agency->is_active ? 'Active' : 'Suspendue' }}</td>
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
</main>
</body>
</html>
