<?php

use App\Models\Classroom;
use App\Models\ExamAssignment;
use App\Services\ClassroomProgressService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Class')] class extends Component {
    public Classroom $classroom;

    // Subject form
    public string $subjectName = '';

    // Assign-exam form
    public ?int $examId = null;
    public ?int $subjectId = null;
    public ?string $opensAt = null;
    public ?string $closesAt = null;
    public ?string $revealAt = null;

    public function mount(): void
    {
        abort_unless(Gate::allows('manage', $this->classroom), 403);
    }

    // ── Join code ───────────────────────────────────────────────────────

    public function regenerateCode(): void
    {
        Gate::authorize('manage', $this->classroom);

        $this->classroom->regenerateJoinCode();

        $this->dispatch('toast', variant: 'success', heading: 'New code generated', text: 'The old code no longer works.');
    }

    public function toggleJoining(): void
    {
        Gate::authorize('manage', $this->classroom);

        $this->classroom->update(['join_enabled' => ! $this->classroom->join_enabled]);
    }

    // ── Students ────────────────────────────────────────────────────────

    public function removeStudent(int $userId): void
    {
        Gate::authorize('manage', $this->classroom);

        $this->classroom->students()->detach($userId);
        unset($this->progress);
    }

    // ── Subjects ────────────────────────────────────────────────────────

    public function addSubject(): void
    {
        Gate::authorize('manage', $this->classroom);

        $this->validate(['subjectName' => 'required|string|max:255']);

        $this->classroom->subjects()->create(['name' => $this->subjectName]);
        $this->subjectName = '';
        unset($this->subjects);
    }

    public function deleteSubject(int $subjectId): void
    {
        Gate::authorize('manage', $this->classroom);

        // Scoped to this classroom; exams assigned to it just become "unsorted".
        $this->classroom->subjects()->whereKey($subjectId)->delete();
        unset($this->subjects, $this->progress);
    }

    // ── Exam assignments ────────────────────────────────────────────────

    public function assignExam(): void
    {
        Gate::authorize('manage', $this->classroom);

        $this->validate([
            'examId' => 'required|integer',
            'subjectId' => 'nullable|integer',
            'opensAt' => 'nullable|date',
            'closesAt' => 'nullable|date',
            'revealAt' => 'nullable|date',
        ]);

        // Only the teacher's own exams can be assigned
        $exam = auth()->user()->exams()->find($this->examId);
        if (! $exam) {
            $this->addError('examId', 'Choose one of your own exams.');

            return;
        }

        // The subject must belong to this classroom
        if ($this->subjectId && ! $this->classroom->subjects()->whereKey($this->subjectId)->exists()) {
            $this->addError('subjectId', 'Choose a subject of this class.');

            return;
        }

        $opens = $this->opensAt ? Carbon::parse($this->opensAt) : null;
        $closes = $this->closesAt ? Carbon::parse($this->closesAt) : null;
        $reveal = $this->revealAt ? Carbon::parse($this->revealAt) : null;

        if ($opens && $closes && $closes->lessThanOrEqualTo($opens)) {
            $this->addError('closesAt', 'The deadline must be after the opening date.');

            return;
        }

        ExamAssignment::updateOrCreate(
            ['exam_id' => $exam->id, 'classroom_id' => $this->classroom->id],
            [
                'subject_id' => $this->subjectId ?: null,
                'opens_at' => $opens,
                'closes_at' => $closes,
                'reveal_answers_at' => $reveal,
            ],
        );

        $this->reset('examId', 'subjectId', 'opensAt', 'closesAt', 'revealAt');
        unset($this->progress);

        $this->dispatch('toast', variant: 'success', heading: 'Exam assigned', text: 'Students of this class can now see it once it is published.');
    }

    public function unassign(int $assignmentId): void
    {
        Gate::authorize('manage', $this->classroom);

        $this->classroom->assignments()->whereKey($assignmentId)->delete();
        unset($this->progress);
    }

    public function deleteClassroom(): void
    {
        Gate::authorize('manage', $this->classroom);

        $this->classroom->delete();

        $this->redirect(route('teacher.classrooms.index'), navigate: true);
    }

    // ── Data ────────────────────────────────────────────────────────────

    #[Computed]
    public function subjects(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->classroom->subjects()->orderBy('name')->get();
    }

    #[Computed]
    public function myExams(): \Illuminate\Database\Eloquent\Collection
    {
        return auth()->user()->exams()->orderBy('title')->get(['id', 'title', 'published_at']);
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function progress(): array
    {
        return app(ClassroomProgressService::class)->matrix($this->classroom);
    }
}; ?>

