import { expect, test, type Page, type TestInfo } from '@playwright/test';

import { runArtisan, seedPlaywrightScenarios } from '../support/db';
import { authStatePath } from '../support/thesis-fixtures';

test.use({ storageState: authStatePath('kaprodi') });

const emptyReport = 'Tidak ada sidang lulus yang sudah final pada filter ini.';

test.beforeEach(() => seedPlaywrightScenarios());
test.afterAll(() => seedPlaywrightScenarios());

function finalizeDemoSidang() {
    runArtisan([
        'tinker',
        '--execute=' +
            [
                "$student = App\\Models\\User::where('email', 'e2e-sidang@sita.test')->firstOrFail();",
                "$project = App\\Models\\ThesisProject::where('student_user_id', $student->id)->where('state', 'active')->firstOrFail();",
                '$defense = $project->latestSidangDefense;',
                "$admin = App\\Models\\User::where('email', 'superadmin@sita.test')->firstOrFail();",
                "$defense->update(['scheduled_for' => '2026-11-15 02:00:00', 'status' => 'awaiting_finalization']);",
                "$defense->examiners()->update(['score' => 80, 'decision' => 'pass']);",
                "app(App\\Services\\ThesisProjectAdminService::class)->completeSidang($project, $admin->id, 'pass', 'Hasil demo rekap.');",
            ].join(' '),
    ]);
}

async function captureReport(page: Page, testInfo: TestInfo, label: string) {
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
            await testInfo.attach(`${label}-${width}-${theme}`, {
                body: await page.screenshot({ animations: 'disabled' }),
                contentType: 'image/png',
            });
        }
    }
    await page.setViewportSize({ width: 1280, height: 920 });
}

