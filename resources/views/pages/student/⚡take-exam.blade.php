<?php

use App\Ai\Agents\AutoGraderAgent;
use App\Ai\Agents\HintAgent;
use App\Ai\ResolvedProviders;
use App\Enums\QuestionType;
use App\Events\AttemptSubmittedEvent;
use App\Jobs\GenerateAttemptEmbeddingJob;
use App\Models\Attempt;
use App\Models\Exam;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Ai\Streaming\Events\TextDelta;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Take Exam')] class extends Component {
    public Exam $exam;
    public ?Attempt $attempt = null;

    /**
     * Answers keyed by question ID.
     * Shape: { value: string, flagged: bool }
     * After AI grading: { value, flagged, ai_graded, ai_score, ai_explanation, ai_suggestion, raw_answer }
     *
     * @var array<int|string, array<string, mixed>>
     */
    public array $answers = [];

    /** @var array<int|string, string> */
    public array $hints = [];

    public bool $timedOut = false;

    #[Url(as: 'preview', except: false)]
    public bool $isPreview = false;

    /** 'all' | 'flagged' */
    public string $filter = 'all';

    public function mount(): void
    {
        if ($this->isPreview) {
            // Only the exam's owner may preview
            abort_unless(auth()->id() === $this->exam->user_id, 403);
        } else {
            abort_unless($this->exam->isPublished(), 404);

            // The exam must be assigned to a class the student joined, inside its open window
            abort_unless($this->exam->isOpenFor(auth()->user()), 403);
        }

        if (! $this->isPreview) {
            $this->attempt = auth()->user()->attempts()
                ->where('exam_id', $this->exam->id)
                ->whereNull('completed_at')
                ->latest()
                ->first();

            if (! $this->attempt) {
                $this->attempt = auth()->user()->attempts()->create([
                    'exam_id'    => $this->exam->id,
                    'started_at' => now(),
                ]);
            }

            $this->answers = $this->attempt->answers ?? [];
        }

        $this->normalizeAnswers();
    }

    /**
     * Ensure every question slot uses the canonical { value, flagged } shape.
     * Handles three legacy cases:
     *   1. Slot missing entirely (new exam)
     *   2. Slot is a plain string (legacy attempt)
     *   3. Slot is an AI-graded array without the 'value' key (legacy AI-graded)
     */
    private function normalizeAnswers(): void
    {
        foreach ($this->exam->questions as $question) {
            $id  = $question->id;
            $raw = $this->answers[$id] ?? null;

            if ($raw === null) {
                $this->answers[$id] = ['value' => '', 'flagged' => false];
            } elseif (! is_array($raw)) {
                $this->answers[$id] = ['value' => $raw, 'flagged' => false];
            } elseif (! array_key_exists('value', $raw)) {
                // Old AI-graded shape: { raw_answer, ai_score, ... } — lift raw_answer into value
                $this->answers[$id] = array_merge(['value' => $raw['raw_answer'] ?? '', 'flagged' => false], $raw);
            }
            // Otherwise already in new shape — leave as-is
        }
    }

    /**
     * Seconds remaining based on server time — cannot be spoofed by the client.
     * Returns 0 when the exam has no time limit.
     */
    #[Computed]
    public function timeRemaining(): int
    {
        if (! $this->exam->time_limit || ! $this->attempt) {
            return 0;
        }

        $elapsed = (int) $this->attempt->started_at->diffInSeconds(now(), absolute: true);
        $limit   = $this->exam->time_limit * 60;

        return max(0, $limit - $elapsed);
    }

    /**
     * Called by the Alpine ticker every second once the client countdown hits zero.
     * Validates real server-side elapsed time before auto-submitting.
     */
    public function checkTimer(): void
    {
        if (! $this->exam->time_limit) {
            return;
        }

        if ($this->timeRemaining <= 0) {
            $this->timedOut = true;
            $this->submitExam();
        }
    }

    /**
     * Toggle the flagged state for a question and auto-save to the database.
     * Also acts as an auto-save for the current answer value.
     */
    public function toggleFlag(int $questionId): void
    {
        if (! isset($this->answers[$questionId])) {
            $this->answers[$questionId] = ['value' => '', 'flagged' => true];
        } else {
            $this->answers[$questionId]['flagged'] = ! ($this->answers[$questionId]['flagged'] ?? false);
        }

        $this->attempt->update(['answers' => $this->answers]);
    }

    public function streamHint(int $questionId): void
    {
        if (Cache::has('ai_budget_exceeded:'.auth()->id()) ||
            ! RateLimiter::attempt('ai:'.auth()->id(), config('ai.rate_limit.per_minute', 30), fn () => true)) {
            $this->hints[$questionId] = 'AI is temporarily unavailable. Please try again later.';

            return;
        }

        $question = $this->exam->questions->firstWhere('id', $questionId);

        if (! $question) {
            return;
        }

        $this->hints[$questionId] = '';

        $answerData    = $this->answers[$questionId] ?? null;
        $currentAnswer = is_array($answerData) ? ($answerData['value'] ?? '') : ($answerData ?? '');

        try {
            $stream = (new HintAgent($question->question))
                ->stream(
                    $currentAnswer ?: 'I have not answered yet.',
                    provider: ResolvedProviders::list(),
                );

            foreach ($stream as $event) {
                if ($event instanceof TextDelta) {
                    $this->hints[$questionId] .= $event->delta;
                    $this->stream(to: "hint-{$questionId}", content: $event->delta);
                }
            }
        } catch (\Throwable $e) {
            logger()->warning('Hint streaming failed', ['error' => $e->getMessage()]);
            $this->hints[$questionId] = 'Hint unavailable — AI provider could not be reached. Please try again later.';
            $this->stream(to: "hint-{$questionId}", content: $this->hints[$questionId]);
        }
    }

    public function submitExam(): void
    {
        // Block submission in preview mode
        if ($this->isPreview) {
            return;
        }

        // Idempotency guard — already submitted (double-click or race condition)
        if ($this->attempt->isCompleted()) {
            $this->redirect(route('student.attempts.results', $this->attempt), navigate: true);

            return;
        }

        // Skip validation on auto-submit; answers may be partial
        if (! $this->timedOut) {
            $this->validate([
                'answers'           => 'array',
                'answers.*.value'   => 'nullable|string|max:500',
                'answers.*.flagged' => 'nullable|boolean',
            ]);
        }

        $questions = $this->exam->questions;
        $correct   = 0;

        foreach ($questions as $question) {
            $answerData = $this->answers[$question->id] ?? null;
            $given      = is_array($answerData) ? ($answerData['value'] ?? null) : $answerData;
            $flagged    = is_array($answerData) ? ($answerData['flagged'] ?? false) : false;

            if ($given === null || $given === '') {
                continue;
            }

            if ($question->type === QuestionType::ShortAnswer) {
                try {
                    $result = (new AutoGraderAgent($question->question, $question->correct_answer))
                        ->prompt($given, provider: ResolvedProviders::list());

                    $this->answers[$question->id] = [
                        'value'          => $given,
                        'flagged'        => $flagged,
                        'raw_answer'     => $given,
                        'ai_score'       => $result['score'],
                        'ai_explanation' => $result['explanation'],
                        'ai_suggestion'  => $result['suggestion'],
                        'ai_graded'      => true,
                    ];

                    if ($result['is_correct']) {
                        $correct++;
                    }
                } catch (\Throwable) {
                    if (strtolower(trim($given)) === strtolower(trim($question->correct_answer))) {
                        $correct++;
                    }
                }
            } else {
                if (strtolower(trim($given)) === strtolower(trim($question->correct_answer))) {
                    $correct++;
                }
            }
        }

        $score = $questions->count() > 0
            ? (int) round(($correct / $questions->count()) * 100)
            : 0;

        $this->attempt->update([
            'answers'      => $this->answers,
            'score'        => $score,
            'completed_at' => now(),
        ]);

        GenerateAttemptEmbeddingJob::dispatch($this->attempt);

        // Broadcast live submission counter to teacher dashboard
        if (config('broadcasting.default') === 'reverb') {
            $completedCount = $this->exam->attempts()->completed()->count();

            AttemptSubmittedEvent::dispatch(
                $this->exam->id,
                $completedCount,
                auth()->user()->name,
            );
        }

        if ($this->timedOut) {
            $this->dispatch('toast', variant: 'warning', heading: "Time's up", text: 'Your exam has been automatically submitted.');
        }

        $this->redirect(route('student.attempts.results', $this->attempt), navigate: true);
    }

    #[Computed]
    public function questions(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->exam->questions;
    }

    #[Computed]
    public function filteredQuestions(): \Illuminate\Database\Eloquent\Collection
    {
        if ($this->filter === 'flagged') {
            return $this->questions->filter(
                fn ($q) => $this->answers[$q->id]['flagged'] ?? false
            )->values();
        }

        return $this->questions;
    }

    #[Computed]
    public function flaggedCount(): int
    {
        return collect($this->answers)->filter(fn ($a) => $a['flagged'] ?? false)->count();
    }
}; ?>

