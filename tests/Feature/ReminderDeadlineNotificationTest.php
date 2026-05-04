<?php

use App\Enums\AdvisorType;
use App\Models\DosenProfile;
use App\Models\MahasiswaProfile;
use App\Models\MentorshipSchedule;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisProject;
use App\Models\ThesisProjectTitle;
use App\Models\ThesisRevision;
use App\Models\User;
use App\Notifications\RealtimeNotification;
use App\Services\ReminderDeadlineNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

function createReminderLecturer(ProgramStudi $programStudi, array $attributes = []): User
{
    $lecturer = User::factory()->asDosen()->create($attributes);

    DosenProfile::factory()->create([
        'user_id' => $lecturer->id,
        'program_studi_id' => $programStudi->id,
        'concentration' => ProgramStudi::DEFAULT_GENERAL_CONCENTRATION,
        'is_active' => true,
    ]);

    return $lecturer;
}

function createReminderStudent(ProgramStudi $programStudi, array $attributes = []): User
{
    $student = User::factory()->asMahasiswa()->create($attributes);

    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'program_studi_id' => $programStudi->id,
        'concentration' => ProgramStudi::DEFAULT_GENERAL_CONCENTRATION,
        'is_active' => true,
    ]);

    return $student;
}

function createReminderProject(User $student, ProgramStudi $programStudi, string $phase = 'sempro'): ThesisProject
{
    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $programStudi->id,
        'phase' => $phase,
        'state' => 'active',
        'started_at' => now()->subWeek(),
        'created_by' => $student->id,
    ]);

    ThesisProjectTitle::query()->create([
        'project_id' => $project->id,
        'version_no' => 1,
        'title_id' => 'Pengujian Reminder Deadline',
        'title_en' => 'Reminder Deadline Testing',
        'proposal_summary' => 'Ringkasan untuk pengujian reminder deadline.',
        'status' => 'approved',
        'submitted_by_user_id' => $student->id,
        'submitted_at' => now()->subDays(6),
    ]);

    return $project;
}

function attachExaminer(ThesisDefense $defense, User $lecturer, string $role, int $order): void
{
    ThesisDefenseExaminer::query()->create([
        'defense_id' => $defense->id,
        'lecturer_user_id' => $lecturer->id,
        'role' => $role,
        'order_no' => $order,
        'decision' => 'pending',
        'assigned_by' => $lecturer->id,
    ]);
}

