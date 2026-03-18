<?php

use App\Filament\Resources\ThesisProjects\Pages\ListThesisProjects;
use App\Filament\Resources\ThesisProjects\ThesisProjectResource;
use App\Models\AdminProfile;
use App\Models\MahasiswaProfile;
use App\Models\MentorshipDocument;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisDocument;
use App\Models\ThesisProject;
use App\Models\ThesisProjectTitle;
use App\Models\ThesisRevision;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('admin can only see thesis projects from their prodi', function (): void {
    $prodiA = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);
    $prodiB = ProgramStudi::factory()->create(['name' => 'Teknologi Informasi']);

    $admin = User::factory()->asAdmin()->create();
    AdminProfile::query()->create([
        'user_id' => $admin->id,
        'program_studi_id' => $prodiA->id,
    ]);

    $studentA = User::factory()->asMahasiswa()->create(['name' => 'Mahasiswa A']);
    MahasiswaProfile::query()->create([
        'user_id' => $studentA->id,
        'nim' => '2210510101',
        'program_studi_id' => $prodiA->id,
        'angkatan' => 2022,
        'is_active' => true,
    ]);

    $studentB = User::factory()->asMahasiswa()->create(['name' => 'Mahasiswa B']);
    MahasiswaProfile::query()->create([
        'user_id' => $studentB->id,
        'nim' => '2210510102',
        'program_studi_id' => $prodiB->id,
        'angkatan' => 2022,
        'is_active' => true,
    ]);

    $projectA = ThesisProject::query()->create([
        'student_user_id' => $studentA->id,
        'program_studi_id' => $prodiA->id,
        'phase' => 'research',
        'state' => 'active',
        'started_at' => now()->subDays(10),
    ]);

    $projectB = ThesisProject::query()->create([
        'student_user_id' => $studentB->id,
        'program_studi_id' => $prodiB->id,
        'phase' => 'sempro',
        'state' => 'active',
        'started_at' => now()->subDays(8),
    ]);

    ThesisProjectTitle::query()->create([
        'project_id' => $projectA->id,
        'version_no' => 1,
        'title_id' => 'Judul A',
        'status' => 'approved',
        'submitted_by_user_id' => $studentA->id,
        'submitted_at' => now()->subDays(9),
    ]);

    ThesisProjectTitle::query()->create([
        'project_id' => $projectB->id,
        'version_no' => 1,
        'title_id' => 'Judul B',
        'status' => 'approved',
        'submitted_by_user_id' => $studentB->id,
        'submitted_at' => now()->subDays(7),
    ]);

    /** @var \Tests\TestCase $this */
    $this->actingAs($admin);

    Livewire::test(ListThesisProjects::class)
        ->assertCanSeeTableRecords([$projectA])
        ->assertCanNotSeeTableRecords([$projectB]);

    $this->get(ThesisProjectResource::getUrl('view', ['record' => $projectA]))
        ->assertOk();
});

