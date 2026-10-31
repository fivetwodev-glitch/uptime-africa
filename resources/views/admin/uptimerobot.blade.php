<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>UptimeRobot · Administration · {{ config('app.name') }}</title>
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
    <x-page-header title="UptimeRobot" subtitle="Compte central (API v3) partagé par toutes les agences."/>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="card">
            <div class="card-header"><h2 class="card-title">Connexion API</h2></div>
            <div class="space-y-4 p-5 text-sm sm:p-6">
                @if ($configured)
                    <p class="flex items-center gap-2 text-night-900"><x-icon name="check" class="h-4 w-4 text-emerald-600"/> Clé API configurée sur le serveur (variable <code>ZITE_UPTIMEROBOT_API_KEY</code>). Elle n'est jamais affichée.</p>
                    @if ($account)
                        <dl class="grid grid-cols-3 gap-3">
                            <div><dt class="text-slate-500">Plan</dt><dd class="font-semibold text-night-900">{{ $account['plan'] ?? '—' }}</dd></div>
                            <div><dt class="text-slate-500">Moniteurs</dt><dd class="font-semibold text-night-900 tabular-nums">{{ $account['monitorsCount'] ?? '—' }}</dd></div>
                            <div><dt class="text-slate-500">Limite</dt><dd class="font-semibold text-night-900 tabular-nums">{{ $account['monitorLimit'] ?? '—' }}</dd></div>
                        </dl>
                    @endif
                    <form method="POST" action="{{ route('admin.uptimerobot.test') }}">@csrf<button type="submit" class="btn-secondary">Tester la connexion</button></form>
                @else
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-900" role="alert">
                        <p class="font-semibold">Intégration UptimeRobot non configurée.</p>
                        <p class="mt-1">Ajoutez <code>ZITE_UPTIMEROBOT_API_KEY</code> au fichier <code>.env</code> du serveur (jamais dans le code ni dans la base), puis exécutez <code>php artisan config:clear</code>.</p>
                    </div>
                @endif
            </div>
        </section>

        <section class="card">
            <div class="card-header"><h2 class="card-title">Synchronisation</h2></div>
            <div class="space-y-4 p-5 text-sm sm:p-6">
                @if ($lastSync)
                    <p>Dernière synchronisation : <strong class="text-night-900">{{ \App\Support\Format::datetime(\Illuminate\Support\Carbon::parse($lastSync['at'])) }}</strong></p>
                    <p class="text-slate-600">{{ $lastSync['monitors'] }} moniteur(s) lus · {{ $lastSync['updated'] }} site(s) mis à jour · {{ $lastSync['missing'] }} introuvable(s)</p>
                @else
                    <p class="text-slate-600">Aucune synchronisation effectuée.</p>
                @endif
                <p class="hint">Automatique toutes les {{ config('uptime.uptimerobot.sync_every_minutes') }} minutes via le planificateur.</p>
                @if ($configured)
                    <form method="POST" action="{{ route('admin.uptimerobot.sync') }}">@csrf<button type="submit" class="btn-primary">Synchroniser maintenant</button></form>
                @endif
            </div>
        </section>
    </div>

    @if ($configured)
        <section class="card mt-6">
            <div class="card-header">
                <h2 class="card-title">Moniteurs du compte</h2>
                @if ($rows === null)<a href="{{ route('admin.uptimerobot', ['lister' => 1]) }}" class="btn-secondary btn-sm">Charger la liste</a>@endif
            </div>
            @if ($listError)
                <p class="p-5 text-sm text-rose-700">{{ $listError }}</p>
            @elseif ($rows === null)
                <p class="p-5 text-sm text-slate-600">La liste est chargée à la demande pour économiser les requêtes API.</p>
            @elseif (count($rows) === 0)
                <x-empty-state icon="globe" title="Aucun moniteur dans le compte UptimeRobot"/>
            @else
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead><tr><th>ID</th><th>Nom</th><th>URL</th><th>Statut</th><th>Intervalle</th><th>Site lié</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                        @foreach ($rows as $row)
                            <tr>
                                <td class="tabular-nums">{{ $row['monitor']['id'] ?? '—' }}</td>
                                <td>{{ $row['monitor']['friendlyName'] ?? '—' }}</td>
                                <td class="max-w-xs truncate">{{ $row['monitor']['url'] ?? '—' }}</td>
                                <td>{{ $row['monitor']['status'] ?? '—' }}</td>
                                <td class="tabular-nums">{{ isset($row['monitor']['interval']) ? intdiv((int) $row['monitor']['interval'], 60).' min' : '—' }}</td>
                                <td>@if ($row['site']){{ $row['site']->name }} <span class="block text-xs text-slate-500">{{ $row['site']->agency?->name }}</span>@else<span class="text-slate-400">Aucun</span>@endif</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif
</main>
</body>
</html>
