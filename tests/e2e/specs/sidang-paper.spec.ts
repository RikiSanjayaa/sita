import {
    expect,
    test,
    type Locator,
    type Page,
    type TestInfo,
} from '@playwright/test';

import { runArtisan, seedPlaywrightScenarios } from '../support/db';
import { authStatePath } from '../support/thesis-fixtures';

test.use({ storageState: authStatePath('kaprodi'), actionTimeout: 15_000 });

const emptyReport = 'Tidak ada sidang lulus yang sudah final pada filter ini.';

async function pickAdminReportDate(form: Locator, label: string, date: string) {
    const picker = form.getByRole('combobox', {
        name: new RegExp(`^${label}`),
    });
    const calendar = form.getByRole('dialog', { name: label, exact: true });
    const [year, month] = date.split('-');
    if (!(await calendar.isVisible())) await picker.click();
    await calendar
        .getByRole('combobox')
        .selectOption(String(Number(month) - 1));
    await calendar.getByRole('spinbutton').fill(year);
    await calendar.locator(`[role="gridcell"][data-date="${date}"]`).click();
    await expect(calendar).toBeHidden();
}

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

async function captureReport(
    page: Page,
    testInfo: TestInfo,
    label: string,
    fullPage = false,
) {
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
                body: await page.screenshot({
                    animations: 'disabled',
                    fullPage,
                }),
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
    await page.getByLabel('Nilai penguji 2').fill('100.001');
    await expect(
        page.getByText(/paling banyak dua angka desimal/),
    ).toBeVisible();
    await expect(
        page.getByRole('button', { name: 'Simpan untuk Finalisasi' }),
    ).toBeDisabled();
    await page.getByLabel('Nilai penguji 2').clear();
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
    await page.clock.setFixedTime(new Date('2026-11-08T12:00:00+08:00'));
    await page.goto('/kaprodi/sempro-sidang');
    const search = page.getByPlaceholder(
        'Cari nama, NIM, judul, atau dosen...',
    );
    await search.fill('Sinta Sidang Terjadwal');
    await page.getByRole('button', { name: 'Rekap Nilai' }).click();
    const dialog = page.getByRole('dialog');
    const range = dialog.getByRole('button', {
        name: /^Rentang tanggal sidang/,
    });
    await range.click();
    const grid = dialog.getByRole('grid');
    const previousMonth = dialog.getByRole('button', {
        name: /previous month/i,
    });
    await expect(grid).toHaveAccessibleName('November 2026');
    expect((await previousMonth.boundingBox())!.y).toBeLessThan(
        (await grid.boundingBox())!.y,
    );
    await previousMonth.click();
    await expect(grid).toHaveAccessibleName('Oktober 2026');
    await dialog.getByRole('button', { name: /next month/i }).click();
    await expect(grid).toHaveAccessibleName('November 2026');
    await captureReport(page, testInfo, 'kaprodi-kalender-rekap');
    await dialog.locator('[data-day="2026-11-25"] button').click();
    await dialog.locator('[data-day="2026-11-30"] button').click();
    await range.click();
    await dialog.getByRole('button', { name: 'Unduh Excel' }).click();
    await expect(dialog.getByRole('alert')).toHaveText(emptyReport);
    await expect(range).toContainText('25 November 2026');
    await captureReport(page, testInfo, 'kaprodi-rekap-kosong');

    await range.click();
    await dialog.locator('[data-day="2026-11-01"] button').click();
    await range.click();
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
        await page.goto('/admin/thesis-projects?tab=berkas-sidang');
        await page.getByRole('button', { name: 'Atur Periode Rekap' }).click();
        const form = page.locator('.fi-modal-window:visible').last();
        await form.getByRole('combobox', { name: /^Tanggal awal/ }).click();
        await expect(
            form.getByRole('dialog', { name: 'Tanggal awal', exact: true }),
        ).toBeVisible();
        await captureReport(page, testInfo, 'admin-kalender-periode');
        await pickAdminReportDate(form, 'Tanggal awal', '2096-04-14');
        const semester = form.locator('select').filter({
            has: page.locator('option[value="ganjil"]'),
        });
        await expect(semester).toHaveValue('genap');
        await expect(form.getByLabel('Tahun awal akademik')).toHaveValue(
            '2095',
        );
        await pickAdminReportDate(form, 'Tanggal awal', '2096-09-13');
        await expect(semester).toHaveValue('ganjil');
        await expect(form.getByLabel('Tahun awal akademik')).toHaveValue(
            '2096',
        );
        await semester.selectOption('genap');
        await pickAdminReportDate(form, 'Tanggal awal', '2096-09-14');
        await expect(semester).toHaveValue('genap');
        await pickAdminReportDate(form, 'Tanggal akhir', '2096-10-14');
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
        const period = form.getByLabel('Periode rekap', { exact: true });
        await period.selectOption({
            label: 'Semester Genap 2096/2097 (14/09/2096 - 14/10/2096)',
        });
        await expect(
            form.getByRole('combobox', { name: /^Dari tanggal/ }),
        ).toBeHidden();
        await form.getByRole('button', { name: 'Unduh Excel' }).click();
        await expect(form.getByRole('alert')).toHaveText(emptyReport);
        await expect(period.locator('option:checked')).toContainText(
            'Semester Genap 2096/2097',
        );
        await captureReport(page, testInfo, 'admin-rekap-kosong');

        await period.selectOption('');
        await pickAdminReportDate(form, 'Dari tanggal', '2026-11-01');
        await pickAdminReportDate(form, 'Sampai tanggal', '2026-11-30');
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
            await expect(
                dialog.getByRole('button', { name: /^Rentang tanggal sidang/ }),
            ).toBeHidden();
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

test('legacy panels can open the packet without a paper score shortcut', async ({
    browser,
    page,
}, testInfo) => {
    runArtisan([
        'tinker',
        '--execute=' +
            [
                "$student = App\\Models\\User::where('email', 'e2e-sidang@sita.test')->firstOrFail();",
                "$project = App\\Models\\ThesisProject::where('student_user_id', $student->id)->where('state', 'active')->firstOrFail();",
                "$project->latestSidangDefense->examiners()->where('role', 'examiner')->orderByDesc('order_no')->firstOrFail()->update(['role' => 'secondary_supervisor']);",
            ].join(' '),
    ]);
    const admin = await browser.newContext({
        storageState: authStatePath('admin'),
    });
    try {
        const adminPage = await admin.newPage();
        await adminPage.goto('/admin/thesis-projects?tab=berkas-sidang');
        const adminRow = adminPage
            .getByRole('row')
            .filter({ hasText: 'Sinta Sidang Terjadwal' });
        await expect(
            adminRow.getByRole('link', { name: 'Input Nilai', exact: true }),
        ).toHaveCount(0);
        await expect(
            adminRow.getByRole('link', { name: 'Unduh Word' }),
        ).toBeVisible();
        await adminRow.getByRole('link', { name: 'Lihat Berkas' }).click();
        await expect(
            adminPage.getByText(
                /Panel lain tetap mengikuti alur penilaian Dosen/,
            ),
        ).toBeVisible();
        await expect(
            adminPage.getByRole('button', { name: 'Simpan untuk Finalisasi' }),
        ).toHaveCount(0);
        await captureReport(adminPage, testInfo, 'admin-panel-lama', true);

        await page.goto('/kaprodi/sempro-sidang');
        await page
            .getByPlaceholder('Cari nama, NIM, judul, atau dosen...')
            .fill('Sinta Sidang Terjadwal');
        const headRow = page.getByRole('row').filter({
            has: page.getByRole('cell', { name: /^Sidang .*#/ }),
            hasText: 'Sinta Sidang Terjadwal',
        });
        await headRow.getByRole('button', { name: 'Aksi lainnya' }).click();
        await page
            .getByRole('menuitem', { name: 'Kelola berkas sidang' })
            .click();
        await expect(
            page.getByText(/Panel lain tetap mengikuti alur penilaian Dosen/),
        ).toBeVisible();
        await expect(page.getByLabel('Nilai penguji 2')).toHaveCount(0);
        await captureReport(page, testInfo, 'kaprodi-panel-lama', true);
    } finally {
        await admin.close();
    }
});