test('admin can use workflow tabs and filters in thesis projects list', function (): void {
    $prodi = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);

    $admin = User::factory()->asAdmin()->create();
    AdminProfile::query()->create([
        'user_id' => $admin->id,
        'program_studi_id' => $prodi->id,
    ]);

    $lecturer = User::factory()->asDosen()->create(['name' => 'Dosen Aktif']);

    $studentSempro = User::factory()->asMahasiswa()->create(['name' => 'Mahasiswa Sempro']);
    MahasiswaProfile::query()->create([
        'user_id' => $studentSempro->id,
        'nim' => '2210510201',
        'program_studi_id' => $prodi->id,
        'angkatan' => 2022,
        'is_active' => true,
    ]);

    $studentResearch = User::factory()->asMahasiswa()->create(['name' => 'Mahasiswa Riset']);
    MahasiswaProfile::query()->create([
        'user_id' => $studentResearch->id,
        'nim' => '2210510202',
        'program_studi_id' => $prodi->id,
        'angkatan' => 2022,
        'is_active' => true,
    ]);

    $studentSidang = User::factory()->asMahasiswa()->create(['name' => 'Mahasiswa Sidang']);
    MahasiswaProfile::query()->create([
        'user_id' => $studentSidang->id,
        'nim' => '2210510203',
        'program_studi_id' => $prodi->id,
        'angkatan' => 2022,
        'is_active' => true,
    ]);

    $studentRevision = User::factory()->asMahasiswa()->create(['name' => 'Mahasiswa Revisi']);
    MahasiswaProfile::query()->create([
        'user_id' => $studentRevision->id,
        'nim' => '2210510204',
        'program_studi_id' => $prodi->id,
        'angkatan' => 2022,
        'is_active' => true,
    ]);

    $semproProject = ThesisProject::query()->create([
        'student_user_id' => $studentSempro->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'sempro',
        'state' => 'active',
        'started_at' => now()->subDays(10),
        'created_by' => $studentSempro->id,
    ]);

    $researchProject = ThesisProject::query()->create([
        'student_user_id' => $studentResearch->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'research',
        'state' => 'active',
        'started_at' => now()->subDays(9),
        'created_by' => $studentResearch->id,
    ]);

    $sidangProject = ThesisProject::query()->create([
        'student_user_id' => $studentSidang->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'sidang',
        'state' => 'active',
        'started_at' => now()->subDays(8),
        'created_by' => $studentSidang->id,
    ]);

    $revisionProject = ThesisProject::query()->create([
        'student_user_id' => $studentRevision->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'research',
        'state' => 'active',
        'started_at' => now()->subDays(7),
        'created_by' => $studentRevision->id,
    ]);

    foreach ([
        $semproProject,
        $researchProject,
        $sidangProject,
        $revisionProject,
    ] as $index => $project) {
        ThesisProjectTitle::query()->create([
            'project_id' => $project->id,
            'version_no' => 1,
            'title_id' => 'Judul Proyek '.($index + 1),
            'status' => 'approved',
            'submitted_by_user_id' => $project->student_user_id,
            'submitted_at' => now()->subDays(6 - $index),
        ]);
    }

    ThesisSupervisorAssignment::query()->create([
        'project_id' => $sidangProject->id,
        'lecturer_user_id' => $lecturer->id,
        'role' => 'primary',
        'status' => 'active',
        'assigned_by' => $admin->id,
        'started_at' => now()->subDays(5),
    ]);

    ThesisSupervisorAssignment::query()->create([
        'project_id' => $sidangProject->id,
        'lecturer_user_id' => $lecturer->id,
        'role' => 'secondary',
        'status' => 'active',
        'assigned_by' => $admin->id,
        'started_at' => now()->subDays(5),
    ]);

    $sidangTitle = ThesisProjectTitle::query()->where('project_id', $sidangProject->id)->firstOrFail();

    ThesisDefense::query()->create([
        'project_id' => $sidangProject->id,
        'title_version_id' => $sidangTitle->id,
        'type' => 'sidang',
        'attempt_no' => 1,
        'status' => 'scheduled',
        'result' => 'pending',
        'scheduled_for' => now()->addDays(3),
        'location' => 'Ruang Sidang',
        'mode' => 'offline',
    ]);

    ThesisRevision::query()->create([
        'project_id' => $revisionProject->id,
        'defense_id' => null,
        'requested_by_user_id' => $admin->id,
        'status' => 'open',
        'notes' => 'Perbaiki dokumen revisi.',
        'due_at' => now()->addWeek(),
        'created_at' => now()->subDay(),
    ]);

    /** @var \Tests\TestCase $this */
    $this->actingAs($admin);

    Livewire::test(ListThesisProjects::class)
        ->assertCanSeeTableRecords([
            $semproProject,
            $researchProject,
            $sidangProject,
            $revisionProject,
        ])
        ->set('activeTab', 'perlu-sempro')
        ->assertCanSeeTableRecords([$semproProject])
        ->assertCanNotSeeTableRecords([$researchProject, $sidangProject, $revisionProject])
        ->set('activeTab', 'perlu-pembimbing')
        ->assertCanSeeTableRecords([$researchProject, $revisionProject])
        ->assertCanNotSeeTableRecords([$semproProject, $sidangProject])
        ->set('activeTab', 'perlu-sidang')
        ->assertCanSeeTableRecords([$researchProject, $revisionProject])
        ->assertCanNotSeeTableRecords([$semproProject, $sidangProject])
        ->set('activeTab', 'revisi-terbuka')
        ->assertCanSeeTableRecords([$revisionProject])
        ->assertCanNotSeeTableRecords([$semproProject, $researchProject, $sidangProject]);

    Livewire::test(ListThesisProjects::class)
        ->filterTable('phase', ['value' => 'research'])
        ->assertCanSeeTableRecords([$researchProject, $revisionProject])
        ->assertCanNotSeeTableRecords([$semproProject, $sidangProject]);

    Livewire::test(ListThesisProjects::class)
        ->filterTable('missing_supervisors')
        ->assertCanSeeTableRecords([$researchProject, $revisionProject])
        ->assertCanNotSeeTableRecords([$semproProject, $sidangProject]);

    Livewire::test(ListThesisProjects::class)
        ->filterTable('upcoming_agenda')
        ->assertCanSeeTableRecords([$sidangProject])
        ->assertCanNotSeeTableRecords([$semproProject, $researchProject, $revisionProject]);
});

