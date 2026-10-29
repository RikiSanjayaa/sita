<?php

use App\Models\DosenProfile;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\ThesisDefense;
use App\Models\ThesisProject;
use App\Models\ThesisProjectTitle;
use App\Models\User;

it('distinguishes same-name programmes by degree without merging their filtered results', function (): void {
    $programmes = collect(['s1', 's2'])->mapWithKeys(function (string $degree): array {
        $programme = ProgramStudi::factory()->create([
            'name' => 'Ilmu Komputer',
            'slug' => 'ilkom-'.$degree,
            'degree_levels' => [$degree],
        ]);
        $student = User::factory()->asMahasiswa()->create(['name' => 'Mahasiswa '.strtoupper($degree)]);
        MahasiswaProfile::factory()->create([
            'user_id' => $student->id,
            'program_studi_id' => $programme->id,
            'degree_level' => $degree,
            'is_active' => true,
        ]);
        $lecturer = User::factory()->asDosen()->create(['name' => 'Pembimbing '.strtoupper($degree)]);
        DosenProfile::factory()->create(['user_id' => $lecturer->id, 'program_studi_id' => $programme->id, 'is_active' => true]);
        $project = ThesisProject::query()->create([
            'student_user_id' => $student->id,
            'program_studi_id' => $programme->id,
            'phase' => 'research',
            'state' => 'active',
            'started_at' => now()->subMonth(),
        ]);
        $title = ThesisProjectTitle::query()->create([
            'project_id' => $project->id,
            'version_no' => 1,
            'title_id' => 'Topik '.strtoupper($degree),
            'status' => 'approved',
            'submitted_by_user_id' => $student->id,
            'submitted_at' => now()->subMonth(),
        ]);
        ThesisDefense::query()->create([
            'project_id' => $project->id,
            'title_version_id' => $title->id,
            'type' => 'sempro',
            'attempt_no' => 1,
            'status' => 'completed',
            'result' => 'pass',
            'scheduled_for' => now()->subWeek(),
        ]);

        return [$degree => $programme];
    });

    foreach ([
        ['/mahasiswa-aktif', 'studentPrograms', 'activeStudents', 'name', 'Mahasiswa S2'],
        ['/topik', 'topicPrograms', 'semproTitles', 'title', 'Topik S2'],
        ['/pembimbing', 'advisorPrograms', 'advisorDirectory', 'name', 'Pembimbing S2'],
    ] as [$route, $options, $rows, $name, $expected]) {
        $props = $this->get($route)->assertOk()->viewData('page')['props'];
        expect(collect($props[$options])->pluck('name', 'slug')->all())->toBe([
            'ilkom-s1' => 'Ilmu Komputer (S1)',
            'ilkom-s2' => 'Ilmu Komputer (S2)',
        ]);
        // Advisor filtering is client-side; the directory retains distinct programme slugs.
        $props = $this->get($route.'?program=ilkom-s2')->assertOk()->viewData('page')['props'];
        $selected = collect($props[$rows])->where('programSlug', 'ilkom-s2');
        expect($selected->pluck($name)->all())->toBe([$expected]);
        if ($route !== '/pembimbing') {
            expect($props[$rows])->toHaveCount(1);
        }
    }

    $viewer = User::factory()->asAdmin()->create();
    $props = $this->actingAs($viewer)->get('/progres-mahasiswa?prodi='.$programmes['s2']->id)
        ->assertOk()->viewData('page')['props'];
    expect(collect($props['filterOptions']['prodi'])->pluck('label', 'value')->all())->toBe([
        $programmes['s1']->id => 'Ilmu Komputer (S1)',
        $programmes['s2']->id => 'Ilmu Komputer (S2)',
    ])->and(collect($props['rows'])->pluck('name')->all())->toBe(['Mahasiswa S2']);
});