test('kaprodi opens the scheduled packet and downloads a filtered report', async ({
    page,
}, testInfo) => {
    await page.goto('/kaprodi/sempro-sidang');
    await page
        .getByPlaceholder('Cari nama, NIM, judul, atau dosen...')
        .fill('Sinta Sidang Terjadwal');
    const row = page.getByRole('row').filter({
        has: page.getByRole('cell', { name: /^Sidang .*#/ }),
        hasText: 'Sinta Sidang Terjadwal',
    });
    await row.getByRole('button', { name: 'Aksi lainnya' }).click();
    await page.getByRole('menuitem', { name: 'Kelola berkas sidang' }).click();
    await expect(
        page.getByRole('heading', { name: /Berkas Sidang/ }),
    ).toBeVisible();
    await expect(page.getByLabel('Nilai penguji 2')).toBeEditable();
    await expect(page.getByLabel('Moderator', { exact: true })).toHaveCount(0);
    await expect(page.getByLabel('Nomor SK')).toBeEditable();
    await expect(
        page.getByRole('button', { name: 'Simpan No SK' }),
    ).toBeEnabled();

    const content = page
        .getByRole('main')
        .filter({ has: page.getByRole('heading', { name: /Berkas Sidang/ }) })
        .last();
    for (const width of [1280, 390]) {
        await page.setViewportSize({ width, height: 920 });
        if (width === 1280) {
            expect((await content.boundingBox())?.width).toBeGreaterThan(896);
        }
        for (const theme of ['light', 'dark'] as const) {
            await page.emulateMedia({ colorScheme: theme });
            await page.evaluate((mode) => {
                document.documentElement.classList.toggle(
                    'dark',
                    mode === 'dark',
                );
            }, theme);
            expect(
                await page.evaluate(() => document.documentElement.scrollWidth),
            ).toBeLessThanOrEqual(width);
            await testInfo.attach(`berkas-${width}-${theme}`, {
                body: await page.screenshot({ fullPage: true }),
                contentType: 'image/png',
            });
        }
    }

    const wordDownload = page.waitForEvent('download');
    await page.getByRole('link', { name: 'Berita Acara', exact: true }).click();
    expect((await wordDownload).suggestedFilename()).toMatch(
        /^berita-acara-\d+\.docx$/,
    );

    finalizeDemoSidang();
    await page.setViewportSize({ width: 1280, height: 920 });
    await page.goto('/kaprodi/sempro-sidang');
    const search = page.getByPlaceholder(
        'Cari nama, NIM, judul, atau dosen...',
    );
    await search.fill('Sinta Sidang Terjadwal');
    await page.getByRole('button', { name: 'Rekap Nilai' }).click();
    const dialog = page.getByRole('dialog');
    await dialog.getByLabel('Dari tanggal').fill('2096-11-01');
    await dialog.getByLabel('Sampai tanggal').fill('2096-11-30');
    await dialog.getByRole('button', { name: 'Unduh Excel' }).click();
    await expect(dialog.getByRole('alert')).toHaveText(emptyReport);
    await expect(dialog.getByLabel('Dari tanggal')).toHaveValue('2096-11-01');
    await captureReport(page, testInfo, 'kaprodi-rekap-kosong');

    await dialog.getByLabel('Dari tanggal').fill('2026-11-01');
    await dialog.getByLabel('Sampai tanggal').fill('2026-11-30');
    const downloaded = page.waitForEvent('download');
    await dialog.getByRole('button', { name: 'Unduh Excel' }).click();
    expect((await downloaded).suggestedFilename()).toBe(
        'rekap-nilai-sidang.xlsx',
    );
    await expect(dialog.getByText('Unduhan Excel dimulai.')).toBeVisible();
    await captureReport(page, testInfo, 'kaprodi-rekap-berhasil');
});

test('admin saves an editable semester suggestion and reuses it for Excel', async ({
    browser,
}, testInfo) => {
    finalizeDemoSidang();
    const admin = await browser.newContext({
        storageState: authStatePath('admin'),
    });
    try {
        const page = await admin.newPage();
        await page.goto('/admin/thesis-projects');
        await page.getByText('Berkas Sidang Akhir', { exact: true }).click();
        await page.getByRole('button', { name: 'Atur Periode Rekap' }).click();
        const form = page.locator('.fi-modal-window:visible').last();
        await form.getByLabel('Tanggal awal').fill('2096-09-14');
        const semester = form.locator('select').filter({
            has: page.locator('option[value="ganjil"]'),
        });
        await expect(semester).toHaveValue('ganjil');
        await expect(form.getByLabel('Tahun awal akademik')).toHaveValue(
            '2096',
        );
        await semester.selectOption('genap');
        await form.getByLabel('Tanggal akhir').fill('2096-10-14');
        for (const width of [1280, 390]) {
            await page.setViewportSize({ width, height: 920 });
            for (const theme of ['light', 'dark'] as const) {
                await page.evaluate((mode) => {
                    document.documentElement.classList.toggle(
                        'dark',
                        mode === 'dark',
                    );
                }, theme);
                const bounds = await form.boundingBox();
                expect(bounds?.width).toBeLessThanOrEqual(width);
                await testInfo.attach(`admin-atur-periode-${width}-${theme}`, {
                    body: await page.screenshot(),
                    contentType: 'image/png',
                });
            }
        }
        await page.setViewportSize({ width: 1280, height: 920 });
        await form.getByRole('button', { name: 'Simpan Periode' }).click();
        await expect(form).toBeHidden();

        await page.getByRole('button', { name: 'Ekspor Rekap Nilai' }).click();
        await form.locator('select').selectOption({
            label: 'Semester Genap 2096/2097 (14/09/2096 - 14/10/2096)',
        });
        await expect(form.getByLabel('Dari tanggal')).toBeHidden();
        await form.getByRole('button', { name: 'Unduh Excel' }).click();
        await expect(form.getByRole('alert')).toHaveText(emptyReport);
        await expect(
            form.locator('select').locator('option:checked'),
        ).toContainText('Semester Genap 2096/2097');
        await captureReport(page, testInfo, 'admin-rekap-kosong');

        await form.locator('select').selectOption('');
        await form.getByLabel('Dari tanggal').fill('2026-11-01');
        await form.getByLabel('Sampai tanggal').fill('2026-11-30');
        const downloaded = page.waitForEvent('download');
        await form.getByRole('button', { name: 'Unduh Excel' }).click();
        expect((await downloaded).suggestedFilename()).toBe(
            'rekap-nilai-sidang.xlsx',
        );
        await expect(form.getByText('Unduhan Excel dimulai.')).toBeVisible();
        await captureReport(page, testInfo, 'admin-rekap-berhasil');
        await form.getByRole('button', { name: 'Batal', exact: true }).click();

        const kaprodi = await browser.newContext({
            storageState: authStatePath('kaprodi'),
        });
        try {
            const kaprodiPage = await kaprodi.newPage();
            await kaprodiPage.goto('/kaprodi/sempro-sidang');
            await kaprodiPage
                .getByRole('button', { name: 'Rekap Nilai' })
                .click();
            const dialog = kaprodiPage.getByRole('dialog');
            await dialog.getByLabel('Periode rekap').selectOption({
                label: 'Semester Genap 2096/2097 (14/09/2096 - 14/10/2096)',
            });
            await expect(dialog.getByLabel('Dari tanggal')).toBeHidden();
        } finally {
            await kaprodi.close();
        }
    } finally {
        await admin.close();
        runArtisan([
            'tinker',
            "--execute=App\\Models\\SidangReportPeriod::query()->where('start_year', 2096)->whereDate('starts_on', '2096-09-14')->delete();",
        ]);
    }
});
