<?php

namespace App\Services;

use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use Illuminate\Support\Collection;

class DosenThesisWorkspaceService
{
    /**
     * @return Collection<int, ThesisSupervisorAssignment>
     */
    public function activeSupervisionAssignments(User $lecturer): Collection
    {
        return ThesisSupervisorAssignment::query()
            ->with([
                'project.student.mahasiswaProfile',
                'project.latestTitle',
                'project.defenses.examiners.lecturer',
                'project.revisions',
            ])
            ->where('lecturer_user_id', $lecturer->id)
            ->where('status', 'active')
            ->orderBy('role')
            ->orderByDesc('started_at')
            ->get();
    }

    /**
     * @return Collection<int, ThesisDefenseExaminer>
     */
    public function defenseExaminerAssignments(User $lecturer): Collection
    {
        return ThesisDefenseExaminer::query()
            ->with([
                'defense.project.student.mahasiswaProfile',
                'defense.project.latestTitle',
                'defense.examiners.lecturer',
                'defense.revisions.requestedBy',
                'defense.revisions.resolvedBy',
            ])
            ->where('lecturer_user_id', $lecturer->id)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return array<int, int>
     */
    public function activeSupervisionStudentIds(User $lecturer): array
    {
        return $this->activeSupervisionAssignments($lecturer)
            ->pluck('project.student_user_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    public function relevantProjectIds(User $lecturer): array
    {
        return $this->activeSupervisionAssignments($lecturer)
            ->pluck('project_id')
            ->merge($this->defenseExaminerAssignments($lecturer)->pluck('defense.project_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
