import { execFileSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';

import { expect, type Page } from '@playwright/test';

import { playwrightEnvironment, repositoryRoot, runArtisan } from './db';

/** Data the admin dashboard and thesis project detail specs need on top of the seeded scenarios. */
export function lapseMentorship(studentEmail: string): void {
    runArtisan([
        'tinker',
        `--execute=$project = App\\Models\\ThesisProject::query()->whereHas("student", fn($q) => $q->where("email", "${studentEmail}"))->where("state", "active")->latest("started_at")->firstOrFail(); $project->forceFill(["mentorship_valid_until" => now()->subDays(3)->toDateString()])->save();`,
    ]);
}

export function seedDocuments(studentEmail: string): void {
    runArtisan([
        'tinker',
        `--execute=$project = App\\Models\\ThesisProject::query()->whereHas("student", fn($q) => $q->where("email", "${studentEmail}"))->where("state", "active")->latest("started_at")->firstOrFail(); foreach ([["proposal", "Proposal Skripsi Sistem Rekomendasi Topik Bimbingan"], ["revision_submission", "Revisi Proposal Setelah Seminar Hasil Perbaikan Bab Tiga"], ["final_manuscript", "Naskah Akhir Lengkap Dengan Lampiran Pendukung"]] as $index => [$kind, $title]) { App\\Models\\ThesisDocument::query()->updateOrCreate(["project_id" => $project->id, "kind" => $kind, "version_no" => 1], ["uploaded_by_user_id" => $project->student_user_id, "status" => "active", "title" => $title, "storage_disk" => "public", "storage_path" => "thesis/e2e-" . $index . ".pdf", "file_name" => "dokumen-panjang-nama-berkas-" . $index . ".pdf", "mime_type" => "application/pdf", "file_size_kb" => 1, "uploaded_at" => now()->subDays($index + 1)]); }`,
    ]);
}

/**
 * Isolated admin dashboard fixtures. Each one gets its own faculty, two program
 * studi, a program-studi admin and every student below, so counts never depend
 * on the shared seeded scenarios earlier specs change, and no seeded identity
 * is touched. Setup is one transaction; cleanup removes only what this fixture
 * created (rows are found by the fixture token in e-mails and slugs).
 *
 * - populated: the admin's program studi has every queue item, an agenda with
 *   items inside and outside the seven days, and real statistics.
 * - all-clear: the admin's program studi is empty (zero statistics, no queue,
 *   no agenda).
 *
 * Both scenarios also create a second, foreign program studi with queue items
 * and an agenda. The admin is scoped to the first one, so those names must
 * never appear anywhere the admin looks.
 */
export type AdminDashboardScenario = 'populated' | 'all-clear';

export type AdminDashboardFixture = {
    scenario: AdminDashboardScenario;
    email: string;
    password: string;
    adminName: string;
    programName: string;
    cleanup: () => void;
};

export type AdminQueueKey =
    | 'title_review'
    | 'awaiting_finalization'
    | 'no_supervisor'
    | 'revision_overdue'
    | 'mentorship_lapsed'
    | 'archive_soon'
    | 'mentorship_unset'
    | 'phone_missing';

export type AdminQueueExpectation = {
    key: AdminQueueKey;
    label: string;
    /** Visible name of the call to action. */
    actionLabel: string;
    count: number;
    /** Students the filtered project list must show, and no other fixture student. */
    students: string[];
};

export const adminDashboardNames = {
    titleA: 'Aisyah Judul Admin Dashboard',
    titleB: 'Bayu Judul Admin Dashboard',
    titleDecoy: 'Cahya Judul Disetujui Admin Dashboard',
    awaiting: 'Dewi Finalisasi Admin Dashboard',
    noSupervisor: 'Eko Tanpa Pembimbing Admin Dashboard',
    revisionOverdue: 'Fitri Revisi Telat Admin Dashboard',
    lapsed: 'Galih Masa Habis Admin Dashboard',
    archiveSoon: 'Hana Arsip Admin Dashboard',
    unset: 'Irfan Belum Diatur Admin Dashboard',
    phoneMissing: 'Jasmine Tanpa HP Admin Dashboard',
    agendaSoon: 'Kirana Ujian Besok Admin Dashboard',
    agendaLater: 'Lutfi Ujian Mendatang Admin Dashboard',
    agendaBeyond: 'Maya Ujian Jauh Admin Dashboard',
    foreignTitle: 'Luar Prodi Judul Admin Dashboard',
    foreignLapsed: 'Luar Prodi Masa Habis Admin Dashboard',
    foreignPhone: 'Luar Prodi Tanpa HP Admin Dashboard',
    foreignAgenda: 'Luar Prodi Ujian Admin Dashboard',
} as const;

export const agendaLocations = {
    soon: 'Ruang Agenda Besok Admin Dashboard',
    later: 'Ruang Agenda Mendatang Admin Dashboard',
    beyond: 'Ruang Agenda Jauh Admin Dashboard',
    foreign: 'Ruang Agenda Luar Prodi Admin Dashboard',
} as const;

const names = adminDashboardNames;

/** Every student of the admin's own program studi in the populated fixture. */
export const ownStudentNames: string[] = [
    names.titleA,
    names.titleB,
    names.titleDecoy,
    names.awaiting,
    names.noSupervisor,
    names.revisionOverdue,
    names.lapsed,
    names.archiveSoon,
    names.unset,
    names.phoneMissing,
    names.agendaSoon,
    names.agendaLater,
    names.agendaBeyond,
];

/** Students of the other program studi; they must never reach a scoped admin. */
export const foreignStudentNames: string[] = [
    names.foreignTitle,
    names.foreignLapsed,
    names.foreignPhone,
    names.foreignAgenda,
];

/** The first item is the most urgent, so it is the only one that may preview a name. */
export const populatedQueue: AdminQueueExpectation[] = [
    {
        key: 'title_review',
        label: 'Judul menunggu review',
        actionLabel: 'Review',
        count: 2,
        students: [names.titleA, names.titleB],
    },
    {
        key: 'awaiting_finalization',
        label: 'Ujian menunggu finalisasi',
        actionLabel: 'Finalisasi',
        count: 1,
        students: [names.awaiting],
    },
    {
        key: 'no_supervisor',
        label: 'Pembimbing belum ditetapkan',
        actionLabel: 'Tetapkan',
        count: 1,
        students: [names.noSupervisor],
    },
    {
        key: 'revision_overdue',
        label: 'Revisi melewati batas waktu',
        actionLabel: 'Tinjau',
        count: 1,
        students: [names.revisionOverdue],
    },
    {
        key: 'mentorship_lapsed',
        label: 'Masa aktif bimbingan habis',
        actionLabel: 'Perpanjang',
        count: 1,
        students: [names.lapsed],
    },
    {
        key: 'archive_soon',
        label: 'Akan diarsipkan dalam 7 hari',
        actionLabel: 'Tinjau',
        count: 1,
        students: [names.archiveSoon],
    },
    {
        key: 'mentorship_unset',
        label: 'Masa aktif bimbingan belum diatur',
        actionLabel: 'Atur',
        count: 1,
        students: [names.unset],
    },
    {
        key: 'phone_missing',
        label: 'Nomor HP belum ada atau belum dikonfirmasi',
        actionLabel: 'Lihat',
        count: 1,
        students: [names.phoneMissing],
    },
];

export type AdminSummaryExpectation = {
    /** Matches the stat label. */
    label: RegExp;
    /** Exact value text of the stat in the populated and all-clear fixtures. */
    populated: string;
    allClear: string;
};

/**
 * The four statistics the dashboard already had, with the values this fixture
 * produces for the admin's program studi: 7 early-stage projects of which 4
 * lack a scheduled Sempro, 5 late-stage projects without a Sidang, one
 * overdue revision and one late-stage project without a supervisor.
 */
export const summaryStats: AdminSummaryExpectation[] = [
    {
        label: /Gap .* fase awal/i,
        populated: '57%',
        allClear: '0%',
    },
    {
        label: /Gap .* fase lanjut/i,
        populated: '100%',
        allClear: '0%',
    },
    {
        label: /Revisi lewat batas/i,
        populated: '1',
        allClear: '0',
    },
    {
        label: /Belum ada pembimbing/i,
        populated: '1',
        allClear: '0',
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
    scenario: AdminDashboardScenario,
    email: string,
    adminName: string,
    programName: string,
): string {
    const config = JSON.stringify({
        token,
        email,
        adminName,
        programName,
        populated: scenario === 'populated',
        names,
        locations: agendaLocations,
    });

    // Every student is confirmed, supervised and has a valid masa aktif unless
    // the queue under test needs otherwise, so each student matches exactly the
    // queue named in the comment and no other.
    return `
        $cfg = json_decode('${config}', true);
        Illuminate\\Support\\Facades\\DB::transaction(function () use ($cfg) {
            $n = $cfg['names'];
            $where = $cfg['locations'];
            $faculty = App\\Models\\Faculty::factory()->create(['name' => 'Fakultas '.$cfg['token']]);
            $own = App\\Models\\ProgramStudi::factory()->create(['faculty_id' => $faculty->id, 'name' => $cfg['programName'], 'slug' => $cfg['token']]);
            $other = App\\Models\\ProgramStudi::factory()->create(['faculty_id' => $faculty->id, 'name' => $cfg['programName'].' Lain', 'slug' => $cfg['token'].'-lain']);
            $admin = App\\Models\\User::factory()->asAdmin()->create(['name' => $cfg['adminName'], 'email' => $cfg['email'], 'phone_number' => '081234567891', 'phone_number_confirmed_at' => now()]);
            App\\Models\\AdminProfile::query()->create(['user_id' => $admin->id, 'program_studi_id' => $own->id]);
            $lecturer = App\\Models\\User::factory()->asDosen()->create(['name' => 'Dosen Admin Dashboard', 'email' => $cfg['token'].'-lecturer@sita.test', 'phone_number' => '081234567892', 'phone_number_confirmed_at' => now()]);
            $index = 0;
            $make = function ($program, string $name, string $phase, bool $supervised, array $project = [], bool $phone = true) use ($cfg, $lecturer, &$index) {
                $index++;
                $student = App\\Models\\User::factory()->asMahasiswa()->create(array_merge(['name' => $name, 'email' => $cfg['token'].'-student-'.$index.'@sita.test'], $phone ? ['phone_number' => '081234567890', 'phone_number_confirmed_at' => now()] : ['phone_number' => null, 'phone_number_confirmed_at' => null]));
                App\\Models\\MahasiswaProfile::factory()->create(['user_id' => $student->id, 'program_studi_id' => $program->id, 'is_active' => true]);
                $record = App\\Models\\ThesisProject::query()->create(array_merge(['student_user_id' => $student->id, 'program_studi_id' => $program->id, 'phase' => $phase, 'state' => 'active', 'started_at' => now()->subDays(100), 'mentorship_valid_until' => now()->addMonths(3)->toDateString(), 'created_by' => $student->id], $project));
                if ($supervised) {
                    App\\Models\\ThesisSupervisorAssignment::query()->create(['project_id' => $record->id, 'lecturer_user_id' => $lecturer->id, 'role' => 'primary', 'status' => 'active', 'started_at' => now()->subDays(60), 'assigned_by' => $lecturer->id]);
                }
                return $record;
            };
            $title = fn ($record, string $name, string $status) => App\\Models\\ThesisProjectTitle::query()->create(['project_id' => $record->id, 'version_no' => 1, 'title_id' => 'Riset '.$name, 'status' => $status, 'submitted_by_user_id' => $record->student_user_id, 'submitted_at' => now()->subDay()]);
            $defense = fn ($record, string $status, $when, string $location) => App\\Models\\ThesisDefense::query()->create(['project_id' => $record->id, 'type' => 'sempro', 'attempt_no' => 1, 'status' => $status, 'scheduled_for' => $when, 'location' => $location, 'mode' => 'offline', 'created_by' => $record->student_user_id]);

            // Foreign program studi: three queue items and one agenda item.
            $title($make($other, $n['foreignTitle'], 'title_review', false), $n['foreignTitle'], 'submitted');
            $make($other, $n['foreignLapsed'], 'research', true, ['mentorship_valid_until' => now()->subDays(3)->toDateString()]);
            $make($other, $n['foreignPhone'], 'research', true, [], false);
            $defense($make($other, $n['foreignAgenda'], 'sempro', true), 'scheduled', now()->addDays(2), $where['foreign']);

            if (! $cfg['populated']) {
                return;
            }

            // title_review: two submitted titles; the decoy has an approved title.
            $title($make($own, $n['titleA'], 'title_review', false), $n['titleA'], 'submitted');
            $title($make($own, $n['titleB'], 'title_review', false), $n['titleB'], 'submitted');
            $title($make($own, $n['titleDecoy'], 'title_review', false), $n['titleDecoy'], 'approved');
            // awaiting_finalization.
            $defense($make($own, $n['awaiting'], 'sempro', true), 'awaiting_finalization', now()->subDay(), 'Ruang Finalisasi Admin Dashboard');
            // no_supervisor.
            $make($own, $n['noSupervisor'], 'research', false);
            // revision_overdue.
            $overdue = $make($own, $n['revisionOverdue'], 'research', true);
            App\\Models\\ThesisRevision::query()->create(['project_id' => $overdue->id, 'requested_by_user_id' => $lecturer->id, 'status' => 'open', 'notes' => 'Revisi terlambat.', 'due_at' => now()->subDay()]);
            // mentorship_lapsed.
            $make($own, $n['lapsed'], 'research', true, ['mentorship_valid_until' => now()->subDays(3)->toDateString()]);
            // archive_soon: completed three days before the archive grace ends.
            $make($own, $n['archiveSoon'], 'completed', true, ['state' => 'completed', 'completed_at' => now()->subDays((int) config('sita.archive_grace_days', 30) - 3)]);
            // mentorship_unset.
            $make($own, $n['unset'], 'research', true, ['mentorship_valid_until' => null]);
            // phone_missing.
            $make($own, $n['phoneMissing'], 'research', true, [], false);
            // Agenda: two inside the seven days, one outside.
            $defense($make($own, $n['agendaSoon'], 'sempro', true), 'scheduled', now()->addDay(), $where['soon']);
            $defense($make($own, $n['agendaLater'], 'sempro', true), 'scheduled', now()->addDays(3), $where['later']);
            $defense($make($own, $n['agendaBeyond'], 'sempro', true), 'scheduled', now()->addDays(10), $where['beyond']);
        });
    `;
}

function cleanupCode(token: string): string {
    return `
        $token = '${token}';
        Illuminate\\Support\\Facades\\DB::transaction(function () use ($token) {
            App\\Models\\User::query()->where('email', 'like', $token.'%')->get()->each->delete();
            $programs = App\\Models\\ProgramStudi::query()->where('slug', 'like', $token.'%')->get();
            $facultyIds = $programs->pluck('faculty_id')->unique()->all();
            $programs->each->delete();
            App\\Models\\Faculty::query()->whereIn('id', $facultyIds)->delete();
        });
    `;
}

export function createAdminDashboardFixture(
    scenario: AdminDashboardScenario,
): AdminDashboardFixture {
    const token = `admin-dashboard-${randomUUID()}`;
    const email = `${token}-admin@sita.test`;
    const adminName = 'Admin Dashboard Fixture';
    const programName = `Prodi Admin Dashboard ${token.slice(-8)}`;

    try {
        php(setupCode(token, scenario, email, adminName, programName));
    } catch (error) {
        // A failed transaction rolls back by itself; this only clears leftovers.
        php(cleanupCode(token));
        throw error;
    }

    return {
        scenario,
        email,
        password: 'password',
        adminName,
        programName,
        cleanup: () => php(cleanupCode(token)),
    };
}

export async function loginAsFixtureAdmin(
    page: Page,
    fixture: AdminDashboardFixture,
): Promise<void> {
    await page.context().clearCookies();
    await page.goto('/admin/login');
    await page
        .getByRole('textbox', { name: /Email address\*/i })
        .fill(fixture.email);
    await page.locator('input[id="form.password"]').fill(fixture.password);
    await page.getByRole('button', { name: /Masuk ke dashboard/i }).click();
    await expect(page).toHaveURL(/\/admin$/);
}

/**
 * Create the fixture, sign in as its admin, run the scenario and always remove
 * the data. For screenshots, call `createAdminDashboardFixture` and
 * `loginAsFixtureAdmin` yourself, capture, then call `fixture.cleanup()`.
 */
export async function withAdminDashboard(
    page: Page,
    scenario: AdminDashboardScenario,
    run: (fixture: AdminDashboardFixture) => Promise<void>,
): Promise<void> {
    const fixture = createAdminDashboardFixture(scenario);

    try {
        await loginAsFixtureAdmin(page, fixture);
        await run(fixture);
    } finally {
        fixture.cleanup();
    }
}
