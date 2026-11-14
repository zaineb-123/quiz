<?php

use App\Models\Exam;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Available Exams')] class extends Component {
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function availableExams(): \Illuminate\Pagination\LengthAwarePaginator
    {
        // Only exams assigned (and currently open) to a class the student has joined
        return Exam::query()
            ->visibleTo(auth()->user())
            ->withCount('questions')
            ->when($this->search, fn ($q) => $q->where('title', 'ilike', '%'.$this->search.'%'))
            ->latest('published_at')
            ->paginate(12);
    }

    #[Computed]
    public function myAttempts(): \Illuminate\Database\Eloquent\Collection
    {
        return auth()->user()->attempts()
            ->with('exam')
            ->latest()
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function totalAttempts(): int
    {
        return auth()->user()->attempts()->completed()->count();
    }

    #[Computed]
    public function averageScore(): int
    {
        return (int) round(
            auth()->user()->attempts()->completed()->avg('score') ?? 0
        );
    }

    #[Computed]
    public function bestScore(): int
    {
        return (int) (auth()->user()->attempts()->completed()->max('score') ?? 0);
    }

    /**
     * @return array<int, array{label: string, score: int, date: string}>
     */
    #[Computed]
    public function progressChartData(): array
    {
        return auth()->user()->attempts()
            ->completed()
            ->with('exam:id,title')
            ->orderBy('completed_at')
            ->get()
            ->map(fn ($attempt) => [
                'label' => $attempt->exam->title.' ('.$attempt->completed_at->format('M j').')',
                'score' => $attempt->score,
                'date' => $attempt->completed_at->format('M j, Y'),
            ])
            ->toArray();
    }

    /**
     * Study streak: consecutive days (ending today or yesterday) with a completed exam.
     *
     * @return array{days: array<string,int>, streak: int}
     */
    #[Computed]
    public function activity(): array
    {
        $days = auth()->user()->attempts()->whereNotNull('completed_at')->pluck('completed_at')
            ->map(fn ($d) => $d->toDateString())->unique()->flip();

        $cursor = today();
        if (! $days->has($cursor->toDateString())) {
            $cursor = $cursor->copy()->subDay();
        }

        $streak = 0;
        while ($days->has($cursor->toDateString())) {
            $streak++;
            $cursor = $cursor->copy()->subDay();
        }

        return ['days' => $days->all(), 'streak' => $streak];
    }
}; ?>

@php
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    $firstName = \Illuminate\Support\Str::of(auth()->user()->name)->before(' ');

    // Resume card: the most recent unfinished attempt
    $inProgress = $this->myAttempts->first(fn ($a) => ! $a->isCompleted());
    if ($inProgress) {
        $ipTotal = max(1, $inProgress->exam->questions()->count());
        $ipDone = collect($inProgress->answers ?? [])
            ->filter(fn ($a) => filled(is_array($a) ? ($a['value'] ?? null) : $a))->count();
        $ipPct = (int) round($ipDone / $ipTotal * 100);
    }

    // Sparkline from the last 8 scores
    $scores = collect($this->progressChartData)->pluck('score')->take(-8)->values();
    $spark = null;
    if ($scores->count() >= 2) {
        $w = 190; $h = 56; $step = $w / ($scores->count() - 1);
        $pts = $scores->map(fn ($s, $i) => round($i * $step, 1).','.round($h - 6 - ($s / 100) * ($h - 12), 1));
        $spark = ['line' => 'M'.$pts->implode(' L'), 'area' => 'M'.$pts->implode(' L')." L{$w},{$h} L0,{$h} Z", 'last' => $pts->last()];
    }
    $streak = $this->activity['streak'];
    $week = collect(range(6, 0))->map(fn ($d) => today()->copy()->subDays($d));
@endphp

