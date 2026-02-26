<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipDocument;
use App\Models\MentorshipSchedule;
use App\Services\DosenBimbinganService;
use App\Services\MentorshipAssignmentService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DosenBimbinganService $dosenBimbinganService,
    ) {}

    public function __invoke(Request $request): Response
    {
        $lecturer = $request->user();
        abort_if($lecturer === null, 401);

        $studentIds = $this->dosenBimbinganService->activeStudentIds($lecturer);
        $threadIds = MentorshipChatThread::query()
            ->whereIn('student_user_id', $studentIds)
            ->pluck('id')
            ->all();

        $pendingSchedules = MentorshipSchedule::query()
            ->where('lecturer_user_id', $lecturer->id)
            ->where('status', 'pending')
            ->count();

        $pendingDocuments = MentorshipDocument::query()
            ->where('lecturer_user_id', $lecturer->id)
            ->whereIn('status', ['submitted', 'needs_revision'])
            ->count();

        $unreadMessages = MentorshipChatMessage::query()
            ->whereIn('mentorship_chat_thread_id', $threadIds)
            ->where('sender_user_id', '!=', $lecturer->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $activeStudentCount = count($studentIds);

        $todayQueue = MentorshipSchedule::query()
            ->with('student')
            ->where('lecturer_user_id', $lecturer->id)
            ->whereIn('status', ['pending', 'approved', 'rescheduled'])
            ->orderBy('scheduled_for')
            ->orderBy('requested_for')
            ->limit(5)
            ->get()
            ->map(function (MentorshipSchedule $schedule): array {
                return [
                    'id' => $schedule->id,
                    'mahasiswa' => $schedule->student?->name ?? '-',
                    'task' => $schedule->topic,
                    'time' => optional($schedule->scheduled_for ?? $schedule->requested_for)?->format('d M Y H:i') ?? '-',
                    'priority' => $schedule->status === 'pending' ? 'Tinggi' : 'Normal',
                    'status' => $schedule->status,
                ];
            })
            ->all();

        return Inertia::render('dosen/dashboard', [
            'queueCards' => [
                [
                    'title' => 'Jadwal Pending',
                    'value' => (string) $pendingSchedules,
                    'description' => 'Permintaan jadwal menunggu konfirmasi',
                ],
                [
                    'title' => 'Revisi Belum Dicek',
                    'value' => (string) $pendingDocuments,
                    'description' => 'Dokumen mahasiswa menunggu review',
                ],
                [
                    'title' => 'Pesan Belum Dibaca',
                    'value' => (string) $unreadMessages,
                    'description' => 'Aktivitas terbaru di grup bimbingan',
                ],
                [
                    'title' => 'Mahasiswa Aktif',
                    'value' => sprintf(
                        '%d/%d',
                        $activeStudentCount,
                        MentorshipAssignmentService::MAX_ACTIVE_STUDENTS_PER_LECTURER,
                    ),
                    'description' => 'Kapasitas bimbingan aktif saat ini',
                ],
            ],
            'todayQueue' => $todayQueue,
        ]);
    }
}
