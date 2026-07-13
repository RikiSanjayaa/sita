<?php

namespace App\Policies;

use App\Enums\AppRole;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasAnyRole([AppRole::Admin, AppRole::SuperAdmin]);
    }

    public function view(User $actor, User $target): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function update(User $actor, User $target): bool
    {
        return $this->canManage($actor, $target);
    }

    public function delete(User $actor, User $target): bool
    {
        return $this->canManage($actor, $target);
    }

    public function deleteAny(User $actor): bool
    {
        return $actor->hasRole(AppRole::SuperAdmin);
    }

    private function canManage(User $actor, User $target): bool
    {
        if ($actor->hasRole(AppRole::SuperAdmin)) {
            return true;
        }

        $programStudiId = $actor->adminProgramStudiId();

        if (! $actor->hasRole(AppRole::Admin) || $programStudiId === null) {
            return false;
        }

        return $target->mahasiswaProfile()->where('program_studi_id', $programStudiId)->exists()
            || $target->dosenProfile()->where('program_studi_id', $programStudiId)->exists()
            || $target->dosenProgramStudiAssignments()->where('program_studi_id', $programStudiId)->exists()
            || $target->adminProfile()->where('program_studi_id', $programStudiId)->exists()
            || $target->kaprodiAssignment()->where('program_studi_id', $programStudiId)->exists();
    }
}
