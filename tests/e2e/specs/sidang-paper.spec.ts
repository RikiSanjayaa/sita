import {
    expect,
    test,
    type Locator,
    type Page,
    type Route,
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

async function openAdminRekap(page: Page, action: string): Promise<void> {
    await page.getByRole('button', { name: 'Rekap', exact: true }).click();
    await page.getByRole('menuitem', { name: action, exact: true }).click();
}

async function downloadWithBusyEvidence(
    page: Page,
    dialog: Locator,
    info: TestInfo,
    label: string,
): Promise<void> {
    let release: () => void = () => {};
    const gate = new Promise<void>((resolve) => {
        release = resolve;
    });
    let requests = 0;
    const handler = async (route: Route) => {
        requests++;
        await gate;
        await route.continue();
    };
    await page.route('**/rekap-nilai-sidang**', handler);
    const downloaded = page.waitForEvent('download');
    try {
        const button = dialog.getByRole('button', {
            name: 'Unduh Excel',
            exact: true,
        });
        await button.click();
        await expect(button).toBeDisabled();
        await expect(
            dialog
                .getByRole('status')
                .filter({ hasText: 'Menyiapkan file Excel...' }),
        ).toBeVisible();
        await captureReport(page, info, label);
        expect(requests).toBe(1);
    } finally {
        release();
    }
    try {
        expect((await downloaded).suggestedFilename()).toBe(
            'rekap-nilai-sidang.xlsx',
        );
    } finally {
        await page.unroute('**/rekap-nilai-sidang**', handler);
    }
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
            if (fullPage) {
                await page.evaluate(() => window.scrollTo(0, 0));
            }
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

async function attachPrintedPdf(
    page: Page,
    testInfo: TestInfo,
    name: string,
    expectedPages: number,
) {
    const pdf = await page.pdf({
        preferCSSPageSize: true,
        printBackground: true,
    });
    // Chromium emits uncompressed page dictionaries. Catch orphaned print pages.
    const pages = pdf.toString('latin1').match(/\/Type\s*\/Page\b/g) ?? [];
    expect(pages.length, `${name} print page count`).toBe(expectedPages);
    await testInfo.attach(name, { body: pdf, contentType: 'application/pdf' });
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
                window.scrollTo(0, 0);
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
    await page
        .getByRole('link', { name: 'Unduh Word Berita Acara', exact: true })
        .click();
    expect((await wordDownload).suggestedFilename()).toMatch(
        /^berita-acara-\d+\.docx$/,
    );
    const packetDownload = page.waitForEvent('download');
    await page
        .getByRole('link', { name: 'Unduh Paket Sidang (.docx)', exact: true })
        .click();
    const packetWord = await packetDownload;
    const packetWordPath = testInfo.outputPath('paket-sidang.docx');
    await packetWord.saveAs(packetWordPath);
    await testInfo.attach('paket-sidang.docx', {
        path: packetWordPath,
        contentType:
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    });

    const packetOpened = page.waitForEvent('popup');
    await page
        .getByRole('link', { name: 'Cetak / Simpan PDF', exact: true })
        .click();
    const packet = await packetOpened;
    await expect(packet.locator('article.paper')).toHaveCount(9);
    const packetUrl = packet.url();
    await expect(packet.locator('.letterhead img').first()).toBeVisible();
    await captureReport(packet, testInfo, 'cetak-paket');
    await packet.evaluate(() => {
        window.print = () =>
            document.body.setAttribute('data-print-requested', 'true');
    });
    await packet.getByRole('button', { name: 'Cetak / Simpan PDF' }).click();
    await expect(packet.locator('body')).toHaveAttribute(
        'data-print-requested',
        'true',
    );
    await attachPrintedPdf(packet, testInfo, 'paket-sidang.pdf', 9);
    await packet.close();
    for (const name of [
        'Berita Acara',
        'Catatan Perbaikan Penguji',
        'Daftar Hadir',
        'Formulir Penilaian Penguji',
        'Formulir Penilaian Pembimbing',
        'Lembar Total Penilaian',
        'Masukan untuk Pembimbing',
        'Pertanyaan dan Jawaban',
        'Catatan Perbaikan Pembimbing',
    ]) {
        const opened = page.waitForEvent('popup');
        await page
            .getByRole('link', { name: `Cetak ${name}`, exact: true })
            .click();
        const sheet = await opened;
        await expect(sheet.locator('article.paper')).toHaveCount(1);
        await attachPrintedPdf(sheet, testInfo, `${name}.pdf`, 1);
        await sheet.close();
    }

    finalizeDemoSidang();
    const finalPacket = await page.context().newPage();
    await finalPacket.goto(packetUrl);
    await expect(
        finalPacket.locator('[data-form="rekap-nilai"]'),
    ).toContainText('80');
    await attachPrintedPdf(finalPacket, testInfo, 'paket-final.pdf', 9);
    await finalPacket.close();
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
    await downloadWithBusyEvidence(
        page,
        dialog,
        testInfo,
        'kaprodi-rekap-proses',
    );
    await expect(dialog.getByText('Unduhan Excel dimulai.')).toBeVisible();
    await captureReport(page, testInfo, 'kaprodi-rekap-berhasil');
});

test('admin and panel lecturers can print through their own workspaces', async ({
    browser,
}, testInfo) => {
    for (const role of ['admin', 'dosen3'] as const) {
        const context = await browser.newContext({
            storageState: authStatePath(role),
        });
        try {
            const page = await context.newPage();
            if (role === 'admin') {
                await page.goto('/admin/thesis-projects?tab=berkas-sidang');
                const row = page
                    .getByRole('row')
                    .filter({ hasText: 'Sinta Sidang Terjadwal' });
                await expect(
                    row.getByRole('link', { name: 'Cetak / PDF', exact: true }),
                ).toBeVisible();
                await expect(
                    row.getByRole('link', { name: 'Unduh Word', exact: true }),
                ).toBeVisible();
                const inputScore = row.getByRole('link', {
                    name: 'Input Nilai',
                    exact: true,
                });
                const inputBounds = await inputScore.boundingBox();
                expect(inputBounds).not.toBeNull();
                expect(inputBounds!.x + inputBounds!.width).toBeLessThanOrEqual(
                    page.viewportSize()!.width,
                );
                await captureReport(page, testInfo, 'admin-daftar-cetak');
                await row
                    .getByRole('link', { name: 'Input Nilai', exact: true })
                    .click();
            } else {
                await page.goto('/dosen/seminar-proposal');
                await page
                    .getByRole('radio', { name: 'Sidang Akhir', exact: true })
                    .click();
                await page
                    .getByPlaceholder(/Cari nama/i)
                    .fill('Sinta Sidang Terjadwal');
                const row = page
                    .getByRole('row')
                    .filter({ hasText: 'Sinta Sidang Terjadwal' })
                    .filter({ hasText: /#1/ });
                await row.locator('td').first().click();
            }
            const print = page.getByRole('link', {
                name: 'Cetak / Simpan PDF',
                exact: true,
            });
            await expect(print).toBeVisible();
            await print.scrollIntoViewIfNeeded();
            await captureReport(page, testInfo, `${role}-aksi-cetak`);
            const opened = page.waitForEvent('popup');
            await print.click();
            const packet = await opened;
            await expect(packet.locator('article.paper')).toHaveCount(9);
            await expect(packet.locator('article.paper').first()).toContainText(
                'Sinta Sidang Terjadwal',
            );
            await packet.close();
        } finally {
            await context.close();
        }
    }
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
        await openAdminRekap(page, 'Atur Periode Rekap');
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

        await openAdminRekap(page, 'Ekspor Rekap Nilai');
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
        await downloadWithBusyEvidence(
            page,
            form,
            testInfo,
            'admin-rekap-proses',
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
            await dialog
                .getByRole('combobox', { name: 'Periode rekap' })
                .click();
            await kaprodiPage
                .getByRole('option', {
                    name: 'Semester Genap 2096/2097 (14/09/2096 - 14/10/2096)',
                    exact: true,
                })
                .click();
            await expect(
                dialog.getByRole('button', { name: /^Rentang tanggal sidang/ }),
            ).toBeHidden();
            await captureReport(
                kaprodiPage,
                testInfo,
                'kaprodi-periode-terpilih',
            );
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
        await expect(page.getByLabel('Nilai penguji 2')).toHaveCount(0);
        await captureReport(page, testInfo, 'kaprodi-panel-lama', true);
    } finally {
        await admin.close();
    }
});

test('Admin and Kaprodi see the affected student when a legacy panel cannot yet be exported', async ({
    browser,
}, info) => {
    finalizeDemoSidang();
    // Represent a historical final record with one examiner and two supervisors.
    runArtisan([
        'tinker',
        '--execute=' +
            "$student = App\\Models\\User::where('email', 'e2e-sidang@sita.test')->firstOrFail(); " +
            "$project = App\\Models\\ThesisProject::where('student_user_id', $student->id)->latest('id')->firstOrFail(); " +
            "$project->latestSidangDefense->examiners()->where('role', 'examiner')->orderByDesc('order_no')->firstOrFail()->update(['role' => 'secondary_supervisor']);",
    ]);
    for (const role of ['admin', 'kaprodi'] as const) {
        const context = await browser.newContext({
            storageState: authStatePath(role),
        });
        try {
            const page = await context.newPage();
            await page.clock.setFixedTime(
                new Date('2026-11-08T12:00:00+08:00'),
            );
            let downloads = 0;
            page.on('download', () => downloads++);
            if (role === 'admin') {
                await page.goto('/admin/thesis-projects?tab=berkas-sidang');
                await page
                    .getByRole('searchbox', { name: 'Search', exact: true })
                    .fill('Sinta Sidang Terjadwal');
                await openAdminRekap(page, 'Ekspor Rekap Nilai');
                const dialog = page.locator('.fi-modal-window:visible').last();
                await dialog
                    .getByLabel('Periode rekap', { exact: true })
                    .selectOption('');
                await pickAdminReportDate(dialog, 'Dari tanggal', '2026-11-01');
                await pickAdminReportDate(
                    dialog,
                    'Sampai tanggal',
                    '2026-11-30',
                );
                await dialog
                    .getByRole('button', { name: 'Unduh Excel', exact: true })
                    .click();
                await expect(dialog.getByRole('alert')).toContainText(
                    'Sinta Sidang Terjadwal',
                );
                await expect(dialog.getByRole('alert')).toContainText(
                    'bobot perlu dikonfirmasi ke prodi',
                );
                await captureReport(page, info, 'admin-rekap-panel-lama');
            } else {
                await page.goto('/kaprodi/sempro-sidang');
                await page
                    .getByPlaceholder('Cari nama, NIM, judul, atau dosen...')
                    .fill('Sinta Sidang Terjadwal');
                await page.getByRole('button', { name: 'Rekap Nilai' }).click();
                const dialog = page.getByRole('dialog');
                if (
                    await dialog
                        .getByRole('combobox', { name: 'Periode rekap' })
                        .isVisible()
                ) {
                    await dialog
                        .getByRole('combobox', { name: 'Periode rekap' })
                        .click();
                    await page
                        .getByRole('option', {
                            name: 'Rentang tanggal manual',
                            exact: true,
                        })
                        .click();
                }
                await dialog
                    .getByRole('button', { name: /^Rentang tanggal sidang/ })
                    .click();
                await dialog.locator('[data-day="2026-11-01"] button').click();
                await dialog.locator('[data-day="2026-11-30"] button').click();
                await dialog
                    .getByRole('button', { name: /^Rentang tanggal sidang/ })
                    .click();
                await dialog
                    .getByRole('button', { name: 'Unduh Excel', exact: true })
                    .click();
                await expect(dialog.getByRole('alert')).toContainText([
                    /Sinta Sidang Terjadwal.*2210511005/s,
                ]);
                await captureReport(page, info, 'kaprodi-rekap-panel-lama');
            }
            expect(downloads).toBe(0);
        } finally {
            await context.close();
        }
    }
});

test('Admin and Kaprodi correct a finalized No SK without reopening grades', async ({
    browser,
    page,
}, info) => {
    finalizeDemoSidang();
    const student = 'Sinta Sidang Terjadwal';
    const admin = await browser.newContext({
        storageState: authStatePath('admin'),
    });
    try {
        const adminPage = await admin.newPage();
        await adminPage.goto('/admin/thesis-projects?tab=berkas-sidang');
        const row = adminPage.getByRole('row').filter({ hasText: student });
        await row
            .getByRole('link', { name: 'Lihat Berkas', exact: true })
            .click();
        await adminPage
            .getByRole('button', { name: 'Koreksi No SK', exact: true })
            .click();
        const adminDialog = adminPage
            .getByRole('dialog')
            .filter({ hasText: 'Koreksi No SK' });
        await expect(adminDialog).toContainText(student);
        await expect(adminDialog).toContainText('Belum diisi');
        await adminDialog
            .getByRole('textbox', { name: 'No SK baru' })
            .fill('001/ADUM/UJI');
        await captureReport(adminPage, info, 'admin-konfirmasi-no-sk');
        await adminDialog
            .getByRole('button', { name: 'Batal', exact: true })
            .click();
        await expect(adminDialog).toBeHidden();
        await expect(
            adminPage.getByText('Belum ada koreksi No SK.'),
        ).toBeVisible();
        await adminPage
            .getByRole('button', { name: 'Koreksi No SK', exact: true })
            .click();
        await adminDialog
            .getByRole('textbox', { name: 'No SK baru' })
            .fill('001/ADUM/UJI');
        await adminDialog
            .getByRole('button', { name: 'Simpan koreksi', exact: true })
            .click();
        await expect(adminDialog).toBeHidden();
        await expect(
            adminPage.getByText('001/ADUM/UJI', { exact: true }),
        ).toBeVisible();
        await expect(
            adminPage.getByRole('button', { name: 'Simpan untuk Finalisasi' }),
        ).toBeDisabled();
        await captureReport(adminPage, info, 'admin-riwayat-no-sk', true);

        await page.goto('/kaprodi/sempro-sidang?status=completed');
        await page
            .getByPlaceholder('Cari nama, NIM, judul, atau dosen...')
            .fill(student);
        const kaprodiRow = page.getByRole('row').filter({
            has: page.getByRole('cell', { name: /^Sidang .*#/ }),
            hasText: student,
        });
        await kaprodiRow.getByRole('button', { name: 'Aksi lainnya' }).click();
        await page
            .getByRole('menuitem', { name: 'Kelola berkas sidang' })
            .click();
        await page
            .getByRole('button', { name: 'Koreksi No SK', exact: true })
            .click();
        const dialog = page.getByRole('dialog', { name: 'Koreksi No SK' });
        await expect(dialog).toContainText('001/ADUM/UJI');
        await dialog
            .getByRole('textbox', { name: 'No SK baru' })
            .fill('002/ADUM/UJI');
        await captureReport(page, info, 'kaprodi-konfirmasi-no-sk');
        await dialog
            .getByRole('button', { name: 'Simpan koreksi', exact: true })
            .click();
        await expect(dialog).toBeHidden();
        await expect(page.getByLabel('Nomor SK', { exact: true })).toHaveValue(
            '002/ADUM/UJI',
        );
        const history = page.locator('li').filter({ hasText: '002/ADUM/UJI' });
        await expect(history).toContainText('001/ADUM/UJI');
        await expect(page.getByText(/Nilai akhir 80/)).toBeVisible();
        await expect(
            page.getByLabel('Nilai penguji 1', { exact: true }),
        ).toHaveCount(0);
        await captureReport(page, info, 'kaprodi-riwayat-no-sk', true);
        await page.getByRole('link', { name: 'Kembali', exact: true }).click();
        await expect(page).toHaveURL(/status=completed/);
        await expect(
            page.getByPlaceholder('Cari nama, NIM, judul, atau dosen...'),
        ).toHaveValue(student);
    } finally {
        await admin.close();
    }
});
