<?php

use App\Enums\AppRole;
use App\Models\DosenProfile;
use App\Models\KaprodiAssignment;
use App\Models\MahasiswaProfile;
use App\Models\MentorshipChatMessage;
use App\Models\MentorshipChatThread;
use App\Models\MentorshipDocument;
use App\Models\ProgramStudi;
use App\Models\Role;
use App\Models\SystemAuditLog;
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
            ->missing('summaryCards')
            ->where('workSummary.metrics.0.value', '1'));

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
        ->and($kaprodi->refresh()->last_active_role)->toBe(AppRole::Kaprodi->value)
        ->and($kaprodi->hasRole(AppRole::Kaprodi))->toBeTrue()
        ->and($kaprodi->hasRole(AppRole::Dosen))->toBeTrue()
        ->and($kaprodi->dosenProfile?->program_studi_id)->toBe($prodiA->id)
        ->and($kaprodi->dosenProfile?->is_active)->toBeTrue()
        ->and($kaprodi->teachesInProgramStudi($prodiA->id))->toBeTrue();

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

test('kaprodi assignment service persists custom capabilities', function (): void {
    $prodi = ProgramStudi::factory()->create();
    $kaprodi = kaprodiRoleUser();

    app(KaprodiAssignmentService::class)->syncForProgramStudi($prodi, [
        [
            'user_id' => $kaprodi->id,
            'is_primary' => true,
            'capabilities' => [
                'manage_supervisors',
                'view_documents',
            ],
        ],
    ]);

    $assignment = KaprodiAssignment::query()
        ->where('program_studi_id', $prodi->id)
        ->where('user_id', $kaprodi->id)
        ->firstOrFail();

    expect($assignment->hasCapability('manage_supervisors'))->toBeTrue()
        ->and($assignment->hasCapability('view_documents'))->toBeTrue()
        ->and($assignment->hasCapability('schedule_sempro'))->toBeFalse()
        ->and($assignment->hasCapability('manage_lecturer_quota'))->toBeFalse();
});

test('kaprodi has both kaprodi and dosen portal access', function (): void {
    $prodi = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);
    $kaprodi = kaprodiRoleUser(['name' => 'Kaprodi Dosen Aktif']);

    KaprodiAssignment::factory()->create([
        'program_studi_id' => $prodi->id,
        'user_id' => $kaprodi->id,
        'is_primary' => true,
    ]);

    $this->actingAs($kaprodi)
        ->get('/kaprodi/dashboard')
        ->assertOk();

    $this->actingAs($kaprodi)
        ->get('/dosen/dashboard')
        ->assertOk();

    expect($kaprodi->refresh()->availableRoles())
        ->toContain(AppRole::Kaprodi->value)
        ->toContain(AppRole::Dosen->value);
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
            ->missing('summaryCards')
            ->where('workSummary.metrics.1.value', '1')
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
            ->where('documentQueue.0.nim', $student->mahasiswaProfile?->nim)
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
            ->where('lecturers', fn($lecturers): bool => collect($lecturers)->contains(
                fn(array $lecturer): bool => $lecturer['name'] === 'Dosen Pembimbing'
                    && $lecturer['primaryCount'] === 1,
            )));

    $this->actingAs($kaprodi)
        ->get('/kaprodi/arsip')
        ->assertRedirect('/kaprodi/mahasiswa');

    $this->actingAs($kaprodi)
        ->get('/kaprodi/mahasiswa/'.$student->id)
        ->assertRedirect('/profil/'.$student->id);
});

test('kaprodi lecturer directory stays scoped while assignment options load asynchronously', function (): void {
    $prodi = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);
    $kaprodi = kaprodiRoleUser(['name' => 'Kaprodi Merangkap Dosen']);

    KaprodiAssignment::factory()->create([
        'program_studi_id' => $prodi->id,
        'user_id' => $kaprodi->id,
        'is_primary' => true,
    ]);

    $this->actingAs($kaprodi)
        ->get('/kaprodi/mahasiswa')
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->component('kaprodi/mahasiswa')
            ->missing('lecturerOptions'));

    $this->actingAs($kaprodi)
        ->get('/kaprodi/dosen-prodi')
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->component('kaprodi/dosen-prodi')
            ->where('lecturers.0.name', 'Kaprodi Merangkap Dosen'));
});

