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
use App\Services\SidangPaperPresenter;
use App\Services\SidangPaperService;
use App\Services\SidangReportPeriodService;
use App\Services\SidangReportWriter;
use App\Services\SidangScoreService;
use App\Services\ThesisDefenseExaminerDecisionService;
use App\Services\ThesisProjectAdminService;
use App\Support\SidangDocumentTemplate;
use App\Support\SidangSettings;
use Database\Seeders\AcademicStructureSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
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

it('blocks a mixed report with an identified legacy panel instead of silently dropping its row', function (string $role): void {
    [$supported, , $program, $scores] = sidangPaperFixture();
    [$legacy, $student] = sidangPaperFixture($program);
    $student->update(['name' => 'Mahasiswa Panel Lama']);
    $legacy->examiners()->where('role', 'examiner')->orderByDesc('order_no')->firstOrFail()->update(['role' => 'secondary_supervisor']);
    $legacy->examiners()->update(['score' => 80, 'decision' => 'pass']);
    $legacy->update(['status' => 'completed', 'result' => 'pass']);
    $legacy->project->update(['state' => 'completed', 'phase' => 'completed']);
    $actor = $role === 'admin' ? User::factory()->asAdmin()->create() : User::factory()->asKaprodi()->create();
    if ($role === 'admin') {
        AdminProfile::factory()->create(['user_id' => $actor->id, 'program_studi_id' => $program->id]);
    } else {
        KaprodiAssignment::factory()->create(['user_id' => $actor->id, 'program_studi_id' => $program->id]);
    }
    $superAdmin = User::factory()->asSuperAdmin()->create();
    app(SidangPaperService::class)->record($supported, $superAdmin, ['scores' => $scores]);
    app(ThesisProjectAdminService::class)->completeSidang($supported->project, $superAdmin->id, 'pass', 'Final.');

    $legacyFinal = $legacy->fresh()->getAttributes();
    $legacyPanel = $legacy->examiners()->orderBy('id')->get()->toArray();
    $legacyProject = $legacy->project->fresh()->getAttributes();
    $tempFiles = glob(sys_get_temp_dir().'/sita-office-*');
    $response = $this->actingAs($actor)->postJson(route('sidang.report'))->assertUnprocessable();
    expect($response->json('message'))->toContain('1 sidang', 'Mahasiswa Panel Lama', $student->mahasiswaProfile->nim, 'profil perhitungan', 'bobot perlu dikonfirmasi')
        ->and($response->headers->has('content-disposition'))->toBeFalse()
        ->and($legacy->fresh()->examiners->pluck('score')->unique()->all())->toBe(['80.00'])
        ->and(array_diff(glob(sys_get_temp_dir().'/sita-office-*'), $tempFiles))->toBe([]);
    // An explicit selection may still download the supported row, preserving its final score.
    $excel = sidangExcelContent($this->post(route('sidang.report'), ['ids' => (string) $supported->id])->assertOk());
    expect(sidangReportSheet($excel))->toContain($supported->project->student->name)->not->toContain('Mahasiswa Panel Lama');
    expect($legacy->fresh()->getAttributes())->toBe($legacyFinal)
        ->and($legacy->examiners()->orderBy('id')->get()->toArray())->toBe($legacyPanel)
        ->and($legacy->project->fresh()->getAttributes())->toBe($legacyProject)
        ->and($legacy->project->sidangDefenses()->count())->toBe(1);
})->with(['admin', 'kaprodi']);

