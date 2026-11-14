<?php

use App\Models\Attempt;
use App\Models\Classroom;
use App\Models\Exam;
use App\Models\ExamAssignment;
use App\Models\Question;
use App\Models\User;
use App\Services\ClassroomProgressService;
use Livewire\Livewire;

// ─── Creating classes & join codes ──────────────────────────────────────────

it('teacher can create a class and gets a unique join code', function () {
    $teacher = User::factory()->teacher()->create();

    Livewire::actingAs($teacher)
        ->test('pages::teacher.classrooms.index')
        ->set('name', 'Web Development — L2')
        ->call('create')
        ->assertHasNoErrors();

    $classroom = Classroom::firstOrFail();

    expect($classroom->user_id)->toBe($teacher->id)
        ->and($classroom->join_code)->toHaveLength(8)
        ->and($classroom->join_enabled)->toBeTrue();
});

it('requires a class name', function () {
    $teacher = User::factory()->teacher()->create();

    Livewire::actingAs($teacher)
        ->test('pages::teacher.classrooms.index')
        ->set('name', '')
        ->call('create')
        ->assertHasErrors(['name' => 'required']);
});

it('generates different join codes for different classes', function () {
    $codes = Classroom::factory()->count(20)->create()->pluck('join_code');

    expect($codes->unique())->toHaveCount(20);
});

it('teacher only sees their own classes', function () {
    $teacher = User::factory()->teacher()->create();
    $other = User::factory()->teacher()->create();
    Classroom::factory()->for($teacher, 'teacher')->create(['name' => 'Mine']);
    Classroom::factory()->for($other, 'teacher')->create(['name' => 'Not Mine']);

    Livewire::actingAs($teacher)
        ->test('pages::teacher.classrooms.index')
        ->assertSee('Mine')
        ->assertDontSee('Not Mine');
});

// ─── Joining a class ────────────────────────────────────────────────────────

it('student joins a class with its code', function () {
    $student = User::factory()->student()->create();
    $classroom = Classroom::factory()->create();

    Livewire::actingAs($student)
        ->test('pages::student.classrooms')
        ->set('code', $classroom->join_code)
        ->call('join')
        ->assertHasNoErrors()
        ->assertRedirect(route('student.classrooms.show', $classroom));

    expect($classroom->hasStudent($student))->toBeTrue();
});

it('accepts a lower-case code with spaces or dashes', function () {
    $student = User::factory()->student()->create();
    $classroom = Classroom::factory()->create(['join_code' => 'ABCD2345']);

    Livewire::actingAs($student)
        ->test('pages::student.classrooms')
        ->set('code', ' abcd-2345 ')
        ->call('join')
        ->assertHasNoErrors();

    expect($classroom->hasStudent($student))->toBeTrue();
});

it('rejects an unknown code', function () {
    $student = User::factory()->student()->create();

    Livewire::actingAs($student)
        ->test('pages::student.classrooms')
        ->set('code', 'NOPE1234')
        ->call('join')
        ->assertHasErrors('code');

    expect($student->enrolledClassrooms()->count())->toBe(0);
});

it('rejects the code of a class that closed joining', function () {
    $student = User::factory()->student()->create();
    $classroom = Classroom::factory()->joinDisabled()->create();

    Livewire::actingAs($student)
        ->test('pages::student.classrooms')
        ->set('code', $classroom->join_code)
        ->call('join')
        ->assertHasErrors('code');

    expect($classroom->hasStudent($student))->toBeFalse();
});

it('joining twice does not duplicate the enrolment', function () {
    $student = User::factory()->student()->create();
    $classroom = Classroom::factory()->create();

    foreach (range(1, 2) as $_) {
        Livewire::actingAs($student)
            ->test('pages::student.classrooms')
            ->set('code', $classroom->join_code)
            ->call('join');
    }

    expect($classroom->students()->count())->toBe(1);
});

it('regenerating the code makes the old one useless', function () {
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();
    $classroom = Classroom::factory()->for($teacher, 'teacher')->create();
    $oldCode = $classroom->join_code;

    Livewire::actingAs($teacher)
        ->test('pages::teacher.classrooms.show', ['classroom' => $classroom])
        ->call('regenerateCode');

    expect($classroom->fresh()->join_code)->not->toBe($oldCode);

    Livewire::actingAs($student)
        ->test('pages::student.classrooms')
        ->set('code', $oldCode)
        ->call('join')
        ->assertHasErrors('code');
});

