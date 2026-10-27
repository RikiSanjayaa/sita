<?php

use App\Enums\AdvisorType;
use App\Models\AdminProfile;
use App\Models\KaprodiAssignment;
use App\Models\MahasiswaProfile;
use App\Models\MentorshipSchedule;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisProject;
use App\Models\ThesisSupervisorAssignment;
use App\Services\MentorshipSessionNoteService;
use App\Services\ThesisArchiveService;
use Inertia\Testing\AssertableInertia as Assert;

function sessionNoteScenario(): array
{
    $student = createUserWithRole('mahasiswa');
    $program = ProgramStudi::factory()->create();
    MahasiswaProfile::factory()->create(['user_id' => $student->id, 'program_studi_id' => $program->id, 'is_active' => true]);
    $lecturer = createUserWithRole('dosen');
    syncThesisSupervisor($student, $lecturer, $lecturer, AdvisorType::Primary->value);
    $project = ThesisProject::query()->where('student_user_id', $student->id)->firstOrFail();
    $project->update(['program_studi_id' => $program->id, 'started_at' => now()->subMonth()]);
    $schedule = MentorshipSchedule::factory()->create([
        'student_user_id' => $student->id, 'lecturer_user_id' => $lecturer->id,
        'created_by_user_id' => $student->id, 'status' => 'completed',
        'student_note' => 'Bawa naskah', 'lecturer_note' => 'Disepakati pukul 10',
        'scheduled_for' => now()->subDay(),
    ]);

    return [$student, $lecturer, $project, $schedule, $program];
}

