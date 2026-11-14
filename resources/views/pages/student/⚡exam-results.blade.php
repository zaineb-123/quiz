<?php

use App\Enums\QuestionType;
use App\Jobs\ExportStudentResultJob;
use App\Models\Attempt;
use App\Services\QuestionSimilarityService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Exam Results')] class extends Component {
    public Attempt $attempt;

    public function mount(): void
    {
        abort_unless(auth()->id() === $this->attempt->user_id, 403);
        abort_unless($this->attempt->isCompleted(), 404);
    }

    #[Computed]
    public function correctCount(): int
    {
        $correct = 0;

        foreach ($this->attempt->exam->questions as $question) {
            $given = $this->attempt->answers[$question->id] ?? null;

            if ($given === null) {
                continue;
            }

            if (is_array($given)) {
                if ($given['ai_graded'] ?? false) {
                    // AI-graded short answer (both old and new shape)
                    if (($given['ai_score'] ?? 0) >= 50) {
                        $correct++;
                    }
                } else {
                    // New shape: { value, flagged }
                    $value = $given['value'] ?? null;

                    if (
                        $value !== null && $value !== '' &&
                        strtolower(trim($value)) === strtolower(trim($question->correct_answer))
                    ) {
                        $correct++;
                    }
                }
            } else {
                // Old shape: plain string
                if (strtolower(trim($given)) === strtolower(trim($question->correct_answer))) {
                    $correct++;
                }
            }
        }

        return $correct;
    }

    #[Computed]
    public function totalCount(): int
    {
        return $this->attempt->exam->questions->count();
    }

    /** False while the teacher is still holding back correct answers (see ExamAssignment::reveal_answers_at). */
    #[Computed]
    public function answersRevealed(): bool
    {
        return $this->attempt->answersRevealed();
    }

    public function requestExport(): void
    {
        ExportStudentResultJob::dispatch($this->attempt->id);

        $this->dispatch('toast', variant: 'success', heading: 'Export queued', text: 'Your PDF is being generated — you will receive an email shortly.');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Question>
     */
    #[Computed]
    public function recommendations(): \Illuminate\Database\Eloquent\Collection
    {
        $incorrectTexts = [];

        foreach ($this->attempt->exam->questions as $question) {
            $given = $this->attempt->answers[$question->id] ?? null;

            if ($given === null) {
                continue;
            }

            $isCorrect = false;

            if (is_array($given)) {
                if ($given['ai_graded'] ?? false) {
                    $isCorrect = ($given['ai_score'] ?? 0) >= 50;
                } else {
                    $value = $given['value'] ?? null;
                    $isCorrect = $value !== null && $value !== '' &&
                        strtolower(trim($value)) === strtolower(trim($question->correct_answer));
                }
            } else {
                $isCorrect = strtolower(trim($given)) === strtolower(trim($question->correct_answer));
            }

            if (! $isCorrect) {
                $incorrectTexts[] = $question->question;
            }
        }

        if (empty($incorrectTexts)) {
            return new \Illuminate\Database\Eloquent\Collection;
        }

        $query = implode(' ', array_slice($incorrectTexts, 0, 3));

        try {
            $student = auth()->user();

            return app(QuestionSimilarityService::class)
                ->findSimilar($query, limit: 15, excludeExamId: $this->attempt->exam_id)
                ->load('exam')
                ->filter(fn ($question) => $question->exam && $question->exam->isOpenFor($student))
                ->take(5)
                ->values();
        } catch (\Throwable $e) {
            logger()->warning('Recommendations unavailable', ['error' => $e->getMessage()]);

            return new \Illuminate\Database\Eloquent\Collection;
        }
    }
}; ?>

@php
    $first = \Illuminate\Support\Str::of(auth()->user()->name)->before(' ');
    $score = (int) $attempt->score;
    $headline = $score >= 85 ? "Outstanding work, {$first}." : ($score >= 70 ? "Strong work, {$first}." : ($score >= 50 ? "Good effort, {$first}." : 'This one was tough.'));
    $sub = $score >= 70 ? 'You passed. Here is how it broke down.' : ($score >= 50 ? 'Close. Review the questions below and close the gaps.' : 'Review the answers below, then try again when you are ready.');
    $secs = ($attempt->started_at && $attempt->completed_at) ? (int) $attempt->started_at->diffInSeconds($attempt->completed_at, absolute: true) : null;
    $took = $secs === null ? '—' : ($secs >= 3600 ? gmdate('G:i:s', $secs) : gmdate('i:s', $secs));
