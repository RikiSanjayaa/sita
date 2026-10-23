<?php

use App\Filament\Resources\ThesisProjects\Pages\ListThesisProjects;
use App\Filament\Resources\ThesisProjects\Pages\ViewThesisProject;
use App\Models\AdminProfile;
use App\Models\ProgramStudi;
use App\Models\ThesisProject;
use App\Models\User;
use Livewire\Livewire;

/**
 * @return array{admin: User, archived: ThesisProject, due: ThesisProject, active: ThesisProject}
 */
function archiveListWorld(): array
{
    $prodi = ProgramStudi::factory()->create();
    $admin = User::factory()->asAdmin()->create();
    AdminProfile::query()->create(['user_id' => $admin->id, 'program_studi_id' => $prodi->id]);

    $project = fn (array $attributes): ThesisProject => ThesisProject::factory()->create($attributes + ['program_studi_id' => $prodi->id]);

    return [
        'admin' => $admin,
        'archived' => $project([
            'phase' => 'completed',
            'state' => 'completed',
            'completed_at' => now()->subDays(60),
            'archived_at' => now()->subDays(10),
            'archive_summary' => ['version' => 1, 'completed_mentorship' => [['lecturer_user_id' => 1, 'lecturer_name' => 'Dosen Arsip', 'completed' => 7]], 'documents_uploaded' => 3, 'messages' => 42, 'last_activity_at' => now()->subDays(65)->toIso8601String()],
        ]),
        'due' => $project(['phase' => 'completed', 'state' => 'completed', 'completed_at' => now()->subDays(5)]),
        'active' => $project(['phase' => 'research', 'state' => 'active']),
    ];
}

it('filters the thesis project list by archive status', function (): void {
    ['admin' => $admin, 'archived' => $archived, 'due' => $due, 'active' => $active] = archiveListWorld();
    $this->actingAs($admin);

    Livewire::test(ListThesisProjects::class)
        ->filterTable('archive', 'archived')
        ->assertCanSeeTableRecords([$archived])
        ->assertCanNotSeeTableRecords([$due, $active]);

    Livewire::test(ListThesisProjects::class)
        ->filterTable('archive', 'due')
        ->assertCanSeeTableRecords([$due])
        ->assertCanNotSeeTableRecords([$archived, $active]);
});

it('labels the archive column for archived and soon-to-be-archived projects', function (): void {
    ['admin' => $admin] = archiveListWorld();
    $this->actingAs($admin);

    Livewire::test(ListThesisProjects::class)
        ->assertSee('Diarsipkan')
        ->assertSee('Diarsipkan dalam 25 hari');
});

it('shows the archive summary instead of deleted mentorship documents on the project page', function (): void {
    ['admin' => $admin, 'archived' => $archived, 'due' => $due] = archiveListWorld();
    $this->actingAs($admin);

    Livewire::test(ViewThesisProject::class, ['record' => $archived->getRouteKey()])
        ->assertSee('Data bimbingan sudah diarsipkan')
        ->assertSee('Dosen Arsip: 7 bimbingan selesai')
        ->assertSee('Pesan: 42');

    Livewire::test(ViewThesisProject::class, ['record' => $due->getRouteKey()])
        ->assertDontSee('Data bimbingan sudah diarsipkan');
});