test('kaprodi mahasiswa page exposes progress risk from project and chat activity', function (): void {
    $prodi = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);
    $kaprodi = kaprodiRoleUser();
    $lecturer = User::factory()->asDosen()->create();

    DosenProfile::factory()->create([
        'user_id' => $lecturer->id,
        'program_studi_id' => $prodi->id,
    ]);

    KaprodiAssignment::factory()->create([
        'program_studi_id' => $prodi->id,
        'user_id' => $kaprodi->id,
        'is_primary' => true,
    ]);

    $staleStudent = kaprodiStudent($prodi, 'Ayu Risiko');
    $recentStudent = kaprodiStudent($prodi, 'Bima Terkendali');
    $documentStudent = kaprodiStudent($prodi, 'Citra Dokumen');

    $staleProject = kaprodiProject($staleStudent, $prodi);
    $staleProject->forceFill([
        'started_at' => now()->subDays(90),
        'updated_at' => now()->subDays(60),
    ])->save();

    $recentProject = kaprodiProject($recentStudent, $prodi);
    $recentProject->forceFill([
        'started_at' => now()->subDays(20),
        'updated_at' => now()->subDays(30),
    ])->save();

    $documentProject = kaprodiProject($documentStudent, $prodi);
    $documentProject->forceFill([
        'started_at' => now()->subDays(80),
        'updated_at' => now()->subDays(50),
    ])->save();

    ThesisSupervisorAssignment::query()->create([
        'project_id' => $recentProject->id,
        'lecturer_user_id' => $lecturer->id,
        'role' => 'primary',
        'status' => 'active',
        'assigned_by' => $kaprodi->id,
        'started_at' => now()->subDays(20),
    ]);

    ThesisSupervisorAssignment::query()->create([
        'project_id' => $documentProject->id,
        'lecturer_user_id' => $lecturer->id,
        'role' => 'primary',
        'status' => 'active',
        'assigned_by' => $kaprodi->id,
        'started_at' => now()->subDays(80),
    ]);

    $thread = MentorshipChatThread::factory()->create([
        'student_user_id' => $recentStudent->id,
        'type' => 'pembimbing',
    ]);

    MentorshipChatMessage::factory()->create([
        'mentorship_chat_thread_id' => $thread->id,
        'sender_user_id' => $recentStudent->id,
        'message' => 'Update progres terbaru.',
        'sent_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    MentorshipDocument::query()->create([
        'student_user_id' => $documentStudent->id,
        'lecturer_user_id' => $lecturer->id,
        'title' => 'Draft Bab 3',
        'category' => 'laporan',
        'document_group' => $documentStudent->id.':laporan',
        'version_number' => 1,
        'file_name' => 'draft-bab-3.pdf',
        'file_url' => 'https://example.test/draft-bab-3.pdf',
        'status' => 'needs_revision',
        'revision_notes' => 'Lengkapi metode penelitian.',
        'reviewed_at' => now(),
        'uploaded_by_user_id' => $documentStudent->id,
        'uploaded_by_role' => 'mahasiswa',
    ]);

    $this->actingAs($kaprodi)
        ->get('/kaprodi/mahasiswa')
        ->assertOk()
        ->assertInertia(fn(Assert $page): Assert => $page
            ->component('kaprodi/mahasiswa')
            ->where('students.0.name', 'Ayu Risiko')
            ->where('students.0.progressRisk.level', 'high')
            ->where('students.0.progressRisk.label', 'Risiko Telat')
            ->where('students.1.name', 'Bima Terkendali')
            ->where('students.1.progressRisk.level', 'low')
            ->where('students.1.progressRisk.label', 'Terkendali')
            ->where('students.1.progressRisk.lastActivityLabel', 'Chat terakhir')
            ->where('students.2.name', 'Citra Dokumen')
            ->where('students.2.progressRisk.level', 'low')
            ->where('students.2.progressRisk.lastActivityLabel', 'Dokumen bimbingan'));
});

