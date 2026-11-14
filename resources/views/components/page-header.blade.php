{{-- Page header: round back button, serif title, optional subtitle, actions in the slot.
     <x-page-header title="My Exams" subtitle="…" :back="route('dashboard')"> <flux:button>…</flux:button> </x-page-header> --}}
@props(['title', 'subtitle' => null, 'back' => null])
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-end justify-between gap-4']) }}>
    <div class="flex min-w-0 items-center gap-4">
        @if ($back)
            <a href="{{ $back }}" wire:navigate aria-label="Back" class="back-btn"><flux:icon.arrow-left class="size-5" /></a>
        @endif
        <div class="min-w-0">
            <h1 class="font-display text-3xl text-charcoal-900 md:text-4xl">{{ $title }}</h1>
            @if ($subtitle)<p class="mt-1.5 text-[15px]" style="color:#5d7270">{{ $subtitle }}</p>@endif
        </div>
    </div>
    @if (! $slot->isEmpty())<div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>@endif
</div>
