<?php

namespace App\Services;

use App\Enums\AssignmentStatus;
use App\Models\MentorshipAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class DosenBimbinganService
{
    /**
     * @return Collection<int, \App\Models\MentorshipAssignment>
     */
    public function activeAssignmentsWithStudent(User $lecturer): Collection
    {
        return MentorshipAssignment::query()
            ->with(['student.mahasiswaProfile'])
            ->where('lecturer_user_id', $lecturer->id)
            ->where('status', AssignmentStatus::Active->value)
            ->get();
    }

    /**
     * @return array<int, int>
     */
    public function activeStudentIds(User $lecturer): array
    {
        return $this->activeAssignmentsWithStudent($lecturer)
            ->pluck('student_user_id')
            ->unique()
            ->values()
            ->all();
    }
}
