<?php

namespace App\Services;

use App\Enums\AdvisorType;
use App\Enums\AppRole;
use App\Enums\AssignmentStatus;
use App\Models\MentorshipAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MentorshipAssignmentService
{
    public const MAX_ACTIVE_ADVISORS_PER_STUDENT = 2;

    public function syncStudentAdvisors(
        int $studentUserId,
        int $assignedBy,
        int $primaryLecturerUserId,
        ?int $secondaryLecturerUserId = null,
        ?string $notes = null,
    ): void {
        DB::transaction(function () use ($studentUserId, $assignedBy, $primaryLecturerUserId, $secondaryLecturerUserId, $notes): void {
            $this->syncAdvisor(
                studentUserId: $studentUserId,
                assignedBy: $assignedBy,
                advisorType: AdvisorType::Primary->value,
                lecturerUserId: $primaryLecturerUserId,
                notes: $notes,
            );

            $this->syncAdvisor(
                studentUserId: $studentUserId,
                assignedBy: $assignedBy,
                advisorType: AdvisorType::Secondary->value,
                lecturerUserId: $secondaryLecturerUserId,
                notes: $notes,
            );
        });

    }

    public function activeStudentCountForLecturer(int $lecturerUserId): int
    {
        $activeStudentIds = MentorshipAssignment::query()
            ->select('mentorship_assignments.student_user_id')
            ->leftJoin('mahasiswa_profiles', 'mahasiswa_profiles.user_id', '=', 'mentorship_assignments.student_user_id')
            ->where('mentorship_assignments.lecturer_user_id', $lecturerUserId)
            ->where('mentorship_assignments.status', AssignmentStatus::Active->value)
            ->where(function ($query): void {
                $query->where('mahasiswa_profiles.is_active', true);
            })
            ->distinct();

        return DB::query()
            ->fromSub($activeStudentIds, 'active_students')
            ->count();
    }

    public function isInactiveStudentStatus(bool $isActive): bool
    {
        return ! $isActive;
    }

    public function validateForSave(MentorshipAssignment $assignment): void
    {
        if (! $assignment->isActive()) {
            return;
        }

        $this->ensureUserRole($assignment->student_user_id, AppRole::Mahasiswa->value, 'student_user_id');
        $this->ensureUserRole($assignment->lecturer_user_id, AppRole::Dosen->value, 'lecturer_user_id');
        $this->ensureAdvisorTypeIsUnique($assignment);
        $this->ensureStudentHasMaximumTwoAdvisors($assignment);
        $this->ensureMatchingConcentration($assignment);
        $this->ensureLecturerHasCapacity($assignment);
    }

    private function ensureUserRole(int $userId, string $role, string $field): void
    {
        $hasRole = User::query()
            ->whereKey($userId)
            ->whereHas('roles', static fn($query) => $query->where('name', $role))
            ->exists();

        if ($hasRole) {
            return;
        }

        throw ValidationException::withMessages([
            $field => ["User must have role {$role}."],
        ]);
    }

    private function ensureAdvisorTypeIsUnique(MentorshipAssignment $assignment): void
    {
        $query = MentorshipAssignment::query()
            ->where('student_user_id', $assignment->student_user_id)
            ->where('advisor_type', $assignment->advisor_type)
            ->where('status', AssignmentStatus::Active->value);

        if ($assignment->exists) {
            $query->whereKeyNot($assignment->getKey());
        }

        if (! $query->exists()) {
            return;
        }

        throw ValidationException::withMessages([
            'advisor_type' => ['Mahasiswa already has an active assignment for this advisor type.'],
        ]);
    }

    private function ensureStudentHasMaximumTwoAdvisors(MentorshipAssignment $assignment): void
    {
        $query = MentorshipAssignment::query()
            ->where('student_user_id', $assignment->student_user_id)
            ->where('status', AssignmentStatus::Active->value);

        if ($assignment->exists) {
            $query->whereKeyNot($assignment->getKey());
        }

        $activeAdvisorCount = $query->count();

        if ($activeAdvisorCount < self::MAX_ACTIVE_ADVISORS_PER_STUDENT) {
            return;
        }

        throw ValidationException::withMessages([
            'student_user_id' => ['Mahasiswa already has 2 active advisors.'],
        ]);
    }

    private function ensureLecturerHasCapacity(MentorshipAssignment $assignment): void
    {
        $lecturer = User::query()
            ->with('dosenProfile')
            ->find($assignment->lecturer_user_id);

        $quota = max(1, (int) ($lecturer?->dosenProfile?->supervision_quota ?? 14));
        $activeStudentCount = $this->activeStudentCountForLecturer(
            $assignment->lecturer_user_id,
        );

        $isExistingStudentAlreadyCounted = MentorshipAssignment::query()
            ->where('lecturer_user_id', $assignment->lecturer_user_id)
            ->where('student_user_id', $assignment->student_user_id)
            ->where('status', AssignmentStatus::Active->value)
            ->when(
                $assignment->exists,
                static fn($query) => $query->whereKeyNot($assignment->getKey()),
            )
            ->exists();

        if ($isExistingStudentAlreadyCounted || $activeStudentCount < $quota) {
            return;
        }

        throw ValidationException::withMessages([
            'lecturer_user_id' => [sprintf('Dosen quota reached the maximum of %d active mahasiswa.', $quota)],
        ]);
    }

    private function ensureMatchingConcentration(MentorshipAssignment $assignment): void
    {
        $student = User::query()
            ->with('mahasiswaProfile')
            ->find($assignment->student_user_id);

        $lecturer = User::query()
            ->with('dosenProfile')
            ->find($assignment->lecturer_user_id);

        $studentConcentration = $student?->mahasiswaProfile?->concentration;
        $lecturerConcentration = $lecturer?->dosenProfile?->concentration;

        if (! filled($studentConcentration)) {
            throw ValidationException::withMessages([
                'student_user_id' => ['Mahasiswa concentration must be configured before assigning advisors.'],
            ]);
        }

        if (! filled($lecturerConcentration)) {
            throw ValidationException::withMessages([
                'lecturer_user_id' => ['Dosen concentration must be configured before assigning advisors.'],
            ]);
        }

        if ($studentConcentration === $lecturerConcentration) {
            return;
        }

        throw ValidationException::withMessages([
            'lecturer_user_id' => ['Dosen concentration must match the mahasiswa concentration.'],
        ]);
    }

    private function syncAdvisor(
        int $studentUserId,
        int $assignedBy,
        string $advisorType,
        ?int $lecturerUserId,
        ?string $notes,
    ): void {
        $current = MentorshipAssignment::query()
            ->where('student_user_id', $studentUserId)
            ->where('advisor_type', $advisorType)
            ->where('status', AssignmentStatus::Active->value)
            ->latest('id')
            ->first();

        if ($lecturerUserId === null) {
            if ($current === null) {
                return;
            }

            $current->forceFill([
                'status' => AssignmentStatus::Ended->value,
                'ended_at' => now(),
            ])->save();

            return;
        }

        if ($current !== null && $current->lecturer_user_id === $lecturerUserId) {
            return;
        }

        if ($current !== null) {
            $current->forceFill([
                'status' => AssignmentStatus::Ended->value,
                'ended_at' => now(),
            ])->save();
        }

        MentorshipAssignment::query()->create([
            'student_user_id' => $studentUserId,
            'lecturer_user_id' => $lecturerUserId,
            'advisor_type' => $advisorType,
            'status' => AssignmentStatus::Active->value,
            'assigned_by' => $assignedBy,
            'started_at' => now(),
            'notes' => $notes,
        ]);
    }
}
