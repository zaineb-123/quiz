<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen antialiased" style="font-family:'Lexend',sans-serif;">
        <div class="aurora relative flex min-h-screen flex-col items-center justify-center overflow-hidden px-6 py-10 text-center">
            <div class="grid-fade absolute inset-0"></div>

            <a href="{{ url('/') }}" class="relative z-10 mb-12 flex items-center gap-3">
                <x-app-logo-icon class="size-9" />
                <span class="text-lg font-bold text-white">{{ config('app.name') }}</span>
            </a>

            <div class="relative z-10 float-a">
                <x-ring :pct="72" :size="200" :stroke="14"><span class="err-code">@yield('code')</span></x-ring>
            </div>

            <h1 class="font-display relative z-10 mt-10 text-4xl md:text-5xl" style="color:#fff;font-weight:400">@yield('title')</h1>
            <p class="relative z-10 mt-4 max-w-md text-base leading-relaxed" style="color:#9fc3bd">@yield('message')</p>

            <div class="relative z-10 mt-9 flex flex-col items-center gap-4">
                <a href="{{ url('/') }}" class="btn-teal" style="padding:.9rem 1.8rem;font-weight:600">Go home</a>
                @hasSection('extra-action')
                    <div style="color:#7ff0dc;font-size:.9rem">@yield('extra-action')</div>
                @endif
            </div>
        </div>
        @fluxScripts
    </body>
</html>
