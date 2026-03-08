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

test('public landing pages show schedules, advisors, and sempro topics', function (): void {
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
        'concentration' => 'Sistem Cerdas',
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
        'status' => 'scheduled',
        'result' => 'pending',
        'scheduled_for' => now()->addDays(3),
        'location' => 'Ruang A1',
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
        ->and(data_get($welcomePage, 'props.highlights'))->toHaveCount(3);

    $scheduleResponse = $this->get('/jadwal')
        ->assertOk();

    $schedulePage = $scheduleResponse->viewData('page');

    expect($schedulePage['component'])->toBe('public/jadwal')
        ->and(data_get($schedulePage, 'props.upcomingSchedules'))->toHaveCount(2)
        ->and(data_get($schedulePage, 'props.upcomingSchedules.0.type'))->toBe('sempro');

    $advisorResponse = $this->get('/pembimbing')
        ->assertOk();

    $advisorPage = $advisorResponse->viewData('page');

    expect($advisorPage['component'])->toBe('public/pembimbing')
        ->and(data_get($advisorPage, 'props.advisorPrograms.0.slug'))->toBe('ilkom')
        ->and(data_get($advisorPage, 'props.advisorDirectory.0.name'))->toBe('Dr. Bagas Pranata')
        ->and(data_get($advisorPage, 'props.advisorDirectory.1.primaryCount'))->toBe(1)
        ->and(data_get($advisorPage, 'props.advisorDirectory.1.secondaryCount'))->toBe(0);

    $topicsResponse = $this->get('/topik')
        ->assertOk();

    $topicsPage = $topicsResponse->viewData('page');

    expect($topicsPage['component'])->toBe('public/topik')
        ->and(data_get($topicsPage, 'props.semproTitles.0.title'))->toBe('Optimasi Penjadwalan Seminar Berbasis Sistem Informasi')
        ->and(data_get($topicsPage, 'props.semproTitles.0.advisors.0.name'))->toBe('Dr. Laila Utami');
});
