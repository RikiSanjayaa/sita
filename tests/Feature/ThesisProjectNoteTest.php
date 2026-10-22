<?php

use App\Enums\AdvisorType;
use App\Enums\ThesisNoteVisibility;
use App\Filament\Resources\ThesisProjects\Pages\ViewThesisProject;
use App\Models\AdminProfile;
use App\Models\DosenProfile;
use App\Models\KaprodiAssignment;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisProject;
use App\Models\ThesisProjectNote;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Notifications\RealtimeNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;

/**
 * @return array<string, mixed>
 */
function thesisNoteWorld(): array
{
    $prodi = ProgramStudi::factory()->create();
    $otherProdi = ProgramStudi::factory()->create();

    $makeLecturer = function () use ($prodi): User {
        $lecturer = User::factory()->asDosen()->create();
        DosenProfile::factory()->create(['user_id' => $lecturer->id, 'program_studi_id' => $prodi->id]);

        return $lecturer;
    };
    $makeStudent = function (ProgramStudi $programStudi): User {
        $student = User::factory()->asMahasiswa()->create();
        MahasiswaProfile::factory()->create(['user_id' => $student->id, 'program_studi_id' => $programStudi->id]);

        return $student;
    };

    $student = $makeStudent($prodi);
    $project = ThesisProject::factory()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
    ]);

    $supervisor = $makeLecturer();
    $formerSupervisor = $makeLecturer();
    $examiner = $makeLecturer();

    ThesisSupervisorAssignment::query()->create([
        'project_id' => $project->id,
        'lecturer_user_id' => $supervisor->id,
        'role' => AdvisorType::Primary->value,
        'status' => 'active',
        'started_at' => now(),
    ]);
    ThesisSupervisorAssignment::query()->create([
        'project_id' => $project->id,
        'lecturer_user_id' => $formerSupervisor->id,
        'role' => AdvisorType::Secondary->value,
        'status' => 'ended',
        'started_at' => now()->subMonth(),
        'ended_at' => now()->subWeek(),
    ]);

    $defense = ThesisDefense::query()->create([
        'project_id' => $project->id,
        'type' => 'sempro',
        'attempt_no' => 1,
        'status' => 'completed',
        'result' => 'pass',
    ]);
    ThesisDefenseExaminer::query()->create([
        'defense_id' => $defense->id,
        'lecturer_user_id' => $examiner->id,
        'role' => 'examiner',
        'order_no' => 1,
        'decision' => 'pass',
    ]);

    $kaprodiOwn = User::factory()->asKaprodi()->create();
    KaprodiAssignment::factory()->create(['program_studi_id' => $prodi->id, 'user_id' => $kaprodiOwn->id]);
    $kaprodiOther = User::factory()->asKaprodi()->create();
    KaprodiAssignment::factory()->create(['program_studi_id' => $otherProdi->id, 'user_id' => $kaprodiOther->id]);

    $adminOwn = User::factory()->asAdmin()->create();
    AdminProfile::factory()->create(['user_id' => $adminOwn->id, 'program_studi_id' => $prodi->id]);
    $adminOther = User::factory()->asAdmin()->create();
    AdminProfile::factory()->create(['user_id' => $adminOther->id, 'program_studi_id' => $otherProdi->id]);

    return [
        'project' => $project,
        'student' => $student,
        'actors' => [
            'supervisor' => $supervisor,
            'examiner' => $examiner,
            'former_supervisor' => $formerSupervisor,
            'outsider_dosen' => $makeLecturer(),
            'kaprodi_own_prodi' => $kaprodiOwn,
            'kaprodi_other_prodi' => $kaprodiOther,
            'admin_own_prodi' => $adminOwn,
            'admin_other_prodi' => $adminOther,
            'super_admin' => User::factory()->asSuperAdmin()->create(),
            'student' => $student,
            'other_student' => $makeStudent($prodi),
        ],
    ];
}

/**
 * Role x visibility x action. Notes in the policy tests are authored by the supervisor.
 *
 * @return array<string, array{string, string, string, bool}>
 */