it('a teacher cannot reach the student join page', function () {
    $teacher = User::factory()->teacher()->create();

    $this->actingAs($teacher)->get(route('student.classrooms'))->assertForbidden();
});

// ─── Who sees which exam ────────────────────────────────────────────────────

it('student dashboard only lists exams assigned to joined classes', function () {
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();

    $mine = Exam::factory()->published()->for($teacher)->create(['title' => 'Exam Of My Class']);
    $theirs = Exam::factory()->published()->for($teacher)->create(['title' => 'Exam Of Another Class']);
    $unassigned = Exam::factory()->published()->for($teacher)->create(['title' => 'Exam Assigned Nowhere']);

    $myClass = Classroom::factory()->for($teacher, 'teacher')->create();
    $otherClass = Classroom::factory()->for($teacher, 'teacher')->create();
    $myClass->students()->attach($student->id);

    ExamAssignment::create(['exam_id' => $mine->id, 'classroom_id' => $myClass->id]);
    ExamAssignment::create(['exam_id' => $theirs->id, 'classroom_id' => $otherClass->id]);

    Livewire::actingAs($student)
        ->test('pages::student.dashboard')
        ->assertSee('Exam Of My Class')
        ->assertDontSee('Exam Of Another Class')
        ->assertDontSee('Exam Assigned Nowhere');
});

it('student cannot take a published exam that is not assigned to their class', function () {
    $student = User::factory()->student()->create();
    $exam = Exam::factory()->published()->create();

    $this->actingAs($student)
        ->get(route('student.exams.take', $exam))
        ->assertForbidden();

    expect(Attempt::count())->toBe(0);
});

it('student cannot take an exam assigned only to another class', function () {
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();
    $exam = Exam::factory()->published()->for($teacher)->create();

    $otherClass = Classroom::factory()->for($teacher, 'teacher')->create();
    ExamAssignment::create(['exam_id' => $exam->id, 'classroom_id' => $otherClass->id]);

    $this->actingAs($student)
        ->get(route('student.exams.take', $exam))
        ->assertForbidden();
});

it('student can take an exam assigned to their class', function () {
    $student = User::factory()->student()->create();
    $exam = Exam::factory()->published()->has(Question::factory()->count(2), 'questions')->create();
    giveStudentAccess($student, $exam);

    $this->actingAs($student)
        ->get(route('student.exams.take', $exam))
        ->assertOk();
});

it('a draft exam stays hidden even when assigned', function () {
    $student = User::factory()->student()->create();
    $exam = Exam::factory()->draft()->create();
    giveStudentAccess($student, $exam);

    $this->actingAs($student)
        ->get(route('student.exams.take', $exam))
        ->assertNotFound();
});

it('respects the assignment window', function () {
    $student = User::factory()->student()->create();
    $exam = Exam::factory()->published()->create();

    // Not open yet
    giveStudentAccess($student, $exam, ['opens_at' => now()->addDay()]);
    $this->actingAs($student)->get(route('student.exams.take', $exam))->assertForbidden();

    // Deadline passed
    giveStudentAccess($student, $exam, ['opens_at' => now()->subDays(3), 'closes_at' => now()->subDay()]);
    $this->actingAs($student)->get(route('student.exams.take', $exam))->assertForbidden();

    // Inside the window
    giveStudentAccess($student, $exam, ['opens_at' => now()->subDay(), 'closes_at' => now()->addDay()]);
    $this->actingAs($student)->get(route('student.exams.take', $exam))->assertOk();
});

it('the same exam can be assigned to two classes with different windows', function () {
    $teacher = User::factory()->teacher()->create();
    $exam = Exam::factory()->published()->for($teacher)->create();
    $classA = Classroom::factory()->for($teacher, 'teacher')->create();
    $classB = Classroom::factory()->for($teacher, 'teacher')->create();
    $inA = User::factory()->student()->create();
    $inB = User::factory()->student()->create();
    $classA->students()->attach($inA->id);
    $classB->students()->attach($inB->id);

    ExamAssignment::create(['exam_id' => $exam->id, 'classroom_id' => $classA->id]);
    ExamAssignment::create(['exam_id' => $exam->id, 'classroom_id' => $classB->id, 'closes_at' => now()->subHour()]);

    expect($exam->isOpenFor($inA))->toBeTrue()
        ->and($exam->isOpenFor($inB))->toBeFalse()
        ->and($exam->isAssignedTo($inB))->toBeTrue();
});

