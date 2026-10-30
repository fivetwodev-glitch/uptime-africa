<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-50 font-sans text-slate-800 antialiased">
<div class="grid min-h-full lg:grid-cols-2">
    <div class="relative hidden overflow-hidden bg-night-900 p-12 lg:flex lg:flex-col lg:justify-between">
        <div class="pointer-events-none absolute -top-40 -right-40 h-[28rem] w-[28rem] rounded-full bg-electric-500/20 blur-3xl"></div>
        <a href="{{ route('home') }}" class="relative"><x-logo dark/></a>
        <div class="relative">
            <h2 class="max-w-md text-3xl leading-tight font-bold text-white">La disponibilité des sites de vos clients, sous contrôle.</h2>
            <p class="mt-4 max-w-md text-slate-300">Contrôles réguliers, incidents détaillés et alertes par email et WhatsApp, pour les agences d'Afrique francophone.</p>
        </div>
        <p class="relative text-xs text-slate-500">© {{ date('Y') }} Uptime Africa</p>
    </div>
    <div class="flex items-center justify-center px-4 py-12 sm:px-8">
        <div class="w-full max-w-md">
            <a href="{{ route('home') }}" class="mb-8 inline-block lg:hidden"><x-logo/></a>
            @yield('content')
        </div>
    </div>
</div>
</body>
</html>
