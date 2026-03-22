<?php

use App\Enums\AppRole;
use App\Models\DosenProfile;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\Role;
use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Models\ThesisProjectTitle;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;

function attachRole(User $user, string $role): void
{
    $roleModel = Role::query()->firstOrCreate(['name' => $role]);
    $user->roles()->syncWithoutDetaching([$roleModel->id]);
}

test('public landing pages show schedules, advisors, and finalized thesis topics', function (): void {
    $programStudi = ProgramStudi::query()->create([
        'name' => 'Ilmu Komputer',
        'slug' => 'ilkom',
        'concentrations' => ['Jaringan', 'Sistem Cerdas'],
    ]);

    $primaryAdvisor = User::factory()->create([
        'name' => 'Dr. Laila Utami',
        'last_active_role' => AppRole::Dosen->value,
    ]);
    attachRole($primaryAdvisor, AppRole::Dosen->value);

    DosenProfile::factory()->create([
        'user_id' => $primaryAdvisor->id,
        'program_studi_id' => $programStudi->id,
        'concentration' => 'Jaringan',
        'supervision_quota' => 14,
        'is_active' => true,
    ]);

    $secondaryAdvisor = User::factory()->create([
        'name' => 'Dr. Bagas Pranata',
        'last_active_role' => AppRole::Dosen->value,
    ]);
    attachRole($secondaryAdvisor, AppRole::Dosen->value);

    DosenProfile::factory()->create([
        'user_id' => $secondaryAdvisor->id,
        'program_studi_id' => $programStudi->id,
        'concentration' => 'Jaringan',
        'supervision_quota' => 12,
        'is_active' => true,
    ]);

    $student = User::factory()->create([
        'name' => 'Mahasiswa Publik',
        'last_active_role' => AppRole::Mahasiswa->value,
    ]);
    attachRole($student, AppRole::Mahasiswa->value);

    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'program_studi_id' => $programStudi->id,
        'nim' => '2023123456',
        'is_active' => true,
    ]);

    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $programStudi->id,
        'phase' => 'sempro',
        'state' => 'active',
        'started_at' => now()->subWeeks(4),
        'created_by' => $student->id,
    ]);

    $title = ThesisProjectTitle::query()->create([
        'project_id' => $project->id,
        'version_no' => 1,
        'title_id' => 'Optimasi Penjadwalan Seminar Berbasis Sistem Informasi',
        'title_en' => 'Seminar Scheduling Optimization Based on Information Systems',
        'proposal_summary' => 'Membangun sistem informasi untuk membantu publikasi dan pemantauan jadwal seminar proposal.',
        'status' => 'approved',
        'submitted_by_user_id' => $student->id,
        'submitted_at' => now()->subWeeks(3),
    ]);

    ThesisSupervisorAssignment::query()->create([
        'project_id' => $project->id,
        'lecturer_user_id' => $primaryAdvisor->id,
        'role' => 'primary',
        'status' => 'active',
        'assigned_by' => $student->id,
        'started_at' => now()->subWeeks(3),
    ]);

    ThesisSupervisorAssignment::query()->create([
        'project_id' => $project->id,
        'lecturer_user_id' => $secondaryAdvisor->id,
        'role' => 'secondary',
        'status' => 'active',
        'assigned_by' => $student->id,
        'started_at' => now()->subWeeks(3),
    ]);

    ThesisDefense::query()->create([
        'project_id' => $project->id,
        'title_version_id' => $title->id,
        'type' => 'sempro',
        'attempt_no' => 1,
        'status' => 'completed',
        'result' => 'pass_with_revision',
        'scheduled_for' => now()->addDays(3),
        'location' => 'Ruang A1',
        'mode' => 'offline',
    ]);

    $topicStudent = User::factory()->create([
        'name' => 'Mahasiswa Topik Final',
        'last_active_role' => AppRole::Mahasiswa->value,
    ]);
    attachRole($topicStudent, AppRole::Mahasiswa->value);

    MahasiswaProfile::factory()->create([
        'user_id' => $topicStudent->id,
        'program_studi_id' => $programStudi->id,
        'nim' => '2023987654',
        'is_active' => true,
    ]);

    $completedProject = ThesisProject::query()->create([
        'student_user_id' => $topicStudent->id,
        'program_studi_id' => $programStudi->id,
        'phase' => 'completed',
        'state' => 'completed',
        'started_at' => now()->subMonths(8),
        'completed_at' => now()->subDays(2),
        'created_by' => $topicStudent->id,
    ]);

    $completedTitle = ThesisProjectTitle::query()->create([
        'project_id' => $completedProject->id,
        'version_no' => 1,
        'title_id' => 'Optimasi Penjadwalan Seminar Berbasis Sistem Informasi',
        'title_en' => 'Seminar Scheduling Optimization Based on Information Systems',
        'proposal_summary' => 'Membangun sistem informasi untuk membantu publikasi dan pemantauan jadwal seminar proposal.',
        'status' => 'approved',
        'submitted_by_user_id' => $topicStudent->id,
        'submitted_at' => now()->subMonths(7),
    ]);

    ThesisSupervisorAssignment::query()->create([
        'project_id' => $completedProject->id,
        'lecturer_user_id' => $primaryAdvisor->id,
        'role' => 'primary',
        'status' => 'ended',
        'assigned_by' => $topicStudent->id,
        'started_at' => now()->subMonths(7),
        'ended_at' => now()->subDays(2),
    ]);

    ThesisSupervisorAssignment::query()->create([
        'project_id' => $completedProject->id,
        'lecturer_user_id' => $secondaryAdvisor->id,
        'role' => 'secondary',
        'status' => 'ended',
        'assigned_by' => $topicStudent->id,
        'started_at' => now()->subMonths(7),
        'ended_at' => now()->subDays(2),
    ]);

    ThesisDefense::query()->create([
        'project_id' => $completedProject->id,
        'title_version_id' => $completedTitle->id,
        'type' => 'sempro',
        'attempt_no' => 1,
        'status' => 'completed',
        'result' => 'pass',
        'scheduled_for' => now()->subMonths(6),
        'location' => 'Ruang A2',
        'mode' => 'offline',
    ]);

    ThesisDefense::query()->create([
        'project_id' => $completedProject->id,
        'title_version_id' => $completedTitle->id,
        'type' => 'sidang',
        'attempt_no' => 1,
        'status' => 'completed',
        'result' => 'pass',
        'scheduled_for' => now()->subDays(2),
        'location' => 'Ruang Sidang Final',
        'mode' => 'offline',
    ]);

    ThesisDefense::query()->create([
        'project_id' => $project->id,
        'title_version_id' => $title->id,
        'type' => 'sidang',
        'attempt_no' => 1,
        'status' => 'scheduled',
        'result' => 'pending',
        'scheduled_for' => now()->addDays(10),
        'location' => 'Ruang Sidang 2',
        'mode' => 'offline',
    ]);

    $welcomeResponse = $this->get('/')
        ->assertOk();

    $welcomePage = $welcomeResponse->viewData('page');

    expect($welcomePage['component'])->toBe('welcome')
        ->and(data_get($welcomePage, 'props.highlights'))->toHaveCount(4)
        ->and(data_get($welcomePage, 'props.highlights.0.value'))->toBe('2')
        ->and(data_get($welcomePage, 'props.highlights.2.label'))->toBe('Mahasiswa Aktif');

    $scheduleResponse = $this->get('/jadwal')
        ->assertOk();

    $schedulePage = $scheduleResponse->viewData('page');

    expect($schedulePage['component'])->toBe('public/jadwal')
        ->and(data_get($schedulePage, 'props.upcomingSchedules'))->toHaveCount(1)
        ->and(data_get($schedulePage, 'props.upcomingSchedules.0.type'))->toBe('sidang')
        ->and(data_get($schedulePage, 'props.followUpSchedules'))->toHaveCount(1)
        ->and(data_get($schedulePage, 'props.followUpSchedules.0.statusLabel'))->toBe('Perlu Tindak Lanjut');

    $advisorResponse = $this->get('/pembimbing')
        ->assertOk();

    $advisorPage = $advisorResponse->viewData('page');

    expect($advisorPage['component'])->toBe('public/pembimbing')
        ->and(data_get($advisorPage, 'props.advisorPrograms.0.slug'))->toBe('ilkom')
        ->and(data_get($advisorPage, 'props.advisorDirectory.0.name'))->toBe('Dr. Bagas Pranata')
        ->and(data_get($advisorPage, 'props.advisorDirectory.1.primaryCount'))->toBe(1)
        ->and(data_get($advisorPage, 'props.advisorDirectory.1.secondaryCount'))->toBe(0)
        ->and(data_get($advisorPage, 'props.concentrationStudentTotals.ilkom.Jaringan'))->toBe(1);

    $topicsResponse = $this->get('/topik')
        ->assertOk();

    $topicsPage = $topicsResponse->viewData('page');

    expect($topicsPage['component'])->toBe('public/topik')
        ->and(data_get($topicsPage, 'props.topicPrograms.0.slug'))->toBe('ilkom')
        ->and(data_get($topicsPage, 'props.semproTitles.0.title'))->toBe('Optimasi Penjadwalan Seminar Berbasis Sistem Informasi')
        ->and(data_get($topicsPage, 'props.semproTitles.0.studentName'))->toBe('Mahasiswa Topik Final')
        ->and(data_get($topicsPage, 'props.semproTitles.0.studentNim'))->toBe('2023987654')
        ->and(data_get($topicsPage, 'props.semproTitles.0.titleEn'))->toBe('Seminar Scheduling Optimization Based on Information Systems')
        ->and(data_get($topicsPage, 'props.semproTitles.0.year'))->toBe((string) now()->subDays(2)->format('Y'))
        ->and(data_get($topicsPage, 'props.semproTitles.0.advisors.0.name'))->toBe('Dr. Laila Utami');
});