test('kaprodi can assign supervisors and schedule exams for own active prodi project', function (): void {
    $prodi = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);
    $kaprodi = kaprodiRoleUser();

    KaprodiAssignment::factory()->create([
        'program_studi_id' => $prodi->id,
        'user_id' => $kaprodi->id,
        'is_primary' => true,
    ]);

    $student = kaprodiStudent($prodi, 'Mahasiswa Workflow');
    $project = kaprodiProject($student, $prodi, 'sempro', 'active');
    $primary = User::factory()->asDosen()->create(['name' => 'Dosen Pembimbing Satu']);
    $secondary = User::factory()->asDosen()->create(['name' => 'Dosen Pembimbing Dua']);

    foreach ([$primary, $secondary] as $lecturer) {
        DosenProfile::factory()->create([
            'user_id' => $lecturer->id,
            'program_studi_id' => $prodi->id,
        ]);
    }

    $this->actingAs($kaprodi)
        ->post("/kaprodi/projects/{$project->id}/supervisors", [
            'primary_lecturer_user_id' => $primary->id,
            'secondary_lecturer_user_id' => $secondary->id,
            'notes' => 'Penetapan dari kaprodi.',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(ThesisSupervisorAssignment::query()
        ->where('project_id', $project->id)
        ->where('status', 'active')
        ->count())->toBe(2);

    $this->assertDatabaseHas('system_audit_logs', [
        'user_id' => $kaprodi->id,
        'event_type' => 'kaprodi_supervisors_updated',
        'label' => 'Kaprodi memperbarui pembimbing',
    ]);

    $this->actingAs($kaprodi)
        ->post("/kaprodi/projects/{$project->id}/sempro", [
            'scheduled_for' => now()->addWeek()->format('Y-m-d H:i:s'),
            'location' => 'Ruang Sempro',
            'mode' => 'offline',
            'examiner_1_user_id' => $kaprodi->id,
            'examiner_2_user_id' => $secondary->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $sempro = ThesisDefense::query()
        ->where('project_id', $project->id)
        ->where('type', 'sempro')
        ->firstOrFail();

    expect($sempro->status)->toBe('scheduled')
        ->and($sempro->examiners()->count())->toBe(2)
        ->and($sempro->examiners()->where('lecturer_user_id', $kaprodi->id)->exists())->toBeTrue();

    $this->assertDatabaseHas('system_audit_logs', [
        'user_id' => $kaprodi->id,
        'event_type' => 'kaprodi_sempro_scheduled',
        'label' => 'Kaprodi memperbarui jadwal Sempro',
    ]);

    $sidangStart = now()->addWeeks(3)->setTime(10, 0);
    $sidangEnd = now()->addWeeks(3)->addDays(2)->setTime(10, 0);

    $this->actingAs($kaprodi)
        ->post("/kaprodi/projects/{$project->id}/sidang", [
            'scheduled_date_start' => $sidangStart->format('Y-m-d'),
            'scheduled_date_end' => $sidangEnd->format('Y-m-d'),
            'scheduled_time' => $sidangStart->format('H:i'),
            'location' => 'Ruang Sidang',
            'mode' => 'hybrid',
            'additional_examiner_user_ids' => [$kaprodi->id],
            'notes' => 'Jadwal sidang dari kaprodi.',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $sidang = ThesisDefense::query()
        ->where('project_id', $project->id)
        ->where('type', 'sidang')
        ->firstOrFail();

    expect($sidang->status)->toBe('scheduled')
        ->and($sidang->scheduled_for?->toDateTimeString())->toBe($sidangStart->toDateTimeString())
        ->and($sidang->scheduled_until?->toDateTimeString())->toBe($sidangEnd->toDateTimeString())
        ->and($sidang->examiners()->where('role', 'primary_supervisor')->exists())->toBeTrue()
        ->and($sidang->examiners()->where('role', 'secondary_supervisor')->exists())->toBeTrue()
        ->and($sidang->examiners()->where('role', 'examiner')->where('lecturer_user_id', $kaprodi->id)->exists())->toBeTrue();

    $this->assertDatabaseHas('system_audit_logs', [
        'user_id' => $kaprodi->id,
        'event_type' => 'kaprodi_sidang_scheduled',
        'label' => 'Kaprodi memperbarui jadwal Sidang Skripsi',
    ]);
});

test('kaprodi can be assigned as thesis supervisor for own prodi project', function (): void {
    $prodi = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);
    $kaprodi = kaprodiRoleUser(['name' => 'Kaprodi Pembimbing']);
    $secondary = User::factory()->asDosen()->create(['name' => 'Dosen Pembimbing Dua']);

    KaprodiAssignment::factory()->create([
        'program_studi_id' => $prodi->id,
        'user_id' => $kaprodi->id,
        'is_primary' => true,
    ]);

    DosenProfile::factory()->create([
        'user_id' => $secondary->id,
        'program_studi_id' => $prodi->id,
    ]);

    $student = kaprodiStudent($prodi, 'Mahasiswa Kaprodi Pembimbing');
    $project = kaprodiProject($student, $prodi, 'sempro', 'active');

    $this->actingAs($kaprodi)
        ->post("/kaprodi/projects/{$project->id}/supervisors", [
            'primary_lecturer_user_id' => $kaprodi->id,
            'secondary_lecturer_user_id' => $secondary->id,
            'notes' => 'Kaprodi ikut membimbing.',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('thesis_supervisor_assignments', [
        'project_id' => $project->id,
        'lecturer_user_id' => $kaprodi->id,
        'role' => 'primary',
        'status' => 'active',
    ]);
});

test('kaprodi can schedule sempro with one examiner and remove an optional second examiner', function (): void {
    $prodi = ProgramStudi::factory()->create(['name' => 'Teknik Informatika']);
    $kaprodi = kaprodiRoleUser();
    $secondExaminer = User::factory()->asDosen()->create();

    KaprodiAssignment::factory()->create([
        'program_studi_id' => $prodi->id,
        'user_id' => $kaprodi->id,
        'is_primary' => true,
    ]);

    DosenProfile::factory()->create([
        'user_id' => $secondExaminer->id,
        'program_studi_id' => $prodi->id,
    ]);

    $student = kaprodiStudent($prodi, 'Mahasiswa Satu Penguji');
    $project = kaprodiProject($student, $prodi, 'sempro', 'active');
    $scheduledStart = now()->addWeek()->setTime(9, 0);
    $scheduledEnd = now()->addWeek()->addDay()->setTime(9, 0);

    $this->actingAs($kaprodi)
        ->post("/kaprodi/projects/{$project->id}/sempro", [
            'scheduled_date_start' => $scheduledStart->format('Y-m-d'),
            'scheduled_date_end' => $scheduledEnd->format('Y-m-d'),
            'scheduled_time' => $scheduledStart->format('H:i'),
            'location' => 'Ruang Sempro',
            'mode' => 'offline',
            'examiner_1_user_id' => $kaprodi->id,
            'examiner_2_user_id' => $secondExaminer->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($kaprodi)
        ->post("/kaprodi/projects/{$project->id}/sempro", [
            'scheduled_date_start' => $scheduledStart->format('Y-m-d'),
            'scheduled_date_end' => $scheduledStart->format('Y-m-d'),
            'scheduled_time' => $scheduledStart->format('H:i'),
            'location' => 'Ruang Sempro Baru',
            'mode' => 'hybrid',
            'examiner_1_user_id' => $kaprodi->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $sempro = ThesisDefense::query()
        ->where('project_id', $project->id)
        ->where('type', 'sempro')
        ->firstOrFail();
    $auditLog = SystemAuditLog::query()
        ->where('event_type', 'kaprodi_sempro_scheduled')
        ->latest('id')
        ->firstOrFail();

    expect($sempro->examiners()->pluck('lecturer_user_id')->all())
        ->toBe([$kaprodi->id])
        ->and($sempro->scheduled_for?->toDateTimeString())->toBe($scheduledStart->toDateTimeString())
        ->and($sempro->scheduled_until?->toDateTimeString())->toBe($scheduledStart->toDateTimeString())
        ->and($auditLog->payload['examiner_user_ids'])->toBe([$kaprodi->id]);
});

test('sempro schedule requires one unique examiner', function (): void {
    $prodi = ProgramStudi::factory()->create(['name' => 'Teknik Informatika']);
    $kaprodi = kaprodiRoleUser();

    KaprodiAssignment::factory()->create([
        'program_studi_id' => $prodi->id,
        'user_id' => $kaprodi->id,
        'is_primary' => true,
    ]);

    $student = kaprodiStudent($prodi, 'Mahasiswa Validasi Penguji');
    $project = kaprodiProject($student, $prodi, 'sempro', 'active');
    $payload = [
        'scheduled_for' => now()->addWeek()->format('Y-m-d H:i:s'),
        'location' => 'Ruang Sempro',
        'mode' => 'offline',
    ];

    $this->actingAs($kaprodi)
        ->post("/kaprodi/projects/{$project->id}/sempro", $payload)
        ->assertSessionHasErrors('examiner_1_user_id');

    $this->actingAs($kaprodi)
        ->post("/kaprodi/projects/{$project->id}/sempro", [
            ...$payload,
            'examiner_1_user_id' => $kaprodi->id,
            'examiner_2_user_id' => $kaprodi->id,
        ])
        ->assertSessionHasErrors('examiner_2_user_id');
});

test('kaprodi workflow mutations are scoped to own prodi and active mutable exams', function (): void {
    $prodiA = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);
    $prodiB = ProgramStudi::factory()->create(['name' => 'Sistem Informasi']);
    $kaprodi = kaprodiRoleUser();
    $studentA = kaprodiStudent($prodiA, 'Mahasiswa Prodi A');
    $studentB = kaprodiStudent($prodiB, 'Mahasiswa Prodi B');
    $projectA = kaprodiProject($studentA, $prodiA, 'sempro', 'active');
    $projectB = kaprodiProject($studentB, $prodiB, 'sempro', 'active');
    $lecturerA = User::factory()->asDosen()->create();
    $lecturerB = User::factory()->asDosen()->create();

    KaprodiAssignment::factory()->create([
        'program_studi_id' => $prodiA->id,
        'user_id' => $kaprodi->id,
        'is_primary' => true,
    ]);

    foreach ([$lecturerA, $lecturerB] as $lecturer) {
        DosenProfile::factory()->create([
            'user_id' => $lecturer->id,
            'program_studi_id' => $prodiA->id,
        ]);
    }

    $this->actingAs($kaprodi)
        ->post("/kaprodi/projects/{$projectB->id}/supervisors", [
            'primary_lecturer_user_id' => $lecturerA->id,
            'secondary_lecturer_user_id' => $lecturerB->id,
        ])
        ->assertNotFound();

    ThesisDefense::query()->create([
        'project_id' => $projectA->id,
        'type' => 'sempro',
        'attempt_no' => 1,
        'status' => 'awaiting_finalization',
        'result' => 'pending',
        'scheduled_for' => now()->addDay(),
        'location' => 'Ruang Sempro',
    ]);

    $this->actingAs($kaprodi)
        ->post("/kaprodi/projects/{$projectA->id}/sempro", [
            'scheduled_for' => now()->addWeek()->format('Y-m-d H:i:s'),
            'location' => 'Ruang Baru',
            'mode' => 'offline',
            'examiner_1_user_id' => $lecturerA->id,
            'examiner_2_user_id' => $lecturerB->id,
        ])
        ->assertSessionHasErrors('scheduled_for');

    $this->actingAs(User::factory()->asMahasiswa()->create())
        ->post("/kaprodi/projects/{$projectA->id}/supervisors", [
            'primary_lecturer_user_id' => $lecturerA->id,
            'secondary_lecturer_user_id' => $lecturerB->id,
        ])
        ->assertForbidden();
});

test('kaprodi can schedule next sempro attempt after failed result', function (): void {
    $programStudi = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);
    $kaprodi = kaprodiRoleUser();
    $student = kaprodiStudent($programStudi, 'Mahasiswa Sempro Ulang');
    $project = kaprodiProject($student, $programStudi, 'sempro', 'active');
    $lecturer = User::factory()->asDosen()->create();

    KaprodiAssignment::factory()->create([
        'program_studi_id' => $programStudi->id,
        'user_id' => $kaprodi->id,
        'is_primary' => true,
    ]);

    DosenProfile::factory()->create([
        'user_id' => $lecturer->id,
        'program_studi_id' => $programStudi->id,
    ]);

    ThesisDefense::query()->create([
        'project_id' => $project->id,
        'type' => 'sempro',
        'attempt_no' => 1,
        'status' => 'completed',
        'result' => 'fail',
        'scheduled_for' => now()->subWeek(),
        'scheduled_until' => now()->subWeek(),
        'location' => 'Ruang Lama',
    ]);

    $this->actingAs($kaprodi)
        ->post("/kaprodi/projects/{$project->id}/sempro", [
            'scheduled_date_start' => now()->addWeek()->toDateString(),
            'scheduled_date_end' => now()->addWeek()->toDateString(),
            'scheduled_time' => '09:00',
            'location' => 'Ruang Sempro Ulang',
            'mode' => 'offline',
            'examiner_1_user_id' => $lecturer->id,
        ])
        ->assertSessionHasNoErrors();

    $latestSempro = $project->semproDefenses()->latest('attempt_no')->first();

    expect($latestSempro?->attempt_no)->toBe(2)
        ->and($latestSempro?->status)->toBe('scheduled')
        ->and($latestSempro?->location)->toBe('Ruang Sempro Ulang');
});

test('kaprodi workflow accepts active lecturers outside project prodi', function (): void {
    $prodiA = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);
    $prodiB = ProgramStudi::factory()->create(['name' => 'Sistem Informasi']);
    $kaprodi = kaprodiRoleUser();
    $student = kaprodiStudent($prodiA, 'Mahasiswa Scope Dosen');
    $project = kaprodiProject($student, $prodiA, 'research', 'active');
    $lecturerA = User::factory()->asDosen()->create();
    $lecturerB = User::factory()->asDosen()->create();

    KaprodiAssignment::factory()->create([
        'program_studi_id' => $prodiA->id,
        'user_id' => $kaprodi->id,
        'is_primary' => true,
    ]);

    DosenProfile::factory()->create([
        'user_id' => $lecturerA->id,
        'program_studi_id' => $prodiA->id,
    ]);

    DosenProfile::factory()->create([
        'user_id' => $lecturerB->id,
        'program_studi_id' => $prodiB->id,
    ]);

    $this->actingAs($kaprodi)
        ->post("/kaprodi/projects/{$project->id}/supervisors", [
            'primary_lecturer_user_id' => $lecturerA->id,
            'secondary_lecturer_user_id' => $lecturerB->id,
        ])
        ->assertSessionHasNoErrors();

    expect($project->activeSupervisorAssignments()->pluck('lecturer_user_id')->all())
        ->toContain($lecturerA->id, $lecturerB->id);
});

test('kaprodi lecturer search waits for two characters and searches across prodi', function (): void {
    $prodiA = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);
    $prodiB = ProgramStudi::factory()->create(['name' => 'Sistem Informasi']);
    $kaprodi = kaprodiRoleUser();
    $student = kaprodiStudent($prodiA, 'Mahasiswa Pencarian Dosen');
    $project = kaprodiProject($student, $prodiA, 'research', 'active');
    $sameProdiLecturer = User::factory()->asDosen()->create(['name' => 'Budi Data']);
    $otherProdiLecturer = User::factory()->asDosen()->create(['name' => 'Budi Jaringan']);

    KaprodiAssignment::factory()->create([
        'program_studi_id' => $prodiA->id,
        'user_id' => $kaprodi->id,
        'is_primary' => true,
    ]);

    DosenProfile::factory()->create([
        'user_id' => $sameProdiLecturer->id,
        'program_studi_id' => $prodiA->id,
        'nik' => 'DOSEN-DATA',
    ]);
    DosenProfile::factory()->create([
        'user_id' => $otherProdiLecturer->id,
        'program_studi_id' => $prodiB->id,
        'nik' => 'DOSEN-JARINGAN',
    ]);

    $this->actingAs($kaprodi)
        ->getJson('/kaprodi/lecturers/search?project_id='.$project->id.'&purpose=supervisor&q=B')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->actingAs($kaprodi)
        ->getJson('/kaprodi/lecturers/search?project_id='.$project->id.'&purpose=supervisor&q=Budi')
        ->assertOk()
        ->assertJsonPath('data.0.id', $sameProdiLecturer->id)
        ->assertJsonPath('data.0.sameProgram', true)
        ->assertJsonFragment([
            'id' => $otherProdiLecturer->id,
            'sameProgram' => false,
        ]);
});

test('kaprodi can update own prodi lecturer supervision quota safely', function (): void {
    $prodiA = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);
    $prodiB = ProgramStudi::factory()->create(['name' => 'Sistem Informasi']);
    $kaprodi = kaprodiRoleUser();
    $lecturer = User::factory()->asDosen()->create();
    $otherLecturer = User::factory()->asDosen()->create();
    $student = kaprodiStudent($prodiA, 'Mahasiswa Bimbingan Aktif');
    $project = kaprodiProject($student, $prodiA, 'research', 'active');

    KaprodiAssignment::factory()->create([
        'program_studi_id' => $prodiA->id,
        'user_id' => $kaprodi->id,
        'is_primary' => true,
    ]);

    DosenProfile::factory()->create([
        'user_id' => $lecturer->id,
        'program_studi_id' => $prodiA->id,
        'supervision_quota' => 14,
    ]);

    DosenProfile::factory()->create([
        'user_id' => $otherLecturer->id,
        'program_studi_id' => $prodiB->id,
        'supervision_quota' => 14,
    ]);

    ThesisSupervisorAssignment::query()->create([
        'project_id' => $project->id,
        'lecturer_user_id' => $lecturer->id,
        'role' => 'primary',
        'status' => 'active',
        'assigned_by' => $kaprodi->id,
        'started_at' => now(),
    ]);

    $this->actingAs($kaprodi)
        ->patch("/kaprodi/dosen-prodi/{$lecturer->id}/quota", [
            'supervision_quota' => 20,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($lecturer->dosenProfile?->refresh()->supervision_quota)->toBe(20);

    $this->assertDatabaseHas('system_audit_logs', [
        'user_id' => $kaprodi->id,
        'event_type' => 'kaprodi_lecturer_quota_updated',
        'label' => 'Kaprodi memperbarui kuota bimbingan',
    ]);

    $this->actingAs($kaprodi)
        ->patch("/kaprodi/dosen-prodi/{$lecturer->id}/quota", [
            'supervision_quota' => 0,
        ])
        ->assertSessionHasErrors('supervision_quota');

    $this->actingAs($kaprodi)
        ->patch("/kaprodi/dosen-prodi/{$otherLecturer->id}/quota", [
            'supervision_quota' => 20,
        ])
        ->assertNotFound();
});

test('kaprodi capabilities can disable selected operational access', function (): void {
    $prodi = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);
    $kaprodi = kaprodiRoleUser();
    $student = kaprodiStudent($prodi, 'Mahasiswa Capability');
    $project = kaprodiProject($student, $prodi, 'sempro', 'active');
    $lecturerA = User::factory()->asDosen()->create();
    $lecturerB = User::factory()->asDosen()->create();

    KaprodiAssignment::factory()->create([
        'program_studi_id' => $prodi->id,
        'user_id' => $kaprodi->id,
        'is_primary' => true,
        'capabilities' => [
            'manage_supervisors' => true,
            'schedule_sempro' => false,
            'schedule_sidang' => false,
            'manage_lecturer_quota' => false,
            'view_documents' => false,
            'download_documents' => false,
        ],
    ]);

    foreach ([$lecturerA, $lecturerB] as $lecturer) {
        DosenProfile::factory()->create([
            'user_id' => $lecturer->id,
            'program_studi_id' => $prodi->id,
        ]);
    }

    $this->actingAs($kaprodi)
        ->post("/kaprodi/projects/{$project->id}/supervisors", [
            'primary_lecturer_user_id' => $lecturerA->id,
            'secondary_lecturer_user_id' => $lecturerB->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($kaprodi)
        ->post("/kaprodi/projects/{$project->id}/sempro", [
            'scheduled_for' => now()->addWeek()->format('Y-m-d H:i:s'),
            'location' => 'Ruang Sempro',
            'mode' => 'offline',
            'examiner_1_user_id' => $lecturerA->id,
            'examiner_2_user_id' => $lecturerB->id,
        ])
        ->assertForbidden();

    $this->actingAs($kaprodi)
        ->post("/kaprodi/projects/{$project->id}/sidang", [
            'scheduled_for' => now()->addWeeks(2)->format('Y-m-d H:i:s'),
            'location' => 'Ruang Sidang',
            'mode' => 'offline',
            'additional_examiner_user_ids' => [$lecturerB->id],
        ])
        ->assertForbidden();

    $this->actingAs($kaprodi)
        ->patch("/kaprodi/dosen-prodi/{$lecturerA->id}/quota", [
            'supervision_quota' => 20,
        ])
        ->assertForbidden();

    $this->actingAs($kaprodi)
        ->get('/kaprodi/dokumen')
        ->assertForbidden();
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
