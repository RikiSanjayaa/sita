<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Enums\AssignmentStatus;
use App\Http\Controllers\Controller;
use App\Models\MentorshipAssignment;
use App\Models\MentorshipSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JadwalBimbinganController extends Controller
{
    public function index(Request $request): Response
    {
        $student = $request->user();
        abort_if($student === null, 401);

        $schedules = MentorshipSchedule::query()
            ->with('lecturer')
            ->where('student_user_id', $student->id)
            ->latest('created_at')
            ->get();

        $upcomingMeetings = $schedules
            ->whereIn('status', ['pending', 'approved', 'rescheduled'])
            ->sortBy(fn (MentorshipSchedule $schedule) => $schedule->scheduled_for ?? $schedule->requested_for)
            ->map(function (MentorshipSchedule $schedule): array {
                return [
                    'id' => $schedule->id,
                    'topic' => $schedule->topic,
                    'lecturer' => $schedule->lecturer?->name ?? '-',
                    'requestedAt' => $schedule->requested_for?->format('d M Y H:i') ?? '-',
                    'scheduledAt' => $schedule->scheduled_for?->format('d M Y H:i'),
                    'location' => $schedule->location ?? '-',
                    'status' => $schedule->status,
                    'lecturerNote' => $schedule->lecturer_note,
                ];
            })
            ->values()
            ->all();

        $historyMeetings = $schedules
            ->whereIn('status', ['rejected', 'completed', 'cancelled'])
            ->map(function (MentorshipSchedule $schedule): array {
                return [
                    'id' => $schedule->id,
                    'topic' => $schedule->topic,
                    'lecturer' => $schedule->lecturer?->name ?? '-',
                    'scheduledAt' => $schedule->scheduled_for?->format('d M Y H:i')
                        ?? $schedule->requested_for?->format('d M Y H:i')
                        ?? '-',
                    'location' => $schedule->location ?? '-',
                    'status' => $schedule->status,
                    'lecturerNote' => $schedule->lecturer_note,
                ];
            })
            ->values()
            ->all();

        return Inertia::render('jadwal-bimbingan', [
            'upcomingMeetings' => $upcomingMeetings,
            'historyMeetings' => $historyMeetings,
            'flashMessage' => $request->session()->get('success'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $student = $request->user();
        abort_if($student === null, 401);

        $data = $request->validate([
            'topic' => ['required', 'string', 'max:255'],
            'requested_for' => ['required', 'date'],
            'meeting_type' => ['required', 'in:online,offline'],
            'student_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $assignments = MentorshipAssignment::query()
            ->where('student_user_id', $student->id)
            ->where('status', AssignmentStatus::Active->value)
            ->get();

        if ($assignments->isEmpty()) {
            return back()->withErrors([
                'topic' => 'Belum ada dosen pembimbing aktif. Hubungi admin untuk assignment pembimbing.',
            ]);
        }

        foreach ($assignments as $assignment) {
            MentorshipSchedule::query()->create([
                'student_user_id' => $student->id,
                'lecturer_user_id' => $assignment->lecturer_user_id,
                'mentorship_assignment_id' => $assignment->id,
                'topic' => trim($data['topic']),
                'status' => 'pending',
                'requested_for' => $data['requested_for'],
                'scheduled_for' => null,
                'location' => $data['meeting_type'] === 'online' ? 'Online (akan ditentukan dosen)' : 'Offline (akan ditentukan dosen)',
                'student_note' => $data['student_note'] ?? null,
                'lecturer_note' => null,
                'created_by_user_id' => $student->id,
            ]);
        }

        return redirect()
            ->route('mahasiswa.jadwal-bimbingan')
            ->with('success', 'Permintaan jadwal bimbingan berhasil dikirim.');
    }
}
