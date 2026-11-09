import { execFileSync } from 'node:child_process';
import { randomInt } from 'node:crypto';

import { expect, test, type Page, type TestInfo } from '@playwright/test';

import { playwrightEnvironment, repositoryRoot } from '../support/db';
import { authStatePath } from '../support/thesis-fixtures';

test.use({ storageState: authStatePath('superadmin') });

function php(code: string): string {
    return execFileSync(
        'php',
        [
            '-r',
            `require 'vendor/autoload.php'; $app = require 'bootstrap/app.php'; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); ${code}`,
        ],
        {
            cwd: repositoryRoot,
            env: playwrightEnvironment(),
            encoding: 'utf8',
        },
    ).trim();
}

type Fixture = {
    lecturer: number;
    student: number;
    project: number;
    nidn: string;
    ownNidn: string;
    peer: number;
};

function fixture(): Fixture {
    const token = String(randomInt(0, 100_000_000)).padStart(8, '0');
    return JSON.parse(
        php(`
        $program = App\\Models\\ProgramStudi::query()->firstOrFail();
        $concentration = $program->concentrationList()[0];
        $peer = App\\Models\\DosenProfile::factory()->create(['program_studi_id' => $program->id, 'concentration' => $concentration, 'sidang_nidn' => '00${token}']);
        $lecturer = App\\Models\\DosenProfile::factory()->create(['program_studi_id' => $program->id, 'concentration' => $concentration, 'sidang_nidn' => '01${token}']);
        $lecturer->user->update(['name' => 'Dosen Demo Review']);
        $student = App\\Models\\User::factory()->asMahasiswa()->create(['name' => 'Mahasiswa Demo Selesai']);
        App\\Models\\MahasiswaProfile::factory()->create(['user_id' => $student->id, 'program_studi_id' => $program->id]);
        $project = App\\Models\\ThesisProject::query()->create(['student_user_id' => $student->id, 'program_studi_id' => $program->id, 'phase' => 'completed', 'state' => 'completed', 'started_at' => now()->subMonth(), 'completed_at' => now()]);
        echo json_encode(['lecturer' => $lecturer->user_id, 'student' => $student->id, 'project' => $project->id, 'nidn' => $peer->sidang_nidn, 'ownNidn' => $lecturer->sidang_nidn, 'peer' => $peer->user_id]);
    `),
    ) as Fixture;
}

function cleanup(data: Fixture): void {
    php(
        `App\\Models\\ThesisProject::query()->whereKey(${data.project})->delete(); App\\Models\\User::query()->whereIn('id', [${data.lecturer}, ${data.peer}, ${data.student}])->delete();`,
    );
}

async function capture(
    page: Page,
    info: TestInfo,
    label: string,
): Promise<void> {
    for (const width of [1280, 390]) {
        await page.setViewportSize({ width, height: 920 });
        for (const theme of ['light', 'dark']) {
            await page.evaluate(
                (mode) =>
                    document.documentElement.classList.toggle(
                        'dark',
                        mode === 'dark',
                    ),
                theme,
            );
            expect(
                await page.evaluate(() => document.documentElement.scrollWidth),
            ).toBeLessThanOrEqual(width);
            await page.evaluate(() => window.scrollTo(0, 0));
            await info.attach(`${label}-${width}-${theme}`, {
                body: await page.screenshot({
                    fullPage: true,
                    animations: 'disabled',
                }),
                contentType: 'image/png',
            });
        }
    }
    await page.setViewportSize({ width: 1280, height: 920 });
}

test('Admin rejects duplicate NIDN inline, retains the draft and can save their own NIDN', async ({
    page,
}, info) => {
    const data = fixture();
    try {
        await page.goto(`/admin/users/${data.lecturer}/edit`);
        const nidn = page.getByRole('textbox', { name: 'NIDN', exact: true });
        await expect(nidn).toHaveValue(data.ownNidn);
        await capture(page, info, 'admin-nidn');
        await nidn.fill(` ${data.nidn} `);
        await page
            .getByRole('button', { name: 'Save changes', exact: true })
            .click();
        await expect(
            page.getByText(
                'NIDN sudah digunakan dosen lain. Periksa kembali NIDN.',
                { exact: true },
            ),
        ).toBeVisible();
        await expect(nidn).toHaveValue(new RegExp(data.nidn));
        await capture(page, info, 'admin-nidn-error');
        await nidn.fill(data.ownNidn);
        await page
            .getByRole('textbox', { name: 'Name', exact: false })
            .fill('Dosen Demo Review Tersimpan');
        await page
            .getByRole('button', { name: 'Save changes', exact: true })
            .click();
        await expect(
            page.getByText(
                'NIDN sudah digunakan dosen lain. Periksa kembali NIDN.',
                { exact: true },
            ),
        ).toHaveCount(0);
        expect(
            php(
                `echo App\\Models\\User::findOrFail(${data.lecturer})->dosenProfile->sidang_nidn;`,
            ),
        ).toBe(data.ownNidn);
        expect(
            php(`echo App\\Models\\User::findOrFail(${data.lecturer})->name;`),
        ).toBe('Dosen Demo Review Tersimpan');
    } finally {
        cleanup(data);
    }
});

