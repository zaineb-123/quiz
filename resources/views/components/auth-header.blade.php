@props([
    'title',
    'description',
])

<div class="flex w-full flex-col text-center">
    <h1 class="font-display text-3xl text-charcoal-900 md:text-4xl">{{ $title }}</h1>
    <flux:subheading>{{ $description }}</flux:subheading>
</div>
