<?php

use App\Enums\AppRole;
use App\Models\MahasiswaProfile;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipDocument;
use App\Models\MentorshipSchedule;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisSupervisorAssignment;
use App\Services\StudentActivityService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-23 12:00:00'));
});

function studentActivityFixture(): array
{
    $lecturer = createUserWithRole(AppRole::Dosen->value);
    $student = createUserWithRole(AppRole::Mahasiswa->value);
    MahasiswaProfile::factory()->create(['user_id' => $student->id, 'is_active' => true]);
    $project = ensureActiveThesisProject($student);
    $project->update(['started_at' => now()->subMonths(2)]);
    $assignment = ThesisSupervisorAssignment::query()->create([
        'project_id' => $project->id, 'lecturer_user_id' => $lecturer->id,
        'role' => 'primary', 'status' => 'active', 'started_at' => now()->subMonth(),
        'assigned_by' => $lecturer->id,
    ]);
    $thread = MentorshipChatThread::factory()->create(['student_user_id' => $student->id, 'type' => 'pembimbing']);

    return compact('lecturer', 'student', 'project', 'assignment', 'thread');
}

function studentActivityDocument(array $fixture, int $days): MentorshipDocument
{
    return MentorshipDocument::factory()->create([
        'student_user_id' => $fixture['student']->id, 'lecturer_user_id' => $fixture['lecturer']->id,
        'uploaded_by_user_id' => $fixture['student']->id,
        'created_at' => now()->subDays($days), 'updated_at' => now()->subDays($days),
    ]);
}

function studentActivityMessage(array $fixture, int $days, array $overrides = []): MentorshipChatMessage
{
    return MentorshipChatMessage::factory()->create([
        'mentorship_chat_thread_id' => $fixture['thread']->id,
        'sender_user_id' => $fixture['student']->id, 'message_type' => 'text',
        'sent_at' => now()->subDays($days), ...$overrides,
    ]);
}

it('uses the newest document regardless of insertion order', function (array $days) {
    $fixture = studentActivityFixture();
    foreach ($days as $day) {
        studentActivityDocument($fixture, $day);
    }

    $this->actingAs($fixture['lecturer'])->get('/dosen/mahasiswa-bimbingan')
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('mahasiswaRows.0.lastUpdate', now()->subDays(2)->locale('id')->diffForHumans()));
})->with([[[2, 10, 5]], [[10, 5, 2]]]);

it('takes the maximum document student chat and lecturer completion timestamp', function (int $documentDays, int $chatDays, int $completedDays) {
    $fixture = studentActivityFixture();
    studentActivityDocument($fixture, $documentDays);
    studentActivityMessage($fixture, $chatDays);
    MentorshipSchedule::factory()->create([
        'student_user_id' => $fixture['student']->id, 'lecturer_user_id' => $fixture['lecturer']->id,
        'status' => 'completed', 'created_at' => now()->subDays(20),
        'updated_at' => now()->subDays($completedDays),
    ]);

    $this->actingAs($fixture['lecturer'])->get('/dosen/mahasiswa-bimbingan')
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('mahasiswaRows.0.lastUpdate', now()->subDays(min($documentDays, $chatDays, $completedDays))->locale('id')->diffForHumans()));
})->with([[2, 8, 6], [8, 2, 6], [8, 6, 2]]);

it('ignores requests schedules approvals lecturer system and unrelated private messages', function () {
    $fixture = studentActivityFixture();
    foreach (['pending', 'approved', 'rescheduled', 'cancelled', 'rejected'] as $status) {
        MentorshipSchedule::factory()->create([
            'student_user_id' => $fixture['student']->id, 'lecturer_user_id' => $fixture['lecturer']->id,
            'status' => $status,
        ]);
    }
    studentActivityMessage($fixture, 0, ['sender_user_id' => $fixture['lecturer']->id]);
    studentActivityMessage($fixture, 0, ['message_type' => 'system']);
    studentActivityMessage($fixture, 0, ['sender_user_id' => createUserWithRole(AppRole::Mahasiswa->value)->id]);
    $private = MentorshipChatThread::factory()->create(['student_user_id' => $fixture['student']->id, 'type' => 'private']);
    studentActivityMessage($fixture, 0, ['mentorship_chat_thread_id' => $private->id]);
    $other = createUserWithRole(AppRole::Dosen->value);
    MentorshipSchedule::factory()->create([
        'student_user_id' => $fixture['student']->id, 'lecturer_user_id' => $other->id, 'status' => 'completed',
    ]);

    $this->actingAs($fixture['lecturer'])->get('/dosen/mahasiswa-bimbingan')
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('mahasiswaRows.0.lastUpdate', 'Belum ada aktivitas'));
});

