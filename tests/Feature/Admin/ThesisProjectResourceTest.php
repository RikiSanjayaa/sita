<?php

use App\Filament\Resources\ThesisProjects\Pages\ListThesisProjects;
use App\Filament\Resources\ThesisProjects\ThesisProjectResource;
use App\Models\AdminProfile;
use App\Models\MahasiswaProfile;
use App\Models\MentorshipDocument;
use App\Models\ProgramStudi;
use App\Models\ThesisDocument;
use App\Models\ThesisProject;
use App\Models\ThesisProjectTitle;
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