test('Admin focus describes completed, archived and cancelled records as history', async ({
    page,
}, info) => {
    const data = fixture();
    try {
        for (const [label, changes, expected] of [
            [
                'selesai',
                "['state' => 'completed', 'archived_at' => null]",
                'sudah selesai. Data ini tersedia untuk monitoring dan rekap hasil.',
            ],
            [
                'arsip',
                "['archived_at' => now()]",
                'diarsipkan. Data ini tersedia untuk melihat riwayat.',
            ],
            [
                'batal',
                "['state' => 'cancelled', 'phase' => 'research', 'archived_at' => null]",
                'dibatalkan. Data ini tersedia untuk melihat riwayat.',
            ],
        ]) {
            php(
                `App\\Models\\ThesisProject::findOrFail(${data.project})->update(${changes});`,
            );
            await page.goto(`/admin/thesis-projects/${data.project}`);
            await expect(
                page.getByText(expected, { exact: false }),
            ).toBeVisible();
            await expect(
                page.getByText('Belum ada pembimbing aktif yang tercatat.', {
                    exact: true,
                }),
            ).toHaveCount(0);
            await capture(page, info, `admin-fokus-${label}`);
        }
    } finally {
        cleanup(data);
    }
});

test('Admin keeps the Rekap menu stable across tabs and document actions aligned for different statuses', async ({
    page,
}, info) => {
    const records = [fixture(), fixture(), fixture()];
    const sk = '0292/ADUM/FT/UBG/XI/2026/KEPUTUSAN-PENGUJI-DAN-PEMBIMBING';
    try {
        for (const [index, data] of records.entries()) {
            const status = ['scheduled', 'awaiting_finalization', 'completed'][
                index
            ];
            php(`
                App\\Models\\User::findOrFail(${data.student})->update(['name' => 'Mahasiswa Demo Review ${index}']);
                App\\Models\\ThesisProject::findOrFail(${data.project})->update(['phase' => 'sidang', 'state' => '${index === 2 ? 'completed' : 'active'}']);
                $defense = App\\Models\\ThesisDefense::query()->create(['project_id' => ${data.project}, 'type' => 'sidang', 'status' => '${status}', 'result' => '${index === 2 ? 'pass' : 'pending'}', 'attempt_no' => 1, 'scheduled_for' => '2026-11-15 02:00:00', 'sidang_sk' => '${sk}']);
                foreach ([${data.lecturer}, ${data.peer}, App\\Models\\User::where('email', 'dosen@sita.test')->firstOrFail()->id] as $order => $lecturerId) {
                    $defense->examiners()->create(['lecturer_user_id' => $lecturerId, 'role' => $order < 2 ? 'examiner' : 'primary_supervisor', 'order_no' => $order + 1, 'decision' => 'pending']);
                }
            `);
        }
        await page.goto('/admin/thesis-projects');
        const rekap = page.getByRole('button', { name: 'Rekap', exact: true });
        await rekap.click();
        await expect(
            page.getByRole('menuitem', {
                name: 'Atur Periode Rekap',
                exact: true,
            }),
        ).toBeVisible();
        await expect(
            page.getByRole('menuitem', {
                name: 'Ekspor Rekap Nilai',
                exact: true,
            }),
        ).toBeVisible();
        await capture(page, info, 'admin-daftar-semua');
        await page.keyboard.press('Escape');
        await page.goto('/admin/thesis-projects?tab=berkas-sidang');
        await expect(rekap).toBeVisible();
        await page
            .getByRole('searchbox', { name: 'Search', exact: true })
            .fill('Mahasiswa Demo Review');
        await expect(
            page
                .getByRole('table', { name: 'Tugas Akhir', exact: true })
                .getByRole('row'),
        ).toHaveCount(4);
        const expectedLabels = [
            'Input Nilai',
            'Tinjau & Finalisasi',
            'Lihat Berkas',
        ];
        const iconPositions = [];
        for (const [index, label] of expectedLabels.entries()) {
            const row = page
                .getByRole('row')
                .filter({ hasText: `Mahasiswa Demo Review ${index}` });
            await expect(
                row.getByRole('link', { name: label, exact: true }),
            ).toBeVisible();
            const print = row.getByRole('link', {
                name: 'Cetak / PDF',
                exact: true,
            });
            const word = row.getByRole('link', {
                name: 'Unduh Word',
                exact: true,
            });
            await expect(print).toBeVisible();
            await expect(word).toBeVisible();
            iconPositions.push([
                (await print.boundingBox())!.x,
                (await word.boundingBox())!.x,
            ]);
        }
        for (const position of iconPositions) {
            expect(
                Math.abs(position[0] - iconPositions[0][0]),
            ).toBeLessThanOrEqual(1);
            expect(
                Math.abs(position[1] - iconPositions[0][1]),
            ).toBeLessThanOrEqual(1);
        }
        await capture(page, info, 'admin-aksi-tiga-status');
        await page.setViewportSize({ width: 390, height: 920 });
        const mobileWord = page
            .getByRole('row')
            .filter({ hasText: 'Mahasiswa Demo Review 0' })
            .getByRole('link', { name: 'Unduh Word', exact: true });
        await mobileWord.scrollIntoViewIfNeeded();
        expect(
            (await mobileWord.boundingBox())!.x +
                (await mobileWord.boundingBox())!.width,
        ).toBeLessThanOrEqual(390);
        for (const theme of ['light', 'dark']) {
            await page.evaluate(
                (mode) =>
                    document.documentElement.classList.toggle(
                        'dark',
                        mode === 'dark',
                    ),
                theme,
            );
            await info.attach(`admin-aksi-mobile-kanan-${theme}`, {
                body: await page.screenshot({ animations: 'disabled' }),
                contentType: 'image/png',
            });
        }
    } finally {
        records.forEach(cleanup);
    }
});