test('deadline reminder service sends reminders for upcoming bimbingan sempro sidang and revision deadlines', function (): void {
    Notification::fake();

    $now = CarbonImmutable::parse('2026-03-21 08:00:00');
    $programStudi = ProgramStudi::factory()->create();

    $studentBimbingan = createReminderStudent($programStudi);
    $lecturerBimbingan = createReminderLecturer($programStudi);

    MentorshipSchedule::factory()->create([
        'student_user_id' => $studentBimbingan->id,
        'lecturer_user_id' => $lecturerBimbingan->id,
        'mentorship_assignment_id' => null,
        'topic' => 'Review Bab 4',
        'status' => 'approved',
        'scheduled_for' => $now->addMinutes(30),
        'requested_for' => $now->addMinutes(30),
    ]);

    $studentSempro = createReminderStudent($programStudi);
    $examinerSemproA = createReminderLecturer($programStudi);
    $examinerSemproB = createReminderLecturer($programStudi);
    $projectSempro = createReminderProject($studentSempro, $programStudi, 'sempro');

    $sempro = ThesisDefense::query()->create([
        'project_id' => $projectSempro->id,
        'title_version_id' => $projectSempro->latestTitle?->id,
        'type' => 'sempro',
        'attempt_no' => 1,
        'status' => 'scheduled',
        'result' => 'pending',
        'scheduled_for' => $now->addHours(23)->addMinutes(30),
        'location' => 'Ruang Sempro A',
        'mode' => 'offline',
        'created_by' => $examinerSemproA->id,
    ]);

    attachExaminer($sempro, $examinerSemproA, 'examiner', 1);
    attachExaminer($sempro, $examinerSemproB, 'examiner', 2);

    $studentSidang = createReminderStudent($programStudi);
    $examinerSidangA = createReminderLecturer($programStudi);
    $examinerSidangB = createReminderLecturer($programStudi);
    $projectSidang = createReminderProject($studentSidang, $programStudi, 'sidang');

    $sidang = ThesisDefense::query()->create([
        'project_id' => $projectSidang->id,
        'title_version_id' => $projectSidang->latestTitle?->id,
        'type' => 'sidang',
        'attempt_no' => 1,
        'status' => 'scheduled',
        'result' => 'pending',
        'scheduled_for' => $now->addMinutes(45),
        'location' => 'Ruang Sidang 2',
        'mode' => 'offline',
        'created_by' => $examinerSidangA->id,
    ]);

    attachExaminer($sidang, $examinerSidangA, AdvisorType::Primary->value, 1);
    attachExaminer($sidang, $examinerSidangB, 'examiner', 2);

    $studentRevision = createReminderStudent($programStudi);
    $projectRevision = createReminderProject($studentRevision, $programStudi, 'sidang');
    $defenseRevision = ThesisDefense::query()->create([
        'project_id' => $projectRevision->id,
        'title_version_id' => $projectRevision->latestTitle?->id,
        'type' => 'sidang',
        'attempt_no' => 1,
        'status' => 'completed',
        'result' => 'pass_with_revision',
        'scheduled_for' => $now->subDay(),
        'location' => 'Ruang Sidang 3',
        'mode' => 'offline',
        'created_by' => $examinerSidangA->id,
    ]);

    ThesisRevision::query()->create([
        'project_id' => $projectRevision->id,
        'defense_id' => $defenseRevision->id,
        'requested_by_user_id' => $examinerSidangA->id,
        'status' => 'open',
        'notes' => 'Lengkapi revisi dokumen final.',
        'due_at' => $now->addMinutes(40),
    ]);

    $sentCount = app(ReminderDeadlineNotificationService::class)->sendUpcomingReminders($now);

    expect($sentCount)->toBe(9);

    Notification::assertSentTo($studentBimbingan, RealtimeNotification::class, function (RealtimeNotification $notification, array $channels) use ($studentBimbingan): bool {
        $data = $notification->toArray($studentBimbingan);

        return in_array('database', $channels, true)
            && $data['title'] === 'Reminder jadwal bimbingan'
            && str_contains($data['description'], 'Review Bab 4')
            && $data['preferenceKey'] === 'reminderDeadline';
    });

    Notification::assertSentTo($studentSempro, RealtimeNotification::class, function (RealtimeNotification $notification, array $channels) use ($studentSempro): bool {
        $data = $notification->toArray($studentSempro);

        return in_array('broadcast', $channels, true)
            && $data['title'] === 'Reminder sempro'
            && str_contains($data['description'], 'Ruang Sempro A')
            && $data['preferenceKey'] === 'reminderDeadline';
    });

    Notification::assertSentTo($studentSidang, RealtimeNotification::class, function (RealtimeNotification $notification, array $channels) use ($studentSidang): bool {
        $data = $notification->toArray($studentSidang);

        return in_array('broadcast', $channels, true)
            && $data['title'] === 'Reminder sidang'
            && str_contains($data['description'], 'Ruang Sidang 2')
            && $data['preferenceKey'] === 'reminderDeadline';
    });

    Notification::assertSentTo($studentRevision, RealtimeNotification::class, function (RealtimeNotification $notification, array $channels) use ($studentRevision): bool {
        $data = $notification->toArray($studentRevision);

        return in_array('database', $channels, true)
            && $data['title'] === 'Reminder deadline revisi'
            && str_contains($data['description'], 'revisi sidang')
            && $data['preferenceKey'] === 'reminderDeadline';
    });
});

test('deadline reminder service respects reminder preference and avoids duplicates', function (): void {
    $now = CarbonImmutable::parse('2026-03-21 08:00:00');
    $programStudi = ProgramStudi::factory()->create();

    $student = createReminderStudent($programStudi, [
        'notification_preferences' => [
            'reminderDeadline' => false,
        ],
    ]);
    $lecturer = createReminderLecturer($programStudi);

    $schedule = MentorshipSchedule::factory()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $lecturer->id,
        'topic' => 'Review Proposal',
        'status' => 'approved',
        'scheduled_for' => $now->addMinutes(20),
        'requested_for' => $now->addMinutes(20),
    ]);

    $service = app(ReminderDeadlineNotificationService::class);

    $firstRunCount = $service->sendUpcomingReminders($now);
    $secondRunCount = $service->sendUpcomingReminders($now);

    expect($firstRunCount)->toBe(1)
        ->and($secondRunCount)->toBe(0)
        ->and($student->fresh()->notifications()->count())->toBe(0)
        ->and($lecturer->fresh()->notifications()->count())->toBe(1)
        ->and($lecturer->fresh()->notifications()->first()?->data['reminderKey'] ?? null)->toBe("mentorship_schedule:{$schedule->id}:hour");
});

test('deadline reminder command runs successfully', function (): void {
    Artisan::call('notifications:send-deadline-reminders', [
        '--at' => '2026-03-21 08:00:00',
    ]);

    expect(Artisan::output())->toContain('Sent');
});
