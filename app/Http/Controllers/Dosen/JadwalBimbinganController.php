<?php

namespace App\Http\Controllers\Dosen;

use App\Events\ScheduleUpdated;
use App\Http\Controllers\Controller;
use App\Models\MentorshipSchedule;
use App\Services\DosenBimbinganService;
use App\Services\RealtimeNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class JadwalBimbinganController extends Controller
{
    public function __construct(
        private readonly DosenBimbinganService $dosenBimbinganService,
        private readonly RealtimeNotificationService $realtimeNotificationService,
    ) {
    }

    public function index(Request $request): Response
    {
        $lecturer = $request->user();
        abort_if($lecturer === null, 401);

        $studentIds = $this->dosenBimbinganService->activeStudentIds($lecturer);

        $schedules = MentorshipSchedule::query()
            ->with('student')
            ->where('lecturer_user_id', $lecturer->id)
            ->whereIn('student_user_id', $studentIds)
            ->latest('updated_at')
            ->get();

        $pendingRequests = $schedules
            ->where('status', 'pending')
            ->take(8)
            ->map(function (MentorshipSchedule $item): array {
                return [
                    'id' => $item->id,
                    'mahasiswa' => $item->student?->name ?? '-',
                    'topic' => $item->topic,
                    'requestedAt' => $item->requested_for?->toIso8601String(),
                    'requestedForInput' => $item->requested_for?->format('Y-m-d\TH:i'),
                    'studentNote' => $item->student_note,
                    'location' => $item->location,
                    'status' => $item->status,
                ];
            })
            ->values()
            ->all();

        $upcomingSchedules = $schedules
            ->whereIn('status', ['approved', 'rescheduled'])
            ->sortBy('scheduled_for')
            ->take(8)
            ->map(function (MentorshipSchedule $item): array {
                return [
                    'id' => $item->id,
                    'mahasiswa' => $item->student?->name ?? '-',
                    'topic' => $item->topic,
                    'date' => $item->scheduled_for?->format('d F Y') ?? '-',
                    'time' => $item->scheduled_for?->format('H:i') ?? '-',
                    'location' => $item->location ?? '-',
                    'status' => $item->status,
                    'lecturerNote' => $item->lecturer_note,
                ];
            })
            ->values()
            ->all();

        $historySchedules = $schedules
            ->whereIn('status', ['rejected', 'completed', 'cancelled'])
            ->take(12)
            ->map(function (MentorshipSchedule $item): array {
                return [
                    'id' => $item->id,
                    'mahasiswa' => $item->student?->name ?? '-',
                    'topic' => $item->topic,
                    'date' => $item->scheduled_for?->toIso8601String()
                        ?? $item->requested_for?->toIso8601String(),
                    'time' => $item->scheduled_for?->toIso8601String()
                        ?? $item->requested_for?->toIso8601String(),
                    'location' => $item->location ?? '-',
                    'status' => $item->status,
                    'lecturerNote' => $item->lecturer_note,
                ];
            })
            ->values()
            ->all();

        return Inertia::render('dosen/jadwal-bimbingan', [
            'pendingRequests' => $pendingRequests,
            'upcomingSchedules' => $upcomingSchedules,
            'historySchedules' => $historySchedules,
            'flashMessage' => $request->session()->get('success'),
        ]);
    }

    public function decide(Request $request, MentorshipSchedule $schedule): RedirectResponse
    {
        $lecturer = $request->user();
        abort_if($lecturer === null, 401);
        abort_unless($schedule->lecturer_user_id === $lecturer->id, 403);

        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject,reschedule,complete,cancel'],
            'scheduled_for' => ['nullable', 'date', 'after_or_equal:now'],
            'location' => ['nullable', 'string', 'max:255'],
            'lecturer_note' => ['nullable', 'string'],
        ]);

        if ($data['decision'] === 'reschedule' && empty($data['scheduled_for'])) {
            return back()->withErrors([
                'scheduled_for' => 'Tanggal dan jam baru wajib diisi saat menjadwalkan ulang.',
            ]);
        }

        if (in_array($data['decision'], ['approve', 'reject', 'reschedule'], true) && blank($data['lecturer_note'] ?? null)) {
            return back()->withErrors([
                'lecturer_note' => 'Feedback dosen wajib diisi saat konfirmasi, menolak, atau menjadwalkan ulang.',
            ]);
        }

        $status = match ($data['decision']) {
            'approve' => 'approved',
            'reject' => 'rejected',
            'reschedule' => 'rescheduled',
            'complete' => 'completed',
            default => 'cancelled',
        };

        $isRejectDecision = $data['decision'] === 'reject';

        $schedule->forceFill([
            'status' => $status,
            'scheduled_for' => $isRejectDecision
                ? null
                : ($data['scheduled_for'] ?? $schedule->scheduled_for ?? $schedule->requested_for),
            'location' => $data['location'] ?? $schedule->location,
            'lecturer_note' => isset($data['lecturer_note']) ? trim((string) $data['lecturer_note']) : $schedule->lecturer_note,
        ])->save();

        $this->broadcastScheduleUpdated($schedule->lecturer_user_id);
        $this->broadcastScheduleUpdated($schedule->student_user_id);

        if ($schedule->student !== null) {
            $this->realtimeNotificationService->notifyUser($schedule->student, 'konfirmasiBimbingan', [
                'title' => 'Status jadwal bimbingan diperbarui',
                'description' => sprintf('Jadwal "%s" %s.', $schedule->topic, $status),
                'url' => '/mahasiswa/jadwal-bimbingan',
                'icon' => 'calendar-clock',
                'createdAt' => now()->toIso8601String(),
            ]);
        }

        return back()->with('success', 'Keputusan jadwal berhasil disimpan.');
    }

    private function broadcastScheduleUpdated(int $userId): void
    {
        try {
            broadcast(new ScheduleUpdated($userId))->toOthers();
        } catch (Throwable $exception) {
            Log::warning('Schedule realtime update skipped because realtime server is unavailable.', [
                'user_id' => $userId,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