it('isolates ended and current supervisor lifetimes and project attempts', function () {
    $fixture = studentActivityFixture();
    $old = $fixture['assignment'];
    $old->update(['status' => 'ended', 'started_at' => now()->subDays(25), 'ended_at' => now()->subDays(15)]);
    $current = ThesisSupervisorAssignment::query()->create([
        'project_id' => $fixture['project']->id, 'lecturer_user_id' => $fixture['lecturer']->id,
        'role' => 'primary', 'status' => 'active', 'started_at' => now()->subDays(10), 'assigned_by' => $fixture['lecturer']->id,
    ]);
    studentActivityDocument($fixture, 20);
    studentActivityMessage($fixture, 2);
    studentActivityDocument($fixture, 50);

    $this->actingAs($fixture['lecturer'])->get('/dosen/mahasiswa-bimbingan')
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('mahasiswaRows.0.lastUpdate', now()->subDays(2)->locale('id')->diffForHumans())
        ->where('historyRows.0.lastUpdate', now()->subDays(20)->locale('id')->diffForHumans()));
});

it('isolates examiner defense chats and compares exam events rather than coalescing', function () {
    $fixture = studentActivityFixture();
    $defenses = collect();
    $examiners = collect();
    foreach (['sempro', 'sidang'] as $type) {
        $defense = ThesisDefense::query()->create([
            'project_id' => $fixture['project']->id, 'type' => $type, 'attempt_no' => 1,
            'status' => 'scheduled', 'result' => 'pending', 'mode' => 'offline',
            'created_by' => $fixture['lecturer']->id,
        ]);
        $defense->forceFill(['created_at' => now()->subDays(12), 'updated_at' => now()->subDays(10)])->save();
        $examiner = ThesisDefenseExaminer::query()->create([
            'defense_id' => $defense->id, 'lecturer_user_id' => $fixture['lecturer']->id,
            'role' => 'examiner', 'order_no' => 1, 'decision' => 'pending',
            'assigned_by' => $fixture['lecturer']->id, 'decided_at' => now()->subDays(15),
        ]);
        $defenses->push($defense);
        $examiners->push($examiner);
    }
    studentActivityDocument($fixture, 8);
    $examThread = MentorshipChatThread::factory()->create([
        'student_user_id' => $fixture['student']->id, 'type' => 'sempro', 'context_id' => $defenses[0]->id,
    ]);
    studentActivityMessage($fixture, 2, ['mentorship_chat_thread_id' => $examThread->id]);
    studentActivityMessage($fixture, 1); // Supervision chat must not advance examiner rows.
    studentActivityMessage($fixture, 0, ['mentorship_chat_thread_id' => $examThread->id, 'sender_user_id' => $fixture['lecturer']->id]);
    $unrelatedAttempt = MentorshipChatThread::factory()->create([
        'student_user_id' => $fixture['student']->id, 'type' => 'sempro', 'context_id' => $defenses[1]->id,
    ]);
    studentActivityMessage($fixture, 0, ['mentorship_chat_thread_id' => $unrelatedAttempt->id]);
    $latest = app(StudentActivityService::class)->latest(collect(), $examiners);
    expect($latest['examiner:'.$examiners[0]->id]->toDateTimeString())->toBe(now()->subDays(2)->toDateTimeString())
        ->and($latest['examiner:'.$examiners[1]->id]->toDateTimeString())->toBe(now()->subDays(8)->toDateTimeString());
    $defenses[1]->forceFill(['updated_at' => now()->subDay()])->save();
    $latest = app(StudentActivityService::class)->latest(collect(), $examiners->map->fresh());
    expect($latest['examiner:'.$examiners[1]->id]->toDateTimeString())->toBe(now()->subDay()->toDateTimeString());
});

it('aggregates multiple students in three queries without per-row activity lookups', function () {
    $fixtures = collect([studentActivityFixture(), studentActivityFixture(), studentActivityFixture()]);
    foreach ($fixtures as $fixture) {
        studentActivityDocument($fixture, 10);
        foreach ([2, 7, 4] as $days) {
            MentorshipSchedule::factory()->create([
                'student_user_id' => $fixture['student']->id, 'lecturer_user_id' => $fixture['lecturer']->id,
                'status' => 'completed', 'created_at' => now()->subDays(20), 'updated_at' => now()->subDays($days),
            ]);
        }
    }
    $assignments = $fixtures->pluck('assignment');
    $assignments->each(fn ($assignment) => $assignment->load('project'));
    \Illuminate\Support\Facades\DB::enableQueryLog();
    \Illuminate\Support\Facades\DB::flushQueryLog();
    try {
        $latest = app(StudentActivityService::class)->latest($assignments, collect());
        expect(\Illuminate\Support\Facades\DB::getQueryLog())->toHaveCount(3);
        foreach ($assignments as $assignment) {
            expect($latest['supervisor:'.$assignment->id]->toDateTimeString())->toBe(now()->subDays(2)->toDateTimeString());
        }
    } finally {
        \Illuminate\Support\Facades\DB::disableQueryLog();
    }
});

