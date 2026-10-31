<nav class="mb-6 flex gap-1 overflow-x-auto border-b border-slate-200" aria-label="Sections des paramètres">
    @foreach ([['settings.index', 'Profil et agence', 'settings.index'], ['settings.security', 'Sécurité', 'settings.security'], ['team.index', 'Équipe', 'team.*']] as [$route, $label, $pattern])
        @php $active = request()->routeIs($pattern); @endphp
        <a href="{{ route($route) }}" @if ($active) aria-current="page" @endif
           class="-mb-px border-b-2 px-4 py-2.5 text-sm font-semibold whitespace-nowrap {{ $active ? 'border-electric-500 text-electric-600' : 'border-transparent text-slate-500 hover:text-night-900' }}">{{ $label }}</a>
    @endforeach
</nav>