test('mahasiswa cannot open filament thesis projects page', function (): void {
    $mahasiswa = User::factory()->asMahasiswa()->create();

    /** @var \Tests\TestCase $this */
    $this->actingAs($mahasiswa)
        ->get('/admin/thesis-projects')
        ->assertForbidden();
});

test('admin can view thesis and mentorship documents in thesis project page', function (): void {
    Storage::fake('public');

    $prodi = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);

    $admin = User::factory()->asAdmin()->create();
    AdminProfile::query()->create([
        'user_id' => $admin->id,
        'program_studi_id' => $prodi->id,
    ]);

    $student = User::factory()->asMahasiswa()->create(['name' => 'Mahasiswa Dokumen']);
    MahasiswaProfile::query()->create([
        'user_id' => $student->id,
        'nim' => '2210510199',
        'program_studi_id' => $prodi->id,
        'angkatan' => 2022,
        'is_active' => true,
    ]);

    $lecturer = User::factory()->asDosen()->create(['name' => 'Dosen Pembimbing']);

    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'research',
        'state' => 'active',
        'started_at' => now()->subDays(10),
        'created_by' => $student->id,
    ]);

    $title = ThesisProjectTitle::query()->create([
        'project_id' => $project->id,
        'version_no' => 1,
        'title_id' => 'Judul Dokumen Admin',
        'status' => 'approved',
        'submitted_by_user_id' => $student->id,
        'submitted_at' => now()->subDays(9),
    ]);

    Storage::disk('public')->put('thesis/proposals/admin-view-proposal.pdf', 'proposal');
    Storage::disk('public')->put('documents/mahasiswa/admin-view-draft.pdf', 'draft');

    ThesisDocument::query()->create([
        'project_id' => $project->id,
        'title_version_id' => $title->id,
        'uploaded_by_user_id' => $student->id,
        'kind' => 'proposal',
        'status' => 'active',
        'version_no' => 1,
        'title' => 'Proposal Skripsi',
        'storage_disk' => 'public',
        'storage_path' => 'thesis/proposals/admin-view-proposal.pdf',
        'file_name' => 'admin-view-proposal.pdf',
        'mime_type' => 'application/pdf',
        'file_size_kb' => 1,
        'uploaded_at' => now()->subDays(8),
    ]);

    MentorshipDocument::query()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $lecturer->id,
        'mentorship_assignment_id' => null,
        'title' => 'Draft Bab 2',
        'category' => 'draft-tugas-akhir',
        'document_group' => sprintf('%d:draft-tugas-akhir', $student->id),
        'version_number' => 1,
        'file_name' => 'admin-view-draft.pdf',
        'file_url' => null,
        'storage_disk' => 'public',
        'storage_path' => 'documents/mahasiswa/admin-view-draft.pdf',
        'mime_type' => 'application/pdf',
        'file_size_kb' => 1,
        'status' => 'submitted',
        'revision_notes' => 'Menunggu review.',
        'reviewed_at' => null,
        'uploaded_by_user_id' => $student->id,
        'uploaded_by_role' => 'mahasiswa',
    ]);

    /** @var \Tests\TestCase $this */
    $this->actingAs($admin)
        ->get(ThesisProjectResource::getUrl('view', ['record' => $project]))
        ->assertOk()
        ->assertSee('Status Workflow')
        ->assertSee('Aksi Berikutnya')
        ->assertSee('Workflow')
        ->assertSee('Dokumen Tugas Akhir')
        ->assertSee('admin-view-proposal.pdf')
        ->assertSee('Dokumen Bimbingan Proyek')
        ->assertSee('Draft Bab 2')
        ->assertSee('admin-view-draft.pdf');
});

