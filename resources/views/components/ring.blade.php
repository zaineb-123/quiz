{{-- Ember ring — the QuizForge signature. Animates from empty to $pct on load.
     Usage: <x-ring :pct="86" :size="180" :stroke="14">inner content</x-ring> --}}
@props(['pct' => 0, 'size' => 120, 'stroke' => 10, 'track' => 'rgba(255,255,255,.12)', 'glow' => true])
@php
    $r = ($size - $stroke) / 2;
    $c = round(2 * M_PI * $r, 2);
    $o = round($c * (1 - max(0, min(100, $pct)) / 100), 2);
    $gid = 'ember-'.$size.'-'.$stroke;
@endphp
<div {{ $attributes->merge(['class' => 'relative shrink-0']) }} style="width:{{ $size }}px;height:{{ $size }}px">
    <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 {{ $size }} {{ $size }}"
         style="transform:rotate(-90deg);{{ $glow ? 'filter:drop-shadow(0 0 14px rgba(94,234,212,.45));' : '' }}">
        <defs>
            <linearGradient id="{{ $gid }}" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="#5eead4"/><stop offset=".6" stop-color="#fbbf24"/><stop offset="1" stop-color="#ff9494"/>
            </linearGradient>
        </defs>
        <circle cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ $r }}" fill="none" stroke="{{ $track }}" stroke-width="{{ $stroke }}"/>
        <circle class="ring-fill" cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ $r }}" fill="none"
                stroke="url(#{{ $gid }})" stroke-width="{{ $stroke }}" stroke-linecap="round"
                style="--c:{{ $c }};--o:{{ $o }}"/>
    </svg>
    <div class="absolute inset-0 grid place-items-center text-center">{{ $slot }}</div>
</div>
