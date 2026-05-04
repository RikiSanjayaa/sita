<?php

namespace App\Services;

use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class DosenBimbinganService
{
    public const DEFAULT_MAX_ACTIVE_STUDENTS_PER_LECTURER = 14;

    /**
     * @return Collection<int, ThesisSupervisorAssignment>
     */
    public function activeAssignmentsWithStudent(User $lecturer): Collection
    {
        return ThesisSupervisorAssignment::query()
            ->with([
                'project.student.mahasiswaProfile',
                'project.defenses',
                'project.activeSupervisorAssignments.lecturer.dosenProfile.programStudi',
                'project.activeSupervisorAssignments.lecturer.roles',
            ])
            ->where('lecturer_user_id', $lecturer->id)
            ->where('status', 'active')
            ->get();
    }

    /**
     * @return Collection<int, ThesisSupervisorAssignment>
     */
    public function archivedAssignmentsWithStudent(User $lecturer): Collection
    {
        return ThesisSupervisorAssignment::query()
            ->with(['project.student.mahasiswaProfile', 'project.defenses'])
            ->where('lecturer_user_id', $lecturer->id)
            ->where('status', 'ended')
            ->get();
    }

    /**
     * @return array<int, int>
     */
    public function activeStudentIds(User $lecturer): array
    {
        return $this->activeAssignmentsWithStudent($lecturer)
            ->pluck('project.student_user_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public function archivedStudentIds(User $lecturer): array
    {
        return $this->archivedAssignmentsWithStudent($lecturer)
            ->pluck('project.student_user_id')
            ->unique()
            ->values()
            ->all();
    }

    public function lecturerQuota(User $lecturer): int
    {
        return max(1, (int) ($lecturer->dosenProfile?->supervision_quota ?? self::DEFAULT_MAX_ACTIVE_STUDENTS_PER_LECTURER));
    }
}
