<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen antialiased" style="font-family:'Lexend',sans-serif;">

        <div style="display:flex; min-height:100vh;">

            {{-- ── Left: ink brand panel (desktop only) ── --}}
            <div class="aurora hidden lg:flex relative overflow-hidden" style="width:46%; max-width:640px; flex-shrink:0; flex-direction:column; justify-content:space-between; padding:3rem; color:#d9f3ef;">
                <div class="grid-fade absolute inset-0"></div>

                <a href="{{ route('home') }}" class="relative z-10 flex items-center gap-3" wire:navigate>
                    <x-app-logo-icon class="size-10" />
                    <span style="font-size:1.25rem; font-weight:700; letter-spacing:-0.02em; color:#fff;">{{ config('app.name') }}</span>
                </a>

                <div class="relative z-10 flex flex-col gap-10">
                    <div class="float-a self-start">
                        <x-ring :pct="86" :size="150" :stroke="13">
                            <div class="font-display" style="font-size:2.8rem; line-height:1; color:#fff;">86<span style="font-size:1.2rem; color:#8fb3ad;">%</span></div>
                        </x-ring>
                    </div>
                    <blockquote>
                        <p class="font-display" style="font-size:2rem; line-height:1.25; color:#fff; font-weight:400;">
                            Testing yourself is the single most effective study strategy known to science.
                        </p>
                        <footer style="margin-top:.9rem; font-size:.8125rem; color:#7fa9a3;">Roediger &amp; Butler, 2011</footer>
                    </blockquote>
                    <ul style="display:flex; flex-direction:column; gap:.75rem; list-style:none; padding:0; margin:0; font-size:.9rem; color:#a9cbc6;">
                        <li class="flex items-center gap-3"><flux:icon.check-circle class="size-5" style="color:#5eead4" /> Multiple choice, true/false and short answer</li>
                        <li class="flex items-center gap-3"><flux:icon.check-circle class="size-5" style="color:#5eead4" /> Instant grading with feedback that explains why</li>
                        <li class="flex items-center gap-3"><flux:icon.check-circle class="size-5" style="color:#5eead4" /> Optional time limits that feel like the real thing</li>
                    </ul>
                </div>

                <p class="relative z-10" style="font-size:.75rem; color:#5f8680;">© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
            </div>

            {{-- ── Right: form panel ── --}}
            <div style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:2rem 1.5rem;">
                <a href="{{ route('home') }}" class="mb-8 flex items-center gap-2 lg:hidden" wire:navigate>
                    <x-app-logo-icon class="size-8" />
                    <span style="font-size:1.125rem; font-weight:700; color:#1F2937;">{{ config('app.name') }}</span>
                </a>

                <div class="qf-page" style="width:100%; max-width:24rem;">
                    {{ $slot }}
                </div>
            </div>
        </div>

        @fluxScripts
    </body>
</html>
