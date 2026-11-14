<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}: exams that teach while they test</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lexend:wght@300;400;500;600;700&family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body style="font-family:'Lexend',sans-serif; -webkit-font-smoothing:antialiased;">

{{-- ═══ Hero (dark) ═══ --}}
<section class="aurora relative overflow-hidden" style="color:#d9f3ef">
    <div class="grid-fade absolute inset-0"></div>

    <header class="relative z-10 mx-auto flex max-w-7xl items-center justify-between px-6 py-6 md:px-10">
        <a href="{{ route('home') }}" class="flex items-center gap-3">
            <x-app-logo-icon class="size-9" />
            <span class="text-lg font-bold tracking-tight text-white">{{ config('app.name') }}</span>
        </a>
        <nav class="hidden items-center gap-9 text-[14.5px] md:flex" style="color:#a9cbc6">
            <a href="#how" class="transition hover:text-white">How it works</a>
            <a href="#features" class="transition hover:text-white">Features</a>
        </nav>
        @if (Route::has('login'))
            <div class="flex items-center gap-2">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn-teal" style="padding:.65rem 1.4rem;font-size:.875rem;font-weight:600">Open dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="rounded-full px-4 py-2 text-sm transition hover:text-white" style="color:#a9cbc6">Log in</a>
                    @if (Route::has('register'))<a href="{{ route('register') }}" class="btn-teal" style="padding:.65rem 1.4rem;font-size:.875rem;font-weight:600">Start free</a>@endif
                @endauth
            </div>
        @endif
    </header>

    <div class="relative z-10 mx-auto grid max-w-7xl items-center gap-14 px-6 pb-24 pt-10 md:px-10 lg:grid-cols-2 lg:pb-32 lg:pt-16">
        <div>
            <h1 class="font-display text-5xl leading-[1.03] text-white sm:text-6xl lg:text-[4.9rem]" style="color:#f1fffc;font-weight:400">Exams that teach while they test.</h1>
            <p class="mt-7 max-w-lg text-lg leading-relaxed" style="color:#9fc3bd">Build a full exam from a single topic in seconds. Students get instant grading, hints when they’re stuck, and feedback that explains why.</p>
            <div class="mt-9 flex flex-wrap gap-3">
                @guest
                    <a href="{{ route('register') }}" class="btn-teal" style="padding:1rem 1.9rem;font-size:1rem;font-weight:600">Create your first exam</a>
                    <a href="{{ route('login') }}" class="inline-flex items-center rounded-full px-7 py-4 font-semibold transition hover:bg-white/10" style="border:1px solid rgba(255,255,255,.22);color:#e8fffb">Log in</a>
                @else
                    <a href="{{ route('dashboard') }}" class="btn-teal" style="padding:1rem 1.9rem;font-size:1rem;font-weight:600">Go to dashboard</a>
                @endguest
            </div>
        </div>

        {{-- Live, clickable quiz card: the product is the pitch --}}
        <div class="relative mx-auto w-full max-w-[34rem] pb-24 lg:pb-0"
             x-data="{ sel: null, right: 2, opts: ['Nucleus', 'Ribosome', 'Mitochondrion', 'Golgi apparatus'], secs: 760,
                       init() { setInterval(() => { if (this.secs > 0) this.secs-- }, 1000) } }">
            <div class="glass float-a p-7 sm:p-8" style="transform:rotate(-2deg);box-shadow:0 50px 90px -30px rgba(0,0,0,.65)">
                <div class="mb-5 flex items-center justify-between">
                    <div><div class="text-[13px]" style="color:#7fa9a3">Biology, chapter 4</div><div class="font-semibold text-white">Cell structure</div></div>
                    <x-ring :pct="62" :size="58" :stroke="6"><span class="text-xs font-semibold text-white tabular-nums" x-text="`${Math.floor(secs/60)}:${String(secs%60).padStart(2,'0')}`">12:40</span></x-ring>
                </div>
                <div class="flex gap-1.5"><template x-for="n in 8"><div class="h-1.5 flex-1 rounded-full" :style="n <= 5 ? 'background:linear-gradient(90deg,#5eead4,#2dd4bf)' : (n === 6 ? 'background:#fbbf24;box-shadow:0 0 12px rgba(251,191,36,.7)' : 'background:rgba(255,255,255,.12)')"></div></template></div>
                <p class="font-display my-6 text-[1.7rem] leading-snug" style="color:#fff">Which organelle produces most of a cell’s ATP?</p>
                <div class="space-y-2.5">
                    <template x-for="(o, k) in opts" :key="k">
                        <button type="button" @click="sel = k" class="flex w-full items-center gap-3.5 rounded-2xl px-3.5 text-left text-[15.5px] transition-all duration-200"
                            style="height:56px" :style="sel === k
                                ? (k === right ? 'border:1px solid #5eead4;background:rgba(94,234,212,.13);box-shadow:0 0 0 4px rgba(94,234,212,.10);color:#fff' : 'border:1px solid #ff9494;background:rgba(255,148,148,.12);color:#fff')
                                : 'border:1px solid rgba(255,255,255,.13);background:rgba(255,255,255,.04);color:#e3f5f2'">
                            <span class="grid size-[34px] shrink-0 place-items-center rounded-[11px] text-sm font-semibold" x-text="'ABCD'[k]"
                                :style="sel === k ? (k === right ? 'background:#5eead4;color:#042220' : 'background:#ff9494;color:#3a0d0d') : 'background:rgba(255,255,255,.08);color:#a9c9c4'"></span>
                            <span x-text="o"></span>
                        </button>
                    </template>
                </div>
                <p class="mt-4 text-sm" style="color:#7fa9a3" x-show="sel === null">Try it. Pick an answer.</p>
                <p class="mt-4 text-sm font-medium" x-cloak x-show="sel !== null" x-text="sel === right ? 'Correct. Mitochondria make most of the cell’s ATP.' : 'Not quite. Where do cells turn glucose into energy?'" :style="sel === right ? 'color:#7ff0dc' : 'color:#ffb4b4'"></p>
            </div>
            <div class="float-b absolute -bottom-2 left-0 w-72 rounded-3xl p-5 sm:-left-10 lg:-bottom-12" style="background:rgba(255,148,148,.14);border:1px solid rgba(255,148,148,.4);backdrop-filter:blur(16px);transform:rotate(2deg);box-shadow:0 30px 60px -24px rgba(0,0,0,.6)">
                <div class="flex items-center gap-2 text-[13px] font-semibold" style="color:#ffb4b4"><flux:icon.sparkles class="size-4" /> AI hint</div>
                <p class="mt-2 text-[14.5px] leading-relaxed" style="color:#ffe3e1">Think about where cells turn glucose into usable energy.</p>
            </div>
            <div class="glass float-a absolute -bottom-6 right-0 flex items-center gap-3 p-3 pr-5 sm:-right-4" style="border-radius:24px;transform:rotate(-1deg)">
                <x-ring :pct="92" :size="56" :stroke="7"><b class="font-display text-lg" style="color:#fff">92</b></x-ring>
                <div><div class="text-xs" style="color:#7fa9a3">Instant result</div><div class="text-[15px] font-semibold text-white">Passed with honours</div></div>
            </div>
        </div>
    </div>
