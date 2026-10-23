<?php

use App\Filament\Resources\ThesisProjects\Pages\ViewThesisProject;
use App\Models\AdminProfile;
use App\Models\DosenProfile;
use App\Models\KaprodiAssignment;
use App\Models\MahasiswaProfile;
use App\Models\MentorshipSchedule;
use App\Models\ProgramStudi;
use App\Models\ThesisProject;
use App\Models\ThesisProjectEvent;
use App\Models\ThesisProjectTitle;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Services\MentorshipProgressService;
use Filament\Schemas\Components\Callout;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;

/**
 * @return array{project: ThesisProject, student: User, prodi: ProgramStudi, primary: User, secondary: User}
 */
function mentorshipProject(?string $startedAt = null): array
{
    $prodi = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);
    $student = User::factory()->asMahasiswa()->create();
    $primary = User::factory()->asDosen()->create(['name' => 'Dosen Satu']);
    $secondary = User::factory()->asDosen()->create(['name' => 'Dosen Dua']);

    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'is_active' => true,
    ]);

    foreach ([$primary, $secondary] as $lecturer) {
        DosenProfile::factory()->create([
            'user_id' => $lecturer->id,
            'program_studi_id' => $prodi->id,
            'is_active' => true,
        ]);
    }

    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'research',
        'state' => 'active',
        'started_at' => now()->subMonths(6),
        'created_by' => $student->id,
    ]);

    ThesisProjectTitle::query()->create([
        'project_id' => $project->id,
        'version_no' => 1,
        'title_id' => 'Judul Pemantauan Bimbingan',
        'status' => 'approved',
        'submitted_by_user_id' => $student->id,
        'submitted_at' => now()->subMonths(6),
    ]);

    foreach (['primary' => $primary, 'secondary' => $secondary] as $role => $lecturer) {
        ThesisSupervisorAssignment::query()->create([
            'project_id' => $project->id,
            'lecturer_user_id' => $lecturer->id,
            'role' => $role,
            'status' => 'active',
            'assigned_by' => $student->id,
            'started_at' => $startedAt ?? now()->subMonths(5),
        ]);
    }

    return compact('project', 'student', 'prodi', 'primary', 'secondary');
}

function mentorshipSessions(User $student, User $lecturer, int $count, string $status = 'completed', mixed $when = null): void
{
    MentorshipSchedule::factory()->count($count)->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $lecturer->id,
        'status' => $status,
        'requested_for' => $when ?? now()->subMonth(),
        'scheduled_for' => $when ?? now()->subMonth(),
    ]);
}

it('counts only completed sessions per active supervisor', function (string $status, int $expected): void {
    ['project' => $project, 'student' => $student, 'primary' => $primary] = mentorshipProject();

    mentorshipSessions($student, $primary, 3, $status);

    $progress = app(MentorshipProgressService::class)->forProject($project);

    expect($progress)->toHaveCount(2)
        ->and($progress[0]['lecturer']['id'])->toBe($primary->id)
        ->and($progress[0]['completed'])->toBe($expected)
        ->and($progress[0]['minimum'])->toBe(6)
        ->and($progress[0]['met'])->toBeFalse()
        ->and($progress[1]['completed'])->toBe(0);
})->with([
    'completed' => ['completed', 3],
    'pending' => ['pending', 0],
    'approved' => ['approved', 0],
    'rescheduled' => ['rescheduled', 0],
    'rejected' => ['rejected', 0],
    'cancelled' => ['cancelled', 0],
]);

it('marks a supervisor as met at the configured minimum', function (): void {
    ['project' => $project, 'student' => $student, 'primary' => $primary] = mentorshipProject();

    mentorshipSessions($student, $primary, 6);

    expect(app(MentorshipProgressService::class)->forProject($project)[0])
        ->completed->toBe(6)
        ->met->toBeTrue();

    config(['sita.minimum_mentorship_sessions' => 7]);

    expect(app(MentorshipProgressService::class)->forProject($project)[0])
        ->minimum->toBe(7)
        ->met->toBeFalse();
});

it('only counts sessions since the assignment started', function (): void {
    ['project' => $project, 'student' => $student, 'primary' => $primary] = mentorshipProject(now()->subWeek()->toDateTimeString());

    mentorshipSessions($student, $primary, 4, 'completed', now()->subWeeks(3));
    mentorshipSessions($student, $primary, 2, 'completed', now()->subDay());

    expect(app(MentorshipProgressService::class)->forProject($project)[0]['completed'])->toBe(2);
});

