<?php

use App\Filament\Resources\ProgramStudis\Pages\EditProgramStudi;
use App\Filament\Resources\ThesisProjects\Pages\ListThesisProjects;
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
use App\Services\ThesisProjectAdminService;
use App\Support\SidangSettings;
use Illuminate\Validation\ValidationException;
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

it('opens and saves the sidang document and grade settings for a program', function (): void {
    $program = ProgramStudi::factory()->create();
    $this->actingAs(User::factory()->asSuperAdmin()->create());

    Livewire::test(EditProgramStudi::class, ['record' => $program->getRouteKey()])
        ->mountAction('sidangSettings')
        ->setActionData(['sidang_settings' => [
            ...SidangSettings::forProgram($program),
            'city' => 'Kota Uji',
        ]])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($program->fresh()->sidang_settings['city'])->toBe('Kota Uji');
});

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

it('requires the Ilmu Komputer paper panel and completes old schedules without losing scores', function (): void {
    [$defense] = sidangPaperFixture(ProgramStudi::factory()->create(['slug' => 'ilkom']));
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
    expect(fn () => $schedule([$supervisor->lecturer_user_id, $first->lecturer_user_id, $supervisor->lecturer_user_id]))
        ->toThrow(RuntimeException::class, 'Pilih minimal dua dosen penguji selain pembimbing');
    expect($defense->fresh()->scheduled_for->toDateTimeString())->toBe('2026-11-04 23:30:00')
        ->and($defense->examiners()->count())->toBe(2);

    $updated = $schedule([$supervisor->lecturer_user_id, $first->lecturer_user_id, $second->lecturer_user_id]);
    expect($updated->id)->toBe($defense->id)
        ->and($updated->examiners()->orderBy('order_no')->pluck('role')->all())->toBe(['primary_supervisor', 'examiner', 'examiner'])
        ->and($first->fresh()->score)->toEqual(90);
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
        ->assertTableActionHasUrl('tindakan_berkas_sidang', route('sidang.paper.show', ['defense' => $scheduled, 'dari' => 'daftar']).'#nilai-dari-berkas', $scheduled->project)
        ->assertTableActionHasLabel('tindakan_berkas_sidang', 'Lihat Berkas', $completed->project);

    $page->filterTable('sidang_paper_status', 'completed')
        ->assertCanSeeTableRecords([$completed->project])
        ->assertCanNotSeeTableRecords([$scheduled->project]);

    $scheduled->update(['status' => 'awaiting_finalization']);
    Livewire::test(ListThesisProjects::class)
        ->set('activeTab', ListThesisProjects::SIDANG_PAPER_TAB)
        ->assertTableActionHasLabel('tindakan_berkas_sidang', 'Finalisasi', $scheduled->project)
        ->assertTableActionHasUrl('tindakan_berkas_sidang', ThesisProjectResource::getUrl('view', [
            'record' => $scheduled->project, 'action' => 'complete_sidang',
        ]), $scheduled->project);
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
    $this->actingAs($admin)->get(route('sidang.paper.show', $defense))->assertOk();
    $this->post(route('sidang.paper.store', $defense), ['sidang_sk' => '001/UJI'])
        ->assertSessionHasNoErrors()->assertRedirect();
    expect($defense->fresh()->sidang_sk)->toBe('001/UJI')
        ->and($defense->fresh()->moderator_user_id)->toBe($moderator->id);
    $this->post(route('sidang.paper.store', $defense), [
        'scores' => $scores,
    ])->assertSessionHasNoErrors()->assertRedirect();

    expect($defense->fresh()->status)->toBe('awaiting_finalization')
        ->and($defense->fresh()->moderator_user_id)->toBe($moderator->id)
        ->and($defense->fresh()->sidang_paper['value'])->toEqual(80)
        ->and($defense->fresh()->sidang_paper['grade'])->toBe('B+')
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