test('public topic and schedule pages support query filters and pagination props', function (): void {
    $programStudi = ProgramStudi::query()->create([
        'name' => 'Sistem Informasi',
        'slug' => 'si',
        'concentrations' => ['Data'],
    ]);

    $advisor = User::factory()->create([
        'name' => 'Dr. Nanda Putri',
        'last_active_role' => AppRole::Dosen->value,
    ]);
    attachRole($advisor, AppRole::Dosen->value);

    DosenProfile::factory()->create([
        'user_id' => $advisor->id,
        'program_studi_id' => $programStudi->id,
        'concentration' => 'Data',
        'supervision_quota' => 20,
        'is_active' => true,
    ]);

    foreach (range(1, 11) as $index) {
        $student = User::factory()->create([
            'name' => "Mahasiswa Topik {$index}",
            'last_active_role' => AppRole::Mahasiswa->value,
        ]);
        attachRole($student, AppRole::Mahasiswa->value);

        MahasiswaProfile::factory()->create([
            'user_id' => $student->id,
            'program_studi_id' => $programStudi->id,
            'nim' => sprintf('202400%04d', $index),
            'is_active' => true,
        ]);

        $project = ThesisProject::query()->create([
            'student_user_id' => $student->id,
            'program_studi_id' => $programStudi->id,
            'phase' => 'completed',
            'state' => 'completed',
            'started_at' => now()->subWeeks(8),
            'completed_at' => now()->subDays($index),
            'created_by' => $student->id,
        ]);

        $title = ThesisProjectTitle::query()->create([
            'project_id' => $project->id,
            'version_no' => 1,
            'title_id' => $index === 11 ? 'Topik Spesifik Alpha' : "Topik Umum {$index}",
            'title_en' => "Topic {$index}",
            'proposal_summary' => $index === 11 ? 'Ringkasan alpha untuk pencarian publik.' : "Ringkasan {$index}",
            'status' => 'approved',
            'submitted_by_user_id' => $student->id,
            'submitted_at' => now()->subWeeks(7),
        ]);

        ThesisSupervisorAssignment::query()->create([
            'project_id' => $project->id,
            'lecturer_user_id' => $advisor->id,
            'role' => 'primary',
            'status' => 'ended',
            'assigned_by' => $student->id,
            'started_at' => now()->subWeeks(7),
            'ended_at' => now()->subDays($index),
        ]);

        ThesisDefense::query()->create([
            'project_id' => $project->id,
            'title_version_id' => $title->id,
            'type' => 'sempro',
            'attempt_no' => 1,
            'status' => 'completed',
            'result' => 'pass',
            'scheduled_for' => now()->subDays($index),
            'location' => "Ruang Topik {$index}",
            'mode' => 'offline',
        ]);

        ThesisDefense::query()->create([
            'project_id' => $project->id,
            'title_version_id' => $title->id,
            'type' => 'sidang',
            'attempt_no' => 1,
            'status' => 'completed',
            'result' => 'pass',
            'scheduled_for' => now()->subDays($index),
            'location' => "Ruang Sidang Topik {$index}",
            'mode' => 'offline',
        ]);
    }

    foreach (range(1, 11) as $index) {
        $student = User::factory()->create([
            'name' => "Mahasiswa Jadwal {$index}",
            'last_active_role' => AppRole::Mahasiswa->value,
        ]);
        attachRole($student, AppRole::Mahasiswa->value);

        MahasiswaProfile::factory()->create([
            'user_id' => $student->id,
            'program_studi_id' => $programStudi->id,
            'nim' => sprintf('202401%04d', $index),
            'is_active' => true,
        ]);

        $project = ThesisProject::query()->create([
            'student_user_id' => $student->id,
            'program_studi_id' => $programStudi->id,
            'phase' => 'sidang',
            'state' => 'active',
            'started_at' => now()->subWeeks(6),
            'created_by' => $student->id,
        ]);

        $title = ThesisProjectTitle::query()->create([
            'project_id' => $project->id,
            'version_no' => 1,
            'title_id' => $index === 11 ? 'Jadwal Spesifik Bravo' : "Jadwal Umum {$index}",
            'title_en' => "Schedule {$index}",
            'proposal_summary' => "Jadwal ringkasan {$index}",
            'status' => 'approved',
            'submitted_by_user_id' => $student->id,
            'submitted_at' => now()->subWeeks(5),
        ]);

        ThesisDefense::query()->create([
            'project_id' => $project->id,
            'title_version_id' => $title->id,
            'type' => 'sidang',
            'attempt_no' => 1,
            'status' => 'scheduled',
            'result' => 'pending',
            'scheduled_for' => now()->addDays($index),
            'location' => $index === 11 ? 'Ruang Bravo' : "Ruang Jadwal {$index}",
            'mode' => 'offline',
        ]);
    }

    foreach (range(1, 9) as $index) {
        $student = User::factory()->create([
            'name' => "Mahasiswa Tindak {$index}",
            'last_active_role' => AppRole::Mahasiswa->value,
        ]);
        attachRole($student, AppRole::Mahasiswa->value);

        MahasiswaProfile::factory()->create([
            'user_id' => $student->id,
            'program_studi_id' => $programStudi->id,
            'nim' => sprintf('202402%04d', $index),
            'is_active' => true,
        ]);

        $project = ThesisProject::query()->create([
            'student_user_id' => $student->id,
            'program_studi_id' => $programStudi->id,
            'phase' => 'sempro',
            'state' => 'active',
            'started_at' => now()->subWeeks(5),
            'created_by' => $student->id,
        ]);

        $title = ThesisProjectTitle::query()->create([
            'project_id' => $project->id,
            'version_no' => 1,
            'title_id' => $index === 9 ? 'Follow Up Charlie' : "Follow Up {$index}",
            'title_en' => "Follow Up {$index}",
            'proposal_summary' => "Tindak lanjut {$index}",
            'status' => 'approved',
            'submitted_by_user_id' => $student->id,
            'submitted_at' => now()->subWeeks(4),
        ]);

        ThesisDefense::query()->create([
            'project_id' => $project->id,
            'title_version_id' => $title->id,
            'type' => 'sempro',
            'attempt_no' => 1,
            'status' => 'completed',
            'result' => 'pass_with_revision',
            'scheduled_for' => now()->subDays($index),
            'location' => $index === 9 ? 'Ruang Charlie' : "Ruang Tindak {$index}",
            'mode' => 'offline',
        ]);
    }

    $topicsResponse = $this->get('/topik?search=Alpha&program=si')
        ->assertOk();

    $topicsPage = $topicsResponse->viewData('page');

    expect(data_get($topicsPage, 'props.filters.search'))->toBe('Alpha')
        ->and(data_get($topicsPage, 'props.filters.program'))->toBe('si')
        ->and(data_get($topicsPage, 'props.semproTitles'))->toHaveCount(1)
        ->and(data_get($topicsPage, 'props.semproTitles.0.title'))->toBe('Topik Spesifik Alpha')
        ->and(data_get($topicsPage, 'props.topicPagination.currentPage'))->toBe(1)
        ->and(data_get($topicsPage, 'props.topicPagination.hasMorePages'))->toBeFalse();

    $topicsSecondPageResponse = $this->get('/topik?page=2')
        ->assertOk();

    $topicsSecondPage = $topicsSecondPageResponse->viewData('page');

    expect(data_get($topicsSecondPage, 'props.semproTitles'))->toHaveCount(1)
        ->and(data_get($topicsSecondPage, 'props.topicPagination.currentPage'))->toBe(2)
        ->and(data_get($topicsSecondPage, 'props.topicPagination.previousPage'))->toBe(1);

    $scheduleResponse = $this->get('/jadwal?search=Bravo')
        ->assertOk();

    $schedulePage = $scheduleResponse->viewData('page');

    expect(data_get($schedulePage, 'props.filters.search'))->toBe('Bravo')
        ->and(data_get($schedulePage, 'props.upcomingSchedules'))->toHaveCount(1)
        ->and(data_get($schedulePage, 'props.upcomingPagination.currentPage'))->toBe(1);

    $followUpPageResponse = $this->get('/jadwal?follow_up_page=2')
        ->assertOk();

    $followUpPage = $followUpPageResponse->viewData('page');

    expect(data_get($followUpPage, 'props.followUpSchedules'))->toHaveCount(1)
        ->and(data_get($followUpPage, 'props.followUpPagination.currentPage'))->toBe(2)
        ->and(data_get($followUpPage, 'props.followUpPagination.previousPage'))->toBe(1);
});