it('leaving a class removes access to its exams', function () {
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();
    $exam = Exam::factory()->published()->for($teacher)->create();
    $classroom = Classroom::factory()->for($teacher, 'teacher')->create();
    $classroom->students()->attach($student->id);
    ExamAssignment::create(['exam_id' => $exam->id, 'classroom_id' => $classroom->id]);

    expect($exam->isOpenFor($student))->toBeTrue();

    Livewire::actingAs($teacher)
        ->test('pages::teacher.classrooms.show', ['classroom' => $classroom])
        ->call('removeStudent', $student->id);

    expect($exam->isOpenFor($student))->toBeFalse();
});

it('a deleted class no longer gives access', function () {
    $student = User::factory()->student()->create();
    $exam = Exam::factory()->published()->create();
    $assignment = giveStudentAccess($student, $exam);

    expect($exam->isOpenFor($student))->toBeTrue();

    $assignment->classroom->delete();

    expect($exam->isOpenFor($student))->toBeFalse();
});

it('leaderboard is closed to students outside the class', function () {
    $student = User::factory()->student()->create();
    $exam = Exam::factory()->published()->create(['leaderboard_enabled' => true]);

    Livewire::actingAs($student)
        ->test('pages::student.leaderboard', ['exam' => $exam])
        ->assertStatus(403);
});

// ─── Class pages authorization ──────────────────────────────────────────────

it('a student cannot open a class they did not join', function () {
    $student = User::factory()->student()->create();
    $classroom = Classroom::factory()->create();

    Livewire::actingAs($student)
        ->test('pages::student.classroom', ['classroom' => $classroom])
        ->assertStatus(403);
});

it('a student can open the class they joined and sees its subjects', function () {
    $teacher = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();
    $classroom = Classroom::factory()->for($teacher, 'teacher')->create();
    $classroom->students()->attach($student->id);

    $subject = $classroom->subjects()->create(['name' => 'Databases']);
    $exam = Exam::factory()->published()->for($teacher)->create(['title' => 'SQL Basics']);
    ExamAssignment::create(['exam_id' => $exam->id, 'classroom_id' => $classroom->id, 'subject_id' => $subject->id]);

    Livewire::actingAs($student)
        ->test('pages::student.classroom', ['classroom' => $classroom])
        ->assertSee('Databases')
        ->assertSee('SQL Basics');
});

it("a teacher cannot manage another teacher's class", function () {
    $teacher = User::factory()->teacher()->create();
    $classroom = Classroom::factory()->create();

    Livewire::actingAs($teacher)
        ->test('pages::teacher.classrooms.show', ['classroom' => $classroom])
        ->assertStatus(403);
});

// ─── Subjects & assigning exams ─────────────────────────────────────────────

it('teacher can add and delete subjects', function () {
    $teacher = User::factory()->teacher()->create();
    $classroom = Classroom::factory()->for($teacher, 'teacher')->create();

    $component = Livewire::actingAs($teacher)
        ->test('pages::teacher.classrooms.show', ['classroom' => $classroom])
        ->set('subjectName', 'Laravel')
        ->call('addSubject')
        ->assertHasNoErrors();

    $subject = $classroom->subjects()->firstOrFail();
    expect($subject->name)->toBe('Laravel');

    $component->call('deleteSubject', $subject->id);
    expect($classroom->subjects()->count())->toBe(0);
});

it('teacher can assign one of their own exams with dates', function () {
    $teacher = User::factory()->teacher()->create();
    $classroom = Classroom::factory()->for($teacher, 'teacher')->create();
    $subject = $classroom->subjects()->create(['name' => 'PHP']);
    $exam = Exam::factory()->published()->for($teacher)->create();

    Livewire::actingAs($teacher)
        ->test('pages::teacher.classrooms.show', ['classroom' => $classroom])
        ->set('examId', $exam->id)
        ->set('subjectId', $subject->id)
        ->set('opensAt', now()->addDay()->format('Y-m-d\TH:i'))
        ->set('closesAt', now()->addDays(3)->format('Y-m-d\TH:i'))
        ->call('assignExam')
        ->assertHasNoErrors();

    $assignment = ExamAssignment::firstOrFail();

    expect($assignment->exam_id)->toBe($exam->id)
        ->and($assignment->classroom_id)->toBe($classroom->id)
        ->and($assignment->subject_id)->toBe($subject->id)
        ->and($assignment->opens_at)->not->toBeNull()
        ->and($assignment->closes_at->greaterThan($assignment->opens_at))->toBeTrue();
});

