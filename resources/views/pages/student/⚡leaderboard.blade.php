<?php

use App\Models\Exam;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Leaderboard')] class extends Component {
    public Exam $exam;

    public function mount(): void
    {
        abort_unless($this->exam->isPublished(), 404);
        abort_unless($this->exam->leaderboard_enabled, 404);
        abort_unless($this->exam->isAssignedTo(auth()->user()), 403);
    }

    /** Triggered by Alpine Echo listener when a new submission arrives. */
    #[On('exam-attempt-submitted')]
    public function refresh(): void
    {
        unset($this->topAttempts);
    }

    #[Computed]
    public function topAttempts(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->exam->attempts()
            ->completed()
            ->with('student:id,name')
            ->orderByDesc('score')
            ->orderBy('completed_at')
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function myRank(): ?int
    {
        $myAttempt = $this->exam->attempts()
            ->completed()
            ->where('user_id', auth()->id())
            ->orderByDesc('score')
            ->first();

        if (! $myAttempt) {
            return null;
        }

        return $this->exam->attempts()
            ->completed()
            ->where(function ($q) use ($myAttempt) {
                $q->where('score', '>', $myAttempt->score)
                    ->orWhere(function ($q2) use ($myAttempt) {
                        $q2->where('score', $myAttempt->score)
                            ->where('completed_at', '<', $myAttempt->completed_at);
                    });
            })
            ->count() + 1;
    }
}; ?>

@php
    $top = $this->topAttempts->values();
    $order = [1, 0, 2];                     // visual order: 2nd, 1st, 3rd
    $heights = [0 => 190, 1 => 150, 2 => 120];
    $tones = [0 => 'linear-gradient(180deg,#fbbf24,#f59e0b)', 1 => 'linear-gradient(180deg,#5eead4,#14b8a6)', 2 => 'linear-gradient(180deg,#ffb4b4,#ff8f8f)'];
@endphp

<div class="mx-auto max-w-2xl flex flex-col gap-8"
    x-data="{
        initEcho() {
            if (typeof Echo === 'undefined') return;
            Echo.channel('exam.{{ $exam->id }}').listen('AttemptSubmittedEvent', () => { $wire.dispatch('exam-attempt-submitted'); });
        }
    }"
    x-init="initEcho()">

    <x-page-header :back="route('student.dashboard')" title="Leaderboard" :subtitle="$exam->title">
        <span class="inline-flex items-center gap-2 rounded-full px-3.5 py-1.5 text-sm font-medium" style="background:#dcfaf3;color:#0f766e"><i class="dot-live"></i>Live</span>
    </x-page-header>

    @if ($this->myRank !== null)
        <div class="hero-ink flex items-center justify-between gap-4 p-7">
            <div class="relative z-10">
                <div class="text-sm" style="color:#8fb3ad">Your current rank</div>
                <div class="font-display text-6xl leading-none" style="color:#fff">#{{ $this->myRank }}</div>
            </div>
            <span class="relative z-10 rounded-full px-4 py-2 text-sm font-medium" style="background:rgba(94,234,212,.16);color:#7ff0dc">{{ $this->myRank === 1 ? 'Top of the class' : 'Keep it up' }}</span>
        </div>
    @endif

    @if ($top->isNotEmpty())
        <div class="podium px-2 pt-4">
            @foreach ($order as $idx)
                @if ($top->has($idx))
                    @php $a = $top[$idx]; $isMe = $a->user_id === auth()->id(); @endphp
                    <div class="podium-col" wire:key="podium-{{ $a->id }}">
                        <div class="avatar-dot">{{ \Illuminate\Support\Str::of($a->student->name)->substr(0, 1)->upper() }}</div>
                        <div class="truncate text-sm font-semibold text-charcoal-900">{{ $isMe ? 'You' : $a->student->name }}</div>
                        <div class="mb-2 text-[13px]" style="color:#5d7270">{{ $a->score }}%</div>
                        <div class="podium-bar" style="height:{{ $heights[$idx] }}px;background:{{ $tones[$idx] }};--i:{{ $idx }}">{{ $idx + 1 }}</div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif

    <div class="bento-flat !p-2">
        @forelse ($top->slice(3) as $index => $attempt)
            @php $rank = $index + 1; $isMe = $attempt->user_id === auth()->id(); @endphp
            <div class="flex items-center gap-4 rounded-2xl px-4 py-3 {{ $isMe ? 'bg-teal-50' : '' }}" wire:key="rank-{{ $attempt->id }}">
                <div class="w-8 text-center text-sm font-semibold" style="color:#5d7270">#{{ $rank }}</div>
                <div class="min-w-0 flex-1">
                    <div class="truncate font-medium text-charcoal-900">{{ $isMe ? 'You' : $attempt->student->name }}</div>
                    <div class="text-xs" style="color:#8aa09c">Submitted {{ $attempt->completed_at->diffForHumans() }}</div>
                </div>
                <x-ring :pct="$attempt->score" :size="46" :stroke="5" track="#e8f0ee" :glow="false"><span class="text-[11px] font-semibold text-charcoal-900">{{ $attempt->score }}</span></x-ring>
            </div>
        @empty
            @if ($top->isEmpty())
                <div class="empty-state p-12 text-center"><p class="font-display text-2xl text-charcoal-900">No submissions yet.</p><p class="mt-1" style="color:#5d7270">Be the first to complete this exam.</p></div>
            @endif
        @endforelse
    </div>

    <div class="flex justify-center"><a href="{{ route('student.dashboard') }}" wire:navigate class="btn-line">Back to dashboard</a></div>
</div>