@endphp

<div class="mx-auto max-w-3xl flex flex-col gap-6">
    <div class="flex items-center justify-between gap-3">
        <a href="{{ route('student.dashboard') }}" wire:navigate class="btn-line"><flux:icon.arrow-left class="size-4" /> Dashboard</a>
        <span class="truncate text-sm" style="color:#5d7270">{{ $attempt->exam->title }}</span>
    </div>

    {{-- Score hero --}}
    <div class="hero-ink p-8 text-center md:p-12" x-data x-init="{{ $score >= 70 ? 'window.qfConfetti($el)' : '' }}">
        <div class="relative z-10 flex flex-col items-center">
            <x-ring :pct="$score" :size="240" :stroke="18">
                <div class="font-display leading-none" style="color:#fff;font-size:5.5rem"><x-count-up :to="$score" /><span style="font-size:2.2rem;color:#8fb3ad">%</span></div>
            </x-ring>
            <span class="mt-6 inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-medium"
                  style="background:{{ $score >= 70 ? 'rgba(94,234,212,.16);color:#7ff0dc' : ($score >= 50 ? 'rgba(251,191,36,.16);color:#fcd34d' : 'rgba(255,148,148,.16);color:#ffb4b4') }}">
                {{ $score >= 70 ? 'Passed' : ($score >= 50 ? 'Needs improvement' : 'Not passed') }}
            </span>
            <h1 class="font-display mt-4 text-4xl md:text-5xl" style="color:#fff">{{ $headline }}</h1>
            <p class="mt-2 text-[15px]" style="color:#9fc3bd">{{ $sub }}</p>
            <div class="mt-8 grid w-full max-w-md grid-cols-3 gap-3">
                @foreach ([[$this->correctCount.'/'.$this->totalCount, 'Correct'], [$took, 'Time taken'], [$score.'%', 'Score']] as [$v, $l])
                    <div class="rounded-2xl px-3 py-4" style="background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1)">
                        <div class="font-display text-2xl" style="color:#fff">{{ $v }}</div>
                        <div class="mt-0.5 text-xs" style="color:#7fa9a3">{{ $l }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    @unless ($this->answersRevealed)
        <flux:callout variant="warning" icon="lock-closed">
            <flux:callout.heading>Correct answers are not available yet</flux:callout.heading>
            <flux:callout.text>Your teacher will release the correct answers and explanations later. Your score and which questions you got right are already shown.</flux:callout.text>
        </flux:callout>
    @endunless

    {{-- Recommended Practice --}}
    @if ($this->recommendations->isNotEmpty())
        <div class="bento-flat space-y-3">
            <div class="flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-5 text-teal-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" />
                </svg>
                <h2 class="font-display text-2xl text-charcoal-900">Recommended practice</h2>
            </div>
            <flux:text size="sm">Based on the questions you missed, here are similar questions from other exams to practice:</flux:text>
            <div class="space-y-2">
                @foreach ($this->recommendations as $rec)
                    <div class="flex items-start gap-3 p-3 rounded-lg bg-gray-50 border border-gray-100">
                        <span class="inline-flex items-center justify-center min-w-6 h-6 rounded-md bg-teal-50 text-teal-700 text-xs font-bold shrink-0 mt-0.5">
                            {{ $loop->iteration }}
                        </span>
                        <div class="flex-1 min-w-0">
                            <flux:text class="font-medium">{{ $rec->question }}</flux:text>
                            <flux:text size="sm" class="mt-0.5">
                                From: <a href="{{ route('student.exams.take', $rec->exam) }}" wire:navigate class="text-teal-600 hover:underline">{{ $rec->exam->title }}</a>
                            </flux:text>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Question Review --}}
    <h2 class="font-display text-3xl text-charcoal-900">Question review</h2>
    <div class="space-y-3">
        @foreach ($attempt->exam->questions as $index => $question)
            @php
                $given = $attempt->answers[$question->id] ?? null;
                $isAiGraded = is_array($given) && ($given['ai_graded'] ?? false);
                $isFlagged = is_array($given) && ($given['flagged'] ?? false);

                // Extract the display answer, handling all shapes
                if ($isAiGraded) {
                    $rawAnswer = $given['raw_answer'] ?? $given['value'] ?? null;
                } elseif (is_array($given)) {
                    $rawAnswer = $given['value'] ?? null;    // New { value, flagged } shape
                } else {
                    $rawAnswer = $given;                     // Old plain-string shape
                }

                $isCorrect = $isAiGraded
                    ? ($given['ai_score'] ?? 0) >= 50
                    : ($rawAnswer !== null && $rawAnswer !== '' &&
                        strtolower(trim($rawAnswer)) === strtolower(trim($question->correct_answer)));
            @endphp
            <div class="bento-flat space-y-2" style="{{ $isCorrect
            ? 'border-color:#86EFAC; background:#F0FDF4;'
            : 'border-color:#FCA5A5; background:#FFF5F5;' }}" wire:key="result-{{ $question->id }}">
                <div class="flex items-start justify-between gap-3">
                    <flux:text class="font-medium">{{ $index + 1 }}. {{ $question->question }}</flux:text>
                    <div class="flex items-center gap-2 shrink-0">
                        @if ($isFlagged)
                            <span title="Flagged for review">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-teal-600" fill="currentColor"
                                    viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z" />
                                </svg>
                            </span>
                        @endif
                        @if ($isAiGraded)
                            <flux:badge size="sm" color="zinc">AI graded</flux:badge>
                            <flux:badge size="sm" :color="$isCorrect ? 'green' : 'red'">
                                {{ $given['ai_score'] }}%
                            </flux:badge>
                        @else
                            <flux:badge size="sm" :color="$isCorrect ? 'green' : 'red'">
                                {{ $isCorrect ? 'Correct' : 'Incorrect' }}
                            </flux:badge>
                        @endif
                    </div>
                </div>

                <flux:text size="sm">Your answer: <strong>{{ $rawAnswer ?? 'Not answered' }}</strong></flux:text>

                @if (!$isCorrect && !$isAiGraded && $this->answersRevealed)
                    <flux:text size="sm" style="color:#16A34A;">
                        Correct answer: <strong>{{ $question->correct_answer }}</strong>
                    </flux:text>
                @endif

                @if ($isAiGraded && $this->answersRevealed)
                    @if ($given['ai_explanation'] ?? null)
                        <flux:text size="sm" style="color:#374151;">
                            {{ $given['ai_explanation'] }}
                        </flux:text>
                    @endif
                    @if (!$isCorrect && ($given['ai_suggestion'] ?? null))
                        <div class="pt-1 pl-3 border-l-2 border-amber-300">
                            <flux:text size="sm" style="color:#92400E;">
                                <strong>Tip:</strong> {{ $given['ai_suggestion'] }}
                            </flux:text>
                        </div>
                    @endif
                @endif
            </div>
        @endforeach
    </div>

    <div class="flex justify-center gap-3 flex-wrap">
        <flux:button variant="primary" icon="arrow-down-tray" :href="route('student.attempts.pdf', $attempt)">
            Download PDF
        </flux:button>
        <flux:button variant="outline" wire:click="requestExport" wire:loading.attr="disabled"
            wire:target="requestExport">
            <span wire:loading.remove wire:target="requestExport" class="inline-flex items-center gap-1">
                <flux:icon.envelope class="size-4" />
                Email me the PDF
            </span>
            <span wire:loading wire:target="requestExport" class="inline-flex items-center gap-1">
                <svg class="animate-spin size-4" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>
                Queuing…
            </span>
        </flux:button>
        @if ($attempt->exam->leaderboard_enabled)
            <flux:button variant="outline" icon="chart-bar" :href="route('student.exams.leaderboard', $attempt->exam)"
                wire:navigate>
                View Leaderboard
            </flux:button>
        @endif
        <flux:button variant="primary" :href="route('student.dashboard')" wire:navigate>
            Back to Dashboard
        </flux:button>
    </div>
</div>