it("a teacher cannot assign someone else's exam", function () {
    $teacher = User::factory()->teacher()->create();
    $classroom = Classroom::factory()->for($teacher, 'teacher')->create();
    $foreignExam = Exam::factory()->published()->create();

    Livewire::actingAs($teacher)
        ->test('pages::teacher.classrooms.show', ['classroom' => $classroom])
        ->set('examId', $foreignExam->id)
        ->call('assignExam')
        ->assertHasErrors('examId');

    expect(ExamAssignment::count())->toBe(0);
});

it("a teacher cannot attach a subject of another class", function () {
    $teacher = User::factory()->teacher()->create();
    $classroom = Classroom::factory()->for($teacher, 'teacher')->create();
    $otherSubject = Classroom::factory()->for($teacher, 'teacher')->create()->subjects()->create(['name' => 'Elsewhere']);
    $exam = Exam::factory()->published()->for($teacher)->create();

    Livewire::actingAs($teacher)
        ->test('pages::teacher.classrooms.show', ['classroom' => $classroom])
        ->set('examId', $exam->id)
        ->set('subjectId', $otherSubject->id)
        ->call('assignExam')
        ->assertHasErrors('subjectId');
});

it('rejects a deadline that is before the opening date', function () {
    $teacher = User::factory()->teacher()->create();
    $classroom = Classroom::factory()->for($teacher, 'teacher')->create();
    $exam = Exam::factory()->published()->for($teacher)->create();

    Livewire::actingAs($teacher)
        ->test('pages::teacher.classrooms.show', ['classroom' => $classroom])
        ->set('examId', $exam->id)
        ->set('opensAt', now()->addDays(3)->format('Y-m-d\TH:i'))
        ->set('closesAt', now()->addDay()->format('Y-m-d\TH:i'))
        ->call('assignExam')
        ->assertHasErrors('closesAt');
});

it('assigning the same exam again updates the assignment instead of duplicating it', function () {
    $teacher = User::factory()->teacher()->create();
    $classroom = Classroom::factory()->for($teacher, 'teacher')->create();
    $exam = Exam::factory()->published()->for($teacher)->create();

    $component = Livewire::actingAs($teacher)
        ->test('pages::teacher.classrooms.show', ['classroom' => $classroom]);

    $component->set('examId', $exam->id)->call('assignExam');
    $component->set('examId', $exam->id)->set('closesAt', now()->addWeek()->format('Y-m-d\TH:i'))->call('assignExam');

    expect(ExamAssignment::count())->toBe(1)
        ->and(ExamAssignment::first()->closes_at)->not->toBeNull();
});

// ─── Teacher progress tracking ──────────────────────────────────────────────

it('builds the student × exam progress matrix with best scores', function () {
    $teacher = User::factory()->teacher()->create();
    $classroom = Classroom::factory()->for($teacher, 'teacher')->create();
    $alice = User::factory()->student()->create(['name' => 'Alice']);
    $bob = User::factory()->student()->create(['name' => 'Bob']);
    $classroom->students()->attach([$alice->id, $bob->id]);

    $exam1 = Exam::factory()->published()->for($teacher)->create();
    $exam2 = Exam::factory()->published()->for($teacher)->create();
    $a1 = ExamAssignment::create(['exam_id' => $exam1->id, 'classroom_id' => $classroom->id]);
    $a2 = ExamAssignment::create(['exam_id' => $exam2->id, 'classroom_id' => $classroom->id]);

    // Alice: two attempts on exam1 (best = 80), exam2 only started
    Attempt::factory()->completed()->for($exam1)->for($alice, 'student')->create(['score' => 50]);
    Attempt::factory()->completed()->for($exam1)->for($alice, 'student')->create(['score' => 80]);
    Attempt::factory()->for($exam2)->for($alice, 'student')->create(['completed_at' => null, 'score' => null]);

    // A student outside the class must not leak into the matrix
    $outsider = User::factory()->student()->create();
    Attempt::factory()->completed()->for($exam1)->for($outsider, 'student')->create(['score' => 10]);

    $matrix = app(ClassroomProgressService::class)->matrix($classroom);

    expect($matrix['rows'])->toHaveCount(2);

    $aliceRow = collect($matrix['rows'])->firstWhere('student.name', 'Alice');
    $bobRow = collect($matrix['rows'])->firstWhere('student.name', 'Bob');

    expect($aliceRow['cells'][$a1->id])->toMatchArray(['status' => 'completed', 'best' => 80, 'attempts' => 2])
        ->and($aliceRow['cells'][$a2->id]['status'])->toBe('in_progress')
        ->and($aliceRow['average'])->toBe(80)
        ->and($bobRow['cells'][$a1->id]['status'])->toBe('not_started')
        ->and($bobRow['average'])->toBeNull()
        ->and($matrix['exam_stats'][$a1->id]['average'])->toBe(80)
        ->and($matrix['class_average'])->toBe(80);
});

