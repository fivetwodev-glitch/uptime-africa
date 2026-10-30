<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@hasSection('title')@yield('title') · @endif{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-white font-sans text-slate-800 antialiased">
<div class="flex min-h-full flex-col">
    <header class="bg-night-900">
        <nav class="relative mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6" aria-label="Navigation principale">
            <a href="{{ route('home') }}"><x-logo dark/></a>

            <button type="button" class="rounded-md p-2 text-slate-300 hover:bg-night-800 sm:hidden"
                    data-menu-toggle aria-expanded="false" aria-controls="menu-principal">
                <span class="sr-only">Ouvrir le menu</span>
                <x-icon name="menu" class="h-6 w-6"/>
            </button>

            <ul id="menu-principal" data-menu
                class="absolute inset-x-0 top-full z-20 hidden flex-col gap-1 bg-night-900 px-4 pb-4 sm:static sm:flex sm:flex-row sm:items-center sm:gap-6 sm:p-0">
                <li><a href="{{ route('home') }}#fonctionnalites" class="block py-2 text-sm text-slate-300 hover:text-white">Fonctionnalités</a></li>
                <li><a href="{{ route('home') }}#agences" class="block py-2 text-sm text-slate-300 hover:text-white">Pour les agences</a></li>
                @auth
                    <li><a href="{{ route('dashboard') }}" class="btn-primary mt-2 sm:mt-0">Mon tableau de bord</a></li>
                @else
                    <li><a href="{{ route('login') }}" class="block py-2 text-sm font-semibold text-white">Connexion</a></li>
                    <li><a href="{{ route('register') }}" class="btn-primary mt-2 sm:mt-0">Créer un compte</a></li>
                @endauth
            </ul>
        </nav>
    </header>

    <main class="flex-1">
        @yield('content')
    </main>

    <footer class="border-t border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-col gap-2 px-4 py-6 text-sm text-slate-500 sm:flex-row sm:justify-between sm:px-6">
            <span>© {{ date('Y') }} Uptime Africa</span>
            <span>Surveillance de sites web pour l'Afrique francophone</span>
        </div>
    </footer>
</div>
</body>
</html>
