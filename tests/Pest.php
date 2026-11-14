<?php

use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Embeddings;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => Embeddings::fake())
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Give a student access to an exam the way the app does it: put them in one of the
 * exam owner's classrooms and assign the exam to that classroom.
 *
 * @param  array<string, mixed>  $assignment  Overrides for the assignment (opens_at, closes_at, reveal_answers_at, subject_id)
 */
function giveStudentAccess(User $student, Exam $exam, array $assignment = []): ExamAssignment
{
    $classroom = Classroom::query()->where('user_id', $exam->user_id)->first()
        ?? Classroom::factory()->create(['user_id' => $exam->user_id]);

    $classroom->students()->syncWithoutDetaching([$student->id]);

    return ExamAssignment::updateOrCreate(
        ['exam_id' => $exam->id, 'classroom_id' => $classroom->id],
        $assignment,
    );
}
