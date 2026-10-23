<?php

use App\Enums\AdvisorType;
use App\Enums\AppRole;
use App\Models\MahasiswaProfile;
use App\Models\MentorshipSchedule;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\User;
use App\Notifications\RealtimeNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function createSupervisedStudentForDosenSchedule(User $lecturer): User
{
    $admin = createUserWithRole(AppRole::Admin->value);
    $student = createUserWithRole(AppRole::Mahasiswa->value);

    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'is_active' => true,
    ]);

    createActiveMentorshipAssignment($student, $lecturer, $admin, AdvisorType::Primary);
    syncThesisSupervisor($student, $lecturer, $admin, AdvisorType::Primary->value);

    return $student;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function dosenSchedulePayload(User $student, array $overrides = []): array
{
    return [
        'student_user_id' => $student->id,
        'topic' => 'Review Bab 4',
        'requested_for' => now()->addDay()->format('Y-m-d\TH:i'),
        'location' => 'Ruang Dosen 3',
        'lecturer_note' => 'Bawa draft terbaru.',
        'is_recurring' => false,
        ...$overrides,
    ];
}

test('dosen can create a single schedule for a supervised student', function () {
    Notification::fake();

    $lecturer = createUserWithRole(AppRole::Dosen->value);
    $student = createSupervisedStudentForDosenSchedule($lecturer);

    $this->actingAs($lecturer)
        ->post('/dosen/jadwal-bimbingan', dosenSchedulePayload($student))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseCount('mentorship_schedules', 1);
    $this->assertDatabaseHas('mentorship_schedules', [
        'student_user_id' => $student->id,
        'lecturer_user_id' => $lecturer->id,
        'created_by_user_id' => $lecturer->id,
        'mentorship_assignment_id' => null,
        'topic' => 'Review Bab 4',
        'location' => 'Ruang Dosen 3',
        'status' => 'approved',
        'is_recurring' => false,
    ]);

    $schedule = MentorshipSchedule::query()->sole();

    expect($schedule->scheduled_for)->not->toBeNull()
        ->and($schedule->scheduled_for?->format('Y-m-d H:i:s'))->toBe($schedule->requested_for?->format('Y-m-d H:i:s'));

    Notification::assertSentTo($student, RealtimeNotification::class);
});

test('dosen can create a recurring schedule series for a supervised student', function () {
    Notification::fake();

    $lecturer = createUserWithRole(AppRole::Dosen->value);
    $student = createSupervisedStudentForDosenSchedule($lecturer);

    $this->actingAs($lecturer)
        ->post('/dosen/jadwal-bimbingan', dosenSchedulePayload($student, [
            'is_recurring' => true,
            'recurring_pattern' => 'weekly',
            'recurring_count' => 3,
        ]))
        ->assertRedirect()
        ->assertSessionHas('success');

    $schedules = MentorshipSchedule::query()->orderBy('recurring_index')->get();

    expect($schedules)->toHaveCount(3)
        ->and($schedules->pluck('recurring_index')->all())->toBe([1, 2, 3])
        ->and($schedules->pluck('recurring_group_id')->unique()->count())->toBe(1)
        ->and($schedules->pluck('status')->unique()->all())->toBe(['approved'])
        ->and($schedules->pluck('recurring_pattern')->unique()->all())->toBe(['weekly'])
        ->and($schedules->every(fn (MentorshipSchedule $schedule): bool => $schedule->scheduled_for !== null))->toBeTrue()
        ->and($schedules->every(fn (MentorshipSchedule $schedule): bool => $schedule->created_by_user_id === $lecturer->id))->toBeTrue();

    expect(Carbon::parse($schedules[1]->requested_for)->toDateString())
        ->toBe(Carbon::parse($schedules[0]->requested_for)->addWeek()->toDateString());
    expect(Carbon::parse($schedules[2]->requested_for)->toDateString())
        ->toBe(Carbon::parse($schedules[0]->requested_for)->addWeeks(2)->toDateString());
});

test('dosen schedule rejects a student outside their supervision', function (string $relation) {
    $lecturer = createUserWithRole(AppRole::Dosen->value);
    $otherLecturer = createUserWithRole(AppRole::Dosen->value);
    $student = createSupervisedStudentForDosenSchedule($otherLecturer);

    if ($relation === 'penguji') {
        $defense = ThesisDefense::query()->create([
            'project_id' => ensureActiveThesisProject($student)->id,
            'title_version_id' => null,
            'type' => 'sempro',
            'attempt_no' => 1,
            'status' => 'scheduled',
            'result' => 'pending',
            'mode' => 'offline',
            'scheduled_for' => now()->addDays(2),
            'location' => 'Ruang Uji Sempro',
            'created_by' => $otherLecturer->id,
        ]);

        ThesisDefenseExaminer::query()->create([
            'defense_id' => $defense->id,
            'lecturer_user_id' => $lecturer->id,
            'role' => 'examiner',
            'order_no' => 1,
            'decision' => 'pending',
            'assigned_by' => $otherLecturer->id,
        ]);
    }

    $this->actingAs($lecturer)
        ->from('/dosen/jadwal-bimbingan')
        ->post('/dosen/jadwal-bimbingan', dosenSchedulePayload($student))
        ->assertRedirect('/dosen/jadwal-bimbingan')
        ->assertSessionHasErrors('student_user_id');

    $this->assertDatabaseCount('mentorship_schedules', 0);
})->with([
    'bukan pembimbing' => ['bukan pembimbing'],
    'penguji' => ['penguji'],
]);

test('student sees schedules created by the dosen marked as created by lecturer', function () {
    Notification::fake();

    $lecturer = createUserWithRole(AppRole::Dosen->value);
    $student = createSupervisedStudentForDosenSchedule($lecturer);

    $this->actingAs($lecturer)
        ->post('/dosen/jadwal-bimbingan', dosenSchedulePayload($student))
        ->assertRedirect();

    $this->actingAs($student)
        ->get('/mahasiswa/jadwal-bimbingan')
        ->assertInertia(fn (Assert $page) => $page
            ->has('upcomingMeetings', 1)
            ->where('upcomingMeetings.0.createdByLecturer', true));
});
