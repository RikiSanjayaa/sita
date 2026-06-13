<?php

namespace App\Http\Controllers\Kaprodi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kaprodi\AssignProjectSupervisorsRequest;
use App\Http\Requests\Kaprodi\ScheduleProjectSemproRequest;
use App\Http\Requests\Kaprodi\ScheduleProjectSidangRequest;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Services\ThesisProjectAdminService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ProjectWorkflowController extends Controller
{
    public function __construct(
        private readonly ThesisProjectAdminService $thesisProjectAdminService,
    ) {}

    public function assignSupervisors(AssignProjectSupervisorsRequest $request, ThesisProject $project): RedirectResponse
    {
        $this->ensureProjectCanBeManaged($request, $project);

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

        return back()->with('success', 'Pembimbing berhasil diperbarui.');
    }

    public function scheduleSempro(ScheduleProjectSemproRequest $request, ThesisProject $project): RedirectResponse
    {
        $this->ensureProjectCanBeManaged($request, $project);
        $this->ensureDefenseCanBeScheduled($project, 'sempro');

        try {
            $this->thesisProjectAdminService->scheduleSempro(
                project: $project,
                scheduledBy: (int) $request->user()?->getKey(),
                scheduledFor: (string) $request->validated('scheduled_for'),
                location: (string) $request->validated('location'),
                mode: (string) $request->validated('mode'),
                examinerUserIds: [
                    (int) $request->validated('examiner_1_user_id'),
                    (int) $request->validated('examiner_2_user_id'),
                ],
            );
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'examiner_1_user_id' => $exception->getMessage(),
            ]);
        }

        return back()->with('success', 'Jadwal sempro berhasil diperbarui.');
    }

    public function scheduleSidang(ScheduleProjectSidangRequest $request, ThesisProject $project): RedirectResponse
    {
        $this->ensureProjectCanBeManaged($request, $project);
        $this->ensureDefenseCanBeScheduled($project, 'sidang');

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

        return back()->with('success', 'Jadwal sidang berhasil diperbarui.');
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
}
