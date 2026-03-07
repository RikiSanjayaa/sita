<?php

namespace App\Http\Controllers\Dosen;

use App\Enums\AdvisorType;
use App\Http\Controllers\Controller;
use App\Models\MentorshipDocument;
use App\Models\MentorshipSchedule;
use App\Services\DosenBimbinganService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MahasiswaBimbinganController extends Controller
{
    public function __construct(
        private readonly DosenBimbinganService $dosenBimbinganService,
    ) {}

    public function __invoke(Request $request): Response
    {
        $lecturer = $request->user();
        abort_if($lecturer === null, 401);

        $assignments = $this->dosenBimbinganService->activeAssignmentsWithStudent($lecturer);
        $studentIds = $assignments->pluck('project.student_user_id')->filter()->unique()->values();

        $latestScheduleByStudent = MentorshipSchedule::query()
            ->where('lecturer_user_id', $lecturer->id)
            ->whereIn('student_user_id', $studentIds)
            ->orderByDesc('updated_at')
            ->get()
            ->keyBy('student_user_id');

        $latestDocumentByStudent = MentorshipDocument::query()
            ->where('lecturer_user_id', $lecturer->id)
            ->whereIn('student_user_id', $studentIds)
            ->orderByDesc('updated_at')
            ->get()
            ->keyBy('student_user_id');

        $rows = $assignments->map(function ($assignment) use ($latestScheduleByStudent, $latestDocumentByStudent): array {
            $student = $assignment->project?->student;
            $profile = $student?->mahasiswaProfile;
            $studentUserId = $assignment->project?->student_user_id;
            $latestSchedule = $studentUserId === null ? null : $latestScheduleByStudent->get($studentUserId);
            $latestDocument = $studentUserId === null ? null : $latestDocumentByStudent->get($studentUserId);

            $isActive = $profile?->is_active ?? true;

            $statusLabel = $isActive ? 'Aktif' : 'Nonaktif';
            $progress = $latestDocument?->status === 'approved'
                ? 90
                : ($latestDocument?->status === 'needs_revision' ? 55 : 70);

            return [
                'nim' => $profile?->nim ?? '-',
                'name' => $student?->name ?? '-',
                'advisorType' => $assignment->role === AdvisorType::Primary->value ? 'Pembimbing 1' : 'Pembimbing 2',
                'progress' => $progress,
                'status' => $statusLabel,
                'lastUpdate' => $latestDocument?->updated_at?->diffForHumans()
                    ?? $latestSchedule?->updated_at?->diffForHumans()
                    ?? 'Belum ada aktivitas',
            ];
        })->values()->all();

        return Inertia::render('dosen/mahasiswa-bimbingan', [
            'mahasiswaRows' => $rows,
            'activeCount' => count($rows),
            'capacityLimit' => DosenBimbinganService::MAX_ACTIVE_STUDENTS_PER_LECTURER,
        ]);
    }
}
