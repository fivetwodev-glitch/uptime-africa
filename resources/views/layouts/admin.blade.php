<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@hasSection('title')@yield('title') · @endif Administration · {{ config('app.name') }}</title>
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
<nav class="mx-auto flex max-w-7xl flex-wrap gap-1 px-4 pt-6 sm:px-6" aria-label="Administration">
    @foreach ([
        ['admin.stats', 'Statistiques', 'admin.stats'],
        ['admin.index', 'Agences', 'admin.index'],
        ['admin.users', 'Utilisateurs', 'admin.users*'],
        ['admin.sites', 'Sites', 'admin.sites'],
        ['admin.incidents', 'Incidents', 'admin.incidents'],
        ['admin.plans', 'Offres', 'admin.plans*'],
        ['admin.subscriptions', 'Abonnements', 'admin.subscriptions*'],
        ['admin.audit', 'Journal', 'admin.audit'],
        ['admin.uptimerobot', 'UptimeRobot', 'admin.uptimerobot*'],
    ] as [$route, $label, $pattern])
        <a href="{{ route($route) }}" @if (request()->routeIs($pattern)) aria-current="page" @endif
           class="rounded-lg px-3 py-2 text-sm font-semibold {{ request()->routeIs($pattern) ? 'bg-white text-electric-600 shadow-sm' : 'text-slate-500 hover:text-night-900' }}">{{ $label }}</a>
    @endforeach
</nav>
<main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
    <x-flash/>
    @yield('content')
</main>
</body>
</html>
