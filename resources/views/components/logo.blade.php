@props(['dark' => false])
{{-- Logo original : pulsation stylisée dans un hexagone. --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <svg class="h-8 w-8 shrink-0" viewBox="0 0 32 32" aria-hidden="true">
        <path d="M16 2.5 28 9.25v13.5L16 29.5 4 22.75V9.25z" fill="#2f6bff"/>
        <path d="M8 17h4.2l2.3-5.5 3.2 9 2.3-5.5H24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    <span class="text-[15px] leading-none font-extrabold tracking-[0.08em] {{ $dark ? 'text-white' : 'text-night-900' }}">
        UPTIME <span class="{{ $dark ? 'text-electric-400' : 'text-electric-500' }}">AFRICA</span>
    </span>
</span>