test('public topics include completed theses even when sidang passed with revision first', function (): void {
    $programStudi = ProgramStudi::query()->create([
        'name' => 'Ilmu Komputer',
        'slug' => 'ilkom',
        'concentrations' => ['Jaringan'],
    ]);

    $advisor = User::factory()->create([
        'name' => 'Dr. Laila Utami',
        'last_active_role' => AppRole::Dosen->value,
    ]);
    attachRole($advisor, AppRole::Dosen->value);

    DosenProfile::factory()->create([
        'user_id' => $advisor->id,
        'program_studi_id' => $programStudi->id,
        'concentration' => 'Jaringan',
        'supervision_quota' => 12,
        'is_active' => true,
    ]);

    $student = User::factory()->create([
        'name' => 'Laila Rahma',
        'last_active_role' => AppRole::Mahasiswa->value,
    ]);
    attachRole($student, AppRole::Mahasiswa->value);

    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'program_studi_id' => $programStudi->id,
        'nim' => '2023555001',
        'is_active' => true,
    ]);

    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $programStudi->id,
        'phase' => 'completed',
        'state' => 'completed',
        'started_at' => now()->subMonths(7),
        'completed_at' => now()->subDays(1),
        'created_by' => $student->id,
    ]);

    $title = ThesisProjectTitle::query()->create([
        'project_id' => $project->id,
        'version_no' => 1,
        'title_id' => 'Sistem Rekomendasi Ruang Belajar Kolaboratif Berbasis IoT',
        'title_en' => 'Collaborative Study Space Recommendation System Based on IoT',
        'proposal_summary' => 'Topik final yang sudah selesai dan aman dipublikasikan.',
        'status' => 'approved',
        'submitted_by_user_id' => $student->id,
        'submitted_at' => now()->subMonths(6),
    ]);

    ThesisSupervisorAssignment::query()->create([
        'project_id' => $project->id,
        'lecturer_user_id' => $advisor->id,
        'role' => 'primary',
        'status' => 'ended',
        'assigned_by' => $student->id,
        'started_at' => now()->subMonths(6),
        'ended_at' => now()->subDays(1),
    ]);

    ThesisDefense::query()->create([
        'project_id' => $project->id,
        'title_version_id' => $title->id,
        'type' => 'sidang',
        'attempt_no' => 1,
        'status' => 'completed',
        'result' => 'pass_with_revision',
        'scheduled_for' => now()->subDays(5),
        'location' => 'Ruang Sidang',
        'mode' => 'offline',
    ]);

    $response = $this->get('/topik?search=IoT')
        ->assertOk();

    $page = $response->viewData('page');

    expect(data_get($page, 'props.semproTitles'))->toHaveCount(1)
        ->and(data_get($page, 'props.semproTitles.0.studentName'))->toBe('Laila Rahma')
        ->and(data_get($page, 'props.semproTitles.0.title'))->toBe('Sistem Rekomendasi Ruang Belajar Kolaboratif Berbasis IoT');
});