it('counts progress of the replacement supervisor and ignores ended assignments', function (): void {
    ['project' => $project, 'student' => $student, 'primary' => $primary, 'secondary' => $secondary] = mentorshipProject();
    $replacement = User::factory()->asDosen()->create(['name' => 'Dosen Pengganti']);

    mentorshipSessions($student, $primary, 6);
    mentorshipSessions($student, $replacement, 1, 'completed', now()->subDay());

    ThesisSupervisorAssignment::query()
        ->where('project_id', $project->id)
        ->where('lecturer_user_id', $primary->id)
        ->update(['status' => 'ended', 'ended_at' => now()->subDays(2)]);
    ThesisSupervisorAssignment::query()->create([
        'project_id' => $project->id,
        'lecturer_user_id' => $replacement->id,
        'role' => 'primary',
        'status' => 'active',
        'assigned_by' => $student->id,
        'started_at' => now()->subDays(2),
    ]);

    $progress = app(MentorshipProgressService::class)->forProject($project->fresh());

    expect(collect($progress)->pluck('lecturer.id')->all())->toBe([$replacement->id, $secondary->id])
        ->and($progress[0]['completed'])->toBe(1)
        ->and($progress[0]['met'])->toBeFalse();
});

it('does not count sessions with another student or lecturer', function (): void {
    ['project' => $project, 'student' => $student, 'primary' => $primary] = mentorshipProject();
    $otherStudent = User::factory()->asMahasiswa()->create();
    $otherLecturer = User::factory()->asDosen()->create();

    mentorshipSessions($otherStudent, $primary, 6);
    mentorshipSessions($student, $otherLecturer, 6);

    expect(app(MentorshipProgressService::class)->forProject($project)[0]['completed'])->toBe(0);
});

it('shows the mentorship warning to the kaprodi only for unmet supervisors', function (): void {
    ['project' => $project, 'student' => $student, 'prodi' => $prodi, 'primary' => $primary, 'secondary' => $secondary] = mentorshipProject();
    $kaprodi = User::factory()->asKaprodi()->create();
    KaprodiAssignment::factory()->create([
        'program_studi_id' => $prodi->id,
        'user_id' => $kaprodi->id,
        'is_primary' => true,
    ]);

    mentorshipSessions($student, $primary, 6);
    mentorshipSessions($student, $secondary, 2);

    $this->actingAs($kaprodi)
        ->get('/kaprodi/sempro-sidang')
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->component('kaprodi/sempro-sidang')
            ->where('schedulableProjects.0.mentorshipProgress.0.met', true)
            ->where('schedulableProjects.0.mentorshipProgress.0.completed', 6)
            ->where('schedulableProjects.0.mentorshipProgress.1.met', false)
            ->where('schedulableProjects.0.mentorshipProgress.1.completed', 2)
            ->where('schedulableProjects.0.mentorshipProgress.1.minimum', 6));

    mentorshipSessions($student, $secondary, 4);

    $this->actingAs($kaprodi)
        ->get('/kaprodi/sempro-sidang')
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('schedulableProjects.0.mentorshipProgress.1.met', true)
            ->where('schedulableProjects.0.mentorshipProgress.1.completed', 6));
});

