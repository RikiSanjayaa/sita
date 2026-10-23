<?php

namespace App\Services;

use App\Models\ThesisProject;
use App\Support\WitaDateTime;

class MentorshipTicketService
{
    /**
     * The student's current thesis project (same notion as ThesisProject::current()),
     * whose ticket governs mentorship activity. Null when the current one is not active.
     */
    public function activeProjectForStudent(int $studentUserId): ?ThesisProject
    {
        return ThesisProject::query()
            ->current()
            ->where('student_user_id', $studentUserId)
            ->where('state', 'active')
            ->first();
    }

    /**
     * The reason a student's mentorship activity is blocked, or null while it is allowed.
     */
    public function suspensionMessageForStudent(int $studentUserId): ?string
    {
        $project = $this->activeProjectForStudent($studentUserId);

        return $project?->isMentorshipSuspended() ? self::suspensionMessage($project) : null;
    }

    public static function suspensionMessage(ThesisProject $project): string
    {
        return 'Masa berlaku tiket habis sejak '
            .WitaDateTime::format($project->mentorship_valid_until, 'd M Y', withLabel: false)
            .'. Hubungi admin untuk perpanjangan.';
    }
}
