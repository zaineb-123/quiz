<?php

use App\Models\Classroom;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Class')] class extends Component {
    public Classroom $classroom;

    public function mount(): void
    {
        // Only students who joined this class can open it
        abort_unless(Gate::allows('view', $this->classroom), 403);
    }

    /**
     * Published exams assigned to this class, grouped by subject name.
     *
     * @return Collection<string, Collection<int, \App\Models\ExamAssignment>>
     */
    #[Computed]
    public function assignmentsBySubject(): Collection
    {
        return $this->classroom->assignments()
            ->whereHas('exam', fn (Builder $q) => $q
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now()))
            ->with(['exam' => fn ($q) => $q->withCount('questions'), 'subject'])
            ->get()
            ->sortBy(fn ($a) => $a->subject?->name ?? "\u{FFFF}")
            ->groupBy(fn ($a) => $a->subject?->name ?? 'General');
    }

    /**
     * The student's attempts on this class's exams, keyed by exam id.
     *
     * @return Collection<int, Collection<int, \App\Models\Attempt>>
     */
    #[Computed]
    public function myAttempts(): Collection
    {
        $examIds = $this->assignmentsBySubject->flatten()->pluck('exam_id');

        return auth()->user()->attempts()
            ->whereIn('exam_id', $examIds)
            ->orderByDesc('id')
            ->get()
            ->groupBy('exam_id');
    }
}; ?>

<div class="flex flex-col gap-8">
    <div class="flex items-center gap-4">
        <flux:button variant="ghost" icon="arrow-left" :href="route('student.classrooms')" wire:navigate />
        <div>
            <h1 class="font-display text-3xl text-charcoal-900 md:text-4xl">{{ $classroom->name }}</h1>
            <flux:text class="text-sm text-zinc-500">
                {{ $classroom->teacher->name }}@if ($classroom->academic_year) · {{ $classroom->academic_year }}@endif
            </flux:text>
        </div>
    </div>

    @if ($classroom->description)
        <flux:text>{{ $classroom->description }}</flux:text>
    @endif

    @forelse ($this->assignmentsBySubject as $subjectName => $assignments)
        <div class="space-y-3" wire:key="subject-{{ $loop->index }}">
            <flux:heading size="lg">{{ $subjectName }}</flux:heading>

            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($assignments as $assignment)
                    @php
                        $exam = $assignment->exam;
                        $attempts = $this->myAttempts->get($exam->id, collect());
                        $inProgress = $attempts->first(fn ($a) => ! $a->isCompleted());
                        $completed = $attempts->filter(fn ($a) => $a->isCompleted());
                        $best = $completed->max('score');
                        $lastCompleted = $completed->first();
                    @endphp
                    <div class="bento-card !p-0 overflow-hidden flex flex-col" wire:key="assignment-{{ $assignment->id }}">
                        <div class="cover cover-{{ $loop->index % 3 }}" style="height:56px;background:{{ ['linear-gradient(135deg,#2dd4bf,#0d9488)','linear-gradient(135deg,#ffb4a8,#ff8f8f)','linear-gradient(135deg,#fcd34d,#f59e0b)'][$loop->index % 3] }}"></div>
                <div class="p-6 flex flex-1 flex-col gap-3">
                        <div>
                            <flux:heading size="lg">{{ $exam->title }}</flux:heading>
                            @if ($exam->description)
                                <flux:text class="mt-1 line-clamp-2 text-sm text-zinc-500">{{ $exam->description }}</flux:text>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-3 text-sm text-zinc-500">
                            <span class="flex items-center gap-1">
                                <flux:icon.list-bullet class="size-4" /> {{ $exam->questions_count }} questions
                            </span>
                            @if ($exam->time_limit)
                                <span class="flex items-center gap-1">
                                    <flux:icon.clock class="size-4" /> {{ $exam->time_limit }} min
                                </span>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            @if ($assignment->hasClosed())
                                <flux:badge color="red" size="sm">Closed</flux:badge>
                            @elseif (! $assignment->hasOpened())
                                <flux:badge color="yellow" size="sm">Opens {{ $assignment->opens_at->format('d M, H:i') }}</flux:badge>
                            @else
                                <flux:badge color="green" size="sm">Open</flux:badge>
                            @endif

                            @if ($assignment->closes_at && ! $assignment->hasClosed())
                                <span class="text-xs text-zinc-500">Deadline {{ $assignment->closes_at->format('d M, H:i') }}</span>
                            @endif

                            @if ($best !== null)
                                <flux:badge :color="$best >= 70 ? 'green' : ($best >= 50 ? 'yellow' : 'red')" size="sm">
                                    Best: {{ $best }}%
                                </flux:badge>
                            @endif
                        </div>

                        <div class="mt-auto flex flex-wrap gap-2">
                            @if ($assignment->isOpen())
                                <flux:button variant="primary" :href="route('student.exams.take', $exam)" wire:navigate>
                                    {{ $inProgress ? 'Continue' : ($completed->isNotEmpty() ? 'Retake' : 'Start Exam') }}
                                </flux:button>
                            @endif

                            @if ($lastCompleted)
                                <flux:button variant="outline" :href="route('student.attempts.results', $lastCompleted)" wire:navigate>
                                    View Results
                                </flux:button>
                            @endif
                        </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="empty-state rounded-xl border border-dashed p-12 text-center" style="border-color:var(--color-border-hover)">
            <flux:text>Your teacher hasn't published any exam in this class yet.</flux:text>
        </div>
    @endforelse
</div>
