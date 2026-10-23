<?php

namespace App\Services;

use App\Models\ThesisDefenseExaminer;
use App\Models\User;
use App\Support\AcademicTerminology;

/**
 * Who a mahasiswa may not recognise before the Sidang is completed, for the places that
 * attribute an action to a lecturer (chat messages, document reviews, notifications).
 *
 * The rule itself lives in {@see \App\Models\ThesisDefense::examinersVisibleToStudent()}.
 * Results are remembered per instance, so resolve a fresh instance per request or job.
 */
class SecretSidangExaminers
{
    /** @var array<int, array<int, int>> student user id => secret lecturer ids */
    private array $lecturerIds = [];

    /** @var array<int, string> student user id => label */
    private array $labels = [];

    public function isSecret(int $studentUserId, int $lecturerUserId): bool
    {
        $this->lecturerIds[$studentUserId] ??= ThesisDefenseExaminer::secretLecturerIdsForStudent($studentUserId);

        return in_array($lecturerUserId, $this->lecturerIds[$studentUserId], true);
    }

    /**
     * The label shown instead of the lecturer's name, in the student's own degree wording.
     */
    public function label(int $studentUserId): string
    {
        return $this->labels[$studentUserId] ??= 'Penguji '.AcademicTerminology::forStudent(User::query()->find($studentUserId))['finalExamShort'];
    }

    /**
     * The anonymous label when the lecturer is a secret examiner of this mahasiswa, else null.
     */
    public function labelFor(int $studentUserId, int $lecturerUserId): ?string
    {
        return $this->isSecret($studentUserId, $lecturerUserId) ? $this->label($studentUserId) : null;
    }
}
