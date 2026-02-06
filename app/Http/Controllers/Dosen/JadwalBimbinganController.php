<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\MentorshipSchedule;
use App\Services\DosenBimbinganService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JadwalBimbinganController extends Controller
{
    public function __construct(
        private readonly DosenBimbinganService $dosenBimbinganService,
    ) {}

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
                    'requestedAt' => $item->requested_for?->format('d F Y H:i') ?? '-',
                    'requestedForInput' => $item->requested_for?->format('Y-m-d\TH:i'),
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
                ];
            })
            ->values()
            ->all();

        return Inertia::render('dosen/jadwal-bimbingan', [
            'pendingRequests' => $pendingRequests,
            'upcomingSchedules' => $upcomingSchedules,
            'flashMessage' => $request->session()->get('success'),
        ]);
    }

    public function decide(Request $request, MentorshipSchedule $schedule): RedirectResponse
    {
        $lecturer = $request->user();
        abort_if($lecturer === null, 401);
        abort_unless($schedule->lecturer_user_id === $lecturer->id, 403);

        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject,reschedule'],
            'scheduled_for' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'lecturer_note' => ['nullable', 'string'],
        ]);

        $status = match ($data['decision']) {
            'approve' => 'approved',
            'reject' => 'rejected',
            default => 'rescheduled',
        };

        $schedule->forceFill([
            'status' => $status,
            'scheduled_for' => $data['decision'] === 'reject'
                ? null
                : ($data['scheduled_for'] ?? $schedule->scheduled_for),
            'location' => $data['location'] ?? $schedule->location,
            'lecturer_note' => $data['lecturer_note'] ?? $schedule->lecturer_note,
        ])->save();

        return back()->with('success', 'Keputusan jadwal berhasil disimpan.');
    }
}