</section>

{{-- ═══ How it works (a real sequence, so it is numbered) ═══ --}}
<section id="how" class="mx-auto max-w-6xl px-6 py-24 md:px-10">
    <h2 class="font-display max-w-2xl text-4xl text-charcoal-900 md:text-5xl">From a blank page to graded results in three steps.</h2>
    <div class="mt-14 grid gap-6 md:grid-cols-3">
        @foreach ([
            ['Write or generate', 'Type your questions, or give the AI a topic and get a full draft exam to edit.', '#2dd4bf,#0d9488'],
            ['Share with a class', 'Students join with a code and see only the exams you assign, inside the window you set.', '#ffb4a8,#ff8f8f'],
            ['Learn from results', 'Scores, explanations and a personal study plan arrive the moment an exam is submitted.', '#fcd34d,#f59e0b'],
        ] as $i => [$t, $d, $g])
            <div class="bento-card !p-0 overflow-hidden">
                <div class="cover cover-{{ $i }}" style="background:linear-gradient(135deg,{{ $g }})"><span class="font-display absolute bottom-3 left-5 text-4xl" style="color:rgba(255,255,255,.95)">{{ $i + 1 }}</span></div>
                <div class="p-7"><h3 class="font-display text-2xl text-charcoal-900">{{ $t }}</h3><p class="mt-2 leading-relaxed" style="color:#5d7270">{{ $d }}</p></div>
            </div>
        @endforeach
    </div>
