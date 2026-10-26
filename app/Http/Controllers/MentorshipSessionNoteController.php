<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMentorshipSessionNoteRequest;
use App\Models\MentorshipSchedule;
use Illuminate\Http\RedirectResponse;

class MentorshipSessionNoteController extends Controller
{
    public function __invoke(UpdateMentorshipSessionNoteRequest $request, MentorshipSchedule $schedule): RedirectResponse
    {
        $side = (int) $request->user()->id === (int) $schedule->student_user_id ? 'student' : 'lecturer';
        $schedule->forceFill([
            $side.'_session_note' => $request->validated('body'),
            $side.'_session_noted_at' => now(),
        ])->save();

        return back()->with('success', 'Catatan sesi bimbingan disimpan.');
    }
}
