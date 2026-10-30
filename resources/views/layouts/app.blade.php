<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@hasSection('title')@yield('title') · @endif{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-50 font-sans text-slate-900 antialiased">
<div class="flex min-h-full flex-col">
    <header class="border-b border-slate-200 bg-white">
        <nav class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3 sm:px-6" aria-label="Navigation principale">
            <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold text-brand-900">
                <span class="inline-block h-3 w-3 rounded-full bg-brand-600" aria-hidden="true"></span>
                {{ config('app.name') }}
            </a>

            <button type="button" class="rounded-md p-2 text-slate-600 hover:bg-slate-100 sm:hidden"
                    data-menu-toggle aria-expanded="false" aria-controls="menu-principal">
                <span class="sr-only">Ouvrir le menu</span>
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            <ul id="menu-principal" data-menu
                class="absolute inset-x-0 top-14 hidden flex-col gap-1 border-b border-slate-200 bg-white px-4 py-3 sm:static sm:flex sm:flex-row sm:gap-6 sm:border-0 sm:p-0">
                <li><a href="{{ route('home') }}#fonctionnalites" class="block py-2 text-sm text-slate-700 hover:text-brand-700">Fonctionnalités</a></li>
                <li><a href="{{ route('home') }}#agences" class="block py-2 text-sm text-slate-700 hover:text-brand-700">Pour les agences</a></li>
            </ul>
        </nav>
    </header>

    <main class="flex-1">
        @yield('content')
    </main>

    <footer class="border-t border-slate-200 bg-white">
        <div class="mx-auto max-w-6xl px-4 py-6 text-sm text-slate-500 sm:px-6">
            © {{ date('Y') }} {{ config('app.name') }} · Surveillance de sites web pour l'Afrique francophone
        </div>
    </footer>
</div>
</body>
</html>
