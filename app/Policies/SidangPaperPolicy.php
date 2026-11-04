<?php

namespace App\Policies;

use App\Models\ThesisDefense;
use App\Models\User;

final class SidangPaperPolicy
{
    public function manage(User $user, int $programId): bool
    {
        return $user->hasRole('super_admin')
            || ($user->hasRole('admin') && $user->adminProgramStudiId() === $programId)
            || ($user->hasRole('kaprodi') && (int) $user->kaprodiAssignment?->program_studi_id === $programId);
    }

    public function print(User $user, ThesisDefense $defense): bool
    {
        return $defense->type === 'sidang'
            && ($this->manage($user, (int) $defense->project->program_studi_id)
                || ($user->hasRole('dosen') && $defense->examiners()->where('lecturer_user_id', $user->id)->exists()));
    }
}
