<?php

namespace App\Http\Controllers\Kaprodi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kaprodi\AssignProjectSupervisorsRequest;
use App\Http\Requests\Kaprodi\ScheduleProjectSemproRequest;
use App\Http\Requests\Kaprodi\ScheduleProjectSidangRequest;
use App\Models\KaprodiAssignment;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Services\SystemAuditLogService;
use App\Services\ThesisProjectAdminService;
use App\Support\AcademicTerminology;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ProjectWorkflowController extends Controller
{
    public function __construct(
        private readonly ThesisProjectAdminService $thesisProjectAdminService,
        private readonly SystemAuditLogService $systemAuditLogService,
    ) {}

    public function assignSupervisors(AssignProjectSupervisorsRequest $request, ThesisProject $project): RedirectResponse
    {
        $this->ensureProjectCanBeManaged($request, $project);
        $this->ensureKaprodiCapability($request, KaprodiAssignment::CAPABILITY_MANAGE_SUPERVISORS);
        $terms = AcademicTerminology::forProject($project);

        try {
            $this->thesisProjectAdminService->assignSupervisors(
                project: $project,
                assignedBy: (int) $request->user()?->getKey(),
                primaryLecturerUserId: (int) $request->validated('primary_lecturer_user_id'),
                secondaryLecturerUserId: (int) $request->validated('secondary_lecturer_user_id'),
                notes: $request->validated('notes'),
            );
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'primary_lecturer_user_id' => $exception->getMessage(),
            ]);
        }

        $this->recordProjectAudit(
            request: $request,
            project: $project,
            eventType: 'kaprodi_supervisors_updated',
            label: 'Kaprodi memperbarui pembimbing',
            description: 'Kaprodi memperbarui dosen pembimbing '.$terms['finalWorkLower'].'.',
            payload: [
                'primary_lecturer_user_id' => (int) $request->validated('primary_lecturer_user_id'),
                'secondary_lecturer_user_id' => (int) $request->validated('secondary_lecturer_user_id'),
            ],
        );

        return back()->with('success', 'Pembimbing berhasil diperbarui.');
    }

    public function scheduleSempro(ScheduleProjectSemproRequest $request, ThesisProject $project): RedirectResponse
    {
        $this->ensureProjectCanBeManaged($request, $project);
        $this->ensureKaprodiCapability($request, KaprodiAssignment::CAPABILITY_SCHEDULE_SEMPRO);
        $this->ensureDefenseCanBeScheduled($project, 'sempro');
        $terms = AcademicTerminology::forProject($project);

        $examinerUserIds = collect([
            $request->validated('examiner_1_user_id'),
            $request->validated('examiner_2_user_id'),
        ])->filter(fn($id): bool => filled($id))
            ->map(fn($id): int => (int) $id)
            ->values()
            ->all();

        try {
            $this->thesisProjectAdminService->scheduleSempro(
                project: $project,
                scheduledBy: (int) $request->user()?->getKey(),
                scheduledFor: (string) $request->validated('scheduled_for'),
                location: (string) $request->validated('location'),
                mode: (string) $request->validated('mode'),
                examinerUserIds: $examinerUserIds,
            );
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'examiner_1_user_id' => $exception->getMessage(),
            ]);
        }

        $this->recordProjectAudit(
            request: $request,
            project: $project,
            eventType: 'kaprodi_sempro_scheduled',
            label: 'Kaprodi memperbarui jadwal '.$terms['proposalExamShort'],
            description: 'Kaprodi memperbarui jadwal dan penguji '.$terms['proposalExamShort'].'.',
            payload: [
                'scheduled_for' => $request->validated('scheduled_for'),
                'location' => $request->validated('location'),
                'mode' => $request->validated('mode'),
                'examiner_user_ids' => $examinerUserIds,
            ],
        );

        return back()->with('success', 'Jadwal '.$terms['proposalExamShort'].' berhasil diperbarui.');
    }

    public function scheduleSidang(ScheduleProjectSidangRequest $request, ThesisProject $project): RedirectResponse
    {
        $this->ensureProjectCanBeManaged($request, $project);
        $this->ensureKaprodiCapability($request, KaprodiAssignment::CAPABILITY_SCHEDULE_SIDANG);
        $this->ensureDefenseCanBeScheduled($project, 'sidang');
        $terms = AcademicTerminology::forProject($project);

        $project->loadMissing('activeSupervisorAssignments');
        $supervisorIds = $project->activeSupervisorAssignments
            ->sortBy('role')
            ->pluck('lecturer_user_id')
            ->map(static fn($id): int => (int) $id)
            ->values()
            ->all();

        try {
            $this->thesisProjectAdminService->scheduleSidang(
                project: $project,
                createdBy: (int) $request->user()?->getKey(),
                scheduledFor: (string) $request->validated('scheduled_for'),
                location: (string) $request->validated('location'),
                mode: (string) $request->validated('mode'),
                panelUserIds: array_values(array_unique(array_merge(
                    $supervisorIds,
                    array_map(static fn($id): int => (int) $id, $request->validated('additional_examiner_user_ids')),
                ))),
                notes: $request->validated('notes'),
            );
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'additional_examiner_user_ids' => $exception->getMessage(),
            ]);
        }

        $this->recordProjectAudit(
            request: $request,
            project: $project,
            eventType: 'kaprodi_sidang_scheduled',
            label: 'Kaprodi memperbarui jadwal '.$terms['finalExam'],
            description: 'Kaprodi memperbarui jadwal dan panel '.$terms['finalExam'].'.',
            payload: [
                'scheduled_for' => $request->validated('scheduled_for'),
                'location' => $request->validated('location'),
                'mode' => $request->validated('mode'),
                'supervisor_user_ids' => $supervisorIds,
                'additional_examiner_user_ids' => array_map(static fn($id): int => (int) $id, $request->validated('additional_examiner_user_ids')),
            ],
        );

        return back()->with('success', 'Jadwal '.$terms['finalExam'].' berhasil diperbarui.');
    }

    private function ensureProjectCanBeManaged(Request $request, ThesisProject $project): void
    {
        $programStudi = $request->user()?->kaprodiAssignment?->programStudi;

        abort_unless($programStudi instanceof ProgramStudi, 403);
        abort_unless((int) $project->program_studi_id === (int) $programStudi->id, 404);

        if ($project->state !== 'active') {
            throw ValidationException::withMessages([
                'project' => 'Kaprodi hanya dapat mengubah proyek tugas akhir yang masih aktif.',
            ]);
        }
    }

    private function ensureDefenseCanBeScheduled(ThesisProject $project, string $type): void
    {
        $defense = $project->defenses()
            ->where('type', $type)
            ->latest('attempt_no')
            ->first();

        if (! $defense instanceof ThesisDefense) {
            return;
        }

        if (in_array($defense->status, ['awaiting_finalization', 'completed', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'scheduled_for' => 'Jadwal tidak dapat diubah karena ujian sudah selesai atau menunggu finalisasi.',
            ]);
        }
    }

    private function ensureKaprodiCapability(Request $request, string $capability): void
    {
        $assignment = $request->user()?->kaprodiAssignment;

        abort_unless($assignment instanceof KaprodiAssignment && $assignment->hasCapability($capability), 403);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordProjectAudit(
        Request $request,
        ThesisProject $project,
        string $eventType,
        string $label,
        string $description,
        array $payload,
    ): void {
        $project->loadMissing(['student', 'programStudi']);

        $this->systemAuditLogService->record(
            user: $request->user(),
            eventType: $eventType,
            label: $label,
            description: $description,
            request: $request,
            payload: [
                'program_studi_id' => $project->program_studi_id,
                'program_studi_name' => $project->programStudi?->name,
                'thesis_project_id' => $project->id,
                'student_user_id' => $project->student_user_id,
                'student_name' => $project->student?->name,
                ...$payload,
            ],
        );
    }
}