test('public active students page excludes graduates and nonactive students', function (): void {
    $programStudi = ProgramStudi::query()->create([
        'name' => 'Teknik Informatika',
        'slug' => 'ti',
        'concentrations' => ['Software'],
    ]);

    $advisor = User::factory()->create([
        'name' => 'Dr. Sinta Wijaya',
        'last_active_role' => AppRole::Dosen->value,
    ]);
    attachRole($advisor, AppRole::Dosen->value);

    DosenProfile::factory()->create([
        'user_id' => $advisor->id,
        'program_studi_id' => $programStudi->id,
        'concentration' => 'Software',
        'supervision_quota' => 16,
        'is_active' => true,
    ]);

    $newStudent = User::factory()->create([
        'name' => 'Mahasiswa Baru',
        'last_active_role' => AppRole::Mahasiswa->value,
    ]);
    attachRole($newStudent, AppRole::Mahasiswa->value);

    MahasiswaProfile::factory()->create([
        'user_id' => $newStudent->id,
        'program_studi_id' => $programStudi->id,
        'nim' => '2024555001',
        'is_active' => true,
    ]);

    $researchStudent = User::factory()->create([
        'name' => 'Mahasiswa Riset Aktif',
        'last_active_role' => AppRole::Mahasiswa->value,
    ]);
    attachRole($researchStudent, AppRole::Mahasiswa->value);

    MahasiswaProfile::factory()->create([
        'user_id' => $researchStudent->id,
        'program_studi_id' => $programStudi->id,
        'nim' => '2024555002',
        'is_active' => true,
    ]);

    $researchProject = ThesisProject::query()->create([
        'student_user_id' => $researchStudent->id,
        'program_studi_id' => $programStudi->id,
        'phase' => 'research',
        'state' => 'active',
        'started_at' => now()->subWeeks(6),
        'created_by' => $researchStudent->id,
    ]);

    ThesisProjectTitle::query()->create([
        'project_id' => $researchProject->id,
        'version_no' => 1,
        'title_id' => 'Analisis Sistem Publik Mahasiswa Aktif',
        'title_en' => 'Public Active Student Analysis',
        'proposal_summary' => 'Ringkasan untuk mahasiswa aktif.',
        'status' => 'approved',
        'submitted_by_user_id' => $researchStudent->id,
        'submitted_at' => now()->subWeeks(5),
    ]);

    ThesisSupervisorAssignment::query()->create([
        'project_id' => $researchProject->id,
        'lecturer_user_id' => $advisor->id,
        'role' => 'primary',
        'status' => 'active',
        'assigned_by' => $researchStudent->id,
        'started_at' => now()->subWeeks(5),
    ]);

    $graduate = User::factory()->create([
        'name' => 'Mahasiswa Lulus',
        'last_active_role' => AppRole::Mahasiswa->value,
    ]);
    attachRole($graduate, AppRole::Mahasiswa->value);

    MahasiswaProfile::factory()->create([
        'user_id' => $graduate->id,
        'program_studi_id' => $programStudi->id,
        'nim' => '2024555003',
        'is_active' => true,
    ]);

    $graduateProject = ThesisProject::query()->create([
        'student_user_id' => $graduate->id,
        'program_studi_id' => $programStudi->id,
        'phase' => 'completed',
        'state' => 'completed',
        'started_at' => now()->subMonths(8),
        'completed_at' => now()->subDays(2),
        'created_by' => $graduate->id,
    ]);

    $graduateTitle = ThesisProjectTitle::query()->create([
        'project_id' => $graduateProject->id,
        'version_no' => 1,
        'title_id' => 'Topik Mahasiswa Lulus',
        'title_en' => 'Graduated Topic',
        'proposal_summary' => 'Ringkasan lulus',
        'status' => 'approved',
        'submitted_by_user_id' => $graduate->id,
        'submitted_at' => now()->subMonths(7),
    ]);

    ThesisDefense::query()->create([
        'project_id' => $graduateProject->id,
        'title_version_id' => $graduateTitle->id,
        'type' => 'sidang',
        'attempt_no' => 1,
        'status' => 'completed',
        'result' => 'pass',
        'scheduled_for' => now()->subDays(4),
        'location' => 'Ruang Sidang',
        'mode' => 'offline',
    ]);

    $inactiveStudent = User::factory()->create([
        'name' => 'Mahasiswa Nonaktif',
        'last_active_role' => AppRole::Mahasiswa->value,
    ]);
    attachRole($inactiveStudent, AppRole::Mahasiswa->value);

    MahasiswaProfile::factory()->create([
        'user_id' => $inactiveStudent->id,
        'program_studi_id' => $programStudi->id,
        'nim' => '2024555004',
        'is_active' => false,
    ]);

    $response = $this->get('/mahasiswa-aktif?search=Aktif&program=ti')
        ->assertOk();

    $page = $response->viewData('page');

    expect($page['component'])->toBe('public/mahasiswa')
        ->and(data_get($page, 'props.filters.search'))->toBe('Aktif')
        ->and(data_get($page, 'props.filters.program'))->toBe('ti')
        ->and(data_get($page, 'props.activeStudents'))->toHaveCount(1)
        ->and(data_get($page, 'props.activeStudents.0.name'))->toBe('Mahasiswa Riset Aktif')
        ->and(data_get($page, 'props.activeStudents.0.stageLabel'))->toBe('Bimbingan Aktif')
        ->and(data_get($page, 'props.activeStudents.0.advisors.0.name'))->toBe('Dr. Sinta Wijaya')
        ->and(data_get($page, 'props.studentPrograms.0.slug'))->toBe('ti');

    $allResponse = $this->get('/mahasiswa-aktif')
        ->assertOk();

    $allPage = $allResponse->viewData('page');

    expect(data_get($allPage, 'props.activeStudents'))->toHaveCount(2)
        ->and(collect(data_get($allPage, 'props.activeStudents'))->pluck('name')->all())
        ->toBe(['Mahasiswa Baru', 'Mahasiswa Riset Aktif']);
});