</section>

{{-- ═══ Features ═══ --}}
<section id="features" class="mx-auto max-w-6xl px-6 pb-24 md:px-10">
    <div class="grid gap-5 md:grid-cols-6">
        <div class="hero-ink p-9 md:col-span-4">
            <div class="relative z-10"><h3 class="font-display text-3xl md:text-4xl" style="color:#fff">Hints that guide, never give the answer away.</h3>
            <p class="mt-3 max-w-lg leading-relaxed" style="color:#9fc3bd">Short-answer questions get an AI hint that streams in as the student types, and AI grading that scores meaning, not just exact words.</p></div>
        </div>
        <div class="bento-flat md:col-span-2 flex flex-col justify-between"><x-ring :pct="86" :size="120" :stroke="11" track="#e8f0ee" :glow="false"><b class="font-display text-3xl text-charcoal-900">86%</b></x-ring><p class="mt-5 font-semibold text-charcoal-900">Instant grading and per-question feedback</p></div>
        <div class="bento-flat md:col-span-2"><div class="icon-teal mb-4"><flux:icon.user-group class="size-5" /></div><p class="font-semibold text-charcoal-900">Classrooms with join codes</p><p class="mt-1 text-sm" style="color:#5d7270">Subjects, assigned exams and per-student progress.</p></div>
        <div class="bento-flat md:col-span-2"><div class="icon-sunny mb-4"><flux:icon.clock class="size-5" /></div><p class="font-semibold text-charcoal-900">Server-enforced timers</p><p class="mt-1 text-sm" style="color:#5d7270">A real clock that a refresh or a tab trick can’t reset.</p></div>
        <div class="bento-flat md:col-span-2"><div class="icon-coral mb-4"><flux:icon.trophy class="size-5" /></div><p class="font-semibold text-charcoal-900">Leaderboards and PDF reports</p><p class="mt-1 text-sm" style="color:#5d7270">Friendly competition, and printable results for every class.</p></div>
    </div>
</section>

{{-- ═══ Closing call ═══ --}}
<section class="px-6 pb-20 md:px-10">
    <div class="aurora relative mx-auto max-w-6xl overflow-hidden rounded-[2rem] px-8 py-16 text-center md:py-20">
        <h2 class="font-display mx-auto max-w-2xl text-4xl leading-tight md:text-5xl" style="color:#fff;font-weight:400">Your next exam is a few minutes away.</h2>
        @guest<a href="{{ route('register') }}" class="btn-teal mt-9" style="padding:1rem 2rem;font-size:1rem;font-weight:600">Start for free</a>@else<a href="{{ route('dashboard') }}" class="btn-teal mt-9" style="padding:1rem 2rem;font-size:1rem;font-weight:600">Go to dashboard</a>@endguest
    </div>
</section>

<footer class="px-6 pb-10 text-center text-sm" style="color:#7d928f">© {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</footer>

@fluxScripts
</body>
</html>
