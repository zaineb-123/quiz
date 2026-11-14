<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Publishes an exam to one classroom, with its own availability window.
 * The same Exam can be assigned to several classrooms with different dates.
 */
class ExamAssignment extends Model
{
    protected $fillable = [
        'exam_id',
        'classroom_id',
        'subject_id',
        'opens_at',
        'closes_at',
        'reveal_answers_at',
    ];

    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'reveal_answers_at' => 'datetime',
        ];
    }

    /** Assignments whose window is currently open (started and not yet closed). */
    public function scopeOpenNow(Builder $query): void
    {
        $query
            ->where(fn (Builder $q) => $q->whereNull('opens_at')->orWhere('opens_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('closes_at')->orWhere('closes_at', '>=', now()));
    }

    public function hasOpened(): bool
    {
        return $this->opens_at === null || $this->opens_at->isPast();
    }

    public function hasClosed(): bool
    {
        return $this->closes_at !== null && $this->closes_at->isPast();
    }

    public function isOpen(): bool
    {
        return $this->hasOpened() && ! $this->hasClosed();
    }

    public function answersRevealed(): bool
    {
        return $this->reveal_answers_at === null || $this->reveal_answers_at->isPast();
    }

    /** @return BelongsTo<Exam, $this> */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /** @return BelongsTo<Classroom, $this> */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
