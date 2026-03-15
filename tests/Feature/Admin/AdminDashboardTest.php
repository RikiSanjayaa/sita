<?php

use App\Models\AdminProfile;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\ThesisProject;
use App\Models\ThesisProjectEvent;
use App\Models\ThesisRevision;
use App\Models\User;

test('admin dashboard shows the new overview widgets', function (): void {
    $prodi = ProgramStudi::factory()->create(['name' => 'Ilmu Komputer']);

    $admin = User::factory()->asAdmin()->create();
    AdminProfile::query()->create([
        'user_id' => $admin->id,
        'program_studi_id' => $prodi->id,
    ]);

    $student = User::factory()->asMahasiswa()->create(['name' => 'Mahasiswa Dashboard']);
    MahasiswaProfile::query()->create([
        'user_id' => $student->id,
        'nim' => '2210510701',
        'program_studi_id' => $prodi->id,
        'angkatan' => 2022,
        'is_active' => true,
    ]);

    $project = ThesisProject::query()->create([
        'student_user_id' => $student->id,
        'program_studi_id' => $prodi->id,
        'phase' => 'research',
        'state' => 'active',
        'started_at' => now()->subDays(5),
        'created_by' => $student->id,
    ]);

    ThesisRevision::query()->create([
        'project_id' => $project->id,
        'defense_id' => null,
        'requested_by_user_id' => $admin->id,
        'status' => 'open',
        'notes' => 'Butuh review dari admin.',
        'due_at' => now()->addWeek(),
    ]);

    ThesisProjectEvent::query()->create([
        'project_id' => $project->id,
        'actor_user_id' => $admin->id,
        'event_type' => 'revision_opened',
        'label' => 'Revisi dibuka',
        'description' => 'Butuh tindak lanjut.',
        'occurred_at' => now()->subHour(),
    ]);

    /** @var \Tests\TestCase $this */
    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk()
        ->assertSee('Dashboard Admin')
        ->assertSee('Ringkasan Admin')
        ->assertSee('Proyek yang Perlu Tindak Lanjut')
        ->assertSee('Aktivitas Admin Terkini');
});
