<?php

namespace App\Http\Controllers\Kaprodi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kaprodi\UpdateLecturerQuotaRequest;
use App\Models\ProgramStudi;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Services\SystemAuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class LecturerQuotaController extends Controller
{
    public function __construct(
        private readonly SystemAuditLogService $systemAuditLogService,
    ) {}

    public function __invoke(UpdateLecturerQuotaRequest $request, User $lecturer): RedirectResponse
    {
        $programStudi = $request->user()?->kaprodiAssignment?->programStudi;

        abort_unless($programStudi instanceof ProgramStudi, 403);
        abort_unless($lecturer->hasRole('dosen'), 404);
        abort_unless($lecturer->teachesInProgramStudi((int) $programStudi->id), 404);

        if ($lecturer->dosenProfile === null) {
            throw ValidationException::withMessages([
                'supervision_quota' => 'Dosen belum memiliki profil dosen.',
            ]);
        }

        $quota = (int) $request->validated('supervision_quota');
        $previousQuota = (int) ($lecturer->dosenProfile->supervision_quota ?? 14);
        $activeStudentCount = $this->activeStudentCount($lecturer, $programStudi);

        if ($quota < $activeStudentCount) {
            throw ValidationException::withMessages([
                'supervision_quota' => "Kuota tidak boleh lebih kecil dari {$activeStudentCount} mahasiswa bimbingan aktif.",
            ]);
        }

        $lecturer->dosenProfile->forceFill([
            'supervision_quota' => $quota,
        ])->save();

        $this->systemAuditLogService->record(
            user: $request->user(),
            eventType: 'kaprodi_lecturer_quota_updated',
            label: 'Kaprodi memperbarui kuota bimbingan',
            description: 'Kaprodi memperbarui kuota bimbingan dosen prodi.',
            request: $request,
            payload: [
                'program_studi_id' => $programStudi->id,
                'program_studi_name' => $programStudi->name,
                'lecturer_user_id' => $lecturer->id,
                'lecturer_name' => $lecturer->name,
                'previous_quota' => $previousQuota,
                'new_quota' => $quota,
                'active_supervision_count' => $activeStudentCount,
            ],
        );

        return back()->with('success', 'Kuota bimbingan dosen berhasil diperbarui.');
    }

    private function activeStudentCount(User $lecturer, ProgramStudi $programStudi): int
    {
        return ThesisSupervisorAssignment::query()
            ->where('lecturer_user_id', $lecturer->id)
            ->where('status', 'active')
            ->whereHas('project', fn($query) => $query
                ->where('program_studi_id', $programStudi->id)
                ->where('state', 'active'))
            ->with('project:id,student_user_id')
            ->get()
            ->pluck('project.student_user_id')
            ->filter()
            ->unique()
            ->count();
    }
}
