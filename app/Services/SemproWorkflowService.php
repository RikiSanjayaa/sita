<?php

namespace App\Services;

use App\Enums\AppRole;
use App\Enums\SemproExaminerDecision;
use App\Enums\SemproStatus;
use App\Enums\ThesisSubmissionStatus;
use App\Models\Sempro;
use App\Models\ThesisSubmission;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SemproWorkflowService
{
    public function assignExaminers(Sempro $sempro, array $examinerUserIds, int $assignedBy): void
    {
        $normalized = array_values(array_unique(array_filter($examinerUserIds, static fn ($id): bool => is_int($id))));

        if (count($normalized) !== 2) {
            throw ValidationException::withMessages([
                'examiner_user_ids' => ['Sempro must have exactly 2 examiners.'],
            ]);
        }

        $validExaminerCount = User::query()
            ->whereIn('id', $normalized)
            ->whereHas('roles', static fn ($query) => $query->where('name', AppRole::Dosen->value))
            ->count();

        if ($validExaminerCount !== 2) {
            throw ValidationException::withMessages([
                'examiner_user_ids' => ['Each examiner must be an active dosen user.'],
            ]);
        }

        DB::transaction(function () use ($sempro, $normalized, $assignedBy): void {
            $sempro->examiners()->delete();

            foreach ($normalized as $index => $examinerUserId) {
                $sempro->examiners()->create([
                    'examiner_user_id' => $examinerUserId,
                    'examiner_order' => $index + 1,
                    'assigned_by' => $assignedBy,
                ]);
            }
        });

        $sempro->loadMissing(['submission.student', 'examiners.examiner']);

        $student = $sempro->submission?->student;

        if ($student instanceof User) {
            $examinerNames = $sempro->examiners
                ->pluck('examiner.name')
                ->filter()
                ->values()
                ->implode(', ');

            app(RealtimeNotificationService::class)->notifyUser($student, 'statusTugasAkhir', [
                'title' => 'Penguji Sempro Ditetapkan',
                'description' => $examinerNames === ''
                    ? 'Penguji sempro Anda sudah ditetapkan oleh admin.'
                    : 'Penguji sempro Anda: '.$examinerNames,
                'icon' => 'check-circle',
                'url' => '/mahasiswa/tugas-akhir',
                'createdAt' => now()->toIso8601String(),
            ]);
        }
    }

    public function scheduleSempro(Sempro $sempro): void
    {
        if ($sempro->examiners()->count() !== 2) {
            throw ValidationException::withMessages([
                'examiners' => ['Schedule requires exactly 2 assigned examiners.'],
            ]);
        }

        $sempro->forceFill([
            'status' => SemproStatus::Scheduled->value,
        ])->save();

        $sempro->submission->forceFill([
            'status' => ThesisSubmissionStatus::SemproScheduled->value,
        ])->save();

        $sempro->loadMissing(['submission.student']);

        $student = $sempro->submission?->student;

        if ($student instanceof User) {
            app(RealtimeNotificationService::class)->notifyUser($student, 'jadwalBimbingan', [
                'title' => 'Jadwal Sempro Sudah Ditentukan',
                'description' => 'Sempro Anda dijadwalkan pada '.$this->formatSchedule($sempro->scheduled_for).'.',
                'icon' => 'calendar-clock',
                'url' => '/mahasiswa/tugas-akhir',
                'createdAt' => now()->toIso8601String(),
            ]);
        }
    }

    public function approveSempro(Sempro $sempro, int $approvedBy): void
    {
        $approvedDecisions = $sempro->examiners()
            ->where('decision', SemproExaminerDecision::Approved->value)
            ->count();

        if ($approvedDecisions < 2) {
            throw ValidationException::withMessages([
                'decision' => ['All assigned examiners must approve before sempro approval.'],
            ]);
        }

        DB::transaction(function () use ($sempro, $approvedBy): void {
            $sempro->forceFill([
                'status' => SemproStatus::Approved->value,
                'approved_by' => $approvedBy,
                'approved_at' => now(),
            ])->save();

            $submission = ThesisSubmission::query()->findOrFail($sempro->thesis_submission_id);
            $submission->forceFill([
                'status' => ThesisSubmissionStatus::SemproApproved->value,
                'approved_by' => $approvedBy,
                'approved_at' => now(),
            ])->save();
        });
    }

    private function formatSchedule(CarbonInterface|string|null $scheduledFor): string
    {
        if ($scheduledFor instanceof CarbonInterface) {
            return $scheduledFor->timezone('Asia/Makassar')->format('d M Y H:i');
        }

        if (is_string($scheduledFor) && $scheduledFor !== '') {
            return $scheduledFor;
        }

        return 'waktu yang akan diinformasikan';
    }
}
