import { expect, test, type Page, type TestInfo } from '@playwright/test';

import { seedPlaywrightScenarios } from '../support/db';
import { pickFilamentSelect } from '../support/golden-path';
import { authStatePath, thesisScenarios } from '../support/thesis-fixtures';

const scenario = thesisScenarios.sidangWithPanel;

const adminScore = (page: Page, label: string) =>
    page.getByRole('spinbutton', { name: new RegExp(`^${label}\\b`) });

test.use({ actionTimeout: 15_000 });

async function capture(page: Page, testInfo: TestInfo, name: string) {
    const modalOpen =
        (await page
            .locator('[role="dialog"]:visible, .fi-modal-window:visible')
            .count()) > 0;
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
            if (name.startsWith('mahasiswa')) {
                await page
                    .getByRole('heading', {
                        name: 'Hasil Sidang Skripsi',
                        exact: true,
                    })
                    .scrollIntoViewIfNeeded();
            }
            await testInfo.attach(`${name}-${width}-${theme}`, {
                body: await page.screenshot({
                    fullPage: !modalOpen && !name.startsWith('mahasiswa'),
                    animations: 'disabled',
                }),
                contentType: 'image/png',
            });
        }
    }
    await page.setViewportSize({ width: 1280, height: 920 });
}

async function openLecturer(page: Page) {
    await page.goto('/dosen/seminar-proposal');
    const sidang = page.getByRole('radio', {
        name: 'Sidang Akhir',
        exact: true,
    });
    await expect(sidang).toBeVisible();
    await sidang.click();
    await page.getByPlaceholder(/Cari nama/i).fill(scenario.studentName);
    const row = page
        .getByRole('row')
        .filter({ hasText: scenario.studentName })
        .filter({ hasText: /#1/ });
    await expect(async () => {
        await row.locator('td').first().click();
        await expect(page.getByRole('dialog')).toBeVisible({ timeout: 2000 });
    }).toPass();
}

test('lecturer and admin correct the same sidang scores until finalization', async ({
    browser,
}, testInfo) => {
    test.setTimeout(300_000);
    seedPlaywrightScenarios();
    const contexts = await Promise.all(
        ['admin', 'dosen3', 'sinta', 'kaprodi'].map((key) =>
            browser.newContext({
                storageState: authStatePath(
                    key as 'admin' | 'dosen3' | 'sinta' | 'kaprodi',
                ),
            }),
        ),
    );
    try {
        const [admin, lecturer, student, head] = await Promise.all(
            contexts.map((context) => context.newPage()),
        );
        let paperWrites = 0;
        let lecturerWrites = 0;
        admin.on('request', (request) => {
            if (
                request.method() === 'POST' &&
                /\/livewire(?:-[a-z0-9]+)?\/update$/.test(request.url()) &&
                request.postData()?.includes('"method":"callMountedAction"')
            )
                paperWrites++;
        });
        lecturer.on('request', (request) => {
            if (request.method() === 'PATCH' && /\/score$/.test(request.url()))
                lecturerWrites++;
        });
        await test.step('Lecturer enters a decision and score through the existing confirmation', async () => {
            await openLecturer(lecturer);
            await lecturer
                .getByRole('button', { name: 'Input Keputusan Saya' })
                .click();
            await lecturer
                .getByRole('button', {
                    name: 'Lulus dengan Syarat',
                    exact: true,
                })
                .click();
            await lecturer.getByLabel(/^Nilai/).fill('88.5');
            await lecturer
                .getByLabel(/^Catatan Revisi/)
                .fill('Perbaiki tabel pengujian.');
            await lecturer
                .getByRole('button', { name: 'Submit Keputusan' })
                .click();
            await lecturer
                .getByRole('button', {
                    name: 'Konfirmasi penilaian',
                    exact: true,
                })
                .click();
            await expect(lecturer.getByRole('dialog')).toHaveCount(0);
        });

        await test.step('Admin matches paper scores and cancellation keeps the draft', async () => {
            await head.goto('/kaprodi/sempro-sidang');
            await head
                .getByPlaceholder('Cari nama, NIM, judul, atau dosen...')
                .fill(scenario.studentName);
            const row = head
                .getByRole('row')
                .filter({ hasText: scenario.studentName })
                .filter({
                    has: head.getByRole('cell', { name: /^Sidang .*#1/ }),
                });
            await row.getByRole('button', { name: 'Aksi lainnya' }).click();
            await head
                .getByRole('menuitem', { name: 'Kelola berkas sidang' })
                .click();
            await expect(head).toHaveURL(/\/defenses\/\d+\/berkas-sidang\?/);
            const headPaperUrl = head.url();
            await expect(head).toHaveTitle(new RegExp(scenario.studentName));
            await expect(
                head
                    .locator('[data-sidebar="menu-button"][data-active="true"]')
                    .filter({ hasText: /Sempro.*Sidang Akhir/ }),
            ).toBeVisible();
            await head
                .getByRole('link', { name: 'Kembali', exact: true })
                .click();
            await expect(
                head.getByPlaceholder('Cari nama, NIM, judul, atau dosen...'),
            ).toHaveValue(scenario.studentName);
            await head.goto(headPaperUrl);
            await admin.goto(head.url());
            await expect(admin).toHaveURL(
                /\/admin\/thesis-projects\/\d+\/berkas-sidang\/\d+$/,
            );
            await expect(admin.locator('.fi-sidebar')).toBeVisible();
            await expect(adminScore(admin, 'Nilai penguji 1')).toHaveValue(
                /^88\.5(0)?$/,
            );
            await expect(adminScore(admin, 'Nilai penguji 1')).toBeEditable();
            await adminScore(admin, 'Nilai penguji 2').fill('70');
            await adminScore(admin, 'Nilai pembimbing penilai').fill('80');
            await admin
                .getByRole('button', { name: 'Simpan untuk Finalisasi' })
                .click();
            const confirmation = admin
                .locator('.fi-modal-window:visible')
                .filter({
                    hasText: 'Konfirmasi nilai dari berkas',
                });
            await expect(confirmation).toContainText('88,50');
            await capture(admin, testInfo, 'admin-konfirmasi-nilai');
            await confirmation
                .getByRole('button', { name: 'Batal', exact: true })
                .click();
            await expect(adminScore(admin, 'Nilai penguji 2')).toHaveValue(
                '70',
            );
            expect(paperWrites).toBe(0);
            await admin
                .getByRole('button', { name: 'Simpan untuk Finalisasi' })
                .click();
            await admin
                .getByRole('button', { name: 'Konfirmasi Simpan Nilai' })
                .dblclick();
            await expect(
                admin.getByText('79,55 (B+)', { exact: true }),
            ).toBeVisible();
            await expect(admin.getByText(/Cocok dengan berkas/)).toHaveCount(3);
            expect(paperWrites).toBe(1);
            await capture(admin, testInfo, 'admin-nilai-tercatat');
            await head.reload();
            await expect(
                head.getByText('Nilai akhir 79,55 · Grade B+'),
            ).toBeVisible();
            await capture(head, testInfo, 'kaprodi-nilai-tercatat');
        });

        await test.step('Lecturer corrects only the score and invalidates its old paper match', async () => {
            await openLecturer(lecturer);
            await expect(
                lecturer.getByText('Perbaiki tabel pengujian.'),
            ).toBeVisible();
            await lecturer
                .getByRole('button', { name: 'Koreksi Nilai Saya' })
                .click();
            await lecturer
                .getByLabel('Nilai saya', { exact: true })
                .fill('87.5');
            await lecturer
                .getByRole('button', { name: 'Tinjau Koreksi Nilai' })
                .click();
            await capture(lecturer, testInfo, 'dosen-konfirmasi-koreksi');
            await lecturer
                .getByRole('dialog', { name: 'Konfirmasi koreksi nilai saya' })
                .getByRole('button', { name: 'Batal', exact: true })
                .click();
            await expect(
                lecturer.getByLabel('Nilai saya', { exact: true }),
            ).toHaveValue('87.5');
            await lecturer
                .getByRole('button', { name: 'Tinjau Koreksi Nilai' })
                .click();
            await lecturer
                .getByRole('button', {
                    name: 'Konfirmasi Koreksi Nilai',
                    exact: true,
                })
                .dblclick();
            await expect(
                lecturer.getByRole('button', { name: 'Koreksi Nilai Saya' }),
            ).toBeVisible();
            await expect(
                lecturer.getByText('Perbaiki tabel pengujian.'),
            ).toBeVisible();
            expect(lecturerWrites).toBe(1);
        });

        await test.step('Stale admin input fails visibly and preserves the correction draft', async () => {
            await adminScore(admin, 'Nilai penguji 1').fill('90');
            await admin
                .getByRole('button', { name: 'Simpan Koreksi / Pencocokan' })
                .click();
            await admin
                .getByRole('button', { name: 'Konfirmasi Simpan Nilai' })
                .click();
            await expect(
                admin.getByText(/Nilai sudah berubah/).first(),
            ).toBeVisible();
            await expect(adminScore(admin, 'Nilai penguji 1')).toHaveValue(
                '90',
            );
            await capture(admin, testInfo, 'admin-konflik-nilai');
            await admin.reload();
            await expect(adminScore(admin, 'Nilai penguji 1')).toHaveValue(
                /^87\.5(0)?$/,
            );
            await expect(
                admin.getByText('Belum dicocokkan dengan berkas.', {
                    exact: false,
                }),
            ).toHaveCount(1);
            await adminScore(admin, 'Nilai penguji 1').fill('90');
            await admin
                .getByRole('button', { name: 'Simpan Koreksi / Pencocokan' })
                .click();
            await admin
                .getByRole('button', { name: 'Konfirmasi Simpan Nilai' })
                .click();
            await expect(
                admin.getByText('80,00 (B+)', { exact: true }),
            ).toBeVisible();
        });

        await test.step('A stale lecturer correction also preserves the draft', async () => {
            await lecturer
                .getByRole('button', { name: 'Koreksi Nilai Saya' })
                .click();
            await lecturer.getByLabel('Nilai saya', { exact: true }).fill('89');
            await lecturer
                .getByRole('button', { name: 'Tinjau Koreksi Nilai' })
                .click();
            await lecturer
                .getByRole('button', {
                    name: 'Konfirmasi Koreksi Nilai',
                    exact: true,
                })
                .click();
            await expect(lecturer.getByRole('alert')).toContainText(
                'Nilai sudah berubah.',
            );
            await expect(
                lecturer.getByLabel('Nilai saya', { exact: true }),
            ).toHaveValue('89');
            await capture(lecturer, testInfo, 'dosen-konflik-nilai');
            await lecturer
                .getByRole('button', { name: 'Batal', exact: true })
                .click();
        });

        await test.step('Admin finalizes, the student sees factual paper labels, and score controls are locked', async () => {
            const adminPaperUrl = admin.url();
            await admin
                .getByRole('link', { name: 'Finalisasi Sidang', exact: true })
                .click();
            const modal = admin.locator('.fi-modal-window:visible').last();
            await pickFilamentSelect(modal, 0, 'pass');
            await modal
                .getByRole('textbox', { name: /Catatan Sidang/ })
                .fill('Hasil resmi setelah pencocokan berkas.');
            await modal
                .getByRole('button', { name: 'Tinjau hasil akhir' })
                .click();
            await capture(admin, testInfo, 'admin-finalisasi-nilai');
            await admin
                .getByRole('button', {
                    name: 'Konfirmasi hasil akhir',
                    exact: true,
                })
                .click();
            await expect(modal).toBeHidden();
            await admin
                .getByRole('tab', { name: 'Ujian', exact: true })
                .click();
            await capture(admin, testInfo, 'admin-riwayat-nilai');
            await admin.goto(adminPaperUrl);
            await expect(adminScore(admin, 'Nilai penguji 1')).toBeDisabled();
            await capture(admin, testInfo, 'admin-berkas-selesai');
            await student.goto('/mahasiswa/tugas-akhir');
            await expect(
                student.getByText('Nilai dari berkas', { exact: true }).first(),
            ).toBeVisible();
            await capture(student, testInfo, 'mahasiswa-hasil-nilai');
            await openLecturer(lecturer);
            await lecturer
                .getByText('Keputusan Penguji', { exact: true })
                .scrollIntoViewIfNeeded();
            await expect(
                lecturer.getByRole('button', { name: 'Koreksi Nilai Saya' }),
            ).toHaveCount(0);
            await capture(lecturer, testInfo, 'dosen-hasil-nilai');
        });
    } finally {
        await Promise.all(contexts.map((context) => context.close()));
        seedPlaywrightScenarios();
    }
});
