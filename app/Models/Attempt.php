<?php

namespace App\Models;

use Database\Factories\AttemptFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attempt extends Model
{
    /** @use HasFactory<AttemptFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'exam_id',
        'user_id',
        'answers',
        'score',
        'started_at',
        'completed_at',
        'embedding',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'score' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'embedding' => 'array',
        ];
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * Number of questions answered correctly (exact match for MC/TF, AI score >= 50 for short answers).
     * Handles every historical answer shape: plain string, { value, flagged } and AI-graded arrays.
     */
    public function correctCount(): int
    {
        $correct = 0;

        foreach ($this->exam->questions as $question) {
            $given = $this->answers[$question->id] ?? null;

            if ($given === null) {
                continue;
            }

            if (is_array($given)) {
                if ($given['ai_graded'] ?? false) {
                    if (($given['ai_score'] ?? 0) >= 50) {
                        $correct++;
                    }

                    continue;
                }

                $given = $given['value'] ?? null;
            }

            if (
                $given !== null && $given !== '' &&
                strtolower(trim((string) $given)) === strtolower(trim((string) $question->correct_answer))
            ) {
                $correct++;
            }
        }

        return $correct;
    }

    /**
     * Whether the student may see correct answers and explanations for this attempt.
     * Controlled per classroom by the teacher (ExamAssignment::reveal_answers_at).
     * Without any assignment (legacy exams, previews) answers are always revealed.
     */
    public function answersRevealed(): bool
    {
        $assignments = $this->exam->assignmentsFor($this->student)->get();

        if ($assignments->isEmpty()) {
            return true;
        }

        return $assignments->contains(fn (ExamAssignment $assignment) => $assignment->answersRevealed());
    }

    /** @param Builder<self> $query */
    public function scopeCompleted(Builder $query): void
    {
        $query->whereNotNull('completed_at');
    }

    /** @return BelongsTo<Exam, $this> */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /** @return BelongsTo<User, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