@php
    $total = $this->questions->count();
    $limit = ($exam->time_limit ?? 0) * 60;
    $ids = $this->questions->pluck('id')->implode(',');
    $exitUrl = $isPreview ? route('teacher.exams.questions', $exam) : route('student.dashboard');
@endphp

{{-- Focus mode: one question at a time, all answers stay in the same Livewire form. --}}
<form wire:submit="submitExam" class="focus-stage"
    x-data="{
        i: 0, total: {{ $total }}, ids: [{{ $ids }}],
        secs: {{ $this->timeRemaining }}, limit: {{ $limit }}, expired: false, _t: null,
        get min() { return Math.floor(this.secs / 60) },
        get sec() { return String(this.secs % 60).padStart(2, '0') },
        get warn() { return this.limit > 0 && this.secs <= 300 && this.secs > 60 },
        get danger() { return this.limit > 0 && this.secs <= 60 && !this.expired },
        get frac() { return this.limit ? Math.max(0, this.secs / this.limit) : 1 },
        go(n) { this.i = Math.max(0, Math.min(this.total - 1, n)) },
        pick(n) { const r = this.$refs['q' + this.i]?.querySelectorAll('input[type=radio]'); if (r && r[n]) r[n].click() },
        dot(id, idx) { const a = this.$wire.answers?.[id]; return { cur: idx === this.i, flag: !!a?.flagged && idx !== this.i, ans: !!a?.value && !a?.flagged && idx !== this.i } },
        answered() { return this.ids.filter(id => !!this.$wire.answers?.[id]?.value).length },
        key(e) {
            const t = e.target;
            if (t.tagName === 'TEXTAREA' || (t.tagName === 'INPUT' && t.type !== 'radio')) return;
            if (e.metaKey || e.ctrlKey || e.altKey) return;
            const k = e.key.toLowerCase();
            if (k.length === 1 && 'abcd'.includes(k)) { this.pick('abcd'.indexOf(k)) }
            else if (e.key === 'ArrowRight') { e.preventDefault(); this.go(this.i + 1) }
            else if (e.key === 'ArrowLeft') { e.preventDefault(); this.go(this.i - 1) }
        },
        init() {
            document.body.style.overflow = 'hidden';
            if (this.limit > 0) {
                if (this.secs <= 0) { this.expired = true; this.$wire.checkTimer(); return }
                this._t = setInterval(() => {
                    if (this.secs > 0) { this.secs-- }
                    else { clearInterval(this._t); this.expired = true; this.$wire.checkTimer() }
                }, 1000);
            }
        },
        destroy() { clearInterval(this._t); document.body.style.overflow = '' }
    }"
    @keydown.window="key($event)">

    {{-- ── Top bar ── --}}
    <div class="focus-top">
        <a href="{{ $exitUrl }}" wire:navigate class="f-pill"><flux:icon.arrow-left class="size-4" /> Exit</a>

        <div class="min-w-0 text-center">
            <div class="truncate text-[15px] font-semibold text-white">{{ $exam->title }}</div>
            <div class="text-xs" style="color:#6f9a94">
                <span x-text="answered()"></span> of {{ $total }} answered
            </div>
        </div>

        @if ($limit > 0)
            <div class="flex items-center gap-3">
                <div class="text-right">
                    <div class="text-xl font-bold tabular-nums text-white" :class="{ 'f-warn': danger }" x-text="expired ? '0:00' : `${min}:${sec}`">{{ floor($this->timeRemaining / 60) }}:{{ str_pad($this->timeRemaining % 60, 2, '0', STR_PAD_LEFT) }}</div>
                    <div class="text-xs" style="color:#6f9a94" x-text="expired ? 'Submitting…' : 'time left'">time left</div>
                </div>
                <svg width="52" height="52" viewBox="0 0 52 52" style="transform:rotate(-90deg)">
                    <circle cx="26" cy="26" r="23" fill="none" stroke="rgba(255,255,255,.12)" stroke-width="6"/>
                    <circle cx="26" cy="26" r="23" fill="none" stroke-width="6" stroke-linecap="round" stroke-dasharray="144.51"
                        :stroke-dashoffset="144.51 * (1 - frac)" :stroke="danger || expired ? '#ff6b6b' : (warn ? '#fbbf24' : '#5eead4')"
                        style="transition:stroke-dashoffset 1s linear, stroke .4s"/>
                </svg>
            </div>
        @else
            <div class="text-xs" style="color:#6f9a94">No time limit</div>
        @endif
    </div>

    <div class="focus-wrap">
        @if ($isPreview)
            <div class="mb-4 text-center"><span class="f-chip" style="background:rgba(251,191,36,.14);color:#fcd34d;border-color:rgba(251,191,36,.35)"><flux:icon.eye class="size-4" /> Preview mode. Submission is disabled.</span></div>
        @endif

        <div x-show="warn || danger" x-cloak x-transition class="mb-4 text-center">
            <span class="f-chip" :style="danger ? '' : 'background:rgba(251,191,36,.14);color:#fcd34d;border-color:rgba(251,191,36,.35)'">
                <flux:icon.exclamation-triangle class="size-4" />
                <span x-text="danger ? 'Less than a minute left. Your exam will be submitted automatically.' : '5 minutes left. Start wrapping up.'"></span>
            </span>
        </div>

        @if ($total === 0)
            <div class="py-24 text-center"><p class="f-q">This exam has no questions yet.</p><a href="{{ $exitUrl }}" wire:navigate class="f-pill">Go back</a></div>
        @else
            {{-- Segmented progress --}}
            <div class="f-segs">
                <template x-for="n in total" :key="n"><div class="f-seg" :class="{ done: n - 1 < i, cur: n - 1 === i }"></div></template>
            </div>

            @foreach ($this->questions as $question)
                @php
                    $qid = $question->id;
                    $isFlagged = $answers[$qid]['flagged'] ?? false;
                @endphp
                <section wire:key="q-{{ $qid }}" x-show="i === {{ $loop->index }}" x-cloak x-ref="q{{ $loop->index }}"
                    x-effect="if (i === {{ $loop->index }}) $nextTick(() => $el.querySelector('input[type=text]')?.focus({ preventScroll: true }))"
                    x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">

                    <div class="mt-5 flex items-center justify-between text-sm" style="color:#7fa9a3">
                        <span>Question {{ $loop->iteration }} of {{ $total }}</span>
                        <button type="button" wire:click="toggleFlag({{ $qid }})" wire:loading.attr="disabled" wire:target="toggleFlag({{ $qid }})"
                            class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 transition-colors hover:bg-white/10"
                            style="color:{{ $isFlagged ? '#ff9494' : '#7fa9a3' }}" title="{{ $isFlagged ? 'Remove flag' : 'Flag for review' }}">
                            <svg class="size-4" viewBox="0 0 24 24" fill="{{ $isFlagged ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12v18l-6-4-6 4z"/></svg>
                            {{ $isFlagged ? 'Flagged' : 'Flag for later' }}
                        </button>
                    </div>

                    <h1 class="f-q">{{ $question->question }}</h1>

                    @if ($question->options)
                        <div>
                            @foreach ($question->options as $option)
                                <label class="f-opt" wire:key="opt-{{ $qid }}-{{ $loop->index }}">
                                    <input type="radio" class="sr-only" wire:model="answers.{{ $qid }}.value" value="{{ $option }}" />
                                    <span class="f-key">{{ chr(65 + $loop->index) }}</span>
                                    <span>{{ $option }}</span>
                                    <svg class="f-tick" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-5 hidden text-[13px] md:block" style="color:#5f8680">Press A to D to answer, and the arrow keys to move between questions.</p>
                    @else
                        <input type="text" class="f-input" wire:model="answers.{{ $qid }}.value" placeholder="Type your answer" autocomplete="off" @keydown.enter.prevent="go(i + 1)" />

                        <div x-data="{ showHint: {{ isset($hints[$qid]) ? 'true' : 'false' }}, loading: false }" class="mt-5 space-y-3">
                            <button type="button" class="f-chip" x-bind:disabled="loading"
                                x-on:click="loading = true; showHint = true; $wire.streamHint({{ $qid }}).then(() => loading = false)">
                                <flux:icon.sparkles class="size-4" />
                                <span x-show="!loading">Need a nudge? Get a hint</span>
                                <span x-show="loading" x-cloak>Thinking…</span>
                            </button>
                            <div x-show="showHint" x-cloak x-transition class="f-hint">
                                <span wire:stream="hint-{{ $qid }}">{{ $hints[$qid] ?? '' }}</span>
                            </div>
                        </div>
                    @endif
                </section>
            @endforeach
        @endif
    </div>

    {{-- ── Footer: previous · question map · next / submit ── --}}
    @if ($total > 0)
        <div class="f-foot">
            <button type="button" class="f-pill" @click="go(i - 1)" :class="{ 'pointer-events-none opacity-30': i === 0 }">
                <flux:icon.arrow-left class="size-4" /> <span class="hidden sm:inline">Previous</span>
            </button>

            <div class="f-dots hidden md:flex">
                @foreach ($this->questions as $q)
                    <button type="button" class="f-dot" :class="dot({{ $q->id }}, {{ $loop->index }})" @click="go({{ $loop->index }})" title="Question {{ $loop->iteration }}"></button>
                @endforeach
            </div>

            <div>
                <button type="button" x-show="i < total - 1" @click="go(i + 1)" class="btn-teal" style="padding:.95rem 1.8rem;font-size:1rem">
                    Next question <flux:icon.arrow-right class="size-[18px]" />
                </button>
                <div x-show="i === total - 1" x-cloak>
                    @if ($isPreview)
                        <span class="f-pill">Preview only</span>
                    @else
                        <button type="submit" class="btn-teal" style="padding:.95rem 1.8rem;font-size:1rem" wire:loading.attr="disabled" wire:target="submitExam">
                            <span wire:loading.remove wire:target="submitExam" class="inline-flex items-center gap-2"><flux:icon.check class="size-[18px]" /> Submit exam</span>
                            <span wire:loading wire:target="submitExam" class="inline-flex items-center gap-2">
                                <svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>
                                Grading…
                            </span>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</form>
