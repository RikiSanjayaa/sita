<?php

namespace App\Policies;

use App\Enums\AppRole;
use App\Enums\ThesisNoteVisibility;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisProject;
use App\Models\ThesisProjectNote;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;

/**
 * Access rules for lecturer notes on a thesis project.
 *
 * - Write: active supervisors and lecturers who were ever examiners on the project.
 * - Read internal: those lecturers, the kaprodi of the project's prodi, admins.
 * - Read student-visible: all of the above plus the project's student.
 * - Update/delete: the author only.
 */
class ThesisProjectNotePolicy
{
    public function create(User $user, ThesisProject $project): bool
    {
        return $this->isNoteWriter($user, $project);
    }

    public function view(User $user, ThesisProjectNote $note): bool
    {
        $project = $note->project;

        if (! $project instanceof ThesisProject) {
            return false;
        }

        if ($this->canReadInternal($user, $project)) {
            return true;
        }

        return $note->visibility === ThesisNoteVisibility::Student
            && $user->id === $project->student_user_id;
    }

    public function update(User $user, ThesisProjectNote $note): bool
    {
        return $note->author_user_id !== null && $note->author_user_id === $user->id;
    }

    public function delete(User $user, ThesisProjectNote $note): bool
    {
        return $this->update($user, $note);
    }

    /**
     * Internal notes are visible to everyone who is not the student.
     */
    public function canReadInternal(User $user, ThesisProject $project): bool
    {
        if ($user->hasRole(AppRole::SuperAdmin)) {
            return true;
        }

        if ($user->hasRole(AppRole::Admin)) {
            $adminProgramStudiId = $user->adminProgramStudiId();

            if ($adminProgramStudiId === null || $adminProgramStudiId === (int) $project->program_studi_id) {
                return true;
            }
        }

        $kaprodiProgramStudiId = $user->kaprodiProgramStudiId();

        if ($user->hasRole(AppRole::Kaprodi)
            && $kaprodiProgramStudiId !== null
            && $kaprodiProgramStudiId === (int) $project->program_studi_id) {
            return true;
        }

        return $this->isNoteWriter($user, $project);
    }

    public function canReadStudentVisible(User $user, ThesisProject $project): bool
    {
        return $user->id === $project->student_user_id
            || $this->canReadInternal($user, $project);
    }

    public function isNoteWriter(User $user, ThesisProject $project): bool
    {
        return ThesisSupervisorAssignment::query()
            ->where('project_id', $project->id)
            ->where('lecturer_user_id', $user->id)
            ->where('status', 'active')
            ->exists()
            || ThesisDefenseExaminer::query()
                ->where('lecturer_user_id', $user->id)
                ->whereHas('defense', fn ($query) => $query->where('project_id', $project->id))
                ->exists();
    }
}
