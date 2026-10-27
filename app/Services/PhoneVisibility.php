<?php

namespace App\Services;

use App\Enums\AppRole;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class PhoneVisibility
{
    public function canView(User $owner, ?User $viewer): bool
    {
        if ($viewer === null) {
            return false;
        }

        if ($owner->is($viewer) || $viewer->hasAnyRole([AppRole::Admin, AppRole::SuperAdmin, AppRole::Kaprodi])) {
            return true;
        }

        if ($owner->phone_public) {
            return true;
        }

        return isset($this->relatedUserIds($viewer)[$owner->id]);
    }

    /**
     * Load all contacts relevant to one viewer in a fixed number of queries.
     *
     * @return array<int, true>
     */
    public function relatedUserIds(User $viewer): array
    {
        $supervisors = ThesisSupervisorAssignment::query()
            ->join('thesis_projects', 'thesis_projects.id', '=', 'thesis_supervisor_assignments.project_id')
            ->where(fn (Builder $query) => $query
                ->where('thesis_projects.student_user_id', $viewer->id)
                ->orWhere('thesis_supervisor_assignments.lecturer_user_id', $viewer->id))
            ->get(['thesis_projects.student_user_id', 'thesis_supervisor_assignments.lecturer_user_id']);

        $examiners = ThesisDefenseExaminer::query()
            ->join('thesis_defenses', 'thesis_defenses.id', '=', 'thesis_defense_examiners.defense_id')
            ->join('thesis_projects', 'thesis_projects.id', '=', 'thesis_defenses.project_id')
            ->where(fn (Builder $query) => $query
                ->where('thesis_defense_examiners.lecturer_user_id', $viewer->id)
                ->orWhere(fn (Builder $student) => $student
                    ->where('thesis_projects.student_user_id', $viewer->id)
                    ->where(fn (Builder $visible) => $visible
                        ->whereIn('thesis_defense_examiners.role', ['primary_supervisor', 'secondary_supervisor'])
                        ->orWhere('thesis_defenses.type', '!=', 'sidang')
                        ->orWhere('thesis_defenses.status', 'completed'))))
            ->get(['thesis_projects.student_user_id', 'thesis_defense_examiners.lecturer_user_id']);

        $ids = [];
        foreach ($supervisors->concat($examiners) as $assignment) {
            $id = (int) $assignment->student_user_id === (int) $viewer->id
                ? (int) $assignment->lecturer_user_id
                : (int) $assignment->student_user_id;
            $ids[$id] = true;
        }

        return $ids;
    }

    public function canViewForRequest(User $owner, ?User $viewer): bool
    {
        if ($viewer === null) {
            return false;
        }

        if ($owner->is($viewer) || $viewer->hasAnyRole([AppRole::Admin, AppRole::SuperAdmin, AppRole::Kaprodi]) || $owner->phone_public) {
            return true;
        }

        $key = 'phone_visibility_'.$viewer->id;
        $request = request();
        if (! $request->attributes->has($key)) {
            $request->attributes->set($key, $this->relatedUserIds($viewer));
        }

        return isset($request->attributes->get($key)[$owner->id]);
    }
}