// ─── PDF exports ────────────────────────────────────────────────────────────

it('student downloads the PDF of their own completed attempt', function () {
    $student = User::factory()->student()->create();
    $exam = Exam::factory()->published()->has(Question::factory()->count(2), 'questions')->create();
    giveStudentAccess($student, $exam);
    $attempt = Attempt::factory()->completed()->for($exam)->for($student, 'student')->create(['score' => 50]);

    $this->actingAs($student)
        ->get(route('student.attempts.pdf', $attempt))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it("a student cannot download another student's PDF", function () {
    $owner = User::factory()->student()->create();
    $other = User::factory()->student()->create();
    $attempt = Attempt::factory()->completed()->for($owner, 'student')->create();

    $this->actingAs($other)
        ->get(route('student.attempts.pdf', $attempt))
        ->assertForbidden();
});

it('the PDF is not available while the attempt is still in progress', function () {
    $student = User::factory()->student()->create();
    $attempt = Attempt::factory()->for($student, 'student')->create(['completed_at' => null, 'score' => null]);

    $this->actingAs($student)
        ->get(route('student.attempts.pdf', $attempt))
        ->assertNotFound();
});

it('guests are redirected to login for the PDF', function () {
    $attempt = Attempt::factory()->completed()->create();

    $this->get(route('student.attempts.pdf', $attempt))->assertRedirect(route('login'));
});

it('hides correct answers until the teacher reveals them', function () {
    $student = User::factory()->student()->create();
    $exam = Exam::factory()->published()->create();
    $question = Question::factory()->for($exam)->create(['correct_answer' => 'Paris']);
    $attempt = Attempt::factory()->completed()->for($exam)->for($student, 'student')->create([
        'answers' => [$question->id => ['value' => 'London', 'flagged' => false]],
        'score' => 0,
    ]);

    $assignment = giveStudentAccess($student, $exam, ['reveal_answers_at' => now()->addDay()]);

    $hidden = Livewire::actingAs($student)
        ->test('pages::student.exam-results', ['attempt' => $attempt]);

    expect($hidden->instance()->answersRevealed)->toBeFalse();
    $hidden->assertDontSee('Correct answer:')->assertSee('Correct answers are not available yet');

    $assignment->update(['reveal_answers_at' => now()->subMinute()]);

    $revealed = Livewire::actingAs($student)
        ->test('pages::student.exam-results', ['attempt' => $attempt->fresh()]);

    expect($revealed->instance()->answersRevealed)->toBeTrue();
    $revealed->assertSee('Correct answer:');
});

it('teacher downloads a class report PDF, other teachers cannot', function () {
    $teacher = User::factory()->teacher()->create();
    $stranger = User::factory()->teacher()->create();
    $classroom = Classroom::factory()->for($teacher, 'teacher')->create();

    $this->actingAs($teacher)
        ->get(route('teacher.classrooms.report', $classroom))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->actingAs($stranger)
        ->get(route('teacher.classrooms.report', $classroom))
        ->assertForbidden();
});

it('teacher downloads a printable exam and its answer key, others cannot', function () {
    $teacher = User::factory()->teacher()->create();
    $stranger = User::factory()->teacher()->create();
    $student = User::factory()->student()->create();
    $exam = Exam::factory()->for($teacher)->has(Question::factory()->count(3), 'questions')->create();

    $this->actingAs($teacher)
        ->get(route('teacher.exams.pdf', $exam))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->actingAs($teacher)
        ->get(route('teacher.exams.pdf', ['exam' => $exam, 'key' => 1]))
        ->assertOk();

    $this->actingAs($stranger)->get(route('teacher.exams.pdf', $exam))->assertForbidden();
    $this->actingAs($student)->get(route('teacher.exams.pdf', $exam))->assertForbidden();
});
