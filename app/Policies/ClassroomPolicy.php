<?php

namespace App\Policies;

use App\Models\Classroom;
use App\Models\User;

class ClassroomPolicy
{
    /** Only the teacher who owns the classroom can manage it (students, subjects, assignments, reports). */
    public function manage(User $user, Classroom $classroom): bool
    {
        return $user->isTeacher() && $classroom->user_id === $user->id;
    }

    /** The owner, or a student who joined with the code, can open the classroom. */
    public function view(User $user, Classroom $classroom): bool
    {
        return $this->manage($user, $classroom)
            || ($user->isStudent() && $classroom->hasStudent($user));
    }
}
