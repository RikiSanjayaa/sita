<?php

use App\Enums\AppRole;
use App\Models\DosenProfile;
use App\Models\KaprodiAssignment;
use App\Models\MahasiswaProfile;
use App\Models\MentorshipDocument;
use App\Models\ProgramStudi;
use App\Models\Role;
use App\Models\ThesisDefense;
use App\Models\ThesisDocument;
use App\Models\ThesisProject;
use App\Models\ThesisProjectTitle;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Services\KaprodiAssignmentService;
use Database\Seeders\S2SasingSeeder;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

function kaprodiRoleUser(array $attributes = []): User
{
    return User::factory()->asKaprodi()->create($attributes);
}

function attachKaprodiRole(User $user): void
{
    $role = Role::query()->firstOrCreate(['name' => AppRole::Kaprodi->value]);
    $user->roles()->syncWithoutDetaching([$role->id]);
    $user->forceFill(['last_active_role' => AppRole::Kaprodi->value])->save();
}

function kaprodiStudent(ProgramStudi $programStudi, string $name, bool $active = true): User
{
    $student = User::factory()->asMahasiswa()->create(['name' => $name]);

    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'program_studi_id' => $programStudi->id,
        'nim' => fake()->unique()->numerify('2210510###'),
        'is_active' => $active,
    ]);

    return $student;
}

function kaprodiProject(User $student, ProgramStudi $programStudi, string $phase = 'research', string $state = 'active'): ThesisProject
{
    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $programStudi->id,
        'phase' => $phase,
        'state' => $state,
        'started_at' => now()->subMonth(),
        'completed_at' => $state === 'completed' ? now()->subDay() : null,
    ]);

    ThesisProjectTitle::query()->create([
        'project_id' => $project->id,
        'version_no' => 1,
        'title_id' => 'Portal Monitoring Kaprodi',
        'status' => 'accepted',
    ]);

    return $project;
}

test('kaprodi can only access own prodi read only portal data', function (): void {
    $prodiA = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);
    $prodiB = ProgramStudi::factory()->create(['name' => 'Sistem Informasi']);
    $kaprodi = kaprodiRoleUser();

    KaprodiAssignment::factory()->create([
        'program_studi_id' => $prodiA->id,
        'user_id' => $kaprodi->id,
        'is_primary' => true,
    ]);

    $ownStudent = kaprodiStudent($prodiA, 'Mahasiswa Prodi Sendiri');
    $otherStudent = kaprodiStudent($prodiB, 'Mahasiswa Prodi Lain');
    kaprodiProject($ownStudent, $prodiA);
    kaprodiProject($otherStudent, $prodiB);

    $this->actingAs($kaprodi)
        ->get('/kaprodi/dashboard')
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->component('kaprodi/dashboard')
            ->where('programStudi.name', 'Ilmu Komputer')
            ->missing('students')
            ->where('summaryCards.0.value', '1'));

    $this->actingAs($kaprodi)
        ->get('/kaprodi/mahasiswa')
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->component('kaprodi/mahasiswa')
            ->where('students.0.name', 'Mahasiswa Prodi Sendiri')
            ->missing('students.1'));

    $this->actingAs($kaprodi)
        ->get('/kaprodi/mahasiswa/'.$ownStudent->id)
        ->assertRedirect('/profil/'.$ownStudent->id);

    $this->actingAs($kaprodi)
        ->get('/kaprodi/mahasiswa/'.$otherStudent->id)
        ->assertNotFound();

    $this->actingAs($kaprodi)
        ->post('/mahasiswa/tugas-akhir', [])
        ->assertForbidden();
});

test('non kaprodi users cannot enter kaprodi portal', function (): void {
    $student = User::factory()->asMahasiswa()->create();

    foreach ([
        '/kaprodi/dashboard',
        '/kaprodi/mahasiswa',
        '/kaprodi/sempro-sidang',
        '/kaprodi/dokumen',
        '/kaprodi/dosen-prodi',
        '/kaprodi/arsip',
    ] as $path) {
        $this->actingAs($student)
            ->get($path)
            ->assertForbidden();
    }
});

