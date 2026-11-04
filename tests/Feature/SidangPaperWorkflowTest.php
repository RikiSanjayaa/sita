<?php

use App\Models\DosenProfile;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\User;
use App\Services\ThesisProjectAdminService;

function sidangPaperFixture(): array
{
    $student = User::factory()->asMahasiswa()->create();
    $program = ProgramStudi::factory()->create();
    MahasiswaProfile::factory()->create(['user_id' => $student->id, 'program_studi_id' => $program->id]);
    $project = ensureActiveThesisProject($student);
    $project->update(['phase' => 'sidang']);
    $title = $project->titles()->create([
        'version_no' => 1, 'title_id' => 'Sistem Rekap Penilaian',
        'status' => 'approved', 'submitted_by_user_id' => $student->id, 'submitted_at' => now(),
    ]);
    $defense = $project->sidangDefenses()->create([
        'type' => 'sidang', 'title_version_id' => $title->id, 'attempt_no' => 1,
        'status' => 'scheduled', 'scheduled_for' => '2026-11-04 23:30:00', 'location' => 'Ruang Uji Berkas',
    ]);

    $scores = [];
    foreach (['examiner_1' => ['examiner', 90], 'examiner_2' => ['examiner', 70], 'supervisor' => ['primary_supervisor', 80]] as $key => [$role, $value]) {
        $lecturer = User::factory()->asDosen()->create();
        DosenProfile::factory()->create(['user_id' => $lecturer->id, 'program_studi_id' => $program->id, 'is_active' => true]);
        $panel = $defense->examiners()->create([
            'lecturer_user_id' => $lecturer->id, 'role' => $role,
            'order_no' => count($scores) + 1, 'decision' => 'pending',
        ]);
        $scores[$key] = ['panel_id' => $panel->id, 'value' => $value];
    }

    return [$defense, $student, $program, $scores];
}

function sidangReportSheet(string $content): string
{
    $file = tempnam(sys_get_temp_dir(), 'sita-sidang-');
    file_put_contents($file, $content);
    $archive = new ZipArchive;
    try {
        expect($archive->open($file))->toBeTrue();

        return $archive->getFromName('xl/worksheets/sheet1.xml');
    } finally {
        $archive->close();
        unlink($file);
    }
}

it('prints the scheduled identity, records actual panel totals, and exports only the finalized latest passing attempt', function () {
    [$defense, $student, $program, $scores] = sidangPaperFixture();
    $admin = User::factory()->asSuperAdmin()->create();
    $moderator = $defense->examiners()->first()->lecturer;

    $this->actingAs($student)->get(route('sidang.paper.print', $defense))->assertForbidden();
    $this->post(route('sidang.report'))->assertForbidden();
    $this->actingAs($moderator)->get(route('sidang.paper.print', $defense))->assertOk();
    $this->post(route('sidang.paper.store', $defense), ['scores' => $scores])->assertForbidden();

    $this->actingAs($admin)->get(route('sidang.paper.show', $defense))->assertOk();
    $this->post(route('sidang.paper.store', $defense), [
        'moderator_user_id' => $moderator->id, 'sidang_sk' => '001/UJI', 'scores' => $scores,
    ])->assertSessionHasNoErrors()->assertRedirect();

    expect($defense->fresh()->status)->toBe('awaiting_finalization')
        ->and($defense->fresh()->sidang_paper['value'])->toEqual(80)
        ->and($defense->fresh()->sidang_paper['grade'])->toBe('B+')
        ->and($defense->examiners()->where('decision', 'pending')->count())->toBe(3);
    $this->get(route('sidang.paper.print', [$defense, 'lembar' => 'berita-acara']))
        ->assertOk()->assertSee($student->name)->assertSee('Sistem Rekap Penilaian')
        ->assertSee($moderator->name)->assertSee('001/UJI')->assertSee('□ LULUS');

    $params = ['from' => '2026-11-04', 'until' => '2026-11-04', 'period' => 'Semester Uji'];
    expect(sidangReportSheet($this->post(route('sidang.report'), $params)->assertOk()->streamedContent()))
        ->not->toContain($student->name);

    $program->update(['sidang_settings' => ['a' => 76]]);
    app(ThesisProjectAdminService::class)->completeSidang(
        $defense->project, $admin->id, 'pass_with_revision', 'Keputusan resmi.', 'Perbaiki daftar pustaka.', '2026-11-12',
    );
    $this->get(route('sidang.paper.print', [$defense, 'lembar' => 'berita-acara']))
        ->assertOk()->assertSee('<p>LULUS</p>', false)->assertDontSee('□ LULUS');
    $sheet = sidangReportSheet($this->post(route('sidang.report'), $params)->assertDownload('rekap-nilai-sidang.xlsx')->streamedContent());
    expect($sheet)->toContain($student->name, 'Sistem Rekap Penilaian', 'A7:A9', 'B+', 'Semester Uji');
    $formulaLikePeriod = array_replace($params, ['period' => '=2+2']);
    expect(sidangReportSheet($this->post(route('sidang.report'), $formulaLikePeriod)->streamedContent()))
        ->toContain('=2+2')->not->toContain('<f>');

    $this->actingAs(User::factory()->asAdmin()->create())->get(route('sidang.paper.show', $defense))->assertForbidden();
    $this->actingAs($admin);
    expect(sidangReportSheet($this->post(route('sidang.report'), ['from' => '2026-11-05', 'until' => '2026-11-05'])->streamedContent()))
        ->not->toContain($student->name);
    $defense->project->sidangDefenses()->create(['type' => 'sidang', 'attempt_no' => 2, 'status' => 'scheduled']);
    expect(sidangReportSheet($this->post(route('sidang.report'), $params)->streamedContent()))->not->toContain($student->name);
});

it('rejects a paper score conflicting with a digital score and rolls back any partial changes', function () {
    [$defense, , , $scores] = sidangPaperFixture();
    $admin = User::factory()->asSuperAdmin()->create();
    $examiners = $defense->examiners()->orderBy('order_no')->get();
    $examiners[1]->update(['score' => 68, 'decision' => 'pass']);

    $this->actingAs($admin)->post(route('sidang.paper.store', $defense), ['sidang_sk' => '001/UJI', 'scores' => $scores])
        ->assertSessionHasErrors('paper');
    expect($examiners[0]->fresh()->score)->toBeNull()
        ->and($examiners[1]->fresh()->score)->toEqual(68)
        ->and($defense->fresh()->sidang_sk)->toBeNull()
        ->and($defense->fresh()->status)->toBe('scheduled');

    $scores['examiner_2']['value'] = 68;
    $scores['supervisor']['value'] = 0;
    $this->post(route('sidang.paper.store', $defense), ['scores' => $scores])->assertSessionHasNoErrors();
    expect($defense->fresh()->sidang_paper['value'])->toEqual(47.4);
});