function thesisNoteAccessMatrix(): array
{
    $writers = ['supervisor', 'examiner'];
    $internalReaders = ['supervisor', 'examiner', 'kaprodi_own_prodi', 'admin_own_prodi', 'super_admin'];
    $studentReaders = [...$internalReaders, 'student'];
    $actors = [
        'supervisor', 'examiner', 'former_supervisor', 'outsider_dosen', 'kaprodi_own_prodi',
        'kaprodi_other_prodi', 'admin_own_prodi', 'admin_other_prodi', 'super_admin', 'student', 'other_student',
    ];

    $rows = [];

    foreach ($actors as $actor) {
        foreach (['internal', 'student'] as $visibility) {
            $readers = $visibility === 'internal' ? $internalReaders : $studentReaders;

            $rows["{$actor} / {$visibility} / create"] = [$actor, $visibility, 'create', in_array($actor, $writers, true)];
            $rows["{$actor} / {$visibility} / view"] = [$actor, $visibility, 'view', in_array($actor, $readers, true)];
            $rows["{$actor} / {$visibility} / update"] = [$actor, $visibility, 'update', $actor === 'supervisor'];
            $rows["{$actor} / {$visibility} / delete"] = [$actor, $visibility, 'delete', $actor === 'supervisor'];
        }
    }

    return $rows;
}

it('enforces the access matrix for roles, visibility and actions', function (string $actor, string $visibility, string $action, bool $allowed): void {
    $world = thesisNoteWorld();
    $note = ThesisProjectNote::factory()->create([
        'project_id' => $world['project']->id,
        'author_user_id' => $world['actors']['supervisor']->id,
        'visibility' => $visibility,
    ]);
    $user = $world['actors'][$actor];

    $arguments = $action === 'create' ? [ThesisProjectNote::class, $world['project']] : $note;

    expect($user->can($action, $arguments))->toBe($allowed);
})->with(thesisNoteAccessMatrix());

it('migrates the notes table up and down', function (): void {
    expect(Schema::hasTable('thesis_project_notes'))->toBeTrue();

    $migration = require database_path('migrations/2026_10_22_090000_create_thesis_project_notes_table.php');
    $migration->down();
    expect(Schema::hasTable('thesis_project_notes'))->toBeFalse();

    $migration->up();
    expect(Schema::hasTable('thesis_project_notes'))->toBeTrue();
});

it('lets supervisors and examiners store notes and rejects everyone else', function (string $actor, bool $allowed): void {
    Notification::fake();
    $world = thesisNoteWorld();

    $response = $this->actingAs($world['actors'][$actor])
        ->post(route('dosen.project-notes.store', $world['project']), [
            'visibility' => 'internal',
            'body' => 'Catatan uji.',
        ]);

    if ($allowed) {
        $response->assertRedirect();
        expect(ThesisProjectNote::query()->where('author_user_id', $world['actors'][$actor]->id)->count())->toBe(1);
    } else {
        $response->assertForbidden();
        expect(ThesisProjectNote::query()->count())->toBe(0);
    }
})->with([
    'supervisor' => ['supervisor', true],
    'examiner' => ['examiner', true],
    'former supervisor' => ['former_supervisor', false],
    'outsider dosen' => ['outsider_dosen', false],
    'kaprodi' => ['kaprodi_own_prodi', false],
]);

it('validates note input', function (array $payload, string $field): void {
    $world = thesisNoteWorld();

    $this->actingAs($world['actors']['supervisor'])
        ->post(route('dosen.project-notes.store', $world['project']), $payload)
        ->assertSessionHasErrors($field);
})->with([
    'missing body' => [['visibility' => 'internal', 'body' => ''], 'body'],
    'missing visibility' => [['body' => 'x'], 'visibility'],
    'unknown visibility' => [['visibility' => 'public', 'body' => 'x'], 'visibility'],
    'body too long' => [['visibility' => 'student', 'body' => str_repeat('a', 5001)], 'body'],
]);

it('notifies the student only for student-visible notes', function (): void {
    Notification::fake();
    $world = thesisNoteWorld();
    $supervisor = $world['actors']['supervisor'];

    $this->actingAs($supervisor)->post(route('dosen.project-notes.store', $world['project']), [
        'visibility' => 'internal',
        'body' => 'Hanya untuk dosen.',
    ]);
    Notification::assertNothingSent();

    $this->actingAs($supervisor)->post(route('dosen.project-notes.store', $world['project']), [
        'visibility' => 'student',
        'body' => 'Tolong perbaiki bab 2.',
    ]);
    Notification::assertSentTo($world['student'], RealtimeNotification::class, fn (RealtimeNotification $notification): bool => $notification->toArray($world['student'])['url'] === '/mahasiswa/tugas-akhir');
    Notification::assertCount(1);
});