test('kaprodi assignment rules are enforced', function (): void {
    $prodiA = ProgramStudi::factory()->create();
    $prodiB = ProgramStudi::factory()->create();
    $kaprodi = kaprodiRoleUser();
    $backupOne = kaprodiRoleUser();
    $backupTwo = kaprodiRoleUser();
    $backupThree = kaprodiRoleUser();

    app(KaprodiAssignmentService::class)->syncForProgramStudi($prodiA, [
        ['user_id' => $kaprodi->id, 'is_primary' => true],
        ['user_id' => $backupOne->id, 'is_primary' => false],
        ['user_id' => $backupTwo->id, 'is_primary' => false],
    ]);

    expect(KaprodiAssignment::query()->where('program_studi_id', $prodiA->id)->count())->toBe(3)
        ->and(KaprodiAssignment::query()->where('program_studi_id', $prodiA->id)->where('is_primary', true)->count())->toBe(1)
        ->and($kaprodi->refresh()->last_active_role)->toBe(AppRole::Kaprodi->value);

    expect(fn() => app(KaprodiAssignmentService::class)->syncForProgramStudi($prodiB, [
        ['user_id' => $kaprodi->id, 'is_primary' => true],
    ]))->toThrow(ValidationException::class);

    expect(fn() => app(KaprodiAssignmentService::class)->syncForProgramStudi($prodiB, [
        ['user_id' => $backupOne->id, 'is_primary' => true],
        ['user_id' => $backupTwo->id, 'is_primary' => true],
    ]))->toThrow(ValidationException::class);

    expect(fn() => app(KaprodiAssignmentService::class)->syncForProgramStudi($prodiB, [
        ['user_id' => $backupOne->id, 'is_primary' => true],
        ['user_id' => $backupTwo->id, 'is_primary' => false],
        ['user_id' => $backupThree->id, 'is_primary' => false],
        ['user_id' => User::factory()->asKaprodi()->create()->id, 'is_primary' => false],
    ]))->toThrow(ValidationException::class);
});

