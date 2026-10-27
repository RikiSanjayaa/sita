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

        return $this->related($owner, $viewer, true) || $this->related($viewer, $owner, false);
    }

    private function related(User $student, User $lecturer, bool $studentIsOwner): bool
    {
        $supervisor = ThesisSupervisorAssignment::query()
            ->where('lecturer_user_id', $lecturer->id)
            ->whereHas('project', fn (Builder $query) => $query->where('student_user_id', $student->id))
            ->exists();

        if ($supervisor) {
            return true;
        }

        return ThesisDefenseExaminer::query()
            ->where('lecturer_user_id', $lecturer->id)
            ->whereHas('defense.project', fn (Builder $query) => $query->where('student_user_id', $student->id))
            ->when(! $studentIsOwner, fn (Builder $query) => $query->where(function (Builder $query): void {
                $query->whereIn('role', ['primary_supervisor', 'secondary_supervisor'])
                    ->orWhereHas('defense', fn (Builder $defense) => $defense->where('type', '!=', 'sidang')->orWhere('status', 'completed'));
            }))
            ->exists();
    }
}
