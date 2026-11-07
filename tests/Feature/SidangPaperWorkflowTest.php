<?php

use App\Filament\Resources\ProgramStudis\Pages\EditProgramStudi;
use App\Filament\Resources\ThesisProjects\Pages\ListThesisProjects;
use App\Filament\Resources\ThesisProjects\Pages\ManageSidangPaper;
use App\Filament\Resources\ThesisProjects\Pages\ViewThesisProject;
use App\Filament\Resources\ThesisProjects\ThesisProjectResource;
use App\Models\AdminProfile;
use App\Models\DosenProfile;
use App\Models\Faculty;
use App\Models\KaprodiAssignment;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\SidangReportPeriod;
use App\Models\User;
use App\Services\SidangPaperService;
use App\Services\SidangReportPeriodService;
use App\Services\SidangReportWriter;
use App\Services\SidangScoreService;
use App\Services\ThesisDefenseExaminerDecisionService;
use App\Services\ThesisProjectAdminService;
use App\Support\SidangSettings;
use Database\Seeders\AcademicStructureSeeder;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;

function sidangPaperFixture(?ProgramStudi $program = null): array
{
    $student = User::factory()->asMahasiswa()->create();
    $program ??= ProgramStudi::factory()->create();
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

it('limits the native paper page to the selected project and the admin program', function (): void {
    [$defense, $student, $program] = sidangPaperFixture();
    [$foreign] = sidangPaperFixture();
    $admin = User::factory()->asAdmin()->create();
    AdminProfile::query()->create(['user_id' => $admin->id, 'program_studi_id' => $program->id]);
    $this->actingAs($admin);

    $url = fn ($project, $attempt): string => ThesisProjectResource::getUrl('sidang-paper', [
        'record' => $project, 'defense' => $attempt, 'dari' => 'daftar',
    ]);
    $this->get(route('sidang.paper.show', ['defense' => $defense, 'dari' => 'daftar']))
        ->assertRedirect($url($defense->project, $defense));
    $this->get($url($defense->project, $defense))->assertOk()
        ->assertSee($student->name)->assertSee('Nilai dari berkas')->assertSee('Unduh Paket Sidang');
    $this->get($url($defense->project, $foreign))->assertNotFound();
    $this->get($url($foreign->project, $foreign))->assertNotFound();
});

it('preserves a native admin draft when a lecturer has changed the source score', function (): void {
    [$defense, , , $scores] = sidangPaperFixture();
    $this->actingAs(User::factory()->asSuperAdmin()->create());
    $page = Livewire::test(ManageSidangPaper::class, [
        'record' => $defense->project_id, 'defense' => $defense->id,
    ]);
    $page->set('skData.sidang_sk', '007/UJI')->callAction('saveSk')->assertHasNoErrors();
    expect($defense->fresh()->sidang_sk)->toBe('007/UJI');

    foreach ($scores as $role => $entry) {
        $page->set('scoreData.scores.'.$role.'.value', $entry['value']);
    }
    $page->mountAction('saveScores')->assertActionMounted('saveScores');
    $member = $defense->examiners()->findOrFail($scores['examiner_1']['panel_id']);
    $member->update(['score' => 87]);
    $page->callMountedAction()->assertHasErrors(['scoreData.scores.examiner_1.value'])
        ->assertSet('scoreData.scores.examiner_1.value', 90);
    expect($member->fresh()->score)->toBe('87.00')
        ->and($defense->fresh()->status)->toBe('scheduled');

    $page = Livewire::test(ManageSidangPaper::class, [
        'record' => $defense->project_id, 'defense' => $defense->id,
    ]);
    foreach ($scores as $role => $entry) {
        $page->set('scoreData.scores.'.$role.'.value', $entry['value']);
    }
    $page->callAction('saveScores')->assertHasNoErrors()->assertSee('80,00');
    expect($defense->fresh()->status)->toBe('awaiting_finalization');
});

it('opens and saves the sidang document and grade settings for a program', function (): void {
    $program = ProgramStudi::factory()->create();
    $this->actingAs(User::factory()->asSuperAdmin()->create());

    Livewire::test(EditProgramStudi::class, ['record' => $program->getRouteKey()])
        ->mountAction('sidangSettings')
        ->setActionData(['sidang_settings' => [
            ...SidangSettings::forProgram($program),
            'city' => 'Kota Uji',
            'minimum_paper_examiners' => '2',
        ]])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($program->fresh()->sidang_settings)
        ->toMatchArray(['city' => 'Kota Uji', 'minimum_paper_examiners' => 2]);
});

it('initializes existing Ilkom settings without overwriting other settings or a saved minimum', function (): void {
    $ilkom = ProgramStudi::factory()->create(['slug' => 'ilkom', 'sidang_settings' => ['city' => 'Kota Uji']]);
    $other = ProgramStudi::factory()->create(['sidang_settings' => ['city' => 'Kota Lain']]);
    $migration = require database_path('migrations/2026_11_07_000000_set_initial_sidang_panel_requirement.php');
    $migration->up();

    expect($ilkom->fresh()->sidang_settings)->toBe(['city' => 'Kota Uji', 'minimum_paper_examiners' => 2])
        ->and($other->fresh()->sidang_settings)->toBe(['city' => 'Kota Lain']);

    $ilkom->update(['sidang_settings' => ['city' => 'Kota Uji', 'minimum_paper_examiners' => 0]]);
    $migration->up();
    expect(SidangSettings::minimumPaperExaminers($ilkom->fresh()))->toBe(0);
});

it('seeds the initial Ilkom requirement and preserves a later admin choice', function (): void {
    $this->seed(AcademicStructureSeeder::class);
    $program = ProgramStudi::query()->where('slug', 'ilkom')->firstOrFail();
    expect(SidangSettings::minimumPaperExaminers($program))->toBe(2);
    $program->update(['sidang_settings' => ['minimum_paper_examiners' => 0]]);

    $this->seed(AcademicStructureSeeder::class);
    expect(SidangSettings::minimumPaperExaminers($program->fresh()))->toBe(0);
});

it('shows the setting error on the matching field and keeps the modal open', function (array $changes, string $field, string $message): void {
    $program = ProgramStudi::factory()->create();
    $this->actingAs(User::factory()->asSuperAdmin()->create());

    $page = Livewire::test(EditProgramStudi::class, ['record' => $program->getRouteKey()])
        ->mountAction('sidangSettings')
        ->setActionData(['sidang_settings' => [...SidangSettings::forProgram($program), ...$changes]])
        ->callMountedAction()
        ->assertHasActionErrors(['sidang_settings.'.$field])
        ->assertActionMounted('sidangSettings');

    expect($page->instance()->getErrorBag()->first('mountedActions.0.data.sidang_settings.'.$field))->toContain($message)
        ->and($program->fresh()->sidang_settings)->toBeNull();
    $page->assertActionDataSet(['sidang_settings' => [...SidangSettings::forProgram($program), ...$changes]]);
})->with([
    'weights' => [['examiner_1' => 40], 'supervisor', 'Total bobot ketiga penilai harus 100%.'],
    'grade order' => [['b_plus' => 90], 'a', 'Batas nilai huruf harus berurutan dari tertinggi ke terendah.'],
    'rubric' => [['examiner_rubric' => 'Penguasaan Materi | 60'], 'examiner_rubric', 'Total bobot harus 100%.'],
]);

it('fills the Word letterhead from the issuing program and faculty', function (): void {
    $faculty = Faculty::factory()->create(['name' => 'Fakultas Ekonomi dan Bisnis', 'code' => 'FEB']);
    $program = ProgramStudi::factory()->create([
        'faculty_id' => $faculty->id,
        'name' => 'Manajemen & Bisnis',
        'sidang_settings' => ['heading' => 'Universitas Uji'],
    ]);
    [$defense] = sidangPaperFixture($program);
    $this->actingAs(User::factory()->asSuperAdmin()->create());

    foreach ([[], ['lembar' => 'berita-acara']] as $options) {
        $word = $this->get(route('sidang.paper.print', [$defense, ...$options]))->assertOk()->streamedContent();
        expect(sidangReportSheet($word, 'word/header1.xml'))
            ->toContain('UNIVERSITAS UJI', 'FAKULTAS EKONOMI DAN BISNIS', 'MANAJEMEN &amp; BISNIS')
            ->not->toContain('ILMU KOMPUTER', 'ftd@', '{{');
    }
});

it('requires the configured paper panel and completes old schedules without losing scores', function (): void {
    [$defense] = sidangPaperFixture(ProgramStudi::factory()->create(['sidang_settings' => ['minimum_paper_examiners' => 2]]));
    $admin = User::factory()->asSuperAdmin()->create();
    $project = $defense->project;
    $panel = $defense->examiners()->orderBy('order_no')->get();
    [$first, $second, $supervisor] = $panel->all();
    $second->delete();
    $first->update(['score' => 90]);
    $schedule = fn (array $ids) => app(ThesisProjectAdminService::class)->scheduleSidang(
        $project, $admin->id, '2026-11-06 10:00:00', 'Ruang Sidang', 'offline', $ids,
    );

    expect(fn () => $schedule([$first->lecturer_user_id, $second->lecturer_user_id]))
        ->toThrow(RuntimeException::class, 'Tetapkan minimal satu pembimbing aktif');
    $project->supervisorAssignments()->create([
        'lecturer_user_id' => $supervisor->lecturer_user_id,
        'role' => 'primary', 'status' => 'active', 'assigned_by' => $admin->id, 'started_at' => now(),
    ]);
    $this->actingAs($admin);
    Livewire::test(ViewThesisProject::class, ['record' => $project->getRouteKey()])
        ->mountAction('schedule_sidang')
        ->setActionData(['additional_examiner_user_ids' => [$first->lecturer_user_id]])
        ->callMountedAction()
        ->assertHasActionErrors(['additional_examiner_user_ids' => 'min']);
    $page = Livewire::test(ViewThesisProject::class, ['record' => $project->getRouteKey()])
        ->mountAction('schedule_sidang')
        ->setActionData(['additional_examiner_user_ids' => [$supervisor->lecturer_user_id, $first->lecturer_user_id]])
        ->callMountedAction()
        ->assertHasActionErrors(['additional_examiner_user_ids'])
        ->assertActionDataSet(['additional_examiner_user_ids' => [$supervisor->lecturer_user_id, $first->lecturer_user_id]])
        ->assertActionMounted('schedule_sidang');
    expect($page->instance()->getErrorBag()->first('mountedActions.0.data.additional_examiner_user_ids'))
        ->toBe('Pembimbing aktif sudah menjadi bagian dari panel.');
    expect(fn () => $schedule([$supervisor->lecturer_user_id, $first->lecturer_user_id, $supervisor->lecturer_user_id]))
        ->toThrow(RuntimeException::class, 'Pilih minimal 2 dosen penguji selain pembimbing');
    expect($defense->fresh()->scheduled_for->toDateTimeString())->toBe('2026-11-04 23:30:00')
        ->and($defense->examiners()->count())->toBe(2);

    $updated = $schedule([$supervisor->lecturer_user_id, $first->lecturer_user_id, $second->lecturer_user_id]);
    expect($updated->id)->toBe($defense->id)
        ->and($updated->examiners()->orderBy('order_no')->pluck('role')->all())->toBe(['primary_supervisor', 'examiner', 'examiner'])
        ->and($first->fresh()->score)->toEqual(90);
});

it('uses the saved minimum at both scheduling entry points and accepts the old panel after it is disabled', function (): void {
    [$defense, , $program] = sidangPaperFixture();
    $project = $defense->project;
    $admin = User::factory()->asSuperAdmin()->create();
    $head = User::factory()->asKaprodi()->create();
    KaprodiAssignment::factory()->create(['user_id' => $head->id, 'program_studi_id' => $program->id]);
    $examiner = $defense->examiners()->where('role', 'examiner')->firstOrFail();
    $supervisor = $defense->examiners()->where('role', 'primary_supervisor')->firstOrFail();
    $project->supervisorAssignments()->create([
        'lecturer_user_id' => $supervisor->lecturer_user_id,
        'role' => 'primary', 'status' => 'active', 'assigned_by' => $admin->id, 'started_at' => now(),
    ]);
    $data = [
        'scheduled_for' => '2026-11-06 10:00:00', 'location' => 'Ruang Sidang Uji', 'mode' => 'offline',
        'additional_examiner_user_ids' => [$examiner->lecturer_user_id],
    ];

    $program->update(['sidang_settings' => ['minimum_paper_examiners' => 2]]);
    $this->actingAs($head)->post(route('kaprodi.projects.sidang', $project), $data)
        ->assertSessionHasErrors(['additional_examiner_user_ids' => 'Pilih minimal 2 dosen penguji selain pembimbing.']);

    $program->update(['sidang_settings' => ['minimum_paper_examiners' => 0]]);
    $this->post(route('kaprodi.projects.sidang', $project), $data)->assertSessionHasNoErrors();
    $this->actingAs($admin);
    Livewire::test(ViewThesisProject::class, ['record' => $project->getRouteKey()])
        ->mountAction('schedule_sidang')
        ->setActionData(['additional_examiner_user_ids' => [$examiner->lecturer_user_id]])
        ->callMountedAction()
        ->assertHasNoActionErrors();
    expect($defense->fresh()->examiners()->where('role', 'examiner')->count())->toBe(1);
});

it('lists only accessible latest sidang records and shows the shortcut for each stage', function (): void {
    [$scheduled, , $program] = sidangPaperFixture();
    [$completed] = sidangPaperFixture($program);
    [$cancelled] = sidangPaperFixture($program);
    [$foreign] = sidangPaperFixture();
    $completed->update(['status' => 'completed', 'result' => 'pass']);
    $cancelled->project->sidangDefenses()->create([
        'type' => 'sidang', 'attempt_no' => 2, 'status' => 'cancelled', 'scheduled_for' => now(),
    ]);
    $admin = User::factory()->asAdmin()->create();
    AdminProfile::query()->create(['user_id' => $admin->id, 'program_studi_id' => $program->id]);
    $this->actingAs($admin);

    $page = Livewire::test(ListThesisProjects::class)
        ->set('activeTab', ListThesisProjects::SIDANG_PAPER_TAB)
        ->assertCanSeeTableRecords([$scheduled->project, $completed->project], inOrder: true)
        ->assertCanNotSeeTableRecords([$cancelled->project, $foreign->project])
        ->assertTableActionHasUrl('unduh_berkas_sidang', route('sidang.paper.print', $scheduled), $scheduled->project)
        ->assertTableActionHasLabel('tindakan_berkas_sidang', 'Input Nilai', $scheduled->project)
        ->assertTableActionHasUrl('tindakan_berkas_sidang', ThesisProjectResource::getUrl('sidang-paper', ['record' => $scheduled->project, 'defense' => $scheduled, 'dari' => 'daftar']).'#nilai-dari-berkas', $scheduled->project)
        ->assertTableActionHasLabel('tindakan_berkas_sidang', 'Lihat Berkas', $completed->project);

    $page->filterTable('sidang_paper_status', 'completed')
        ->assertCanSeeTableRecords([$completed->project])
        ->assertCanNotSeeTableRecords([$scheduled->project]);

    $scheduled->update(['status' => 'awaiting_finalization']);
    Livewire::test(ListThesisProjects::class)
        ->set('activeTab', ListThesisProjects::SIDANG_PAPER_TAB)
        ->assertTableActionHasLabel('tindakan_berkas_sidang', 'Tinjau & Finalisasi', $scheduled->project)
        ->assertTableActionHasUrl('tindakan_berkas_sidang', ThesisProjectResource::getUrl('sidang-paper', [
            'record' => $scheduled->project, 'defense' => $scheduled, 'dari' => 'daftar',
        ]).'#nilai-dari-berkas', $scheduled->project);
});

function sidangReportSheet(string $content, string $part = 'xl/worksheets/sheet7.xml'): string
{
    $file = tempnam(sys_get_temp_dir(), 'sita-sidang-');
    file_put_contents($file, $content);
    $archive = new ZipArchive;
    try {
        expect($archive->open($file))->toBeTrue();

        return $archive->getFromName($part);
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

    $defense->forceFill(['moderator_user_id' => $moderator->id])->save();
    $this->actingAs($admin)->get(route('sidang.paper.show', $defense))->assertRedirect(ThesisProjectResource::getUrl('sidang-paper', ['record' => $defense->project, 'defense' => $defense]));
    $this->post(route('sidang.paper.store', $defense), ['sidang_sk' => '001/UJI'])
        ->assertSessionHasNoErrors()->assertRedirect();
    expect($defense->fresh()->sidang_sk)->toBe('001/UJI')
        ->and($defense->fresh()->moderator_user_id)->toBe($moderator->id);
    $this->post(route('sidang.paper.store', $defense), [
        'scores' => $scores,
    ])->assertSessionHasNoErrors()->assertRedirect();

    expect($defense->fresh()->status)->toBe('awaiting_finalization')
        ->and($defense->fresh()->moderator_user_id)->toBe($moderator->id)
        ->and(app(SidangScoreService::class)->summary($defense->fresh())['value'])->toEqual(80)
        ->and(app(SidangScoreService::class)->summary($defense->fresh())['grade'])->toBe('B+')
        ->and($defense->examiners()->where('decision', 'pending')->count())->toBe(3);
    $word = $this->get(route('sidang.paper.print', [$defense, 'lembar' => 'berita-acara']))
        ->assertDownload('berita-acara-'.$defense->id.'.docx')->streamedContent();
    expect(sidangReportSheet($word, 'word/document.xml'))
        ->toContain($student->name, 'Sistem Rekap Penilaian', 'TIDAK LULUS', '<w:tbl>')
        ->not->toContain('Suwist', 'DAFTAR HADIR', '{{');
    $template = file_get_contents(resource_path('templates/sidang/berkas-sidang.docx'));
    expect(sidangReportSheet($word, 'word/header1.xml'))->toContain('UNIVERSITAS BUMIGORA')
        ->and(sidangReportSheet($word, 'word/media/image1.png'))->toBe(sidangReportSheet($template, 'word/media/image1.png'));

    $params = ['from' => '2026-11-04', 'until' => '2026-11-04', 'period' => 'Semester Uji'];
    expect(sidangReportSheet($this->post(route('sidang.report'), $params)->assertOk()->streamedContent()))
        ->not->toContain($student->name);

    $program->update(['sidang_settings' => ['a' => 76]]);
    app(ThesisProjectAdminService::class)->completeSidang(
        $defense->project, $admin->id, 'pass_with_revision', 'Keputusan resmi.', 'Perbaiki daftar pustaka.', '2026-11-12',
    );
    expect($defense->fresh()->sidang_paper['grade'])->toBe('A');
    $word = $this->get(route('sidang.paper.print', [$defense, 'lembar' => 'berita-acara']))->streamedContent();
    expect(sidangReportSheet($word, 'word/document.xml'))->toContain('>LULUS<')->not->toContain('TIDAK LULUS');
    $sheet = sidangReportSheet($this->post(route('sidang.report'), $params)->assertDownload('rekap-nilai-sidang.xlsx')->streamedContent());
    expect($sheet)->toContain($student->name, 'Sistem Rekap Penilaian', 'A12:A14', 'B+', 'Semester Uji', '<f>(L12*30%+L13*30%+L14*40%)</f>', '<f>ROUND(M12,0)</f>');
    $formulaLikePeriod = array_replace($params, ['period' => '=2+2']);
    expect(sidangReportSheet($this->post(route('sidang.report'), $formulaLikePeriod)->streamedContent()))
        ->toContain('>=2+2</t>')->not->toContain('<f>2+2</f>');

    $this->actingAs(User::factory()->asAdmin()->create())->get(route('sidang.paper.show', $defense))->assertForbidden();
    $this->actingAs($admin);
    expect(sidangReportSheet($this->post(route('sidang.report'), ['from' => '2026-11-05', 'until' => '2026-11-05'])->streamedContent()))
        ->not->toContain($student->name);
    $defense->project->sidangDefenses()->create(['type' => 'sidang', 'attempt_no' => 2, 'status' => 'scheduled']);
    expect(sidangReportSheet($this->post(route('sidang.report'), $params)->streamedContent()))->not->toContain($student->name);
});

it('corrects a digital score from paper while preserving the decision and revision notes', function () {
    [$defense, , , $scores] = sidangPaperFixture();
    $admin = User::factory()->asSuperAdmin()->create();
    $examiners = $defense->examiners()->orderBy('order_no')->get();
    $examiners[1]->update(['score' => 68, 'decision' => 'pass']);

    $examiners[1]->update(['revision_notes' => 'Pertahankan catatan lama.']);
    $this->actingAs($admin)->post(route('sidang.paper.store', $defense), ['sidang_sk' => '001/UJI', 'scores' => $scores])
        ->assertSessionHasNoErrors();
    expect($examiners[1]->fresh()->score)->toEqual(70)
        ->and($examiners[1]->fresh()->decision)->toBe('pass')
        ->and($examiners[1]->fresh()->revision_notes)->toBe('Pertahankan catatan lama.')
        ->and($defense->fresh()->status)->toBe('awaiting_finalization');

    $scores['examiner_2']['value'] = 68;
    $scores['supervisor']['value'] = 0;
    $this->post(route('sidang.paper.store', $defense), ['scores' => $scores])->assertSessionHasNoErrors();
    expect(app(SidangScoreService::class)->summary($defense->fresh())['value'])->toEqual(47.4);
});

it('keeps one score through lecturer input, paper matching, admin correction and lecturer correction', function (): void {
    [$defense, , , $scores] = sidangPaperFixture();
    $admin = User::factory()->asSuperAdmin()->create();
    $member = $defense->examiners()->find($scores['examiner_1']['panel_id']);
    app(ThesisDefenseExaminerDecisionService::class)->submit($member->lecturer, $defense, [
        'decision' => 'pass_with_revision', 'score' => 90, 'revision_notes' => 'Perbaiki tabel.',
        'revision_items' => [['description' => 'Rujukan tabel', 'location' => 'Bab 4']],
    ]);
    $decisionTime = $member->fresh()->decided_at->toIso8601String();
    $this->actingAs($admin)->post(route('sidang.paper.store', $defense), ['scores' => $scores])->assertSessionHasNoErrors();
    expect($member->fresh()->score_source)->toBe('lecturer')
        ->and($member->fresh()->paper_verified_by)->toBe($admin->id)
        ->and($member->fresh()->score_recorded_by)->toBe($member->lecturer_user_id);

    $scores['examiner_1']['value'] = 87.25;
    $this->post(route('sidang.paper.store', $defense), ['scores' => $scores])->assertSessionHasNoErrors();
    expect($member->fresh()->score)->toEqual(87.25)->and($member->fresh()->score_source)->toBe('paper');
    $this->actingAs($member->lecturer)->patch(route('dosen.seminar-proposal.score', $defense), [
        'score' => 88.5, 'expected_score' => 87.25,
    ])->assertSessionHasNoErrors();
    expect($member->fresh()->score)->toEqual(88.5)
        ->and($member->fresh()->paper_verified_at)->toBeNull()
        ->and($member->fresh()->paper_verified_by)->toBeNull()
        ->and($member->fresh()->decision)->toBe('pass_with_revision')
        ->and($member->fresh()->revision_notes)->toBe('Perbaiki tabel.')
        ->and($member->fresh()->revision_items[0]['description'])->toBe('Rujukan tabel')
        ->and($member->fresh()->decided_at->toIso8601String())->toBe($decisionTime)
        ->and($defense->fresh()->status)->toBe('awaiting_finalization')
        ->and(app(SidangScoreService::class)->summary($defense->fresh())['value'])->toEqual(79.55);
    $this->assertDatabaseHas('thesis_defense_score_changes', [
        'examiner_id' => $member->id, 'actor_user_id' => $member->lecturer_user_id,
        'source' => 'lecturer', 'old_score' => 87.25, 'new_score' => 88.5,
    ]);
});

it('rejects stale paper input atomically and keeps the newer lecturer score', function (): void {
    [$defense, , , $scores] = sidangPaperFixture();
    $admin = User::factory()->asSuperAdmin()->create();
    foreach ($scores as &$entry) {
        $entry['expected_value'] = null;
    }
    unset($entry);
    $member = $defense->examiners()->find($scores['examiner_2']['panel_id']);
    app(SidangScoreService::class)->correct($member->lecturer, $defense, ['score' => 68, 'expected_score' => null]);
    $this->actingAs($admin)->post(route('sidang.paper.store', $defense), ['scores' => $scores, 'sidang_sk' => 'STALE'])
        ->assertSessionHasErrors('scores.examiner_2.value');
    expect($defense->fresh()->sidang_sk)->toBeNull()
        ->and($defense->fresh()->sidang_paper)->toBeNull()
        ->and($defense->examiners()->find($scores['examiner_1']['panel_id'])->score)->toBeNull()
        ->and($member->fresh()->score)->toEqual(68);
    $this->assertDatabaseCount('thesis_defense_score_changes', 1);
    expect($defense->examiners()->whereNotNull('paper_verified_at')->count())->toBe(0);
});

it('allows an outstanding lecturer decision after paper input without moving the defense back to scheduled', function (): void {
    [$defense, , , $scores] = sidangPaperFixture();
    app(SidangPaperService::class)->record($defense, User::factory()->asSuperAdmin()->create(), ['scores' => $scores]);
    $member = $defense->examiners()->first();
    app(ThesisDefenseExaminerDecisionService::class)->submit($member->lecturer, $defense, [
        'decision' => 'pass', 'score' => $member->score, 'expected_score' => $member->score,
    ]);
    expect($defense->fresh()->status)->toBe('awaiting_finalization');
});

it('rejects a panel member from another attempt without changing scores or audit rows', function (): void {
    [$defense, , $program, $scores] = sidangPaperFixture();
    [$other, , , $otherScores] = sidangPaperFixture($program);
    $scores['examiner_2']['panel_id'] = $otherScores['examiner_2']['panel_id'];
    $this->actingAs(User::factory()->asSuperAdmin()->create())->post(route('sidang.paper.store', $defense), ['scores' => $scores])
        ->assertSessionHasErrors('paper');
    expect($defense->examiners()->whereNotNull('score')->count())->toBe(0)
        ->and($other->examiners()->whereNotNull('score')->count())->toBe(0);
    $this->assertDatabaseCount('thesis_defense_score_changes', 0);
});

it('locks every score entry point after finalization even when passed a stale scheduled model', function (): void {
    [$defense, , , $scores] = sidangPaperFixture();
    $admin = User::factory()->asSuperAdmin()->create();
    $stale = $defense->fresh();
    app(SidangPaperService::class)->record($defense, $admin, ['scores' => $scores]);
    app(ThesisProjectAdminService::class)->completeSidang($defense->project, $admin->id, 'pass', 'Lulus.');
    $member = $defense->examiners()->first();
    expect(fn () => app(SidangScoreService::class)->correct($member->lecturer, $stale, ['score' => 10, 'expected_score' => 90]))->toThrow(ValidationException::class)
        ->and(fn () => app(SidangPaperService::class)->record($stale, $admin, ['scores' => $scores]))->toThrow(ValidationException::class)
        ->and(fn () => app(ThesisDefenseExaminerDecisionService::class)->submit($member->lecturer, $stale, ['decision' => 'pass', 'score' => 10]))->toThrow(RuntimeException::class);
    expect($member->fresh()->score)->toEqual(90);
});

it('snapshots a complete digital panel at finalization and exports it without paper input', function (): void {
    [$defense, $student, $program, $scores] = sidangPaperFixture();
    $admin = User::factory()->asSuperAdmin()->create();
    foreach ($scores as $entry) {
        $member = $defense->examiners()->find($entry['panel_id']);
        app(ThesisDefenseExaminerDecisionService::class)->submit($member->lecturer, $defense, ['decision' => 'pass', 'score' => $entry['value']]);
    }
    expect($defense->fresh()->sidang_paper)->toBeNull();
    app(ThesisProjectAdminService::class)->completeSidang($defense->project, $admin->id, 'pass', 'Hasil resmi.');
    $program->update(['sidang_settings' => ['a' => 1, 'examiner_1' => 100, 'examiner_2' => 0, 'supervisor' => 0]]);
    expect(app(SidangScoreService::class)->summary($defense->fresh()))->toMatchArray(['value' => 80.0, 'grade' => 'B+']);
    expect($defense->fresh()->sidang_paper)->not->toHaveKey('scores');
    $sheet = sidangReportSheet($this->actingAs($admin)->post(route('sidang.report'))->assertOk()->streamedContent());
    expect($sheet)->toContain($student->name, '<f>(L12*30%+L13*30%+L14*40%)</f>');
});

it('rejects score changes by nonmembers and invalid numeric inputs', function (mixed $score): void {
    [$defense] = sidangPaperFixture();
    $member = $defense->examiners()->first();
    $this->actingAs(User::factory()->asDosen()->create())->patch(route('dosen.seminar-proposal.score', $defense), ['score' => 80, 'expected_score' => null])->assertForbidden();
    $this->actingAs($member->lecturer)->patch(route('dosen.seminar-proposal.score', $defense), ['score' => $score, 'expected_score' => null])->assertSessionHasErrors('score');
    expect($member->fresh()->score)->toBeNull();
})->with([-1, 100.01, 80.123, 'delapan puluh', null]);

it('preserves old final snapshots and backfills only missing or provable paper metadata', function (): void {
    [$defense, , , $scores] = sidangPaperFixture();
    $admin = User::factory()->asSuperAdmin()->create();
    $paper = ['recorded_by' => $admin->id, 'recorded_at' => '2026-11-04T13:00:00+08:00',
        'scores' => $scores, 'value' => 80, 'grade' => 'B+', 'settings' => SidangSettings::DEFAULTS];
    $member = $defense->examiners()->find($scores['examiner_2']['panel_id']);
    $member->update(['score' => 68, 'decision' => 'pass']);
    $defense->forceFill(['status' => 'completed', 'sidang_paper' => $paper])->save();
    $migration = require database_path('migrations/2026_11_07_000002_restore_missing_sidang_panel_scores.php');
    $migration->up();
    $migration->up();
    expect($defense->fresh()->sidang_paper)->toBe($paper)
        ->and($defense->examiners()->find($scores['examiner_1']['panel_id'])->score)->toEqual(90)
        ->and($member->fresh()->score)->toEqual(68)
        ->and($member->fresh()->decision)->toBe('pass')
        ->and($member->fresh()->paper_verified_at)->toBeNull()
        ->and(app(SidangScoreService::class)->summary($defense->fresh())['value'])->toEqual(80);
    $expected = json_encode(['defense_id' => $defense->id, 'panel_id' => $member->id, 'problem' => 'different_panel_score']);
    $this->artisan('sidang:audit-scores')->expectsOutput($expected)->assertSuccessful();
});

it('reports legacy panels it cannot map and preserves their scores', function (): void {
    [$defense, , , $scores] = sidangPaperFixture();
    $scores['supervisor']['panel_id'] = 999999;
    $defense->forceFill(['sidang_paper' => ['scores' => $scores]])->save();
    app(SidangScoreService::class)->importLegacy($defense);
    expect($defense->examiners()->whereNotNull('score')->count())->toBe(0)
        ->and(app(SidangScoreService::class)->legacyIssues($defense->fresh())[0]['problem'])->toBe('unmapped_panel');
});

it('does not restore an old paper match over a newer lecturer correction during a migration retry', function (): void {
    [$defense, , , $scores] = sidangPaperFixture();
    $defense->forceFill(['sidang_paper' => ['scores' => $scores, 'recorded_at' => now()->subDay()->toIso8601String()]])->save();
    $member = $defense->examiners()->find($scores['examiner_1']['panel_id']);
    app(SidangScoreService::class)->correct($member->lecturer, $defense, ['score' => 90, 'expected_score' => null]);
    app(SidangScoreService::class)->importLegacy($defense);
    expect($member->fresh()->score_source)->toBe('lecturer')
        ->and($member->fresh()->paper_verified_at)->toBeNull();
});

it('freezes the migration baseline for an old digital result without inventing its finalization date', function (): void {
    [$defense, , $program, $scores] = sidangPaperFixture();
    foreach ($scores as $entry) {
        $defense->examiners()->find($entry['panel_id'])->update(['score' => $entry['value'], 'decision' => 'pass']);
    }
    $defense->update(['status' => 'completed', 'result' => 'pass']);
    $migration = require database_path('migrations/2026_11_07_000002_restore_missing_sidang_panel_scores.php');
    $migration->up();
    $program->update(['sidang_settings' => ['examiner_1' => 100, 'examiner_2' => 0, 'supervisor' => 0]]);
    expect(app(SidangScoreService::class)->summary($defense->fresh())['value'])->toEqual(80)
        ->and($defense->fresh()->sidang_paper['settings_origin'])->toBe('program_at_migration')
        ->and($defense->fresh()->sidang_paper['finalized_at'])->toBeNull()
        ->and($defense->fresh()->sidang_paper)->not->toHaveKey('scores');
});

it('uses the same corrected score on manager, lecturer, student, Word and Excel views', function (): void {
    [$defense, $student, , $scores] = sidangPaperFixture();
    $admin = User::factory()->asSuperAdmin()->create();
    $scores['examiner_1']['value'] = 88.5;
    $this->actingAs($admin)->post(route('sidang.paper.store', $defense), ['scores' => $scores])->assertSessionHasNoErrors();
    $head = User::factory()->asKaprodi()->create();
    KaprodiAssignment::factory()->create(['user_id' => $head->id, 'program_studi_id' => $defense->project->program_studi_id]);
    $this->actingAs($head)->get(route('sidang.paper.show', $defense))->assertInertia(fn (Assert $page) => $page
        ->where('defense.summary.value', 79.55)->where('defense.panel.0.source', 'paper')
        ->where('defense.panel.0.recordedBy', $admin->name)->where('defense.panel.0.verifiedBy', $admin->name));
    app(ThesisProjectAdminService::class)->completeSidang($defense->project, $admin->id, 'pass', 'Selesai.');
    $member = $defense->examiners()->first();
    $this->actingAs($member->lecturer)->get(route('dosen.seminar-proposal'))->assertInertia(fn (Assert $page) => $page
        ->where('defenses.0.paperScore', 79.55)->where('defenses.0.myScore', '88.50')
        ->where('defenses.0.canEditScore', false)->where('defenses.0.myScoreSource', 'paper'));
    $this->actingAs($student)->get(route('mahasiswa.tugas-akhir'))->assertInertia(fn (Assert $page) => $page
        ->where('sidangResult.paperScore', 79.55)->where('sidangResult.examiners.0.decisionLabel', 'Nilai dari berkas'));
    $this->actingAs($admin);
    $word = $this->get(route('sidang.paper.print', [$defense, 'lembar' => 'rekap-nilai']))->streamedContent();
    expect(sidangReportSheet($word, 'word/document.xml'))->toContain('79.55', '88.5');
    $excel = $this->post(route('sidang.report'))->assertOk()->streamedContent();
    expect(sidangReportSheet($excel))->toContain('79.55', '88.5');
});

it('continues the reference row pattern past nine defenses and keeps each program on its own sheet', function () {
    $admin = User::factory()->asSuperAdmin()->create();
    [$first, , , $scores] = sidangPaperFixture();
    [$second, $student, , $otherScores] = sidangPaperFixture();
    app(SidangPaperService::class)->record($first, $admin, ['scores' => $scores]);
    app(SidangPaperService::class)->record($second, $admin, ['scores' => $otherScores]);

    ob_start();
    try {
        app(SidangReportWriter::class)->write([...array_fill(0, 10, $first->fresh()), $second->fresh()], 'Uji');
        $content = ob_get_contents();
    } finally {
        ob_end_clean();
    }

    expect(sidangReportSheet($content))->toContain('A39:A41', '<f>(L39*30%+L40*30%+L41*40%)</f>', 'A44:B44');
    expect(sidangReportSheet($content, 'xl/worksheets/rekap2.xml'))->toContain($student->name, 'A12:A14');
});

it('saves editable semester periods by program and exports the selected WITA range', function (): void {
    [$defense, $student, $program, $scores] = sidangPaperFixture();
    [$otherDefense, $otherStudent, , $otherScores] = sidangPaperFixture($program);
    $otherDefense->update(['scheduled_for' => '2026-11-05 00:00:00']);
    $admin = User::factory()->asAdmin()->create();
    AdminProfile::query()->create(['user_id' => $admin->id, 'program_studi_id' => $program->id]);
    $this->actingAs($admin);
    $service = app(SidangReportPeriodService::class);
    expect($service->suggestion('2026-09-14'))->toBe(['semester' => 'ganjil', 'start_year' => 2026])
        ->and($service->suggestion('2027-03-01'))->toBe(['semester' => 'genap', 'start_year' => 2026]);

    $data = ['starts_on' => '2026-11-04', 'ends_on' => '2026-11-04', 'semester' => 'genap', 'start_year' => 2026];
    $page = Livewire::test(ListThesisProjects::class)
        ->assertActionHidden('manageSidangReportPeriod')
        ->set('activeTab', ListThesisProjects::SIDANG_PAPER_TAB)
        ->assertActionVisible('manageSidangReportPeriod')
        ->mountAction('manageSidangReportPeriod');
    $page->set('mountedActions.0.data.starts_on', '2026-11-04')
        ->assertActionDataSet(['semester' => 'ganjil', 'start_year' => 2026])
        ->setActionData($data)
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $period = SidangReportPeriod::query()->sole();
    expect($period->label())->toBe('Semester Genap 2026/2027');
    $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-04 23:59:00', 'Asia/Makassar'));
    expect($service->activeId($service->periods($program->id)))->toBe($period->id);
    $page->mountAction('exportSidangReport')
        ->assertActionDataSet(['period_id' => $period->id])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect(fn () => $service->save($admin, [
        ...$data, 'semester' => 'ganjil', 'ends_on' => '2026-11-30',
    ]))->toThrow(ValidationException::class, 'Rentang tanggal bertumpang tindih');
    expect($service->save($admin, [...$data, 'period_id' => $period->id])->id)->toBe($period->id);

    foreach ([[$defense, $scores], [$otherDefense, $otherScores]] as [$attempt, $totals]) {
        app(SidangPaperService::class)->record($attempt, $admin, ['scores' => $totals]);
        $attempt->update(['status' => 'completed', 'result' => 'pass']);
    }
    $sheet = sidangReportSheet($this->post(route('sidang.report'), ['period_id' => $period->id])
        ->assertDownload('rekap-nilai-sidang.xlsx')->streamedContent());
    expect($sheet)->toContain($student->name, 'SEMESTER GENAP 2026/2027')->not->toContain($otherStudent->name);

    $foreignAdmin = User::factory()->asAdmin()->create();
    AdminProfile::query()->create(['user_id' => $foreignAdmin->id, 'program_studi_id' => ProgramStudi::factory()->create()->id]);
    $this->actingAs($foreignAdmin)->post(route('sidang.report'), ['period_id' => $period->id])->assertForbidden();
});

it('assigns a shared No SK and preserves grades and existing different numbers', function (): void {
    [$first, , $program, $scores] = sidangPaperFixture();
    [$second, , , $secondScores] = sidangPaperFixture($program);
    [$third, , , $thirdScores] = sidangPaperFixture($program);
    $admin = User::factory()->asAdmin()->create();
    AdminProfile::query()->create(['user_id' => $admin->id, 'program_studi_id' => $program->id]);
    $service = app(SidangPaperService::class);
    foreach ([[$first, $scores], [$second, $secondScores], [$third, $thirdScores]] as [$attempt, $totals]) {
        $service->record($attempt, $admin, ['scores' => $totals]);
        $attempt->update(['status' => 'completed', 'result' => 'pass']);
    }
    $paper = $first->fresh()->sidang_paper;
    $third->forceFill(['sidang_sk' => '002/ADUM'])->save();
    expect(fn () => $service->assignSk($admin, collect([$first->project, $third->project]), '001/ADUM'))
        ->toThrow(ValidationException::class, 'Ada mahasiswa dengan No SK berbeda');
    expect($first->fresh()->sidang_sk)->toBeNull();

    $this->actingAs($admin);
    Livewire::test(ListThesisProjects::class)
        ->set('activeTab', ListThesisProjects::SIDANG_PAPER_TAB)
        ->callTableBulkAction('isi_nomor_sk', [$first->project, $second->project], ['sidang_sk' => '001/ADUM'])
        ->assertHasNoActionErrors();
    expect($first->fresh()->sidang_sk)->toBe('001/ADUM')
        ->and($second->fresh()->sidang_sk)->toBe('001/ADUM')
        ->and($third->fresh()->sidang_sk)->toBe('002/ADUM')
        ->and($first->fresh()->sidang_paper)->toBe($paper);
    expect($service->assignSk($admin, collect([$first->project, $second->project]), '001/ADUM'))->toBe(2);

    $sheet = sidangReportSheet($this->post(route('sidang.report'))->assertOk()->streamedContent());
    expect($sheet)->toContain('E12:E17', 'E18:E20', '001/ADUM', '002/ADUM');
});

it('limits kaprodi to the own program for the paper, the Word packet and the Excel report', function (): void {
    [$own, $ownStudent, $ownProgram, $ownScores] = sidangPaperFixture();
    [$foreign, $foreignStudent, $foreignProgram, $foreignScores] = sidangPaperFixture();
    $admin = User::factory()->asSuperAdmin()->create();
    foreach ([[$own, $ownScores], [$foreign, $foreignScores]] as [$defense, $scores]) {
        app(SidangPaperService::class)->record($defense, $admin, ['scores' => $scores]);
        app(ThesisProjectAdminService::class)->completeSidang(
            $defense->project, $admin->id, 'pass', 'Keputusan resmi.', null, null,
        );
    }
    $foreignPeriod = app(SidangReportPeriodService::class)->save($admin, [
        'program_studi_id' => $foreignProgram->id, 'semester' => 'ganjil', 'start_year' => 2026,
        'starts_on' => '2026-11-01', 'ends_on' => '2026-11-30',
    ]);

    $kaprodi = User::factory()->asKaprodi()->create();
    KaprodiAssignment::factory()->create(['program_studi_id' => $ownProgram->id, 'user_id' => $kaprodi->id]);
    $this->actingAs($kaprodi->fresh());

    $this->get(route('sidang.paper.show', $own))->assertOk();
    $filters = ['search' => 'Nama & NIM', 'type' => 'sidang', 'status' => 'completed', 'page' => '2'];
    $this->get(route('sidang.paper.show', ['defense' => $own, ...$filters, 'returnTo' => 'https://example.test']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('returnFilters', $filters)
            ->where('backUrl', route('kaprodi.sempro-sidang', $filters)));
    $this->get(route('sidang.paper.print', $own))->assertOk();
    $this->get(route('sidang.paper.show', $foreign))->assertForbidden();
    $this->get(route('sidang.paper.print', $foreign))->assertForbidden();
    $this->post(route('sidang.paper.store', $foreign), ['sidang_sk' => '009/LAIN'])->assertForbidden();
    expect($foreign->fresh()->sidang_sk)->toBeNull();
    $this->post(route('sidang.report'), ['period_id' => $foreignPeriod->id])->assertForbidden();

    $sheet = sidangReportSheet($this->post(route('sidang.report'))->assertOk()->streamedContent());
    expect($sheet)->toContain($ownStudent->name)->not->toContain($foreignStudent->name);

    $outsider = User::factory()->asDosen()->create();
    DosenProfile::factory()->create(['user_id' => $outsider->id, 'program_studi_id' => $ownProgram->id, 'is_active' => true]);
    $this->actingAs($outsider)->get(route('sidang.paper.print', $own))->assertForbidden();
    $this->post(route('sidang.report'))->assertForbidden();
});