test('dashboard counts active and archived data and detail returns full project data', function (): void {
    $prodi = ProgramStudi::factory()->create(['name' => 'Teknik Informatika']);
    $kaprodi = kaprodiRoleUser();
    $lecturer = User::factory()->asDosen()->create(['name' => 'Dosen Pembimbing']);
    $secondLecturer = User::factory()->asDosen()->create(['name' => 'Dosen Penguji']);
    DosenProfile::factory()->create([
        'user_id' => $lecturer->id,
        'program_studi_id' => $prodi->id,
    ]);
    DosenProfile::factory()->create([
        'user_id' => $secondLecturer->id,
        'program_studi_id' => $prodi->id,
    ]);
    $student = kaprodiStudent($prodi, 'Mahasiswa Arsip');
    $activeProject = kaprodiProject($student, $prodi, 'sempro', 'active');
    $archivedProject = kaprodiProject($student, $prodi, 'completed', 'completed');
    $archivedProject->forceFill(['started_at' => now()->subMonths(2)])->save();

    KaprodiAssignment::factory()->create([
        'program_studi_id' => $prodi->id,
        'user_id' => $kaprodi->id,
        'is_primary' => true,
    ]);

    ThesisSupervisorAssignment::query()->create([
        'project_id' => $activeProject->id,
        'lecturer_user_id' => $lecturer->id,
        'role' => 'primary',
        'status' => 'active',
        'assigned_by' => $kaprodi->id,
        'started_at' => now(),
    ]);

    $defense = ThesisDefense::query()->create([
        'project_id' => $activeProject->id,
        'type' => 'sempro',
        'attempt_no' => 1,
        'status' => 'scheduled',
        'scheduled_for' => now()->addWeek(),
        'location' => 'Ruang Sidang',
    ]);

    ThesisDocument::query()->create([
        'project_id' => $archivedProject->id,
        'uploaded_by_user_id' => $student->id,
        'kind' => 'final_report',
        'status' => 'approved',
        'version_no' => 1,
        'title' => 'Dokumen Arsip Final',
        'storage_disk' => 'public',
        'storage_path' => 'dummy.pdf',
        'file_name' => 'dummy.pdf',
        'uploaded_at' => now()->subDay(),
    ]);

    MentorshipDocument::query()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $lecturer->id,
        'title' => 'Draft Proposal Bimbingan',
        'category' => 'proposal',
        'document_group' => $student->id.':proposal',
        'version_number' => 1,
        'file_name' => 'draft-proposal.pdf',
        'file_url' => 'https://example.test/draft-proposal.pdf',
        'status' => 'needs_revision',
        'revision_notes' => 'Perbaiki rumusan masalah.',
        'uploaded_by_user_id' => $student->id,
        'uploaded_by_role' => 'mahasiswa',
    ]);

    MentorshipDocument::query()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $secondLecturer->id,
        'title' => 'Draft Proposal Bimbingan',
        'category' => 'proposal',
        'document_group' => $student->id.':proposal',
        'version_number' => 1,
        'file_name' => 'draft-proposal.pdf',
        'file_url' => 'https://example.test/draft-proposal.pdf',
        'status' => 'approved',
        'revision_notes' => null,
        'uploaded_by_user_id' => $student->id,
        'uploaded_by_role' => 'mahasiswa',
    ]);

    $this->actingAs($kaprodi)
        ->get('/kaprodi/dashboard')
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->component('kaprodi/dashboard')
            ->where('summaryCards.1.value', '1')
            ->where('summaryCards.1.description', '1 arsip')
            ->where('defenseProgress.0.count', 1)
            ->where('upcomingAgenda.0.title', 'Mahasiswa Arsip')
            ->missing('students'));

    $this->actingAs($kaprodi)
        ->get('/kaprodi/mahasiswa')
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->component('kaprodi/mahasiswa')
            ->where('students.0.name', 'Mahasiswa Arsip')
            ->where('students.0.projectState', 'Aktif')
            ->where('archives.0.student', 'Mahasiswa Arsip')
            ->where('archives.0.title', 'Portal Monitoring Kaprodi'));

    $this->actingAs($kaprodi)
        ->get('/kaprodi/sempro-sidang')
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->component('kaprodi/sempro-sidang')
            ->where('exams.0.id', $defense->id)
            ->where('exams.0.student', 'Mahasiswa Arsip')
            ->where('calendarEvents.0.person', 'Mahasiswa Arsip'));

    $this->actingAs($kaprodi)
        ->get('/kaprodi/dokumen')
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->component('kaprodi/dokumen')
            ->has('documentQueue', 2)
            ->where('documentQueue.0.mahasiswa', 'Mahasiswa Arsip')
            ->where('documentQueue.0.status', 'Perlu Revisi')
            ->where('documentQueue.0.reviewCount', 2)
            ->where('documentQueue.0.revisionCount', 1)
            ->where('documentQueue.0.approvedCount', 1)
            ->has('documentQueue.0.reviews', 2)
            ->where('documentQueue.0.reviews.0.reviewer', 'Dosen Pembimbing')
            ->where('documentQueue.0.reviews.1.reviewer', 'Dosen Penguji'));

    $this->actingAs($kaprodi)
        ->get('/kaprodi/dosen-prodi')
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->component('kaprodi/dosen-prodi')
            ->where('lecturers.0.name', 'Dosen Pembimbing')
            ->where('lecturers.0.primaryCount', 1));

    $this->actingAs($kaprodi)
        ->get('/kaprodi/arsip')
        ->assertRedirect('/kaprodi/mahasiswa');

    $this->actingAs($kaprodi)
        ->get('/kaprodi/mahasiswa/'.$student->id)
        ->assertRedirect('/profil/'.$student->id);
});

test('s2 sasing seeder creates a primary kaprodi account', function (): void {
    $this->seed(S2SasingSeeder::class);

    $kaprodi = User::query()
        ->where('email', 'kaprodi.s2.sasing@gmail.com')
        ->with(['kaprodiAssignment.programStudi', 'roles'])
        ->firstOrFail();

    expect($kaprodi->hasRole(AppRole::Kaprodi))->toBeTrue()
        ->and($kaprodi->last_active_role)->toBe(AppRole::Kaprodi->value)
        ->and($kaprodi->kaprodiAssignment?->is_primary)->toBeTrue()
        ->and($kaprodi->kaprodiAssignment?->programStudi?->slug)->toBe('s2-sastra-inggris');
});
