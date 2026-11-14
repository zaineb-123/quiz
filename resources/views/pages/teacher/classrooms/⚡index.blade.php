<?php

use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('My Classes')] class extends Component {
    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string|max:20')]
    public string $academic_year = '';

    #[Validate('nullable|string|max:2000')]
    public string $description = '';

    public function create(): void
    {
        $this->validate();

        // The join code is generated automatically by Classroom::booted()
        $classroom = auth()->user()->classrooms()->create([
            'name' => $this->name,
            'academic_year' => $this->academic_year ?: null,
            'description' => $this->description ?: null,
        ]);

        $this->redirect(route('teacher.classrooms.show', $classroom), navigate: true);
    }

    /** @return Collection<int, \App\Models\Classroom> */
    #[Computed]
    public function classrooms(): Collection
    {
        return auth()->user()->classrooms()
            ->withCount(['students', 'subjects', 'assignments'])
            ->latest()
            ->get();
    }
}; ?>

<div class="flex flex-col gap-8">
    <div>
        <h1 class="font-display text-3xl text-charcoal-900 md:text-4xl">My Classes</h1>
        <flux:text class="mt-1">
            Create a class, share its code with your students, then assign your exams to it.
            Students only see the exams of the classes they joined.
        </flux:text>
    </div>

    {{-- Create a class --}}
    <div class="bento-flat p-5">
        <flux:heading size="lg" class="mb-4">New class</flux:heading>
        <form wire:submit="create" class="grid gap-4 md:grid-cols-3">
            <flux:field class="md:col-span-2">
                <flux:label>Class name</flux:label>
                <flux:input wire:model="name" placeholder="e.g. Web Development — L2" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>Academic year <flux:badge size="sm" color="zinc">Optional</flux:badge></flux:label>
                <flux:input wire:model="academic_year" placeholder="2026-2027" />
                <flux:error name="academic_year" />
            </flux:field>

            <flux:field class="md:col-span-3">
                <flux:label>Description <flux:badge size="sm" color="zinc">Optional</flux:badge></flux:label>
                <flux:textarea wire:model="description" rows="2" placeholder="What is this class about?" />
                <flux:error name="description" />
            </flux:field>

            <div class="md:col-span-3 flex justify-end">
                <flux:button variant="primary" type="submit" icon="plus">Create class</flux:button>
            </div>
        </form>
    </div>

    {{-- My classes --}}
    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        @forelse ($this->classrooms as $classroom)
            <a
                href="{{ route('teacher.classrooms.show', $classroom) }}"
                wire:navigate
                wire:key="classroom-{{ $classroom->id }}"
                class="bento-card !p-0 overflow-hidden flex flex-col"
            >
                <div class="cover cover-{{ $loop->index % 3 }}" style="height:64px;background:{{ ['linear-gradient(135deg,#2dd4bf,#0d9488)','linear-gradient(135deg,#ffb4a8,#ff8f8f)','linear-gradient(135deg,#fcd34d,#f59e0b)'][$loop->index % 3] }}"></div>
                <div class="p-6 flex flex-1 flex-col gap-3">
                <div>
                    <flux:heading size="lg">{{ $classroom->name }}</flux:heading>
                    @if ($classroom->academic_year)
                        <flux:text class="text-sm text-zinc-500">{{ $classroom->academic_year }}</flux:text>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-3 text-sm text-zinc-500">
                    <span>{{ $classroom->students_count }} {{ \Illuminate\Support\Str::plural('student', $classroom->students_count) }}</span>
                    <span style="color:#bfd3cf">/</span>
                    <span>{{ $classroom->subjects_count }} {{ \Illuminate\Support\Str::plural('subject', $classroom->subjects_count) }}</span>
                    <span style="color:#bfd3cf">/</span>
                    <span>{{ $classroom->assignments_count }} {{ \Illuminate\Support\Str::plural('exam', $classroom->assignments_count) }}</span>
                </div>

                <div class="mt-auto flex items-center gap-2">
                    <flux:badge color="teal" size="sm">Code: {{ $classroom->join_code }}</flux:badge>
                    @unless ($classroom->join_enabled)
                        <flux:badge color="zinc" size="sm">Joining closed</flux:badge>
                    @endunless
                </div>
            </div>
            </a>
        @empty
            <div class="empty-state col-span-3 rounded-xl border border-dashed p-12 text-center" style="border-color:var(--color-border-hover)">
                <flux:text>You have no classes yet. Create your first one above.</flux:text>
            </div>
        @endforelse
    </div>
</div>