it('records the unmet minimum in the audit and event payloads when kaprodi schedules sidang', function (): void {
    Notification::fake();
    ['project' => $project, 'student' => $student, 'prodi' => $prodi, 'primary' => $primary, 'secondary' => $secondary] = mentorshipProject();
    $kaprodi = User::factory()->asKaprodi()->create();
    $examiner = User::factory()->asDosen()->create();
    KaprodiAssignment::factory()->create([
        'program_studi_id' => $prodi->id,
        'user_id' => $kaprodi->id,
        'is_primary' => true,
    ]);
    DosenProfile::factory()->create(['user_id' => $examiner->id, 'program_studi_id' => $prodi->id, 'is_active' => true]);

    mentorshipSessions($student, $primary, 6);
    mentorshipSessions($student, $secondary, 2);

    $this->actingAs($kaprodi)
        ->post("/kaprodi/projects/{$project->id}/sidang", [
            'scheduled_date_start' => now()->addWeeks(2)->format('Y-m-d'),
            'scheduled_date_end' => now()->addWeeks(2)->format('Y-m-d'),
            'scheduled_time' => '10:00',
            'location' => 'Ruang Sidang',
            'mode' => 'offline',
            'additional_examiner_user_ids' => [$examiner->id],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $expected = [
        ['lecturer_user_id' => $primary->id, 'completed' => 6, 'minimum' => 6],
        ['lecturer_user_id' => $secondary->id, 'completed' => 2, 'minimum' => 6],
    ];

    $audit = \App\Models\SystemAuditLog::query()->where('event_type', 'kaprodi_sidang_scheduled')->firstOrFail();
    $event = ThesisProjectEvent::query()->where('project_id', $project->id)->where('event_type', 'sidang_scheduled')->firstOrFail();

    expect($audit->payload['mentorship_minimum_unmet'])->toBeTrue()
        ->and($audit->payload['mentorship_progress'])->toBe($expected)
        ->and($event->payload['mentorship_minimum_unmet'])->toBeTrue()
        ->and($event->payload['mentorship_progress'])->toBe($expected);
});

it('flags no unmet minimum when every supervisor reached it', function (): void {
    ['project' => $project, 'student' => $student, 'primary' => $primary, 'secondary' => $secondary] = mentorshipProject();

    mentorshipSessions($student, $primary, 6);
    mentorshipSessions($student, $secondary, 7);

    expect(app(MentorshipProgressService::class)->auditPayload($project)['mentorship_minimum_unmet'])->toBeFalse();
});

it('warns admin in the schedule sidang action yet still saves', function (): void {
    Notification::fake();
    ['project' => $project, 'student' => $student, 'prodi' => $prodi, 'primary' => $primary, 'secondary' => $secondary] = mentorshipProject();
    $admin = User::factory()->asAdmin()->create();
    $examiner = User::factory()->asDosen()->create();
    AdminProfile::query()->create(['user_id' => $admin->id, 'program_studi_id' => $prodi->id]);
    DosenProfile::factory()->create(['user_id' => $examiner->id, 'program_studi_id' => $prodi->id, 'is_active' => true]);

    mentorshipSessions($student, $primary, 6);
    mentorshipSessions($student, $secondary, 1);

    $this->actingAs($admin);

    Livewire::test(ViewThesisProject::class, ['record' => $project->getRouteKey()])
        ->mountAction('schedule_sidang')
        ->assertSchemaComponentVisible('mentorship_minimum_warning', 'mountedActionSchema0')
        ->assertSchemaComponentExists(
            'mentorship_minimum_warning',
            'mountedActionSchema0',
            fn(Callout $callout): bool => str_contains((string) $callout->getDescription(), 'Dosen Dua (1/6)')
                && ! str_contains((string) $callout->getDescription(), 'Dosen Satu'),
        )
        ->setActionData([
            'scheduled_starts_on' => now()->addWeeks(2)->format('Y-m-d'),
            'scheduled_at_time' => '09:00',
            'location' => 'Ruang Sidang',
            'mode' => 'offline',
            'additional_examiner_user_ids' => [$examiner->id],
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $event = ThesisProjectEvent::query()->where('project_id', $project->id)->where('event_type', 'sidang_scheduled')->firstOrFail();

    expect($event->payload['mentorship_minimum_unmet'])->toBeTrue()
        ->and($event->payload['mentorship_progress'][1])->toBe(['lecturer_user_id' => $secondary->id, 'completed' => 1, 'minimum' => 6]);
});

it('does not warn admin when every supervisor reached the minimum', function (): void {
    ['project' => $project, 'student' => $student, 'prodi' => $prodi, 'primary' => $primary, 'secondary' => $secondary] = mentorshipProject();
    $admin = User::factory()->asAdmin()->create();
    AdminProfile::query()->create(['user_id' => $admin->id, 'program_studi_id' => $prodi->id]);

    mentorshipSessions($student, $primary, 6);
    mentorshipSessions($student, $secondary, 6);

    $this->actingAs($admin);

    Livewire::test(ViewThesisProject::class, ['record' => $project->getRouteKey()])
        ->mountAction('schedule_sidang')
        ->assertSchemaComponentHidden('mentorship_minimum_warning', 'mountedActionSchema0');
});

it('shares mentorship progress with the student, lecturer and kaprodi pages', function (): void {
    ['project' => $project, 'student' => $student, 'prodi' => $prodi, 'primary' => $primary] = mentorshipProject();
    $kaprodi = User::factory()->asKaprodi()->create();
    KaprodiAssignment::factory()->create(['program_studi_id' => $prodi->id, 'user_id' => $kaprodi->id, 'is_primary' => true]);

    mentorshipSessions($student, $primary, 3);

    $this->actingAs($student)
        ->get('/mahasiswa/tugas-akhir')
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('mentorshipProgress.0.completed', 3)
            ->where('mentorshipProgress.0.minimum', 6));

    $this->actingAs($primary)
        ->get('/dosen/mahasiswa-bimbingan')
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('mahasiswaRows.0.mentorshipCompleted', 3));

    $this->actingAs($kaprodi)
        ->get('/kaprodi/mahasiswa')
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('students.0.mentorshipProgress.0.completed', 3));
});

it('shows the same mentorship counts on the student and lecturer profiles', function (): void {
    ['project' => $project, 'student' => $student, 'primary' => $primary, 'secondary' => $secondary] = mentorshipProject();
    $service = app(MentorshipProgressService::class);

    mentorshipSessions($student, $primary, 3);
    mentorshipSessions($student, $secondary, 1);
    mentorshipSessions($student, $primary, 2, 'cancelled');

    $expected = collect($service->forProject($project))
        ->mapWithKeys(fn(array $row): array => [$row['lecturer']['id'] => $row['completed']])
        ->all();
    expect($expected)->toBe([$primary->id => 3, $secondary->id => 1]);

    $this->actingAs($student)
        ->get(route('users.profile.show', $student))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('profile.thesis.advisors.0.id', $primary->id)
            ->where('profile.thesis.advisors.0.mentorshipCount', $expected[$primary->id])
            ->where('profile.thesis.advisors.1.id', $secondary->id)
            ->where('profile.thesis.advisors.1.mentorshipCount', $expected[$secondary->id]));

    $this->actingAs($secondary)
        ->get(route('users.profile.show', $primary))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('profile.relatedUsers.0.users.0.id', $student->id)
            ->where('profile.relatedUsers.0.users.0.mentorshipCount', $expected[$primary->id]));

    $this->actingAs($student)
        ->get(route('users.profile.show', $secondary))
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->where('profile.relatedUsers.0.users.0.mentorshipCount', $expected[$secondary->id]));
});