<div class="flex flex-col gap-8">

    {{-- Greeting --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-display text-4xl md:text-5xl text-charcoal-900">{{ $greeting }}, {{ $firstName }}.</h1>
            <p class="mt-2 text-[15px]" style="color:#5d7270">
                @if ($inProgress) You have an exam in progress. Pick up where you left off.
                @elseif ($this->availableExams->total() > 0) {{ $this->availableExams->total() }} {{ \Illuminate\Support\Str::plural('exam', $this->availableExams->total()) }} {{ $this->availableExams->total() === 1 ? 'is' : 'are' }} open for you.
                @else Join a class to see your exams. @endif
            </p>
        </div>
        <div class="w-full md:w-72"><flux:input wire:model.live.debounce="search" placeholder="Search exams" icon="magnifying-glass" clearable /></div>
    </div>

    {{-- Bento hero row (one orchestrated entrance) --}}
    <div class="stagger grid gap-5 lg:grid-cols-5">
        {{-- Resume / welcome card --}}
        <div class="hero-ink p-8 lg:col-span-3" style="--i:0; min-height:262px">
            <div class="relative z-10 flex h-full flex-wrap items-center justify-between gap-6">
                @if ($inProgress)
                    <div class="flex h-full flex-col" style="min-height:190px">
                        <span class="inline-flex items-center gap-2 self-start rounded-full px-3 py-1 text-xs font-medium" style="background:rgba(94,234,212,.16);color:#7ff0dc"><i class="dot-live"></i>In progress</span>
                        <h2 class="font-display mt-4 text-3xl leading-tight text-white md:text-4xl" style="color:#fff">{{ $inProgress->exam->title }}</h2>
                        <p class="mt-2 text-sm" style="color:#8fb3ad">{{ $ipDone }} of {{ $ipTotal }} answered{{ $inProgress->exam->time_limit ? ', '.$inProgress->exam->time_limit.' min limit' : '' }}</p>
                        <a href="{{ route('student.exams.take', $inProgress->exam) }}" wire:navigate class="btn-teal mt-auto self-start" style="padding:.8rem 1.6rem">
                            Resume exam <flux:icon.arrow-right class="size-4" />
                        </a>
                    </div>
                    <x-ring :pct="$ipPct" :size="176" :stroke="13">
                        <div><div class="font-display text-4xl text-white leading-none" style="color:#fff"><x-count-up :to="$ipPct" suffix="%" /></div><div class="mt-1 text-xs" style="color:#8fb3ad">complete</div></div>
                    </x-ring>
                @else
                    <div class="flex h-full flex-col" style="min-height:190px">
                        <span class="inline-flex items-center gap-2 self-start rounded-full px-3 py-1 text-xs font-medium" style="background:rgba(94,234,212,.16);color:#7ff0dc"><i class="dot-live"></i>Ready when you are</span>
                        <h2 class="font-display mt-4 text-3xl leading-tight md:text-4xl" style="color:#fff">{{ $this->totalAttempts > 0 ? 'Keep your momentum going.' : 'Take your first exam.' }}</h2>
                        <p class="mt-2 text-sm" style="color:#8fb3ad">{{ $this->totalAttempts > 0 ? 'Every attempt sharpens recall. Choose an exam below.' : 'Pick any exam below, or join a class to unlock more.' }}</p>
                        <a href="{{ route('student.classrooms') }}" wire:navigate class="btn-teal mt-auto self-start" style="padding:.8rem 1.6rem">My classes <flux:icon.arrow-right class="size-4" /></a>
                    </div>
                    <x-ring :pct="$this->averageScore" :size="176" :stroke="13">
                        <div><div class="font-display text-4xl leading-none" style="color:#fff"><x-count-up :to="$this->averageScore" suffix="%" /></div><div class="mt-1 text-xs" style="color:#8fb3ad">average score</div></div>
                    </x-ring>
                @endif
            </div>
        </div>

        {{-- Stats column --}}
        <div class="flex flex-col gap-5 lg:col-span-2">
            <div class="bento-flat flex items-center justify-between gap-4" style="--i:1; padding:1.25rem 1.5rem">
                <div>
                    <div class="text-[13px]" style="color:#5d7270">Average score</div>
                    <div class="mt-1 flex flex-wrap items-center gap-2">
                        <span class="font-display text-4xl text-charcoal-900"><x-count-up :to="$this->averageScore" suffix="%" /></span>
                        @if ($this->bestScore > 0)<span class="rounded-full px-2.5 py-1 text-xs font-medium" style="background:#dcfaf3;color:#0f766e">Best {{ $this->bestScore }}%</span>@endif
                    </div>
                </div>
                @if ($spark)
                    <svg width="150" height="56" viewBox="0 0 190 56" class="shrink-0" preserveAspectRatio="none">
                        <defs><linearGradient id="spk" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#14b8a6" stop-opacity=".35"/><stop offset="1" stop-color="#14b8a6" stop-opacity="0"/></linearGradient></defs>
                        <path d="{{ $spark['area'] }}" fill="url(#spk)"/>
                        <path d="{{ $spark['line'] }}" fill="none" stroke="#0d9488" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" pathLength="1" style="stroke-dasharray:1;stroke-dashoffset:0;animation:ring-in 1.4s var(--ease-out) both;--c:1"/>
                    </svg>
                @endif
            </div>
            <div class="bento-flat" style="--i:2; padding:1.25rem 1.5rem">
                <div class="flex items-center gap-3">
                    <div class="grid size-10 place-items-center rounded-2xl" style="background:linear-gradient(135deg,#fff1d6,#ffe3e1)"><svg class="flame size-5" viewBox="0 0 24 24" fill="#f08a3c"><path d="M12 3s6 4 6 10a6 6 0 0 1-12 0c0-2.2 1-3.5 2-4.5.2 2 1.2 3 2.3 3C10.5 8 11 5.5 12 3z"/></svg></div>
                    <div><div class="text-[17px] font-semibold text-charcoal-900">{{ $streak }}-day streak</div><div class="text-[12.5px]" style="color:#5d7270">{{ $streak > 0 ? 'Finish an exam today to keep it alive' : 'Complete an exam to start a streak' }}</div></div>
                </div>
                <div class="mt-4 flex gap-2.5">
                    @foreach ($week as $day)
                        <div class="day-dot {{ isset($this->activity['days'][$day->toDateString()]) ? 'on' : 'off' }}" title="{{ $day->format('D j M') }}">
                            @if (isset($this->activity['days'][$day->toDateString()]))<flux:icon.check class="size-3.5" style="stroke-width:3" />@endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Available exams --}}
    <div class="flex items-center justify-between">
        <h2 class="font-display text-3xl text-charcoal-900">Available exams</h2>
    </div>

    <div class="stagger grid gap-5 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($this->availableExams as $exam)
            <a href="{{ route('student.exams.take', $exam) }}" wire:navigate class="bento-card block !p-0 overflow-hidden" style="--i:{{ $loop->index % 6 }}" wire:key="exam-{{ $exam->id }}">
                <div class="cover cover-{{ $loop->index % 3 }}" style="background:{{ ['linear-gradient(135deg,#2dd4bf,#0d9488)','linear-gradient(135deg,#ffb4a8,#ff8f8f)','linear-gradient(135deg,#fcd34d,#f59e0b)'][$loop->index % 3] }}"></div>
                <div class="p-6">
                    <div class="text-[17.5px] font-semibold leading-snug text-charcoal-900">{{ $exam->title }}</div>
                    <div class="mt-1 line-clamp-2 min-h-[2.5rem] text-[13px]" style="color:#5d7270">{{ $exam->description ?: 'Ready when you are.' }}</div>
                    <div class="mt-5 flex items-center justify-between">
                        <div class="flex items-center gap-4 text-[13px]" style="color:#5d7270">
                            <span class="flex items-center gap-1.5"><flux:icon.list-bullet class="size-4" />{{ $exam->questions_count }} questions</span>
                            @if ($exam->time_limit)<span class="flex items-center gap-1.5"><flux:icon.clock class="size-4" />{{ $exam->time_limit }} min</span>@endif
                        </div>
                        <span class="go-btn"><flux:icon.arrow-right class="size-[18px]" /></span>
                    </div>
                </div>
            </a>
        @empty
            <div class="col-span-full rounded-3xl border border-dashed p-12 text-center space-y-4" style="border-color:#cbd9d6;background:rgba(255,255,255,.6)">
                <p class="font-display text-2xl text-charcoal-900">{{ $search ? 'No exam matches “'.$search.'”.' : 'No exams yet.' }}</p>
                <p style="color:#5d7270">{{ $search ? 'Try a shorter word, or clear the search.' : 'Join your teacher’s class to see its exams here.' }}</p>
                @unless ($search)<a href="{{ route('student.classrooms') }}" wire:navigate class="btn-teal">Join or open a class</a>@endunless
            </div>
        @endforelse
    </div>

    {{ $this->availableExams->links() }}

    @if (count($this->progressChartData) >= 2)
        <div class="bento-flat space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-2xl text-charcoal-900">Score progress</h2>
                <flux:button variant="ghost" size="sm" :href="route('student.attempts')" wire:navigate>View all</flux:button>
            </div>
            <div x-data="progressChart(@js($this->progressChartData))" x-init="init()" style="height:220px; position:relative;"><canvas x-ref="canvas"></canvas></div>
        </div>
    @endif

    @if ($this->myAttempts->isNotEmpty())
        <div class="space-y-4">
            <h2 class="font-display text-2xl text-charcoal-900">Recent attempts</h2>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Exam</flux:table.column>
                    <flux:table.column>Score</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Date</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->myAttempts as $attempt)
                        <flux:table.row :key="$attempt->id">
                            <flux:table.cell variant="strong">{{ $attempt->exam->title }}</flux:table.cell>
                            <flux:table.cell>{{ $attempt->score !== null ? $attempt->score.'%' : '—' }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($attempt->isCompleted())<flux:badge color="green" size="sm">Completed</flux:badge>
                                @else<flux:badge color="yellow" size="sm">In progress</flux:badge>@endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $attempt->started_at?->diffForHumans() }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($attempt->isCompleted())
                                    <flux:button size="sm" variant="ghost" :href="route('student.attempts.results', $attempt)" wire:navigate>View results</flux:button>
                                @else
                                    <flux:button size="sm" variant="ghost" :href="route('student.exams.take', $attempt->exam)" wire:navigate>Continue</flux:button>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @endif
