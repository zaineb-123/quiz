{{-- Stat tile: <x-stat label="Submissions">24</x-stat>  (value-class optional, e.g. score-pass) --}}
@props(['label', 'valueClass' => 'text-charcoal-900'])
<div {{ $attributes->merge(['class' => 'stat-tile']) }}>
    <div class="font-display text-4xl leading-none {{ $valueClass }}">{{ $slot }}</div>
    <div class="mt-2 text-[13px]" style="color:#5d7270">{{ $label }}</div>
</div>