it('does not reuse documents and student messages from before the current project', function () {
    $fixture = studentActivityFixture();
    $fixture['project']->update(['started_at' => now()->subDays(10)]);
    studentActivityDocument($fixture, 20);
    studentActivityMessage($fixture, 15);
    $fixture['assignment']->load('project');
    $latest = app(StudentActivityService::class)->latest(collect([$fixture['assignment']]), collect());
    expect($latest)->toBe([]);

    studentActivityDocument($fixture, 4);
    $latest = app(StudentActivityService::class)->latest(collect([$fixture['assignment']]), collect());
    expect($latest['supervisor:'.$fixture['assignment']->id]->toDateTimeString())->toBe(now()->subDays(4)->toDateTimeString());
});

it('only exposes open examiner contexts as active and excludes cancelled defenses', function (string $status, int $activeCount, int $historyCount) {
    $fixture = studentActivityFixture();
    $fixture['assignment']->delete();
    $defense = ThesisDefense::query()->create([
        'project_id' => $fixture['project']->id, 'type' => 'sempro', 'attempt_no' => 1,
        'status' => $status, 'result' => 'pending', 'mode' => 'offline', 'created_by' => $fixture['lecturer']->id,
    ]);
    ThesisDefenseExaminer::query()->create([
        'defense_id' => $defense->id, 'lecturer_user_id' => $fixture['lecturer']->id,
        'role' => 'examiner', 'order_no' => 1, 'decision' => 'pending', 'assigned_by' => $fixture['lecturer']->id,
    ]);

    $this->actingAs($fixture['lecturer'])->get('/dosen/mahasiswa-bimbingan')
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('mahasiswaRows', $activeCount)->has('historyRows', $historyCount));
})->with([
    ['draft', 1, 0],
    ['scheduled', 1, 0],
    ['awaiting_finalization', 1, 0],
    ['completed', 0, 1],
    ['cancelled', 0, 0],
]);

it('counts student messages to the related lecturer in private chat regardless of initiator', function (bool $lecturerInitiates) {
    $fixture = studentActivityFixture();
    studentActivityDocument($fixture, 10);
    $initiator = $lecturerInitiates ? $fixture['lecturer'] : $fixture['student'];
    $recipient = $lecturerInitiates ? $fixture['student'] : $fixture['lecturer'];
    $thread = app(\App\Services\PrivateChatService::class)->findOrCreateThread($initiator, $recipient);
    expect($thread->student_user_id)->toBe($lecturerInitiates ? null : $fixture['student']->id);

    studentActivityMessage($fixture, 15, ['mentorship_chat_thread_id' => $thread->id]);
    studentActivityMessage($fixture, 2, ['mentorship_chat_thread_id' => $thread->id]);
    studentActivityMessage($fixture, 0, [
        'mentorship_chat_thread_id' => $thread->id, 'sender_user_id' => $fixture['lecturer']->id,
    ]);
    studentActivityMessage($fixture, 0, ['mentorship_chat_thread_id' => $thread->id, 'message_type' => 'system']);
    $otherLecturer = createUserWithRole(AppRole::Dosen->value);
    $unrelated = app(\App\Services\PrivateChatService::class)->findOrCreateThread($fixture['student'], $otherLecturer);
    studentActivityMessage($fixture, 0, ['mentorship_chat_thread_id' => $unrelated->id]);

    $this->actingAs($fixture['lecturer'])->get('/dosen/mahasiswa-bimbingan')
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('mahasiswaRows.0.lastUpdate', now()->subDays(2)->locale('id')->diffForHumans()));
})->with([false, true]);

it('requires both actual private thread participants and keeps private chat within assignment lifetime', function () {
    $fixture = studentActivityFixture();
    $fixture['assignment']->update(['started_at' => now()->subDays(5)]);
    $thread = app(\App\Services\PrivateChatService::class)->findOrCreateThread($fixture['lecturer'], $fixture['student']);
    studentActivityMessage($fixture, 6, ['mentorship_chat_thread_id' => $thread->id]);
    $this->actingAs($fixture['lecturer'])->get('/dosen/mahasiswa-bimbingan')
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('mahasiswaRows.0.lastUpdate', 'Belum ada aktivitas'));

    studentActivityMessage($fixture, 2, ['mentorship_chat_thread_id' => $thread->id]);
    $thread->participants()->where('user_id', $fixture['student']->id)->delete();
    $this->actingAs($fixture['lecturer'])->get('/dosen/mahasiswa-bimbingan')
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('mahasiswaRows.0.lastUpdate', 'Belum ada aktivitas'));

    $thread->participants()->create(['user_id' => $fixture['student']->id, 'role' => 'student']);
    $thread->participants()->where('user_id', $fixture['lecturer']->id)->delete();
    $this->actingAs($fixture['lecturer'])->get('/dosen/mahasiswa-bimbingan')
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('mahasiswaRows.0.lastUpdate', 'Belum ada aktivitas'));
});
