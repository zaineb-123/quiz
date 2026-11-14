<?php

namespace App\Models;

use Database\Factories\ExamFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Exam extends Model
{
    /** @use HasFactory<ExamFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'time_limit',
        'published_at',
        'leaderboard_enabled',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'time_limit' => 'integer',
            'leaderboard_enabled' => 'boolean',
        ];
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->isPast();
    }

    /**
     * Exams a student can currently start: published AND assigned (inside its
     * availability window) to a classroom the student has joined.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $student): void
    {
        $query
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereHas('assignments', function (Builder $assignment) use ($student) {
                $assignment->openNow()->whereHas(
                    'classroom.students',
                    fn (Builder $students) => $students->where('users.id', $student->getKey()),
                );
            });
    }

    /**
     * Assignments that reach the given student through a classroom they belong to.
     *
     * @return HasMany<ExamAssignment, $this>
     */
    public function assignmentsFor(User $student): HasMany
    {
        return $this->assignments()->whereHas(
            'classroom.students',
            fn (Builder $students) => $students->where('users.id', $student->getKey()),
        );
    }

    /** Published and assigned to one of the student's classrooms (ignores the time window). */
    public function isAssignedTo(User $student): bool
    {
        return $this->isPublished() && $this->assignmentsFor($student)->exists();
    }

    /** Like isAssignedTo(), but the assignment window must also be open right now. */
    public function isOpenFor(User $student): bool
    {
        return $this->isPublished() && $this->assignmentsFor($student)->openNow()->exists();
    }

    /** @return HasMany<ExamAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(ExamAssignment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Question, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('order');
    }

    /** @return HasMany<Attempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }
}