it('stores independent participant contributions without changing the session or scheduling feedback', function (): void {
    [$student, $lecturer, $project, $schedule] = sessionNoteScenario();
    $other = $schedule->replicate();
    $other->save();
    $long = str_repeat('Hasil analisis dibahas bersama. ', 250)."\nLangkah berikutnya: perbaiki tabel.";
    $this->actingAs($student)->patch(route('mahasiswa.jadwal-bimbingan.notes', $schedule), [
        'body' => '  '.$long.'  ', 'lecturer_session_note' => 'forged', 'status' => 'cancelled',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $firstAt = $schedule->fresh()->student_session_noted_at;
    $this->actingAs($lecturer)->patch(route('dosen.jadwal-bimbingan.notes', $schedule), ['body' => "Tabel perlu diperjelas.\nSertakan satuan."])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($schedule->fresh()->student_session_note)->toBe($long)
        ->and($schedule->fresh()->student_session_noted_at->equalTo($firstAt))->toBeTrue()
        ->and($schedule->fresh()->lecturer_session_note)->toBe("Tabel perlu diperjelas.\nSertakan satuan.")
        ->and($schedule->fresh()->lecturer_session_noted_at)->not->toBeNull()
        ->and($schedule->fresh()->status)->toBe('completed')
        ->and($schedule->fresh()->student_note)->toBe('Bawa naskah')
        ->and($schedule->fresh()->lecturer_note)->toBe('Disepakati pukul 10')
        ->and($other->fresh()->student_session_note)->toBeNull();
    foreach ([[$student, 'mahasiswa', 'historyMeetings'], [$lecturer, 'dosen', 'historySchedules']] as [$viewer, $role, $key]) {
        $this->actingAs($viewer)->get(route($role.'.jadwal-bimbingan'))->assertInertia(fn (Assert $page) => $page
            ->where($key, fn ($rows): bool => collect($rows)->contains(fn ($row): bool => $row['id'] === $schedule->id
                && $row['sessionNotes']['entries'][0]['body'] === $long
                && $row['sessionNotes']['entries'][1]['authorId'] === $lecturer->id)));
    }
    $this->actingAs($student)->patch(route('mahasiswa.jadwal-bimbingan.notes', $schedule), ['body' => 'Kesimpulan diperbarui.'])
        ->assertSessionHasNoErrors();
    expect($schedule->fresh()->lecturer_session_note)->toBe("Tabel perlu diperjelas.\nSertakan satuan.");
});

it('denies writers outside the exact session participants and rejects blank input', function (): void {
    [$student, $lecturer, $project, $schedule] = sessionNoteScenario();
    foreach (['mahasiswa', 'dosen', 'admin', 'kaprodi', 'super_admin'] as $role) {
        $viewer = createUserWithRole($role);
        $routeRole = $role === 'mahasiswa' ? 'mahasiswa' : 'dosen';
        $this->actingAs($viewer)->patch(route($routeRole.'.jadwal-bimbingan.notes', $schedule), ['body' => 'Palsu'])->assertForbidden();
    }
    $this->actingAs($student)->patch(route('mahasiswa.jadwal-bimbingan.notes', $schedule), ['body' => " \n "])->assertSessionHasErrors('body');
    $this->app['auth']->forgetGuards();
    auth()->logout();
    $this->patch(route('mahasiswa.jadwal-bimbingan.notes', $schedule), ['body' => 'Publik'])->assertRedirect('/login');
    expect($schedule->fresh()->student_session_note)->toBeNull()->and($schedule->fresh()->lecturer_session_note)->toBeNull();
});

it('permits only confirmed and completed active sessions and explains readonly states', function (string $state, bool $editable): void {
    [$student, $lecturer, $project, $schedule] = sessionNoteScenario();
    match ($state) {
        'expired' => $project->update(['mentorship_valid_until' => now()->subDay()]),
        'inactive' => $student->mahasiswaProfile->update(['is_active' => false]),
        'ended' => $project->supervisorAssignments()->update(['status' => 'ended']),
        'archived' => $project->update(['state' => 'completed', 'archived_at' => now()]),
        'closed' => $project->update(['state' => 'completed']),
        default => $schedule->update(['status' => $state]),
    };
    foreach ([[$student, 'mahasiswa'], [$lecturer, 'dosen']] as [$viewer, $role]) {
        $response = $this->actingAs($viewer)->patch(route($role.'.jadwal-bimbingan.notes', $schedule), ['body' => 'Hasil diskusi.']);
        if ($editable) {
            $response->assertRedirect()->assertSessionHasNoErrors();
        } else {
            $response->assertForbidden();
            $payload = app(MentorshipSessionNoteService::class)->present($schedule->fresh(), $viewer);
            expect($payload['updateUrl'])->toBeNull()->and($payload['readOnlyReason'])->toBeString();
        }
    }
})->with([
    ['approved', true], ['rescheduled', true], ['completed', true], ['pending', false], ['rejected', false], ['cancelled', false],
    ['expired', false], ['inactive', false], ['ended', false], ['archived', false], ['closed', false],
]);

it('scopes current and archived profile monitoring by program', function (bool $archived): void {
    [$student, $lecturer, $project, $schedule, $program] = sessionNoteScenario();
    $schedule->forceFill(['student_session_note' => 'Catatan privat mahasiswa', 'student_session_noted_at' => now(),
        'lecturer_session_note' => 'Catatan privat dosen', 'lecturer_session_noted_at' => now()])->save();
    if ($archived) {
        $project->update(['state' => 'completed', 'completed_at' => now()->subDays(40)]);
        app(ThesisArchiveService::class)->archive($project->fresh());
    }
    $otherProgram = ProgramStudi::factory()->create();
    foreach ([$program, $otherProgram] as $scope) {
        $kaprodi = createUserWithRole('kaprodi');
        KaprodiAssignment::factory()->create(['user_id' => $kaprodi->id, 'program_studi_id' => $scope->id]);
        $admin = createUserWithRole('admin');
        AdminProfile::query()->create(['user_id' => $admin->id, 'program_studi_id' => $scope->id]);
        foreach ([$kaprodi, $admin] as $viewer) {
            $rows = app(MentorshipSessionNoteService::class)->forProject($project->fresh(), $viewer);
            if ($scope->is($program)) {
                expect($rows[0]['sessionNotes']['entries'][0]['body'])->toBe('Catatan privat mahasiswa')
                    ->and($rows[0]['sessionNotes']['entries'][1]['body'])->toBe('Catatan privat dosen')
                    ->and($rows[0]['sessionNotes']['updateUrl'])->toBeNull();
            } else {
                expect($rows)->toBe([]);
            }
            $props = data_get($this->actingAs($viewer)->get(route('users.profile.show', $student))->assertOk()->viewData('page'), 'props');
            expect(str_contains(json_encode($props), 'Catatan privat mahasiswa'))->toBe($scope->is($program));
            if ($scope->is($program)) {
                expect(data_get($props, 'monitoringSessionNotes.0.sessionNotes.updateUrl'))->toBeNull()
                    ->and(data_get($props, 'monitoringLecturers.0.id'))->toBe($lecturer->id);
            } else {
                expect($props)->not->toHaveKey('monitoringSessionNotes');
            }
        }
    }
    foreach (['mahasiswa', 'dosen'] as $role) {
        $this->actingAs(createUserWithRole($role))->get(route('users.profile.show', $student))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->missing('monitoringSessionNotes')->missing('monitoringLecturers'));
    }
    $this->app['auth']->forgetGuards();
    auth()->logout();
    $this->get('/mahasiswa-aktif')->assertOk()->assertDontSee('Catatan privat mahasiswa');
})->with(['current' => false, 'archived' => true]);

it('isolates lifecycle windows and retains readonly notes after the archive deletes source schedules', function (): void {
    [$student, $lecturer, $project, $schedule, $program] = sessionNoteScenario();
    $project->update(['started_at' => now()->subMonths(3), 'state' => 'completed', 'completed_at' => now()->subDays(40)]);
    $schedule->forceFill(['created_at' => now()->subMonths(2), 'student_session_note' => 'Hasil sesi lama', 'student_session_noted_at' => now()->subMonths(2)])->save();
    $next = ThesisProject::factory()->create(['student_user_id' => $student->id, 'program_studi_id' => $program->id,
        'state' => 'active', 'started_at' => now()->subWeek()]);
    $newLecturer = createUserWithRole('dosen');
    ThesisSupervisorAssignment::query()->create([
        'project_id' => $next->id, 'lecturer_user_id' => $newLecturer->id,
        'role' => 'primary', 'status' => 'active', 'started_at' => now()->subWeek(),
    ]);
    $new = $schedule->replicate();
    $new->forceFill(['created_at' => now(), 'lecturer_user_id' => $newLecturer->id, 'student_session_note' => 'Hasil sesi baru'])->save();
    $service = app(MentorshipSessionNoteService::class);
    expect(array_column($service->forProject($project->fresh(), $student), 'id'))->toBe([$schedule->id])
        ->and(array_column($service->forProject($next, $student), 'id'))->toBe([$new->id]);
    app(ThesisArchiveService::class)->archive($project->fresh());
    expect($schedule->fresh())->toBeNull()->and($new->fresh())->not->toBeNull();
    $archived = app(MentorshipSessionNoteService::class)->forProject($project->fresh(), $lecturer);
    expect($archived[0]['sessionNotes']['entries'][0]['body'])->toBe('Hasil sesi lama')
        ->and($archived[0]['sessionNotes']['updateUrl'])->toBeNull()
        ->and($service->forProject($project->fresh(), createUserWithRole('dosen')))->toBe([]);
    $this->actingAs($student)->get(route('users.profile.show', $student))->assertInertia(fn (Assert $page) => $page
        ->where('profile.thesis.advisors.0.id', $newLecturer->id)
        ->where('monitoringSessionNotes', fn ($rows): bool => collect($rows)->pluck('id')->sort()->values()->all() === collect([$schedule->id, $new->id])->sort()->values()->all())
        ->where('monitoringLecturers', fn ($rows): bool => collect($rows)->pluck('id')->sort()->values()->all() === collect([$lecturer->id, $newLecturer->id])->sort()->values()->all()));
    foreach ([[$lecturer, $schedule, 'Hasil sesi lama'], [$newLecturer, $new, 'Hasil sesi baru']] as [$viewer, $visible, $body]) {
        $this->actingAs($viewer)->get(route('users.profile.show', $student))->assertInertia(fn (Assert $page) => $page
            ->has('monitoringSessionNotes', 1)
            ->where('monitoringSessionNotes.0.id', $visible->id)
            ->where('monitoringSessionNotes.0.sessionNotes.entries.0.body', $body)
            ->where('monitoringSessionNotes.0.sessionNotes.updateUrl', null)
            ->has('monitoringLecturers', 1)
            ->where('monitoringLecturers.0.id', $viewer->id));
    }
    foreach ([[$student, 'mahasiswa'], [$lecturer, 'dosen']] as [$viewer, $role]) {
        $this->actingAs($viewer)->get(route($role.'.jadwal-bimbingan'))->assertInertia(fn (Assert $page) => $page
            ->missing('archivedSessionNotes'));
    }
});

it('shows only the exact lecturer participant notes on a read only student profile', function (): void {
    [$student, $lecturer, $project, $schedule] = sessionNoteScenario();
    $schedule->forceFill(['student_session_note' => 'Catatan pembimbing pertama', 'student_session_noted_at' => now()])->save();
    $secondLecturer = createUserWithRole('dosen');
    $second = $schedule->replicate();
    $second->forceFill(['lecturer_user_id' => $secondLecturer->id, 'student_session_note' => 'Catatan pembimbing kedua'])->save();

    $this->actingAs($student)->get(route('users.profile.show', $student))->assertInertia(fn (Assert $page) => $page
        ->has('monitoringSessionNotes', 2)
        ->where('monitoringSessionNotes', fn ($rows): bool => collect($rows)->every(fn ($row): bool => $row['sessionNotes']['updateUrl'] === null)));
    foreach ([[$lecturer, $schedule], [$secondLecturer, $second]] as [$viewer, $visible]) {
        $this->actingAs($viewer)->get(route('users.profile.show', $student))->assertInertia(fn (Assert $page) => $page
            ->has('monitoringSessionNotes', 1)
            ->where('monitoringSessionNotes.0.id', $visible->id)
            ->where('monitoringSessionNotes.0.sessionNotes.updateUrl', null));
        $this->actingAs($student)->get(route('users.profile.show', $viewer))->assertInertia(fn (Assert $page) => $page
            ->missing('monitoringSessionNotes'));
    }
});

it('does not reveal secret sidang lecturers through current or archived profile sessions', function (bool $archived): void {
    [$student, $lecturer, $project, $schedule] = sessionNoteScenario();
    $secretLecturer = createUserWithRole('dosen');
    $defense = ThesisDefense::query()->create([
        'project_id' => $project->id, 'type' => 'sidang', 'attempt_no' => 1, 'status' => 'scheduled',
    ]);
    ThesisDefenseExaminer::query()->create([
        'defense_id' => $defense->id, 'lecturer_user_id' => $secretLecturer->id, 'role' => 'examiner', 'order_no' => 1,
    ]);
    $secret = $schedule->replicate();
    $secret->forceFill([
        'lecturer_user_id' => $secretLecturer->id, 'created_by_user_id' => $secretLecturer->id,
        'lecturer_session_note' => 'Isi catatan penguji rahasia', 'lecturer_session_noted_at' => now(),
    ])->save();
    if ($archived) {
        $project->update(['state' => 'completed', 'completed_at' => now()->subDays(40)]);
        app(ThesisArchiveService::class)->archive($project->fresh());
    }

    $this->actingAs($student)->get(route('users.profile.show', $student))->assertInertia(fn (Assert $page) => $page
        ->has('monitoringSessionNotes', 1)
        ->where('monitoringSessionNotes.0.id', $schedule->id)
        ->where('monitoringLecturers', fn ($rows): bool => ! collect($rows)->contains('id', $secretLecturer->id)))
        ->assertDontSee('Isi catatan penguji rahasia');
    $this->actingAs($secretLecturer)->get(route('users.profile.show', $student))->assertInertia(fn (Assert $page) => $page
        ->has('monitoringSessionNotes', 1)
        ->where('monitoringSessionNotes.0.id', $secret->id)
        ->where('monitoringSessionNotes.0.sessionNotes.entries.0.body', 'Isi catatan penguji rahasia'));
})->with(['current' => false, 'archived' => true]);

it('keeps empty profile sessions available to an assigned lecturer and the student', function (): void {
    [$student, $lecturer, $project, $schedule] = sessionNoteScenario();
    $schedule->delete();

    foreach ([$student, $lecturer] as $viewer) {
        $this->actingAs($viewer)->get(route('users.profile.show', $student))->assertInertia(fn (Assert $page) => $page
            ->where('profile.thesis.advisors.0.id', $lecturer->id)
            ->where('monitoringSessionNotes', [])
            ->where('monitoringLecturers', []));
    }
});

it('allows global admins to read current and archived profile sessions without write access', function (bool $archived, string $role): void {
    [$student, $lecturer, $project, $schedule] = sessionNoteScenario();
    $schedule->forceFill(['lecturer_session_note' => 'Catatan untuk pemantauan global', 'lecturer_session_noted_at' => now()])->save();
    if ($archived) {
        $project->update(['state' => 'completed', 'completed_at' => now()->subDays(40)]);
        app(ThesisArchiveService::class)->archive($project->fresh());
    }

    $this->actingAs(createUserWithRole($role))->get(route('users.profile.show', $student))->assertInertia(fn (Assert $page) => $page
        ->has('monitoringSessionNotes', 1)
        ->where('monitoringSessionNotes.0.sessionNotes.entries.0.body', 'Catatan untuk pemantauan global')
        ->where('monitoringSessionNotes.0.sessionNotes.updateUrl', null));
})->with([false, true])->with(['admin', 'super_admin']);
