<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@hasSection('title')@yield('title') · @endif{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-50 font-sans text-slate-800 antialiased">
@php
    $navigation = [
        ['dashboard', 'Vue générale', 'home', 'dashboard', null],
        ['sites.index', 'Sites surveillés', 'globe', 'sites.*', 'sites.view'],
        ['clients.index', 'Clients', 'users', 'clients.*', 'clients.view'],
        ['incidents.index', 'Incidents', 'alert', 'incidents.*', 'incidents.view'],
        ['checks.index', 'Contrôles', 'pulse', 'checks.*', 'sites.view'],
        ['notifications.index', 'Notifications', 'bell', 'notifications.*', 'notifications.view'],
        ['subscription.show', 'Abonnement', 'card', 'subscription.*', 'subscriptions.view'],
        ['settings.index', 'Paramètres', 'cog', 'settings.*', null],
    ];
    $user = auth()->user();
    $openIncidents = \App\Models\Incident::open()->count();
@endphp

<div data-sidebar-backdrop class="fixed inset-0 z-30 hidden bg-night-950/60 lg:hidden"></div>

<aside data-sidebar id="menu-console"
       class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-night-900 transition-transform duration-200 lg:translate-x-0">
    <div class="flex h-16 items-center justify-between px-5">
        <a href="{{ route('dashboard') }}"><x-logo dark/></a>
        <button type="button" data-sidebar-toggle class="rounded-md p-1.5 text-slate-400 hover:bg-night-800 hover:text-white lg:hidden">
            <span class="sr-only">Fermer le menu</span>
            <x-icon name="x"/>
        </button>
    </div>

    <p class="truncate px-5 pb-4 text-xs font-medium text-slate-400" title="{{ $user->agency?->name }}">{{ $user->agency?->name }}</p>

    <nav class="flex-1 space-y-0.5 overflow-y-auto px-3" aria-label="Menu principal">
        @foreach ($navigation as [$route, $label, $icon, $pattern, $permission])
            @if (! $permission || $user->can($permission))
                @php $active = request()->routeIs($pattern); @endphp
                <a href="{{ route($route) }}" @if ($active) aria-current="page" @endif
                   class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition
                          {{ $active ? 'bg-electric-500 text-white shadow-sm' : 'text-slate-300 hover:bg-night-800 hover:text-white' }}">
                    <x-icon :name="$icon" class="h-5 w-5 shrink-0 {{ $active ? 'text-white' : 'text-slate-400 group-hover:text-white' }}"/>
                    <span class="flex-1">{{ $label }}</span>
                    @if ($route === 'incidents.index' && $openIncidents > 0)
                        <span class="rounded-full bg-rose-500 px-2 py-0.5 text-[11px] font-bold text-white">{{ $openIncidents }}</span>
                    @endif
                </a>
            @endif
        @endforeach
    </nav>

    <div class="border-t border-night-700 p-3">
        <div class="flex items-center gap-3 rounded-lg px-2 py-2">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-night-700 text-sm font-semibold text-white">
                {{ mb_strtoupper(mb_substr(preg_replace('/[^\pL]/u', '', $user->name) ?: '?', 0, 1)) }}
            </span>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-medium text-white">{{ $user->name }}</p>
                <p class="truncate text-xs text-slate-400">{{ $user->role?->name }}</p>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="rounded-md p-1.5 text-slate-400 hover:bg-night-800 hover:text-white" title="Se déconnecter">
                    <span class="sr-only">Se déconnecter</span>
                    <x-icon name="logout"/>
                </button>
            </form>
        </div>
    </div>
</aside>

<div class="flex min-h-full flex-col lg:pl-64">
    <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6 lg:hidden">
        <button type="button" data-sidebar-toggle aria-controls="menu-console" aria-expanded="false"
                class="rounded-md p-2 text-night-900 hover:bg-slate-100">
            <span class="sr-only">Ouvrir le menu</span>
            <x-icon name="menu" class="h-6 w-6"/>
        </button>
        <a href="{{ route('dashboard') }}"><x-logo/></a>
    </header>

    <main class="mx-auto w-full max-w-7xl flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        <x-flash/>
        @yield('content')
    </main>
</div>
</body>
</html>