it('lets only the author edit and delete a note', function (): void {
    $world = thesisNoteWorld();
    $note = ThesisProjectNote::factory()->create([
        'project_id' => $world['project']->id,
        'author_user_id' => $world['actors']['supervisor']->id,
        'visibility' => ThesisNoteVisibility::Internal,
    ]);

    $this->actingAs($world['actors']['examiner'])
        ->patch(route('dosen.project-notes.update', $note), ['visibility' => 'student', 'body' => 'Diubah.'])
        ->assertForbidden();
    $this->actingAs($world['actors']['examiner'])
        ->delete(route('dosen.project-notes.destroy', $note))
        ->assertForbidden();
    expect($note->fresh()->body)->not->toBe('Diubah.');

    $this->actingAs($world['actors']['supervisor'])
        ->patch(route('dosen.project-notes.update', $note), ['visibility' => 'student', 'body' => 'Diubah.'])
        ->assertRedirect();
    expect($note->fresh())
        ->body->toBe('Diubah.')
        ->visibility->toBe(ThesisNoteVisibility::Student);

    $this->actingAs($world['actors']['supervisor'])
        ->delete(route('dosen.project-notes.destroy', $note))
        ->assertRedirect();
    expect(ThesisProjectNote::query()->count())->toBe(0);
});

it('never sends internal notes to mahasiswa in Inertia props', function (): void {
    $world = thesisNoteWorld();
    $supervisor = $world['actors']['supervisor'];

    ThesisProjectNote::factory()->internal()->create([
        'project_id' => $world['project']->id,
        'author_user_id' => $supervisor->id,
        'body' => 'RAHASIA-INTERNAL-DOSEN',
    ]);
    ThesisProjectNote::factory()->forStudent()->create([
        'project_id' => $world['project']->id,
        'author_user_id' => $supervisor->id,
        'body' => 'CATATAN-UNTUK-MAHASISWA',
    ]);

    $response = $this->actingAs($world['student'])
        ->get('/mahasiswa/tugas-akhir')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tugas-akhir')
            ->has('thesisNotes', 1)
            ->where('thesisNotes.0.body', 'CATATAN-UNTUK-MAHASISWA')
            ->where('thesisNotes.0.authorRole', 'Pembimbing 1')
            ->where('thesisNotes.0.canManage', false));

    expect($response->getContent())
        ->toContain('CATATAN-UNTUK-MAHASISWA')
        ->not->toContain('RAHASIA-INTERNAL-DOSEN');
});

it('gives lecturers both visibilities and kaprodi a read-only view', function (): void {
    $world = thesisNoteWorld();
    $supervisor = $world['actors']['supervisor'];

    ThesisProjectNote::factory()->internal()->create(['project_id' => $world['project']->id, 'author_user_id' => $supervisor->id]);
    ThesisProjectNote::factory()->forStudent()->create(['project_id' => $world['project']->id, 'author_user_id' => $supervisor->id]);

    $this->actingAs($supervisor)
        ->get('/dosen/mahasiswa-bimbingan')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dosen/mahasiswa-bimbingan')
            ->where('projectNotes.'.$world['project']->id.'.canWrite', true)
            ->has('projectNotes.'.$world['project']->id.'.notes', 2));

    $this->actingAs($world['actors']['examiner'])
        ->get('/dosen/seminar-proposal')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dosen/seminar-proposal')
            ->where('projectNotes.'.$world['project']->id.'.canWrite', true)
            ->has('projectNotes.'.$world['project']->id.'.notes', 2));

    $this->actingAs($world['actors']['kaprodi_own_prodi'])
        ->get('/kaprodi/mahasiswa')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kaprodi/mahasiswa')
            ->where('projectNotes.'.$world['project']->id.'.canWrite', false)
            ->has('projectNotes.'.$world['project']->id.'.notes', 2)
            ->where('projectNotes.'.$world['project']->id.'.notes.0.canManage', false));

    $this->actingAs($world['actors']['kaprodi_other_prodi'])
        ->get('/kaprodi/mahasiswa')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->missing('projectNotes.'.$world['project']->id));
});

it('shows notes read-only in the admin thesis project infolist', function (): void {
    $world = thesisNoteWorld();

    ThesisProjectNote::factory()->internal()->create([
        'project_id' => $world['project']->id,
        'author_user_id' => $world['actors']['supervisor']->id,
        'body' => 'Catatan internal untuk admin.',
    ]);

    $this->actingAs($world['actors']['admin_own_prodi']);

    Livewire::test(ViewThesisProject::class, ['record' => $world['project']->getRouteKey()])
        ->assertSee('Catatan Dosen')
        ->assertSee('Catatan internal untuk admin.')
        ->assertSee('Internal dosen');
});
