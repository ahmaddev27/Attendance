@props(['class' => 'h-10 w-auto'])

<svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 300 90" xmlns="http://www.w3.org/2000/svg" aria-label="TAQAT" role="img">
    {{-- T --}}
    <g fill="var(--brand)">
        <rect x="8" y="18" width="52" height="14" rx="2"/>
        <rect x="27" y="18" width="14" height="56" rx="2"/>
    </g>
    {{-- A --}}
    <g fill="var(--brand)" transform="translate(66,0)">
        <polygon points="0,74 22,18 32,18 54,74 42,74 27,34 12,74"/>
        <rect x="15" y="52" width="24" height="10" rx="1"/>
    </g>
    {{-- Sun (Q) --}}
    <g transform="translate(150,46)">
        {{-- 8 triangular rays --}}
        <g fill="var(--accent)">
            <polygon points="-3,-30 3,-30 0,-20"/>
            <polygon points="-3,30 3,30 0,20"/>
            <polygon points="-30,-3 -30,3 -20,0"/>
            <polygon points="30,-3 30,3 20,0"/>
            <polygon points="-3,-30 3,-30 0,-20" transform="rotate(45)"/>
            <polygon points="-3,-30 3,-30 0,-20" transform="rotate(135)"/>
            <polygon points="-3,-30 3,-30 0,-20" transform="rotate(225)"/>
            <polygon points="-3,-30 3,-30 0,-20" transform="rotate(315)"/>
        </g>
        {{-- Outer blue disc --}}
        <circle r="14" fill="var(--brand)"/>
        {{-- White ring --}}
        <circle r="8" fill="white"/>
        {{-- Blue center dot --}}
        <circle r="4" fill="var(--brand)"/>
    </g>
    {{-- A --}}
    <g fill="var(--brand)" transform="translate(180,0)">
        <polygon points="0,74 22,18 32,18 54,74 42,74 27,34 12,74"/>
        <rect x="15" y="52" width="24" height="10" rx="1"/>
    </g>
    {{-- T --}}
    <g fill="var(--brand)" transform="translate(240,0)">
        <rect x="8" y="18" width="52" height="14" rx="2"/>
        <rect x="27" y="18" width="14" height="56" rx="2"/>
    </g>
</svg>
