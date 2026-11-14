<?php

namespace App\Services;

use App\Models\Attempt;
use App\Models\Classroom;
use App\Models\ExamAssignment;
use Illuminate\Support\Collection;

/**
 * Builds the student × exam progress matrix of a classroom.
 * Used by the teacher dashboard and by the class report PDF so both always agree.
 *
 * For each student and each assigned exam we report the BEST completed score
 * (students may retake exams), or "in_progress" / "not_started".
 */
class ClassroomProgressService
{
    /**
     * @return array{
     *     assignments: Collection<int, ExamAssignment>,
     *     rows: list<array{student: \App\Models\User, cells: array<int, array{status: string, best: int|null, attempts: int}>, average: int|null, completed: int}>,
     *     exam_stats: array<int, array{average: int|null, completed: int, pass_rate: int|null}>,
     *     class_average: int|null
     * }
     */
    public function matrix(Classroom $classroom): array
    {
        $assignments = $classroom->assignments()
            ->with(['exam', 'subject'])
            ->whereHas('exam')
            ->orderBy('opens_at')
            ->orderBy('id')
            ->get();

        $students = $classroom->students()->orderBy('name')->get();

        $attempts = Attempt::query()
            ->whereIn('exam_id', $assignments->pluck('exam_id'))
            ->whereIn('user_id', $students->pluck('id'))
            ->get(['id', 'exam_id', 'user_id', 'score', 'completed_at'])
            ->groupBy(fn (Attempt $a) => $a->user_id.':'.$a->exam_id);

        $rows = [];
        $perExamScores = [];

        foreach ($students as $student) {
            $cells = [];
            $bestScores = [];

            foreach ($assignments as $assignment) {
                $mine = $attempts->get($student->id.':'.$assignment->exam_id, collect());
                $completed = $mine->filter(fn (Attempt $a) => $a->completed_at !== null);

                if ($completed->isNotEmpty()) {
                    $best = (int) $completed->max('score');
                    $cells[$assignment->id] = ['status' => 'completed', 'best' => $best, 'attempts' => $mine->count()];
                    $bestScores[] = $best;
                    $perExamScores[$assignment->id][] = $best;
                } elseif ($mine->isNotEmpty()) {
                    $cells[$assignment->id] = ['status' => 'in_progress', 'best' => null, 'attempts' => $mine->count()];
                } else {
                    $cells[$assignment->id] = ['status' => 'not_started', 'best' => null, 'attempts' => 0];
                }
            }

            $rows[] = [
                'student' => $student,
                'cells' => $cells,
                'average' => $bestScores === [] ? null : (int) round(array_sum($bestScores) / count($bestScores)),
                'completed' => count($bestScores),
            ];
        }

        $examStats = [];
        foreach ($assignments as $assignment) {
            $scores = $perExamScores[$assignment->id] ?? [];
            $examStats[$assignment->id] = [
                'average' => $scores === [] ? null : (int) round(array_sum($scores) / count($scores)),
                'completed' => count($scores),
                'pass_rate' => $scores === []
                    ? null
                    : (int) round(count(array_filter($scores, fn (int $s) => $s >= 50)) / count($scores) * 100),
            ];
        }

        $allScores = array_merge(...array_values($perExamScores ?: [[]]));

        return [
            'assignments' => $assignments,
            'rows' => $rows,
            'exam_stats' => $examStats,
            'class_average' => $allScores === [] ? null : (int) round(array_sum($allScores) / count($allScores)),
        ];
    }
}