it('prints the faculty packet and every selected sheet with shared identity and escaped text', function (): void {
    [$defense, $student, $program] = sidangPaperFixture();
    $student->update(['name' => 'Nama <script>alert(1)</script>']);
    $program->update(['sidang_settings' => ['letterhead_address' => 'Jalan Uji 42', 'letterhead_contact' => 'Kontak Prodi', 'letterhead_website' => 'prodi.example.test']]);
    $defense->update(['scheduled_for' => '2026-11-05 07:30:00', 'scheduled_until' => '2026-11-05 09:00:00']);
    $this->actingAs(User::factory()->asSuperAdmin()->create());

    $response = $this->get(route('sidang.paper.preview', $defense))->assertOk()
        ->assertSee('Kamis, 05 November 2026 / 07.30 - 09.00 WITA')
        ->assertSee('Nama &lt;script&gt;alert(1)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('Jalan Uji 42')->assertSee('Kontak Prodi')->assertSee('prodi.example.test')
        ->assertSee('Cetak / Simpan PDF')->assertSee('Unduh Word (.docx)')
        ->assertSee('Penguasaan Materi Skripsi')
        ->assertSee('Karya yang dibuat (Progam /alat)');
    expect(preg_match_all('/<article\b[^>]*\bdata-form=/', $response->getContent()))->toBe(9);
    foreach (array_keys(\App\Http\Controllers\SidangPaperController::FORMS) as $form) {
        $sheet = $this->get(route('sidang.paper.preview', [$defense, 'lembar' => $form]))->assertOk();
        expect(preg_match_all('/<article\b[^>]*\bdata-form=/', $sheet->getContent()))->toBe(1);
        $sheet->assertSee('data-form="'.$form.'"', false);
        $word = $this->get(route('sidang.paper.download', [$defense, 'lembar' => $form]))->assertOk()->streamedContent();
        $xml = new DOMDocument;
        expect($xml->loadXML(sidangReportSheet($word, 'word/document.xml'), LIBXML_NONET))->toBeTrue();
        expect($xml->textContent)->toContain('Nama <script>alert(1)</script>')->not->toContain('tNO', '{{');
        if ($form === 'nilai-penguji') {
            $query = new DOMXPath($xml);
            $query->registerNamespace('w', $xml->documentElement->namespaceURI);
            expect($query->query('//w:txbxContent/w:tbl')->length)->toBe(0)
                ->and($query->query('//w:trPr/w:cantSplit')->length)->toBeGreaterThan(0);
        }
    }
    $this->get(route('sidang.paper.preview', [$defense, 'lembar' => ['berita-acara']]))->assertNotFound();
    $this->get(route('sidang.paper.preview', [$defense, 'lembar' => 'missing']))->assertNotFound();
});

it('uses the same print policy for staff and panel members and rejects other programs', function (): void {
    [$defense, $student, $program] = sidangPaperFixture();
    [$foreign] = sidangPaperFixture();
    $head = User::factory()->asKaprodi()->create();
    KaprodiAssignment::factory()->create(['program_studi_id' => $program->id, 'user_id' => $head->id]);
    $admin = User::factory()->asAdmin()->create();
    AdminProfile::query()->create(['user_id' => $admin->id, 'program_studi_id' => $program->id]);
    foreach ([$head, $admin] as $actor) {
        $this->actingAs($actor)->get(route('sidang.paper.preview', $defense))->assertOk();
        $this->get(route('sidang.paper.preview', $foreign))->assertForbidden();
    }
    $panel = $defense->examiners()->firstOrFail()->lecturer;
    $this->actingAs($panel)->get(route('sidang.paper.preview', $defense))->assertOk();
    $this->actingAs($student)->get(route('sidang.paper.preview', $defense))->assertForbidden();
    $this->actingAs(User::factory()->asDosen()->create())->get(route('sidang.paper.preview', $defense))->assertForbidden();
    $this->actingAs($admin);
    $defense->update(['status' => 'cancelled']);
    $this->get(route('sidang.paper.preview', $defense))->assertNotFound();
    $defense->update(['status' => 'scheduled', 'scheduled_for' => null]);
    $this->get(route('sidang.paper.preview', $defense))->assertNotFound();
});

it('uses final score snapshots for print and Word and leaves scheduled results unfilled', function (): void {
    [$defense, , $program, $scores] = sidangPaperFixture();
    $actor = User::factory()->asSuperAdmin()->create();
    $this->actingAs($actor);
    $presenter = app(SidangPaperPresenter::class);
    expect($presenter->fields($defense))->toMatchArray(['total' => '', 'examiner1Score' => '', 'pass' => 'LULUS', 'fail' => 'TIDAK LULUS']);
    app(SidangPaperService::class)->record($defense->fresh(), $actor, ['scores' => $scores]);
    app(ThesisProjectAdminService::class)->completeSidang($defense->project, $actor->id, 'pass_with_revision', 'Hasil sidang.', 'Perbaiki pembahasan.', '2026-12-01 12:00:00');
    $fields = $presenter->fields($defense->fresh());
    expect($fields)->toMatchArray(['total' => '80', 'examiner1Score' => '90', 'pass' => 'LULUS', 'fail' => '']);
    $program->update(['sidang_settings' => ['examiner_1' => 20, 'examiner_2' => 20, 'supervisor' => 60]]);
    expect($presenter->fields($defense->fresh()))->toMatchArray(['total' => '80', 'examiner1Weight' => '30 %']);
    $this->get(route('sidang.paper.preview', [$defense, 'lembar' => 'rekap-nilai']))->assertOk()->assertSee('<td>80</td>', false);
    $final = $defense->fresh()->getAttributes();
    $panel = $defense->examiners()->orderBy('id')->get()->toArray();
    $project = $defense->project->fresh()->getAttributes();
    expect(fn () => app(SidangPaperService::class)->record($defense, $actor, ['scores' => $scores]))
        ->toThrow(ValidationException::class, 'Sidang ini sudah difinalisasi');
    expect(fn () => app(ThesisProjectAdminService::class)->rescheduleSidang(
        $defense->project, $actor->id, '2026-12-02 10:00:00', null, 'Ruang Baru', 'offline', null,
    ))->toThrow(RuntimeException::class, 'Tidak ada');
    expect($defense->fresh()->getAttributes())->toBe($final)
        ->and($defense->examiners()->orderBy('id')->get()->toArray())->toBe($panel)
        ->and($defense->project->fresh()->getAttributes())->toBe($project)
        ->and($defense->project->sidangDefenses()->count())->toBe(1);
    $defense->update(['result' => 'fail']);
    expect($presenter->fields($defense->fresh()))->toMatchArray(['pass' => '', 'fail' => 'TIDAK LULUS']);
});

it('selects repository templates by program and reports missing templates without falling back silently', function (): void {
    [$defense, , $program] = sidangPaperFixture();
    $this->actingAs(User::factory()->asSuperAdmin()->create());
    expect(SidangDocumentTemplate::description($program))->toBe('Template standar - Format Fakultas Teknik');
    $template = 'test-'.Str::uuid().'.docx';
    $path = resource_path('templates/sidang/'.$template);
    try {
        copy(resource_path('templates/sidang/berkas-sidang.docx'), $path);
        $zip = new ZipArchive;
        $zip->open($path);
        $zip->addFromString('word/document.xml', str_replace('BERITA ACARA', 'BERITA ACARA KHUSUS PRODI', $zip->getFromName('word/document.xml')));
        $zip->close();
        view()->addNamespace('sidang-test', base_path('tests/fixtures/views'));
        config(['sidang-templates.programs' => [$program->slug => ['label' => 'Dokumen Prodi Uji', 'word' => $template, 'view' => 'sidang-test::sidang-custom']]]);
        expect(SidangDocumentTemplate::description($program))->toBe('Template khusus - Dokumen Prodi Uji');
        $word = $this->get(route('sidang.paper.download', $defense))->assertOk()->streamedContent();
        $download = tempnam(sys_get_temp_dir(), 'sita-custom-test');
        try {
            file_put_contents($download, $word);
            $zip->open($download);
            expect($zip->getFromName('word/document.xml'))->toContain('BERITA ACARA KHUSUS PRODI');
            $zip->close();
        } finally {
            unlink($download);
        }
        $this->get(route('sidang.paper.preview', $defense))->assertOk()->assertSee('Template khusus prodi uji');
        config(['sidang-templates.programs' => [$program->slug => null]]);
        expect(SidangDocumentTemplate::description($program))->toBe('Template belum tersedia');
        $this->get(route('sidang.paper.preview', $defense))->assertUnprocessable();
        $this->get(route('sidang.paper.download', $defense))->assertUnprocessable();
    } finally {
        unlink($path);
    }
});

it('prints customized rubric rows and letterhead fields in both document formats', function (): void {
    $program = ProgramStudi::factory()->create(['sidang_settings' => [
        'examiner_rubric' => "Analisis & Hasil | 60\nPenyampaian | 40",
        'letterhead_address' => 'Jalan Fakultas Uji', 'letterhead_contact' => '',
    ]]);
    [$defense] = sidangPaperFixture($program);
    $this->actingAs(User::factory()->asSuperAdmin()->create());
    $this->get(route('sidang.paper.preview', [$defense, 'lembar' => 'nilai-penguji']))->assertOk()
        ->assertSee('Analisis &amp; Hasil', false)->assertSee('60 %')->assertSee('Jalan Fakultas Uji');
    $word = $this->get(route('sidang.paper.download', [$defense, 'lembar' => 'nilai-penguji']))->assertOk()->streamedContent();
    $path = tempnam(sys_get_temp_dir(), 'sita-rubric-test');
    try {
        file_put_contents($path, $word);
        $zip = new ZipArchive;
        $zip->open($path);
        expect($zip->getFromName('word/document.xml'))->toContain('Analisis &amp; Hasil')->toContain('60 %');
        expect($zip->getFromName('word/header1.xml'))->toContain('Jalan Fakultas Uji');
        $zip->close();
    } finally {
        unlink($path);
    }
});

it('offers document shortcuts only when a template is available, independently of score entry', function (): void {
    [$defense, , $program, $scores] = sidangPaperFixture();
    $lecturer = $defense->examiners()->findOrFail($scores['examiner_1']['panel_id'])->lecturer;

    $this->actingAs($lecturer)->get(route('dosen.seminar-proposal'))->assertInertia(fn (Assert $page) => $page
        ->where('defenses.0.paperUrl', route('sidang.paper.download', $defense))
        ->where('defenses.0.paperPrintUrl', route('sidang.paper.preview', $defense)));

    config(['sidang-templates.programs' => [$program->slug => null]]);

    $this->get(route('dosen.seminar-proposal'))->assertInertia(fn (Assert $page) => $page
        ->where('defenses.0.paperUrl', null)
        ->where('defenses.0.paperPrintUrl', null));

    $admin = User::factory()->asAdmin()->create();
    AdminProfile::factory()->create(['user_id' => $admin->id, 'program_studi_id' => $program->id]);
    $this->actingAs($admin);

    Livewire::test(ListThesisProjects::class)
        ->set('activeTab', ListThesisProjects::SIDANG_PAPER_TAB)
        ->assertTableActionHidden('cetak_berkas_sidang', $defense->project)
        ->assertTableActionHidden('unduh_berkas_sidang', $defense->project)
        ->assertTableActionVisible('tindakan_berkas_sidang', $defense->project)
        ->assertTableActionHasLabel('tindakan_berkas_sidang', 'Input Nilai', $defense->project);
});

it('prints degree terminology and both dates for an overnight WITA schedule', function (): void {
    [$defense, $student] = sidangPaperFixture();
    $student->mahasiswaProfile->update(['degree_level' => 'd3']);
    $defense->update(['scheduled_for' => '2026-11-05 23:30:00', 'scheduled_until' => '2026-11-06 01:00:00']);
    $this->actingAs(User::factory()->asSuperAdmin()->create());
    $this->get(route('sidang.paper.preview', [$defense, 'lembar' => 'berita-acara']))
        ->assertOk()->assertSee('UJIAN SEMINAR TUGAS AKHIR')
        ->assertSee('Kamis, 05 November 2026 / 23.30 - Jumat, 06 November 2026 / 01.00 WITA');
});

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

it('cannot save a legacy examiner minimum through program settings', function (int $minimum): void {
    $program = ProgramStudi::factory()->create(['sidang_settings' => ['minimum_paper_examiners' => 0]]);
    $this->actingAs(User::factory()->asSuperAdmin()->create());

    Livewire::test(EditProgramStudi::class, ['record' => $program->getRouteKey()])
        ->mountAction('sidangSettings')
        ->assertActionDataSet(['sidang_settings.minimum_paper_examiners' => 2])
        ->setActionData(['sidang_settings' => [...SidangSettings::forProgram($program), 'minimum_paper_examiners' => $minimum]])
        ->callMountedAction()
        ->assertHasActionErrors(['sidang_settings.minimum_paper_examiners' => 'min'])
        ->assertActionMounted('sidangSettings');
    expect($program->fresh()->sidang_settings)->toBe(['minimum_paper_examiners' => 0])
        ->and(SidangSettings::minimumPaperExaminers($program->fresh()))->toBe(2);
})->with([0, 1]);

it('saves the baseline when the optional examiner minimum is cleared', function (): void {
    $program = ProgramStudi::factory()->create();
    $this->actingAs(User::factory()->asSuperAdmin()->create());

    Livewire::test(EditProgramStudi::class, ['record' => $program->getRouteKey()])
        ->mountAction('sidangSettings')
        ->setActionData(['sidang_settings' => [...SidangSettings::forProgram($program), 'minimum_paper_examiners' => null]])
        ->callMountedAction()
        ->assertHasNoActionErrors();
    expect($program->fresh()->sidang_settings['minimum_paper_examiners'])->toBe(2);
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
    expect($ilkom->fresh()->sidang_settings)->toBe(['city' => 'Kota Uji', 'minimum_paper_examiners' => 0])
        ->and(SidangSettings::minimumPaperExaminers($ilkom->fresh()))->toBe(2)
        ->and(SidangSettings::minimumPaperExaminers($other->fresh()))->toBe(2);
});

it('seeds the initial Ilkom requirement and preserves a later admin choice', function (): void {
    $this->seed(AcademicStructureSeeder::class);
    $program = ProgramStudi::query()->where('slug', 'ilkom')->firstOrFail();
    expect(SidangSettings::minimumPaperExaminers($program))->toBe(2);
    $program->update(['sidang_settings' => ['minimum_paper_examiners' => 0]]);

    $this->seed(AcademicStructureSeeder::class);
    expect($program->fresh()->sidang_settings)->toBe(['minimum_paper_examiners' => 0])
        ->and(SidangSettings::minimumPaperExaminers($program->fresh()))->toBe(2);
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

it('completes unfinished legacy panels without losing scores or removing assessed staff', function (int $savedMinimum): void {
    [$defense] = sidangPaperFixture(ProgramStudi::factory()->create(['slug' => 'manajemen', 'sidang_settings' => ['minimum_paper_examiners' => $savedMinimum]]));
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
    $replacement = User::factory()->asDosen()->create();
    DosenProfile::factory()->create(['user_id' => $replacement->id, 'program_studi_id' => $project->program_studi_id, 'is_active' => true]);
    expect(fn () => $schedule([$supervisor->lecturer_user_id, $second->lecturer_user_id, $replacement->id]))
        ->toThrow(RuntimeException::class, 'Pertahankan dosen yang penilaiannya sudah tercatat');
    expect($first->fresh()->score)->toEqual(90)
        ->and($defense->fresh()->scheduled_for->toDateTimeString())->toBe('2026-11-04 23:30:00');

    $updated = $schedule([$supervisor->lecturer_user_id, $first->lecturer_user_id, $second->lecturer_user_id]);
    expect($updated->id)->toBe($defense->id)
        ->and($updated->examiners()->orderBy('order_no')->pluck('role')->all())->toBe(['primary_supervisor', 'examiner', 'examiner'])
        ->and($first->fresh()->score)->toEqual(90);
})->with([0, 1, 2]);

it('rejects time-only edits of an unfinished legacy panel until it is completed', function (): void {
    [$defense] = sidangPaperFixture(ProgramStudi::factory()->create(['slug' => 'manajemen', 'sidang_settings' => ['minimum_paper_examiners' => 0]]));
    $admin = User::factory()->asSuperAdmin()->create();
    $supervisor = $defense->examiners()->where('role', 'primary_supervisor')->firstOrFail();
    $defense->project->supervisorAssignments()->create([
        'lecturer_user_id' => $supervisor->lecturer_user_id,
        'role' => 'primary', 'status' => 'active', 'assigned_by' => $admin->id, 'started_at' => now(),
    ]);
    $defense->examiners()->where('role', 'examiner')->orderByDesc('order_no')->firstOrFail()->delete();
    $defense->examiners()->where('role', 'examiner')->update(['score' => 90]);
    $before = $defense->fresh()->getAttributes();
    $panel = $defense->examiners()->orderBy('id')->get()->toArray();

    expect(fn () => app(ThesisProjectAdminService::class)->rescheduleSidang(
        $defense->project, $admin->id, '2026-11-06 10:00:00', null, 'Ruang Baru', 'offline', null,
    ))->toThrow(RuntimeException::class, 'Pilih minimal 2 dosen penguji selain pembimbing');
    expect($defense->fresh()->getAttributes())->toBe($before)
        ->and($defense->examiners()->orderBy('id')->get()->toArray())->toBe($panel);
});

it('retains a stricter program examiner minimum', function (): void {
    [$defense] = sidangPaperFixture(ProgramStudi::factory()->create(['slug' => 'manajemen', 'sidang_settings' => ['minimum_paper_examiners' => 3]]));
    $admin = User::factory()->asSuperAdmin()->create();
    $supervisor = $defense->examiners()->where('role', 'primary_supervisor')->firstOrFail();
    $defense->project->supervisorAssignments()->create([
        'lecturer_user_id' => $supervisor->lecturer_user_id,
        'role' => 'primary', 'status' => 'active', 'assigned_by' => $admin->id, 'started_at' => now(),
    ]);
    $ids = $defense->examiners()->pluck('lecturer_user_id')->all();
    $schedule = fn (array $panelIds) => app(ThesisProjectAdminService::class)->scheduleSidang(
        $defense->project, $admin->id, '2026-11-06 10:00:00', 'Ruang Sidang', 'offline', $panelIds,
    );
    expect(fn () => $schedule($ids))->toThrow(RuntimeException::class, 'Pilih minimal 3 dosen penguji selain pembimbing');
    $third = User::factory()->asDosen()->create();
    DosenProfile::factory()->create(['user_id' => $third->id, 'program_studi_id' => $defense->project->program_studi_id, 'is_active' => true]);
    expect($schedule([...$ids, $third->id])->examiners()->where('role', 'examiner')->count())->toBe(3);
});

it('enforces the non Ilkom baseline at Kaprodi and Filament scheduling entry points', function (array $settings, bool $alreadyScheduled): void {
    [$defense, , $program] = sidangPaperFixture(ProgramStudi::factory()->create(['slug' => 'manajemen', 'sidang_settings' => $settings]));
    $project = $defense->project;
    $admin = User::factory()->asSuperAdmin()->create();
    $head = User::factory()->asKaprodi()->create();
    KaprodiAssignment::factory()->create(['user_id' => $head->id, 'program_studi_id' => $program->id]);
    $examiners = $defense->examiners()->where('role', 'examiner')->orderBy('order_no')->get();
    $supervisor = $defense->examiners()->where('role', 'primary_supervisor')->firstOrFail();
    $project->supervisorAssignments()->create([
        'lecturer_user_id' => $supervisor->lecturer_user_id,
        'role' => 'primary', 'status' => 'active', 'assigned_by' => $admin->id, 'started_at' => now(),
    ]);
    if (! $alreadyScheduled) {
        $defense->delete();
    }
    $before = $project->sidangDefenses()->with('examiners')->get()->toArray();
    $data = [
        'scheduled_for' => '2026-11-06 10:00:00', 'location' => 'Ruang Sidang Uji', 'mode' => 'offline',
        'additional_examiner_user_ids' => [$examiners->first()->lecturer_user_id],
    ];
    $actionData = [
        'scheduled_starts_on' => '2026-11-06', 'scheduled_at_time' => '10:00',
        'location' => $data['location'], 'mode' => $data['mode'],
        'additional_examiner_user_ids' => $data['additional_examiner_user_ids'],
    ];

    $this->actingAs($head)->post(route('kaprodi.projects.sidang', $project), $data)
        ->assertSessionHasErrors(['additional_examiner_user_ids' => 'Pilih minimal 2 dosen penguji selain pembimbing.']);
    $this->actingAs($admin);
    Livewire::test(ViewThesisProject::class, ['record' => $project->getRouteKey()])
        ->mountAction('schedule_sidang')
        ->setActionData($actionData)
        ->callMountedAction()
        ->assertHasActionErrors(['additional_examiner_user_ids' => 'min']);
    expect($project->sidangDefenses()->with('examiners')->get()->toArray())->toBe($before);

    $data['additional_examiner_user_ids'] = $examiners->pluck('lecturer_user_id')->all();
    $this->actingAs($head)->post(route('kaprodi.projects.sidang', $project), $data)->assertSessionHasNoErrors();
    $actionData['additional_examiner_user_ids'] = $data['additional_examiner_user_ids'];
    $this->actingAs($admin);
    Livewire::test(ViewThesisProject::class, ['record' => $project->getRouteKey()])
        ->mountAction('schedule_sidang')
        ->setActionData($actionData)
        ->callMountedAction()
        ->assertHasNoActionErrors();
    $updated = $project->sidangDefenses()->firstOrFail();
    expect($updated->examiners()->where('role', 'examiner')->count())->toBe(2)
        ->and($updated->scheduled_for->toDateTimeString())->toBe('2026-11-06 10:00:00')
        ->and($project->sidangDefenses()->count())->toBe(1);
})->with([
    'default' => [[]],
    'saved zero' => [['minimum_paper_examiners' => 0]],
    'saved one' => [['minimum_paper_examiners' => 1]],
])->with(['new schedule' => false, 'edited schedule' => true]);

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
        ->assertTableActionHasUrl('unduh_berkas_sidang', route('sidang.paper.download', $scheduled), $scheduled->project)
        ->assertTableActionHasUrl('cetak_berkas_sidang', route('sidang.paper.preview', $scheduled), $scheduled->project)
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

it('offers print and digital finalization without a paper score shortcut for a legacy panel', function (): void {
    [$defense, , $program, $scores] = sidangPaperFixture();
    $defense->examiners()->findOrFail($scores['examiner_2']['panel_id'])->update(['role' => 'secondary_supervisor']);
    $admin = User::factory()->asAdmin()->create();
    AdminProfile::query()->create(['user_id' => $admin->id, 'program_studi_id' => $program->id]);
    $this->actingAs($admin);

    $url = ThesisProjectResource::getUrl('sidang-paper', [
        'record' => $defense->project, 'defense' => $defense, 'dari' => 'daftar',
    ]);
    Livewire::test(ListThesisProjects::class)->set('activeTab', ListThesisProjects::SIDANG_PAPER_TAB)
        ->assertTableActionHasLabel('tindakan_berkas_sidang', 'Lihat Berkas', $defense->project)
        ->assertTableActionHasUrl('tindakan_berkas_sidang', $url, $defense->project)
        ->assertTableActionHasUrl('unduh_berkas_sidang', route('sidang.paper.download', $defense), $defense->project);
    $this->get($url)->assertOk()->assertDontSee('Nilai penguji 2');

    $defense->update(['status' => 'awaiting_finalization']);
    $this->get($url)->assertOk()->assertSee('Finalisasi Sidang')->assertDontSee('Nilai penguji 2');
});

it('refreshes period suggestions until staff override each field', function (): void {
    $admin = User::factory()->asAdmin()->create();
    AdminProfile::query()->create(['user_id' => $admin->id, 'program_studi_id' => ProgramStudi::factory()->create()->id]);
    $this->actingAs($admin);

    Livewire::test(ListThesisProjects::class)
        ->set('activeTab', ListThesisProjects::SIDANG_PAPER_TAB)
        ->mountAction('manageSidangReportPeriod')
        ->set('mountedActions.0.data.starts_on', '2026-09-01')
        ->assertActionDataSet(['semester' => 'ganjil', 'start_year' => 2026])
        ->set('mountedActions.0.data.starts_on', '2028-03-01')
        ->assertActionDataSet(['semester' => 'genap', 'start_year' => 2027])
        ->set('mountedActions.0.data.semester', 'ganjil')
        ->set('mountedActions.0.data.start_year', 2029)
        ->set('mountedActions.0.data.starts_on', '2028-04-01')
        ->assertActionDataSet(['semester' => 'ganjil', 'start_year' => 2029]);
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

function sidangExcelContent(TestResponse $response): string
{
    $path = $response->baseResponse->getFile()->getPathname();
    ob_start();
    try {
        $response->baseResponse->sendContent();

        return ob_get_contents();
    } finally {
        ob_end_clean();
        expect($path)->not->toBeFile();
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
    $this->postJson(route('sidang.report'), $params)->assertUnprocessable()
        ->assertJsonPath('message', SidangReportWriter::EMPTY_MESSAGE);

    $program->update(['sidang_settings' => ['a' => 76]]);
    app(ThesisProjectAdminService::class)->completeSidang(
        $defense->project, $admin->id, 'pass_with_revision', 'Keputusan resmi.', 'Perbaiki daftar pustaka.', '2026-11-12',
    );
    expect($defense->fresh()->sidang_paper['grade'])->toBe('A');
    $word = $this->get(route('sidang.paper.print', [$defense, 'lembar' => 'berita-acara']))->streamedContent();
    expect(sidangReportSheet($word, 'word/document.xml'))->toContain('>LULUS<')->not->toContain('TIDAK LULUS');
    $sheet = sidangReportSheet(sidangExcelContent($this->post(route('sidang.report'), $params)->assertDownload('rekap-nilai-sidang.xlsx')));
    expect($sheet)->toContain($student->name, 'Sistem Rekap Penilaian', 'A12:A14', 'B+', 'Semester Uji', '<f>ROUND(L12*30%+L13*30%+L14*40%,4)</f>', '<f>ROUND(M12,0)</f>');
    $formulaLikePeriod = array_replace($params, ['period' => '=2+2']);
    expect(sidangReportSheet(sidangExcelContent($this->post(route('sidang.report'), $formulaLikePeriod)->assertOk())))
        ->toContain('>=2+2</t>')->not->toContain('<f>2+2</f>');

    $this->actingAs(User::factory()->asAdmin()->create())->get(route('sidang.paper.show', $defense))->assertForbidden();
    $this->actingAs($admin);
    $this->postJson(route('sidang.report'), ['from' => '2026-11-05', 'until' => '2026-11-05'])->assertUnprocessable();
    $defense->project->sidangDefenses()->create(['type' => 'sidang', 'attempt_no' => 2, 'status' => 'scheduled']);
    $this->postJson(route('sidang.report'), $params)->assertUnprocessable();
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
    $sheet = sidangReportSheet(sidangExcelContent($this->actingAs($admin)->post(route('sidang.report'))->assertOk()));
    expect($sheet)->toContain($student->name, '<f>ROUND(L12*30%+L13*30%+L14*40%,4)</f>');
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
    $excel = sidangExcelContent($this->post(route('sidang.report'))->assertOk());
    expect(sidangReportSheet($excel))->toContain('79.55', '88.5');
});

it('continues the reference row pattern past nine defenses and keeps each program on its own sheet', function () {
    $admin = User::factory()->asSuperAdmin()->create();
    [$first, , , $scores] = sidangPaperFixture();
    [$second, $student, , $otherScores] = sidangPaperFixture();
    app(SidangPaperService::class)->record($first, $admin, ['scores' => $scores]);
    app(SidangPaperService::class)->record($second, $admin, ['scores' => $otherScores]);
    $first->update(['sidang_sk' => '001/ADUM']);
    $first->examiners()->first()->lecturer->dosenProfile->update(['sidang_nidn' => '00001234']);
    $extra = $first->fresh();
    $extra->sidang_sk = '002/ADUM';

    $path = app(SidangReportWriter::class)->prepare([...array_fill(0, 9, $first->fresh()), $extra, ...array_fill(0, 11, $second->fresh())], 'Uji');
    try {
        $content = file_get_contents($path);
    } finally {
        unlink($path);
    }

    $template = file_get_contents(resource_path('templates/sidang/rekap-nilai-sidang.xlsx'));
    expect(sidangReportSheet($content, 'xl/styles.xml'))->toBe(sidangReportSheet($template, 'xl/styles.xml'));
    expect(sidangReportSheet($content, 'xl/workbook.xml'))->toContain('$A$7:$O$52', '$A$7:$O$55');
    expect(sidangReportSheet($content))->toContain('E12:E38', 'E39:E41', '002/ADUM', '>00001234</t>')->not->toContain($student->name);
    expect(sidangReportSheet($content, 'xl/worksheets/rekap2.xml'))->toContain($student->name)->not->toContain($first->project->student->name);

    foreach (['sheet7' => 39, 'rekap2' => 42] as $sheet => $lastDefenseRow) {
        $xml = new DOMDocument;
        expect($xml->loadXML(sidangReportSheet($content, "xl/worksheets/{$sheet}.xml")))->toBeTrue();
        $xpath = new DOMXPath($xml);
        $xpath->registerNamespace('m', $xml->documentElement->namespaceURI);
        $rowNumbers = array_map(fn (DOMElement $row): int => (int) $row->getAttribute('r'), iterator_to_array($xpath->query('//m:sheetData/m:row')));
        $orderedRows = array_values(array_unique($rowNumbers));
        sort($orderedRows);
        $merges = $xml->getElementsByTagName('mergeCells')->item(0);
        $mergesByRange = array_map(fn (DOMElement $merge): string => $merge->getAttribute('ref'), iterator_to_array($merges->getElementsByTagName('mergeCell')));
        expect($rowNumbers)->toBe($orderedRows)
            ->and((int) $merges->getAttribute('count'))->toBe(count(array_unique($mergesByRange)))
            ->and($mergesByRange)->toContain('A'.$lastDefenseRow.':A'.($lastDefenseRow + 2), 'A'.($lastDefenseRow + 5).':B'.($lastDefenseRow + 5))
            ->and($xpath->evaluate("string(//m:c[@r='M{$lastDefenseRow}']/m:f)"))->toBe(sprintf('ROUND(L%d*30%%+L%d*30%%+L%d*40%%,4)', $lastDefenseRow, $lastDefenseRow + 1, $lastDefenseRow + 2))
            ->and($xpath->evaluate("string(//m:c[@r='N{$lastDefenseRow}']/m:f)"))->toBe("ROUND(M{$lastDefenseRow},0)")
            ->and($xpath->evaluate("string(//m:c[@r='N{$lastDefenseRow}']/m:v)"))->toBe('80')
            ->and($xpath->evaluate("string(//m:c[@r='M{$lastDefenseRow}']/@s)"))->toBe($xpath->evaluate("string(//m:c[@r='M36']/@s)"))
            ->and($xpath->evaluate('string(//m:pageSetup/@orientation)'))->toBe('landscape')
            ->and(sidangReportSheet($content, "xl/worksheets/_rels/{$sheet}.xml.rels"))
            ->toBe(sidangReportSheet($template, 'xl/worksheets/_rels/sheet7.xml.rels'));
    }
});

it('cleans up the archive and worksheet spools when an Excel export fails', function (): void {
    $writer = app(SidangReportWriter::class);
    $before = glob(sys_get_temp_dir().'/sita-office-*');
    expect(fn () => $writer->prepare([], 'Uji'))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class, SidangReportWriter::EMPTY_MESSAGE);
    expect(array_diff(glob(sys_get_temp_dir().'/sita-office-*'), $before))->toBe([]);

    [$defense, , , $scores] = sidangPaperFixture();
    app(SidangPaperService::class)->record($defense, User::factory()->asSuperAdmin()->create(), ['scores' => $scores]);
    $attempt = $defense->fresh(['project.student.mahasiswaProfile', 'project.programStudi', 'examiners.lecturer.dosenProfile', 'titleVersion']);
    $streams = get_resources('stream');
    $spools = [];
    $rows = function () use ($attempt, $streams, &$spools): Generator {
        yield from array_fill(0, 10, $attempt);
        foreach (array_diff_key(get_resources('stream'), $streams) as $stream) {
            $path = stream_get_meta_data($stream)['uri'];
            if (is_file($path)) {
                $spools[] = $path;
            }
        }
        throw new RuntimeException('Simulasi gagal setelah menulis baris tambahan.');
    };

    expect(fn () => $writer->prepare($rows(), 'Uji'))->toThrow(RuntimeException::class, 'Simulasi gagal setelah menulis baris tambahan.');
    expect($spools)->toHaveCount(2)
        ->and(array_filter($spools, 'is_file'))->toBe([])
        ->and(array_diff(glob(sys_get_temp_dir().'/sita-office-*'), $before))->toBe([]);
});

it('intersects the admin selection token with the requested attempts and rejects another user token', function (): void {
    [$first, $student, $program, $firstScores] = sidangPaperFixture();
    [$second, $otherStudent, , $secondScores] = sidangPaperFixture($program);
    $admin = User::factory()->asSuperAdmin()->create();
    foreach ([[$first, $firstScores], [$second, $secondScores]] as [$attempt, $scores]) {
        app(SidangPaperService::class)->record($attempt, $admin, ['scores' => $scores]);
        $attempt->update(['status' => 'completed', 'result' => 'pass']);
    }
    $token = (string) Str::uuid();
    $selected = $first->project()->getQuery()->select('thesis_projects.id')->toBase();
    Cache::store('database')->put('sidang-export:'.$token, [
        'user' => $admin->id, 'sql' => $selected->toSql(), 'bindings' => $selected->getBindings(),
    ], 60);

    $this->actingAs($admin);
    $sheet = sidangReportSheet(sidangExcelContent($this->get(route('sidang.report', ['selection' => $token]))->assertOk()));
    expect($sheet)->toContain($student->name)->not->toContain($otherStudent->name);
    $this->postJson(route('sidang.report'), ['selection' => $token, 'ids' => (string) $second->id])
        ->assertUnprocessable()->assertJsonPath('message', SidangReportWriter::EMPTY_MESSAGE);
    $this->actingAs(User::factory()->asSuperAdmin()->create())->getJson(route('sidang.report', ['selection' => $token]))
        ->assertForbidden();
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
        ->assertActionVisible('manageSidangReportPeriod')
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
    $sheet = sidangReportSheet(sidangExcelContent($this->post(route('sidang.report'), [
        'period_id' => $period->id, 'from' => '2096-11-10', 'until' => '2096-11-01', 'period' => 'Tidak dipakai',
    ])
        ->assertDownload('rekap-nilai-sidang.xlsx')));
    expect($sheet)->toContain($student->name, 'SEMESTER GENAP 2026/2027')->not->toContain($otherStudent->name);
    $this->postJson(route('sidang.report'), ['period_id' => null, 'from' => '2026-11-06', 'until' => '2026-11-04'])
        ->assertUnprocessable()->assertJsonValidationErrors('until');

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

    $sheet = sidangReportSheet(sidangExcelContent($this->post(route('sidang.report'))->assertOk()));
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

    $sheet = sidangReportSheet(sidangExcelContent($this->post(route('sidang.report'))->assertOk()));
    expect($sheet)->toContain($ownStudent->name)->not->toContain($foreignStudent->name);

    $outsider = User::factory()->asDosen()->create();
    DosenProfile::factory()->create(['user_id' => $outsider->id, 'program_studi_id' => $ownProgram->id, 'is_active' => true]);
    $this->actingAs($outsider)->get(route('sidang.paper.print', $own))->assertForbidden();
    $this->post(route('sidang.report'))->assertForbidden();
});

it('corrects a single final No SK from Admin and Kaprodi and exports the latest number without changing the final result', function (): void {
    [$defense, $student, $program, $scores] = sidangPaperFixture();
    $admin = User::factory()->asAdmin()->create();
    AdminProfile::factory()->create(['user_id' => $admin->id, 'program_studi_id' => $program->id]);
    $kaprodi = User::factory()->asKaprodi()->create();
    KaprodiAssignment::factory()->create(['user_id' => $kaprodi->id, 'program_studi_id' => $program->id]);
    $service = app(SidangPaperService::class);
    $service->record($defense, $admin, ['sidang_sk' => '001/ADUM', 'scores' => $scores]);
    app(ThesisProjectAdminService::class)->completeSidang($defense->project, $admin->id, 'pass', 'Keputusan resmi.');
    $defense->refresh();
    $snapshot = $defense->sidang_paper;
    $panel = $defense->examiners()->orderBy('id')->get()->toArray();

    $this->actingAs($admin);
    Livewire::test(ManageSidangPaper::class, ['record' => $defense->project_id, 'defense' => $defense->id])
        ->mountAction('correctSk')->assertActionMounted('correctSk')
        ->setActionData(['sidang_sk' => '002/ADUM'])
        ->callMountedAction()->assertHasNoActionErrors()
        ->assertSee('Riwayat koreksi No SK')->assertSee('002/ADUM');

    $filters = ['search' => $student->name, 'status' => 'completed'];
    $url = route('sidang.paper.correct-sk', ['defense' => $defense, ...$filters]);
    $version = $service->skVersion($defense->fresh());
    $this->actingAs($kaprodi->fresh())->patch($url, ['sidang_sk' => ' 003/ADUM ', 'expected_version' => $version])
        ->assertSessionHasNoErrors()->assertRedirect(route('sidang.paper.show', ['defense' => $defense, ...$filters]));
    $this->get(route('sidang.paper.show', $defense))->assertInertia(fn (Assert $page) => $page
        ->where('defense.sk', '003/ADUM')
        ->where('defense.editable', false)
        ->has('defense.skHistory', 2)
        ->where('defense.skHistory.0.oldNumber', '002/ADUM')
        ->where('defense.skHistory.0.newNumber', '003/ADUM')
        ->where('defense.skHistory.0.actor', $kaprodi->name)
        ->where('defense.skHistory.1.actor', $admin->name));
    $events = $defense->project->events()->where('event_type', SidangPaperService::SK_CORRECTED_EVENT)->orderBy('id')->get();
    expect($events->pluck('actor_user_id')->all())->toBe([$admin->id, $kaprodi->id])
        ->and($events->first()->occurred_at)->not->toBeNull()
        ->and($events->first()->payload)->toMatchArray(['defense_id' => $defense->id, 'old_number' => '001/ADUM', 'new_number' => '002/ADUM'])
        ->and($defense->fresh()->sidang_paper)->toBe($snapshot)
        ->and($defense->fresh()->status)->toBe('completed')
        ->and($defense->fresh()->result)->toBe('pass')
        ->and($defense->examiners()->orderBy('id')->get()->toArray())->toBe($panel);
    $sheet = sidangReportSheet(sidangExcelContent($this->post(route('sidang.report'))->assertOk()));
    expect($sheet)->toContain('003/ADUM')->not->toContain('001/ADUM', '002/ADUM');
    expect(fn () => $service->assignSk($admin, collect([$defense->project]), '004/MASSAL'))
        ->toThrow(ValidationException::class, 'Ada mahasiswa dengan No SK berbeda');
});

it('rejects stale No SK edits, validates the input and does not log an unchanged number', function (): void {
    [$defense] = sidangPaperFixture();
    $defense->update(['status' => 'completed', 'sidang_sk' => 'AWAL']);
    $admin = User::factory()->asSuperAdmin()->create();
    $this->actingAs($admin);
    $url = route('sidang.paper.correct-sk', $defense);
    $service = app(SidangPaperService::class);
    $oldVersion = $service->skVersion($defense);
    $page = Livewire::test(ManageSidangPaper::class, ['record' => $defense->project_id, 'defense' => $defense->id])
        ->mountAction('correctSk')->setActionData(['sidang_sk' => 'DRAF']);
    expect($service->correctSk($defense, $admin, 'TERBARU', $oldVersion))->toBeTrue();
    $page->callMountedAction()->assertHasActionErrors(['sidang_sk'])->assertActionMounted('correctSk')
        ->assertActionDataSet(['sidang_sk' => 'DRAF']);

    $this->patchJson($url, ['sidang_sk' => 'DRAF', 'expected_version' => $oldVersion])
        ->assertUnprocessable()->assertJsonValidationErrors('sidang_sk');
    $version = $service->skVersion($defense->fresh());
    foreach (['   ', str_repeat('X', 151)] as $invalid) {
        $this->patchJson($url, ['sidang_sk' => $invalid, 'expected_version' => $version])
            ->assertUnprocessable()->assertJsonValidationErrors('sidang_sk');
    }
    $this->patch($url, ['sidang_sk' => 'TERBARU', 'expected_version' => $version])->assertSessionHasNoErrors();
    expect($defense->fresh()->sidang_sk)->toBe('TERBARU')
        ->and($service->skHistory($defense))->toHaveCount(1);
});

it('restricts No SK correction to authorized managers of the latest finalized attempt', function (): void {
    [$defense, $student, , $scores] = sidangPaperFixture();
    $otherProgram = ProgramStudi::factory()->create();
    $foreignHead = User::factory()->asKaprodi()->create();
    KaprodiAssignment::factory()->create(['user_id' => $foreignHead->id, 'program_studi_id' => $otherProgram->id]);
    $foreignAdmin = User::factory()->asAdmin()->create();
    AdminProfile::factory()->create(['user_id' => $foreignAdmin->id, 'program_studi_id' => $otherProgram->id]);
    $lecturer = $defense->examiners()->findOrFail($scores['examiner_1']['panel_id'])->lecturer;
    $data = ['sidang_sk' => 'KOREKSI', 'expected_version' => app(SidangPaperService::class)->skVersion($defense)];
    $url = route('sidang.paper.correct-sk', $defense);

    $defense->update(['status' => 'completed']);
    foreach ([$foreignHead->fresh(), $foreignAdmin, $lecturer, $student] as $actor) {
        $this->actingAs($actor)->patchJson($url, $data)->assertForbidden();
    }
    $this->actingAs(User::factory()->asSuperAdmin()->create());
    foreach (['scheduled', 'awaiting_finalization', 'cancelled'] as $status) {
        $defense->update(['status' => $status]);
        $this->patchJson($url, $data)->assertUnprocessable()->assertJsonValidationErrors('sidang_sk');
    }
    $defense->update(['status' => 'completed']);
    $defense->project->sidangDefenses()->create([
        'type' => 'sidang', 'attempt_no' => 2, 'status' => 'scheduled', 'scheduled_for' => now(),
    ]);
    $this->patchJson($url, $data)->assertUnprocessable()->assertJsonValidationErrors('sidang_sk');
    expect($defense->fresh()->sidang_sk)->toBeNull()
        ->and(app(SidangPaperService::class)->skHistory($defense))->toBe([]);
});