</div>

@once
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" defer></script>
    <script>
        function progressChart(data) {
            return {
                init() {
                    const scores = data.map(d => d.score);
                    const labels = data.map(d => d.date);

                    new Chart(this.$refs.canvas, {
                        type: 'line',
                        data: {
                            labels,
                            datasets: [{
                                data: scores,
                                borderColor: '#14b8a6',
                                backgroundColor: 'rgba(94,234,212,0.18)',
                                borderWidth: 3,
                                pointBackgroundColor: scores.map(s =>
                                    s >= 70 ? '#16a34a' : s >= 50 ? '#d97706' : '#dc2626'
                                ),
                                pointRadius: 5,
                                pointHoverRadius: 7,
                                fill: true,
                                tension: 0.4,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: ctx => ` ${ctx.parsed.y}%`,
                                    },
                                },
                            },
                            scales: {
                                y: {
                                    min: 0,
                                    max: 100,
                                    ticks: {
                                        callback: v => v + '%',
                                        stepSize: 25,
                                    },
                                    grid: { color: 'rgba(0,0,0,0.05)' },
                                },
                                x: {
                                    grid: { display: false },
                                    ticks: { maxTicksLimit: 8 },
                                },
                            },
                        },
                    });
                },
            };
        }
    </script>
@endonce