it('counts mentorship for the lecturer profile without per-student queries', function (): void {
    ['student' => $student, 'prodi' => $prodi, 'primary' => $primary] = mentorshipProject();
    mentorshipSessions($student, $primary, 2);

    $others = collect(range(1, 3))->map(function () use ($prodi, $primary): User {
        $other = User::factory()->asMahasiswa()->create();
        MahasiswaProfile::factory()->create(['user_id' => $other->id, 'program_studi_id' => $prodi->id, 'is_active' => true]);
        $project = ThesisProject::query()->create([
            'student_user_id' => $other->id,
            'program_studi_id' => $prodi->id,
            'phase' => 'research',
            'state' => 'active',
            'started_at' => now()->subMonths(6),
            'created_by' => $other->id,
        ]);
        ThesisSupervisorAssignment::query()->create([
            'project_id' => $project->id,
            'lecturer_user_id' => $primary->id,
            'role' => 'primary',
            'status' => 'active',
            'assigned_by' => $other->id,
            'started_at' => now()->subMonths(5),
        ]);

        return $other;
    });
    mentorshipSessions($others[1], $primary, 4);

    $sessionQueries = function () use ($primary): int {
        $count = 0;
        DB::listen(function ($query) use (&$count): void {
            if (str_contains($query->sql, 'mentorship_schedules')) {
                $count++;
            }
        });
        app(App\Services\UserProfilePresenter::class)->detail($primary, $primary);

        return $count;
    };

    // One batched query per source (completed by lecturer, last activity, supervision counts), not per student.
    expect($sessionQueries())->toBe(3);

    $users = collect(app(App\Services\UserProfilePresenter::class)->detail($primary, $primary)['relatedUsers'][0]['users'])
        ->pluck('mentorshipCount', 'id');

    expect($users[$student->id])->toBe(2)
        ->and($users[$others[0]->id])->toBe(0)
        ->and($users[$others[1]->id])->toBe(4);
});