<div class="flex flex-col gap-8">
    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-4">
            <flux:button variant="ghost" icon="arrow-left" :href="route('teacher.classrooms.index')" wire:navigate />
            <div>
                <h1 class="font-display text-3xl text-charcoal-900 md:text-4xl">{{ $classroom->name }}</h1>
                @if ($classroom->academic_year)
                    <flux:text class="text-sm text-zinc-500">{{ $classroom->academic_year }}</flux:text>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-2">
            <flux:button variant="outline" icon="arrow-down-tray" :href="route('teacher.classrooms.report', $classroom)">
                Class report (PDF)
            </flux:button>
            <flux:modal.trigger name="delete-classroom">
                <flux:button variant="ghost" icon="trash" class="text-red-500" />
            </flux:modal.trigger>
        </div>
    </div>

    <flux:modal name="delete-classroom" class="md:w-96">
        <div class="space-y-4">
            <flux:heading size="lg">Delete class</flux:heading>
            <flux:text>
                Delete <strong>{{ $classroom->name }}</strong>? Students lose access to its exams.
                Your exams and the students' attempts are kept.
            </flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close>
                <flux:button variant="danger" wire:click="deleteClassroom">Delete</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Join code --}}
    <div class="bento-flat p-5 flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:text class="text-sm text-zinc-500">Join code — give it to your students</flux:text>
            <div
                x-data="{ copied: false }"
                class="mt-1 flex items-center gap-3"
            >
                <span class="font-mono text-3xl font-bold tracking-widest text-teal-700">{{ $classroom->join_code }}</span>
                <flux:button
                    size="sm"
                    variant="ghost"
                    icon="document-duplicate"
                    x-on:click="navigator.clipboard.writeText('{{ $classroom->join_code }}'); copied = true; setTimeout(() => copied = false, 1500)"
                >
                    <span x-text="copied ? 'Copied!' : 'Copy'"></span>
                </flux:button>
            </div>
        </div>

        <div class="flex items-center gap-2">
            @if ($classroom->join_enabled)
                <flux:badge color="green">Joining open</flux:badge>
            @else
                <flux:badge color="zinc">Joining closed</flux:badge>
            @endif
            <flux:button size="sm" variant="outline" wire:click="toggleJoining">
                {{ $classroom->join_enabled ? 'Close joining' : 'Open joining' }}
            </flux:button>
            <flux:button
                size="sm"
                variant="outline"
                wire:click="regenerateCode"
                wire:confirm="Generate a new code? The current code will stop working (students already in the class stay)."
            >
                New code
            </flux:button>
        </div>
    </div>

    {{-- Subjects --}}
    <div class="bento-flat p-5 space-y-4">
        <flux:heading size="lg">Subjects</flux:heading>

        <form wire:submit="addSubject" class="flex items-start gap-3">
            <flux:field class="flex-1">
                <flux:input wire:model="subjectName" placeholder="e.g. Laravel, PHP, Databases…" />
                <flux:error name="subjectName" />
            </flux:field>
            <flux:button type="submit" variant="primary" icon="plus">Add</flux:button>
        </form>

        <div class="flex flex-wrap gap-2">
            @forelse ($this->subjects as $subject)
                <span class="inline-flex items-center gap-2 rounded-full border border-teal-200 bg-teal-50 px-3 py-1 text-sm text-teal-800" wire:key="subject-{{ $subject->id }}">
                    {{ $subject->name }}
                    <button
                        type="button"
                        class="text-teal-500 hover:text-red-500"
                        wire:click="deleteSubject({{ $subject->id }})"
                        wire:confirm="Delete this subject? Its exams stay assigned but become unsorted."
                        aria-label="Delete subject"
                    >&times;</button>
                </span>
            @empty
                <flux:text class="text-sm text-zinc-500">No subjects yet. Subjects are optional: they group exams for students.</flux:text>
            @endforelse
        </div>
    </div>

    {{-- Assign an exam --}}
    <div class="bento-flat p-5 space-y-5">
        <flux:heading size="lg">Assign an exam to this class</flux:heading>

        <form wire:submit="assignExam" class="grid gap-4 md:grid-cols-2">
            <flux:field>
                <flux:label>Exam</flux:label>
                <flux:select wire:model="examId" placeholder="Choose an exam…">
                    @foreach ($this->myExams as $exam)
                        <flux:select.option value="{{ $exam->id }}">
                            {{ $exam->title }}{{ $exam->isPublished() ? '' : ' (draft)' }}
                        </flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="examId" />
            </flux:field>

            <flux:field>
                <flux:label>Subject <flux:badge size="sm" color="zinc">Optional</flux:badge></flux:label>
                <flux:select wire:model="subjectId" placeholder="No subject">
                    @foreach ($this->subjects as $subject)
                        <flux:select.option value="{{ $subject->id }}">{{ $subject->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="subjectId" />
            </flux:field>

            <flux:field>
                <flux:label>Opens <flux:badge size="sm" color="zinc">Optional</flux:badge></flux:label>
                <flux:input type="datetime-local" wire:model="opensAt" />
                <flux:description>Blank = available as soon as the exam is published.</flux:description>
                <flux:error name="opensAt" />
            </flux:field>

            <flux:field>
                <flux:label>Deadline <flux:badge size="sm" color="zinc">Optional</flux:badge></flux:label>
                <flux:input type="datetime-local" wire:model="closesAt" />
                <flux:description>Blank = no deadline.</flux:description>
                <flux:error name="closesAt" />
            </flux:field>

            <flux:field class="md:col-span-2">
                <flux:label>Reveal correct answers <flux:badge size="sm" color="zinc">Optional</flux:badge></flux:label>
                <flux:input type="datetime-local" wire:model="revealAt" />
                <flux:description>
                    Blank = students see the correct answers right after submitting.
                    Set a date (e.g. the deadline) to stop early finishers from sharing the answers.
                </flux:description>
                <flux:error name="revealAt" />
            </flux:field>

            <div class="md:col-span-2 flex items-center justify-between">
                <flux:text class="text-sm text-zinc-500">
                    Re-assigning an exam already in this class updates its dates.
                    Students only see published exams.
                </flux:text>
                <flux:button type="submit" variant="primary" icon="check">Assign</flux:button>
            </div>
        </form>

        {{-- Assigned exams --}}
        @if ($this->progress['assignments']->isNotEmpty())
            <div class="space-y-2 pt-2">
                @foreach ($this->progress['assignments'] as $assignment)
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-zinc-100 bg-zinc-50 px-4 py-3" wire:key="assignment-{{ $assignment->id }}">
                        <div class="min-w-0">
                            <div class="font-medium">{{ $assignment->exam->title }}</div>
                            <div class="text-xs text-zinc-500">
                                {{ $assignment->subject?->name ?? 'No subject' }}
                                @if ($assignment->opens_at) · opens {{ $assignment->opens_at->format('d M, H:i') }} @endif
                                @if ($assignment->closes_at) · deadline {{ $assignment->closes_at->format('d M, H:i') }} @endif
                                @if ($assignment->reveal_answers_at) · answers {{ $assignment->reveal_answers_at->format('d M, H:i') }} @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            @if (! $assignment->exam->isPublished())
                                <flux:badge color="zinc" size="sm">Draft — publish it from My Exams</flux:badge>
                            @elseif ($assignment->hasClosed())
                                <flux:badge color="red" size="sm">Closed</flux:badge>
                            @elseif (! $assignment->hasOpened())
                                <flux:badge color="yellow" size="sm">Scheduled</flux:badge>
                            @else
                                <flux:badge color="green" size="sm">Open</flux:badge>
                            @endif
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="x-mark"
                                class="text-red-500"
                                wire:click="unassign({{ $assignment->id }})"
                                wire:confirm="Remove this exam from the class?"
                            />
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Student progress --}}
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">Students &amp; progress ({{ count($this->progress['rows']) }})</flux:heading>
            @if ($this->progress['class_average'] !== null)
                <flux:badge color="teal">Class average: {{ $this->progress['class_average'] }}%</flux:badge>
            @endif
        </div>

        @if (count($this->progress['rows']) === 0)
            <div class="empty-state rounded-xl border border-dashed p-10 text-center" style="border-color:var(--color-border-hover)">
                <flux:text>
                    No student has joined yet. Share the code
                    <strong class="font-mono">{{ $classroom->join_code }}</strong> with your class.
                </flux:text>
            </div>
        @else
            <div class="overflow-x-auto">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Student</flux:table.column>
                        @foreach ($this->progress['assignments'] as $assignment)
                            <flux:table.column>{{ $assignment->exam->title }}</flux:table.column>
                        @endforeach
                        <flux:table.column>Average</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->progress['rows'] as $row)
                            <flux:table.row :key="'student-'.$row['student']->id">
                                <flux:table.cell variant="strong">
                                    {{ $row['student']->name }}
                                    <div class="text-xs font-normal text-zinc-500">{{ $row['student']->email }}</div>
                                </flux:table.cell>

                                @foreach ($this->progress['assignments'] as $assignment)
                                    @php $cell = $row['cells'][$assignment->id]; @endphp
                                    <flux:table.cell>
                                        @if ($cell['status'] === 'completed')
                                            <span class="font-semibold {{ $cell['best'] >= 70 ? 'score-pass' : ($cell['best'] >= 50 ? 'score-warn' : 'score-fail') }}">
                                                {{ $cell['best'] }}%
                                            </span>
                                            @if ($cell['attempts'] > 1)
                                                <span class="text-xs text-zinc-400">({{ $cell['attempts'] }} tries)</span>
                                            @endif
                                        @elseif ($cell['status'] === 'in_progress')
                                            <flux:badge color="yellow" size="sm">In progress</flux:badge>
                                        @else
                                            <span class="text-zinc-400">—</span>
                                        @endif
                                    </flux:table.cell>
                                @endforeach

                                <flux:table.cell>
                                    @if ($row['average'] !== null)
                                        <span class="font-semibold {{ $row['average'] >= 70 ? 'score-pass' : ($row['average'] >= 50 ? 'score-warn' : 'score-fail') }}">
                                            {{ $row['average'] }}%
                                        </span>
                                    @else
                                        <span class="text-zinc-400">—</span>
                                    @endif
                                </flux:table.cell>

                                <flux:table.cell>
                                    <flux:button
                                        size="sm"
                                        variant="ghost"
                                        icon="user-minus"
                                        class="text-red-500"
                                        wire:click="removeStudent({{ $row['student']->id }})"
                                        wire:confirm="Remove this student from the class?"
                                    />
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
            <flux:text class="text-xs text-zinc-500">Scores show each student's best completed attempt.</flux:text>
        @endif
    </div>
</div>
