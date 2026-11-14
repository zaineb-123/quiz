{{-- Number that counts up from 0 on load. <x-count-up :to="86" suffix="%" /> --}}
@props(['to' => 0, 'suffix' => '', 'duration' => 1100])
<span x-data="{ n: 0 }"
      x-init="const t = {{ (int) $to }}, d = {{ (int) $duration }}, s = performance.now();
              if (matchMedia('(prefers-reduced-motion: reduce)').matches) { n = t; return; }
              const f = (now) => { const p = Math.min(1, (now - s) / d); n = Math.round(t * (1 - Math.pow(1 - p, 3))); if (p < 1) requestAnimationFrame(f); };
              requestAnimationFrame(f);"
      {{ $attributes }}><span x-text="n">{{ (int) $to }}</span>{{ $suffix }}</span>
