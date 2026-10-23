<?php

use App\Enums\AdvisorType;
use App\Enums\AppRole;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Models\ThesisProjectTitle;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $defense
 */
function createWorkflowStatusStudent(array $defense, bool $withSupervisors): User
{
    $admin = createUserWithRole(AppRole::Admin->value);
    $student = createUserWithRole(AppRole::Mahasiswa->value);
    MahasiswaProfile::factory()->create([
        'user_id' => $student->id,
        'is_active' => true,
    ]);

    $prodi = ProgramStudi::factory()->create(['name' => 'Informatika']);

    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'sempro',
        'state' => 'active',
        'started_at' => now()->subDays(30),
        'created_by' => $student->id,
    ]);

    $title = ThesisProjectTitle::query()->create([
        'project_id' => $project->id,
        'version_no' => 1,
        'title_id' => 'Sistem Monitoring Skripsi',
        'title_en' => 'Thesis Monitoring System',
        'proposal_summary' => 'Ringkasan proposal.',
        'status' => 'approved',
        'submitted_by_user_id' => $student->id,
        'submitted_at' => now()->subDays(20),
        'decided_by_user_id' => $admin->id,
        'decided_at' => now()->subDays(19),
    ]);

    if ($withSupervisors) {
        $pembimbing = createUserWithRole(AppRole::Dosen->value);
        ThesisSupervisorAssignment::query()->create([
            'project_id' => $project->id,
            'lecturer_user_id' => $pembimbing->id,
            'role' => AdvisorType::Primary->value,
            'status' => 'active',
            'assigned_by' => $admin->id,
            'started_at' => now()->subDays(10),
        ]);
    }

    ThesisDefense::query()->create([
        'project_id' => $project->id,
        'title_version_id' => $title->id,
        'type' => 'sempro',
        'attempt_no' => 1,
        'result' => 'pending',
        'location' => 'Ruang Sidang A',
        'mode' => 'offline',
        ...$defense,
    ]);

    return $student;
}

test('tugas akhir status follows a passed sempro', function (array $defense, bool $withSupervisors, string $expectedKey) {
    $student = createWorkflowStatusStudent($defense, $withSupervisors);

    $this->actingAs($student)
        ->get('/mahasiswa/tugas-akhir')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('submission.workflow.key', $expectedKey));
})->with([
    'passed sempro with supervisors' => [
        ['status' => 'completed', 'result' => 'pass', 'scheduled_for' => now()->subDays(5)],
        true,
        'research_in_progress',
    ],
    'passed sempro without supervisors' => [
        ['status' => 'completed', 'result' => 'pass', 'scheduled_for' => now()->subDays(5)],
        false,
        'sempro_passed',
    ],
    'sempro passed with revision and supervisors' => [
        ['status' => 'completed', 'result' => 'pass_with_revision', 'scheduled_for' => now()->subDays(5)],
        true,
        'research_in_progress',
    ],
]);
