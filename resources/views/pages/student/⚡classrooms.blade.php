<?php

use App\Models\Classroom;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('My Classes')] class extends Component {
    public string $code = '';

    public function join(): void
    {
        $this->validate(['code' => 'required|string|max:30']);

        $user = auth()->user();
        $limiterKey = 'join-classroom:'.$user->id;

        // Slow down code guessing
        if (RateLimiter::tooManyAttempts($limiterKey, 8)) {
            $this->addError('code', 'Too many attempts. Please wait a minute and try again.');

            return;
        }

        $classroom = Classroom::query()
            ->where('join_code', Classroom::normalizeCode($this->code))
            ->where('join_enabled', true)
            ->first();

        if (! $classroom) {
            RateLimiter::hit($limiterKey, 60);
            // Same message whether the code is wrong or joining is closed: don't reveal valid codes.
            $this->addError('code', 'This code is invalid, or the class is not accepting new students.');

            return;
        }

        if (! $classroom->hasStudent($user)) {
            $classroom->students()->attach($user->id, ['joined_at' => now()]);
        }

        $this->redirect(route('student.classrooms.show', $classroom), navigate: true);
    }

    /** @return Collection<int, Classroom> */
    #[Computed]
    public function classrooms(): Collection
    {
        return auth()->user()->enrolledClassrooms()
            ->with('teacher:id,name')
            ->withCount([
                'assignments as open_exams_count' => fn (Builder $q) => $q
                    ->openNow()
                    ->whereHas('exam', fn (Builder $e) => $e
                        ->whereNotNull('published_at')
                        ->where('published_at', '<=', now())),
            ])
            ->orderBy('name')
            ->get();
    }
}; ?>

<div class="flex flex-col gap-8">
    <div>
        <h1 class="font-display text-3xl text-charcoal-900 md:text-4xl">My Classes</h1>
        <flux:text class="mt-1">Join your teacher's class with its code to see the subjects and exams they publish for you.</flux:text>
    </div>

    <div class="bento-flat p-5">
        <flux:heading size="lg" class="mb-3">Join a class</flux:heading>
        <form wire:submit="join" class="flex items-start gap-3">
            <flux:field class="flex-1">
                <flux:input
                    wire:model="code"
                    placeholder="Enter the code, e.g. K7M2QX9A"
                    class="font-mono uppercase tracking-widest"
                    autocomplete="off"
                />
                <flux:error name="code" />
            </flux:field>
            <flux:button type="submit" variant="primary" icon="arrow-right-end-on-rectangle">Join</flux:button>
        </form>
    </div>

    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        @forelse ($this->classrooms as $classroom)
            <a
                href="{{ route('student.classrooms.show', $classroom) }}"
                wire:navigate
                wire:key="classroom-{{ $classroom->id }}"
                class="bento-card !p-0 overflow-hidden flex flex-col"
            >
                <div class="cover cover-{{ $loop->index % 3 }}" style="height:64px;background:{{ ['linear-gradient(135deg,#2dd4bf,#0d9488)','linear-gradient(135deg,#ffb4a8,#ff8f8f)','linear-gradient(135deg,#fcd34d,#f59e0b)'][$loop->index % 3] }}"></div>
                <div class="p-6 flex flex-1 flex-col gap-3">
                <flux:heading size="lg">{{ $classroom->name }}</flux:heading>
                <flux:text class="text-sm text-zinc-500">
                    {{ $classroom->teacher->name }}@if ($classroom->academic_year) · {{ $classroom->academic_year }}@endif
                </flux:text>
                <div class="mt-auto pt-2">
                    @if ($classroom->open_exams_count > 0)
                        <flux:badge color="green" size="sm">
                            {{ $classroom->open_exams_count }} {{ \Illuminate\Support\Str::plural('exam', $classroom->open_exams_count) }} open
                        </flux:badge>
                    @else
                        <flux:badge color="zinc" size="sm">No exam open</flux:badge>
                    @endif
                </div>
            </div>
            </a>
        @empty
            <div class="empty-state col-span-3 rounded-xl border border-dashed p-12 text-center" style="border-color:var(--color-border-hover)">
                <flux:text>You haven't joined any class yet. Ask your teacher for the code.</flux:text>
            </div>
        @endforelse
    </div>
</div>
