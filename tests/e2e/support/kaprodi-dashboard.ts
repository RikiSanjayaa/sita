import { execFileSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';

import { expect, type Page } from '@playwright/test';

import { playwrightEnvironment, repositoryRoot } from './db';

/**
 * Isolated kaprodi dashboard fixtures. Each one gets its own faculty, program
 * studi, kaprodi, lecturer and students, so counts never depend on the shared
 * seeded scenarios earlier specs change, and no seeded identity is touched.
 * Setup is transactional; cleanup removes only what this fixture created.
 *
 * - populated: every queue item, an agenda and all capabilities.
 * - all-clear: an empty program studi.
 * - restricted: the populated data, but the kaprodi may only view documents.
 */
export type KaprodiDashboardScenario = 'populated' | 'all-clear' | 'restricted';

export type KaprodiDashboardFixture = {
    scenario: KaprodiDashboardScenario;
    email: string;
    password: string;
    programName: string;
    cleanup: () => void;
};

export type QueueExpectation = {
    id: string;
    label: string;
    count: number;
    actionLabel: string;
    href: string;
    /** Capability that must be granted for the item to show. */
    capability?: 'supervisors' | 'sempro' | 'sidang' | 'quota';
    /** Rows the destination lists, or null when it only needs to open. */
    destinationRows?: string[];
};

export const dashboardNames = {
    awaitingA: 'Aldo Finalisasi Dashboard',
    awaitingB: 'Bella Finalisasi Dashboard',
    lapsedA: 'Citra Masa Habis Dashboard',
    lapsedB: 'Dimas Masa Habis Dashboard',
    noSupervisor: 'Elok Tanpa Pembimbing Dashboard',
    readySempro: 'Farhan Siap Sempro Dashboard',
    readySidang: 'Gita Siap Sidang Dashboard',
    inactive: 'Hadi Tidak Aktif Dashboard',
    examToday: 'Indra Ujian Hari Ini Dashboard',
    examLater: 'Jihan Ujian Mendatang Dashboard',
    deactivated: 'Kirana Nonaktif Dashboard',
    archived: 'Lintang Arsip Dashboard',
    lecturer: 'Dosen Kuota Dashboard',
} as const;

/** Queue order of the populated fixture with every capability. */
export const populatedQueue: QueueExpectation[] = [
    {
        id: 'awaiting-finalization',
        label: 'Ujian menunggu finalisasi',
        count: 2,
        actionLabel: 'Buka ujian',
        href: '/kaprodi/sempro-sidang?status=awaiting_finalization',
    },
    {
        id: 'mentorship-lapsed',
        label: 'Masa aktif bimbingan habis',
        count: 2,
        actionLabel: 'Lihat mahasiswa',
        href: '/kaprodi/mahasiswa?activeFocus=masa_habis',
        destinationRows: [dashboardNames.lapsedA, dashboardNames.lapsedB],
    },
    {
        id: 'without-supervisor',
        label: 'Pembimbing belum ditetapkan',
        count: 1,
        actionLabel: 'Tetapkan pembimbing',
        href: '/kaprodi/mahasiswa?activeFocus=tanpa_pembimbing',
        capability: 'supervisors',
        destinationRows: [dashboardNames.noSupervisor],
    },
    {
        id: 'schedule-sidang',
        label: 'Siap dijadwalkan',
        count: 1,
        actionLabel: 'Jadwalkan',
        href: '/kaprodi/sempro-sidang?jadwalkan=sidang',
        capability: 'sidang',
    },
    {
        id: 'schedule-sempro',
        label: 'Siap dijadwalkan',
        count: 1,
        actionLabel: 'Jadwalkan',
        href: '/kaprodi/sempro-sidang?jadwalkan=sempro',
        capability: 'sempro',
    },
    {
        id: 'inactive',
        label: 'Belum ada aktivitas 30 hari terakhir',
        count: 1,
        actionLabel: 'Lihat mahasiswa',
        href: '/kaprodi/mahasiswa?activeFocus=tidak_aktif',
        destinationRows: [dashboardNames.inactive],
    },
    {
        id: 'mentorship-minimum',
        label: 'Minimal bimbingan belum terpenuhi',
        count: 1,
        actionLabel: 'Lihat mahasiswa',
        href: '/kaprodi/mahasiswa?activeFocus=bimbingan_kurang',
        destinationRows: [dashboardNames.readySidang],
    },
    {
        id: 'over-quota',
        label: 'Dosen melebihi kuota bimbingan',
        count: 1,
        actionLabel: 'Atur kuota',
        href: '/kaprodi/dosen-prodi',
        capability: 'quota',
    },
];

export type SummaryExpectation = {
    label: string;
    populatedCount: number;
    href: string;
};

export const summaryTiles: SummaryExpectation[] = [
    {
        label: 'Mahasiswa prodi aktif',
        populatedCount: 10,
        href: '/kaprodi/mahasiswa',
    },
    {
        label: 'Tanpa pembimbing',
        populatedCount: 1,
        href: '/kaprodi/mahasiswa?activeFocus=tanpa_pembimbing',
    },
    {
        label: 'Ujian 7 hari ke depan',
        populatedCount: 2,
        href: '/kaprodi/sempro-sidang',
    },
    {
        label: 'Masa aktif bimbingan habis',
        populatedCount: 2,
        href: '/kaprodi/mahasiswa?activeFocus=masa_habis',
    },
];

function php(code: string): void {
    execFileSync('php', ['artisan', 'tinker', '--execute', code], {
        cwd: repositoryRoot,
        env: playwrightEnvironment(),
        stdio: 'pipe',
    });
}

function setupCode(
    token: string,
    scenario: KaprodiDashboardScenario,
    email: string,
    programName: string,
): string {
    const config = JSON.stringify({
        token,
        email,
        programName,
        populated: scenario !== 'all-clear',
        capabilities: scenario === 'restricted' ? ['view_documents'] : null,
        names: dashboardNames,
    });

    // Students are described once: key, phase, supervised, project overrides,
    // defenses and later timestamp changes. Everyone except the "without
    // supervisor" student has a supervisor, so a supervisor count cannot depend
    // on whether a phase counts as past the proposal exam.
    return `
        $cfg = json_decode('${config}', true);
        Illuminate\\Support\\Facades\\DB::transaction(function () use ($cfg) {
            $n = $cfg['names'];
            $faculty = App\\Models\\Faculty::factory()->create(['name' => 'Fakultas '.$cfg['token']]);
            $program = App\\Models\\ProgramStudi::factory()->create(['faculty_id' => $faculty->id, 'name' => $cfg['programName'], 'slug' => $cfg['token']]);
            $kaprodi = App\\Models\\User::factory()->asKaprodi()->create(['name' => 'Kaprodi Dashboard', 'email' => $cfg['email'], 'phone_number' => '081234567891', 'phone_number_confirmed_at' => now()]);
            $attributes = ['program_studi_id' => $program->id, 'user_id' => $kaprodi->id];
            if ($cfg['capabilities'] !== null) {
                $attributes['capabilities'] = $cfg['capabilities'];
            }
            App\\Models\\KaprodiAssignment::factory()->create($attributes);
            if (! $cfg['populated']) {
                return;
            }
            $lecturer = App\\Models\\User::factory()->asDosen()->create(['name' => $n['lecturer'], 'email' => $cfg['token'].'-lecturer@sita.test', 'phone_number' => '081234567892', 'phone_number_confirmed_at' => now()]);
            App\\Models\\DosenProfile::factory()->create(['user_id' => $lecturer->id, 'program_studi_id' => $program->id, 'is_active' => true, 'supervision_quota' => 1]);
            $wita = fn (int $days, int $hour) => Illuminate\\Support\\Carbon::now('Asia/Makassar')->addDays($days)->setTime($hour, 0)->setTimezone(config('app.timezone'));
            $make = function (int $index, string $name, string $phase, bool $supervised, array $project = [], bool $active = true) use ($cfg, $program, $lecturer) {
                $student = App\\Models\\User::factory()->asMahasiswa()->create(['name' => $name, 'email' => $cfg['token'].'-'.$index.'@sita.test', 'phone_number' => '081234567890', 'phone_number_confirmed_at' => now()]);
                App\\Models\\MahasiswaProfile::factory()->create(['user_id' => $student->id, 'program_studi_id' => $program->id, 'is_active' => $active]);
                $record = App\\Models\\ThesisProject::query()->create(['student_user_id' => $student->id, 'program_studi_id' => $program->id, 'phase' => $phase, 'state' => 'active', 'started_at' => now()->subDays(100), 'created_by' => $student->id, ...$project]);
                $title = App\\Models\\ThesisProjectTitle::query()->create(['project_id' => $record->id, 'version_no' => 1, 'title_id' => 'Riset '.$name, 'status' => 'approved', 'submitted_by_user_id' => $student->id, 'submitted_at' => now()->subDays(90)]);
                if ($supervised) {
                    App\\Models\\ThesisSupervisorAssignment::query()->create(['project_id' => $record->id, 'lecturer_user_id' => $lecturer->id, 'role' => 'primary', 'status' => 'active', 'started_at' => now()->subDays(60), 'assigned_by' => $lecturer->id]);
                }
                return [$record, $title];
            };
            $defend = function ($record, $title, int $attempt, string $status, $when, string $location) use ($lecturer) {
                $defense = App\\Models\\ThesisDefense::query()->create(['project_id' => $record->id, 'title_version_id' => $title->id, 'type' => 'sempro', 'attempt_no' => $attempt, 'status' => $status, 'result' => 'pending', 'scheduled_for' => $when, 'location' => $location, 'mode' => 'offline']);
                App\\Models\\ThesisDefenseExaminer::query()->create(['defense_id' => $defense->id, 'lecturer_user_id' => $lecturer->id, 'role' => 'examiner', 'order_no' => 1, 'decision' => 'pending']);
                return $defense;
            };

            foreach (['awaitingA', 'awaitingB'] as $i => $key) {
                [$record, $title] = $make($i, $n[$key], 'sempro', true);
                $defend($record, $title, 1, 'awaiting_finalization', $wita(-1, 10), 'Ruang Finalisasi '.$i);
            }
            foreach (['lapsedA', 'lapsedB'] as $i => $key) {
                $make(10 + $i, $n[$key], 'research', true, ['mentorship_valid_until' => now()->subDays(3)->toDateString()]);
            }
            $make(20, $n['noSupervisor'], 'research', false);
            $make(21, $n['readySempro'], 'sempro', true);
            $make(22, $n['readySidang'], 'sidang', true);
            [$inactive] = $make(23, $n['inactive'], 'research', true);
            [$today, $todayTitle] = $make(24, $n['examToday'], 'sempro', true);
            $defend($today, $todayTitle, 1, 'scheduled', $wita(0, 23), 'Ruang Hari Ini Dashboard');
            [$later, $laterTitle] = $make(25, $n['examLater'], 'sempro', true);
            $laterDefense = $defend($later, $laterTitle, 1, 'scheduled', $wita(3, 9), 'Ruang Mendatang Dashboard');
            App\\Models\\ThesisRevision::query()->create(['project_id' => $later->id, 'defense_id' => $laterDefense->id, 'requested_by_user_id' => $lecturer->id, 'status' => 'open', 'notes' => 'Revisi bab tiga dashboard', 'submitted_at' => now(), 'due_at' => $wita(2, 10)]);
            $make(26, $n['deactivated'], 'research', true, [], false);
            [$archived] = $make(27, $n['archived'], 'research', true, ['state' => 'completed', 'completed_at' => now()->subDays(5)], false);
            Illuminate\\Support\\Facades\\DB::table('thesis_projects')->where('id', $inactive->id)->update(['updated_at' => now()->subDays(90)]);
        });
    `;
}

function cleanupCode(token: string): string {
    return `
        $token = '${token}';
        Illuminate\\Support\\Facades\\DB::transaction(function () use ($token) {
            App\\Models\\User::query()->where('email', 'like', $token.'%')->get()->each->delete();
            $program = App\\Models\\ProgramStudi::query()->where('slug', $token)->first();
            $facultyId = $program?->faculty_id;
            $program?->delete();
            if ($facultyId !== null) {
                App\\Models\\Faculty::query()->whereKey($facultyId)->delete();
            }
        });
    `;
}

export function createKaprodiDashboardFixture(
    scenario: KaprodiDashboardScenario,
): KaprodiDashboardFixture {
    const token = `kaprodi-dashboard-${randomUUID()}`;
    const email = `${token}-kaprodi@sita.test`;
    const programName = `Prodi Dashboard ${token.slice(-8)}`;

    try {
        php(setupCode(token, scenario, email, programName));
    } catch (error) {
        // A failed transaction rolls back by itself; this only clears leftovers.
        php(cleanupCode(token));
        throw error;
    }

    return {
        scenario,
        email,
        password: 'password',
        programName,
        cleanup: () => php(cleanupCode(token)),
    };
}

export async function loginAsFixtureKaprodi(
    page: Page,
    fixture: KaprodiDashboardFixture,
): Promise<void> {
    await page.context().clearCookies();
    await page.goto('/login');
    await page.getByLabel('Email', { exact: true }).fill(fixture.email);
    await page.getByLabel('Kata sandi', { exact: true }).fill(fixture.password);
    await page.getByRole('button', { name: /log in|masuk/i }).click();
    await expect(page).toHaveURL(/\/kaprodi\/dashboard$/);
}

/** Create the fixture, sign in, run the scenario and always remove the data. */
export async function withKaprodiDashboard(
    page: Page,
    scenario: KaprodiDashboardScenario,
    run: (fixture: KaprodiDashboardFixture) => Promise<void>,
): Promise<void> {
    const fixture = createKaprodiDashboardFixture(scenario);

    try {
        await loginAsFixtureKaprodi(page, fixture);
        await run(fixture);
    } finally {
        fixture.cleanup();
    }
}