test('admin thesis project page only shows mentorship documents within the project window', function (): void {
    Storage::fake('public');

    $prodi = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);

    $admin = User::factory()->asAdmin()->create();
    AdminProfile::query()->create([
        'user_id' => $admin->id,
        'program_studi_id' => $prodi->id,
    ]);

    $student = User::factory()->asMahasiswa()->create(['name' => 'Mahasiswa Riwayat']);
    MahasiswaProfile::query()->create([
        'user_id' => $student->id,
        'nim' => '2210510200',
        'program_studi_id' => $prodi->id,
        'angkatan' => 2022,
        'is_active' => true,
    ]);

    $lecturer = User::factory()->asDosen()->create(['name' => 'Dosen Pembimbing']);

    $historicalProject = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'completed',
        'state' => 'completed',
        'started_at' => now()->subMonths(4),
        'completed_at' => now()->subMonths(3),
        'created_by' => $student->id,
    ]);

    $activeProject = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'research',
        'state' => 'active',
        'started_at' => now()->subMonth(),
        'created_by' => $student->id,
    ]);

    $oldDocument = MentorshipDocument::query()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $lecturer->id,
        'mentorship_assignment_id' => null,
        'title' => 'Draft Lama',
        'category' => 'draft-tugas-akhir',
        'document_group' => sprintf('%d:historical-draft', $student->id),
        'version_number' => 1,
        'file_name' => 'draft-lama.pdf',
        'file_url' => null,
        'storage_disk' => 'public',
        'storage_path' => 'documents/mahasiswa/draft-lama.pdf',
        'mime_type' => 'application/pdf',
        'file_size_kb' => 1,
        'status' => 'approved',
        'revision_notes' => null,
        'reviewed_at' => null,
        'uploaded_by_user_id' => $student->id,
        'uploaded_by_role' => 'mahasiswa',
    ]);

    $oldDocument->forceFill([
        'created_at' => $historicalProject->started_at?->copy()->addWeek(),
        'updated_at' => $historicalProject->started_at?->copy()->addWeek(),
    ])->save();

    $currentDocument = MentorshipDocument::query()->create([
        'student_user_id' => $student->id,
        'lecturer_user_id' => $lecturer->id,
        'mentorship_assignment_id' => null,
        'title' => 'Draft Aktif',
        'category' => 'draft-tugas-akhir',
        'document_group' => sprintf('%d:active-draft', $student->id),
        'version_number' => 1,
        'file_name' => 'draft-aktif.pdf',
        'file_url' => null,
        'storage_disk' => 'public',
        'storage_path' => 'documents/mahasiswa/draft-aktif.pdf',
        'mime_type' => 'application/pdf',
        'file_size_kb' => 1,
        'status' => 'submitted',
        'revision_notes' => null,
        'reviewed_at' => null,
        'uploaded_by_user_id' => $student->id,
        'uploaded_by_role' => 'mahasiswa',
    ]);

    $currentDocument->forceFill([
        'created_at' => $activeProject->started_at?->copy()->addWeek(),
        'updated_at' => $activeProject->started_at?->copy()->addWeek(),
    ])->save();

    /** @var \Tests\TestCase $this */
    $this->actingAs($admin)
        ->get(ThesisProjectResource::getUrl('view', ['record' => $activeProject]))
        ->assertOk()
        ->assertSee('Dokumen Bimbingan Proyek')
        ->assertSee('Draft Aktif')
        ->assertSee('draft-aktif.pdf')
        ->assertDontSee('Draft Lama')
        ->assertDontSee('draft-lama.pdf');
});
