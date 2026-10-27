import fs from 'node:fs/promises';

import {
    expect,
    test,
    type BrowserContext,
    type Locator,
    type Page,
    type TestInfo,
} from '@playwright/test';

import {
    defenseDocumentFiles,
    defenseDocumentFixture as fixture,
    newerFileName,
    replaceSelectedWorkspaceBytes,
    withDefenseDocuments,
    type DefenseType,
    type ExamFile,
} from '../support/defense-documents';
import { attachDocumentStates } from '../support/document-detail';
import { openRecoveryDefense } from '../support/recovery';

const examLabel = {
    sempro: 'Sempro / Sinopsis',
    sidang: 'Sidang Akhir',
} as const;

function documents(page: Page): Locator {
    return page.getByRole('dialog').getByRole('region', {
        name: 'Berkas ujian',
        exact: true,
    });
}

async function openAttempt(
    page: Page,
    type: DefenseType,
    attempt = 2,
): Promise<void> {
    await page.setViewportSize({ width: 1280, height: 900 });
    await openRecoveryDefense(
        page,
        fixture.studentName,
        examLabel[type],
        attempt,
    );
    await expect(
        page.getByRole('dialog').getByRole('heading', {
            name: fixture.title,
            exact: true,
        }),
    ).toBeVisible();
    await expect(documents(page)).toBeVisible();
}

async function selectOlderFiles(page: Page, type: DefenseType): Promise<void> {
    const files = defenseDocumentFiles(type);
    await page.goto('/mahasiswa/tugas-akhir');
    const section = page.locator('section').filter({
        hasText:
            type === 'sempro'
                ? 'Dokumen Seminar Proposal'
                : 'Dokumen Sidang Skripsi',
    });
    await expect(section).toBeVisible();
    for (const [index, file] of files.entries()) {
        const search = section.getByPlaceholder(
            index === 0 ? 'Cari file utama...' : 'Cari file lampiran...',
        );
        const picker = search.locator('..').locator('..');
        await search.fill(file.title);
        // The newer version is already available. Selecting the older file is
        // deliberate, not a side effect of whichever record happens to be latest.
        await expect(
            picker.getByRole('button').filter({ hasText: newerFileName(file) }),
        ).toBeVisible();
        const option = picker
            .getByRole('button')
            .filter({ hasText: file.fileName });
        await expect(option).toContainText(`v${file.version}`);
        await option.click();
    }
    const saved = page.waitForResponse(
        (response) =>
            response.request().method() === 'PATCH' &&
            new URL(response.url()).pathname.endsWith(`/${type}-documents`),
    );
    await section.getByRole('button', { name: 'Simpan Dokumen' }).click();
    expect([200, 302, 303]).toContain((await saved).status());
    await page.reload();
    for (const file of files) {
        await expect(
            section.getByText(file.fileName, { exact: true }).first(),
        ).toBeVisible();
    }
}

async function expectSelectedMetadata(
    page: Page,
    type: DefenseType,
): Promise<void> {
    const region = documents(page);
    const files = defenseDocumentFiles(type);
    await expect(region.getByRole('listitem')).toHaveCount(files.length);
    for (const file of files) {
        const row = region
            .getByRole('listitem')
            .filter({ hasText: file.fileName });
        await expect(
            row.getByText(file.kindLabel, { exact: true }),
        ).toBeVisible();
        await expect(
            row.getByText(file.fileName, { exact: true }),
        ).toBeVisible();
        await expect(row).toContainText(`Versi ${file.version}`);
        await expect(row).toContainText(`Diunggah ${fixture.uploadedAt}`);
        await expect(
            region.getByText(newerFileName(file), { exact: true }),
        ).toHaveCount(0);
    }
    for (const excluded of [
        fixture.priorFileName,
        fixture.annotationFileName,
        fixture.revisionFileName,
        'proposal-pemulihan-awal.pdf',
        'draft-riset-pemulihan.pdf',
    ]) {
        await expect(region.getByText(excluded, { exact: true })).toHaveCount(
            0,
        );
    }
}

async function expectSnapshotBytes(
    page: Page,
    context: BrowserContext,
    file: ExamFile,
): Promise<void> {
    const region = documents(page);
    const view = region.getByRole('link', {
        name: `Buka ${file.fileName}`,
        exact: true,
    });
    const downloadLink = region.getByRole('link', {
        name: `Unduh ${file.fileName}`,
        exact: true,
    });
    const viewUrl = new URL((await view.getAttribute('href'))!, page.url());
    const downloadUrl = new URL(
        (await downloadLink.getAttribute('href'))!,
        page.url(),
    );
    expect(viewUrl.pathname).toMatch(
        /^\/files\/thesis-documents\/\d+\/download$/,
    );
    expect(viewUrl.pathname).toBe(downloadUrl.pathname);
    expect(viewUrl.searchParams.get('inline')).toBe('1');
    expect(downloadUrl.searchParams.has('inline')).toBe(false);

    const navigation = context.waitForEvent('request', {
        predicate: (request) =>
            request.isNavigationRequest() && request.url() === viewUrl.href,
    });
    await view.click();
    await navigation;
    // Read the exact inline URL requested by Buka, with the examiner's session.
    // PDF viewer DOM and headless PDF support differ between Chromium builds.
    const response = await context.request.get(viewUrl.href);
    expect(response.ok()).toBe(true);
    expect(response.headers()['content-type']).toContain('application/pdf');
    expect(response.headers()['content-disposition']).toContain('inline');
    expect(await response.body()).toEqual(file.bytes);
    await Promise.all(
        context
            .pages()
            .filter((opened) => opened !== page)
            .map((opened) => opened.close()),
    );

    const downloadPromise = page.waitForEvent('download');
    await downloadLink.click();
    const download = await downloadPromise;
    expect(download.suggestedFilename()).toBe(file.fileName);
    expect(await download.failure()).toBeNull();
    const savedPath = await download.path();
    expect(savedPath).not.toBeNull();
    expect(await fs.readFile(savedPath!)).toEqual(file.bytes);
}

async function captureState(
    page: Page,
    info: TestInfo,
    name: string,
): Promise<void> {
    await page.clock.setFixedTime(new Date('2026-10-26T08:00:00Z'));
    for (const width of [1280, 390]) {
        for (const theme of ['light', 'dark'] as const) {
            await page.setViewportSize({ width, height: 900 });
            await page.emulateMedia({
                colorScheme: theme,
                reducedMotion: 'reduce',
            });
            await page.evaluate((value) => {
                localStorage.setItem('appearance', value);
                document.documentElement.classList.toggle(
                    'dark',
                    value === 'dark',
                );
                document.documentElement.style.colorScheme = value;
            }, theme);
            await page.evaluate(() => document.fonts.ready);
            await page.evaluate(
                () =>
                    new Promise<void>((resolve) =>
                        requestAnimationFrame(() =>
                            requestAnimationFrame(() => resolve()),
                        ),
                    ),
            );
            expect(
                await page.evaluate(
                    () =>
                        document.documentElement.scrollWidth <=
                        window.innerWidth,
                ),
            ).toBe(true);
            const dialog = page.getByRole('dialog');
            if (await dialog.count()) {
                // Visibility includes the slide-in animation. Check the final
                // position, not an intermediate transform outside the viewport.
                await expect(async () => {
                    const bounds = await dialog.boundingBox();
                    expect(bounds).not.toBeNull();
                    expect(bounds!.x).toBeGreaterThanOrEqual(0);
                    expect(bounds!.x + bounds!.width).toBeLessThanOrEqual(
                        width + 1,
                    );
                }).toPass({ timeout: 10_000 });
            }
            const screenshotName = `${name}-${width}-${theme}.png`;
            const screenshotPath = info.outputPath(screenshotName);
            await page.screenshot({
                path: screenshotPath,
                fullPage: true,
                animations: 'disabled',
                caret: 'hide',
            });
            await info.attach(screenshotName, {
                path: screenshotPath,
                contentType: 'image/png',
            });
        }
    }
}

async function expectChatContext(
    page: Page,
    type: DefenseType,
    info: TestInfo,
): Promise<void> {
    await page.setViewportSize({ width: 1280, height: 900 });
    const link = page.getByRole('dialog').getByRole('link', {
        name: type === 'sempro' ? 'Chat grup ujian' : 'Chat pribadi mahasiswa',
        exact: true,
    });
    const destination = new URL((await link.getAttribute('href'))!, page.url());
    expect(destination.pathname).toBe('/dosen/pesan');
    if (type === 'sempro') {
        expect(destination.searchParams.get('thread')).toMatch(/^\d+$/);
        await link.click();
        await expect(page).toHaveURL(destination.href);
        await expect(
            page.getByText(fixture.groupMessage, { exact: true }).last(),
        ).toBeVisible();
        return;
    }
    expect(destination.searchParams.get('recipient')).toMatch(/^\d+$/);
    await link.click();
    await expect(page).toHaveURL(destination.href);
    const recipient = page.getByPlaceholder(fixture.studentName, {
        exact: true,
    });
    await expect(recipient).toBeVisible();
    const start = page.getByRole('button', { name: 'Mulai chat pribadi' });
    await expect(start).toBeEnabled();
    await captureState(page, info, 'sidang-chat-recipient');
    await page.setViewportSize({ width: 1280, height: 900 });
    const create = page.waitForResponse(
        (response) =>
            response.request().method() === 'POST' &&
            new URL(response.url()).pathname === '/dosen/pesan/private',
    );
    await start.click();
    expect([200, 302, 303]).toContain((await create).status());
    await expect(page).toHaveURL((url) => url.searchParams.has('thread'));
    await expect(
        page
            .getByRole('link', { name: fixture.studentName, exact: true })
            .last(),
    ).toBeVisible();
    await expect(page.getByPlaceholder('Tulis pesan...')).toBeVisible();
    await openAttempt(page, type);
    const existing = page.getByRole('dialog').getByRole('link', {
        name: 'Chat pribadi mahasiswa',
        exact: true,
    });
    const existingDestination = new URL(
        (await existing.getAttribute('href'))!,
        page.url(),
    );
    expect(existingDestination.searchParams.get('thread')).toMatch(/^\d+$/);
    expect(existingDestination.searchParams.has('recipient')).toBe(false);
    await existing.click();
    await expect(page).toHaveURL(existingDestination.href);
    await expect(
        page
            .getByRole('link', { name: fixture.studentName, exact: true })
            .last(),
    ).toBeVisible();
}

async function expectSnapshotDetail(
    page: Page,
    file: ExamFile,
    info: TestInfo,
    type: DefenseType,
): Promise<void> {
    await page.setViewportSize({ width: 1280, height: 900 });
    const attemptUrl = page.url();
    expect(new URL(attemptUrl).searchParams.get('defense')).toMatch(/^\d+$/);
    const filename = documents(page).getByRole('link', {
        name: `Detail ${file.fileName}`,
        exact: true,
    });
    const detailUrl = await filename.getAttribute('href');
    await filename.click();
    await expect(page).toHaveURL(new URL(detailUrl!, attemptUrl).href);
    await expect(
        page.getByRole('heading', { name: 'Detail dokumen', exact: true }),
    ).toBeVisible();
    await expect(
        page.getByText(file.fileName, { exact: true }).first(),
    ).toBeVisible();
    await expect(
        page.getByRole('textbox', { name: 'Catatan dosen' }),
    ).toHaveCount(0);
    await expect(
        page.getByRole('button', { name: 'Setujui', exact: true }),
    ).toHaveCount(0);
    await attachDocumentStates(
        page,
        info,
        `${type}-snapshot-detail-${file.category}`,
    );
    const workspaceLink = page.locator('a[href*="/dosen/dokumen-revisi/"]');
    await expect(workspaceLink).toHaveCount(1);
    await workspaceLink.click();
    await expect(page).toHaveURL(/\/dosen\/dokumen-revisi\/\d+$/);
    await expect(
        page.getByText(file.fileName, { exact: true }).first(),
    ).toBeVisible();
    await expect(
        page.getByText(
            'Versi ini telah digantikan. Beri review pada versi terbaru.',
        ),
    ).toBeVisible();
    await expect(
        page.getByRole('textbox', { name: 'Catatan dosen' }),
    ).toHaveCount(0);
    await page.goBack();
    await expect(page).toHaveURL(new URL(detailUrl!, attemptUrl).href);
    await page
        .getByRole('navigation', { name: 'Navigasi dokumen', exact: true })
        .getByRole('link', { name: /Kembali/ })
        .click();
    const original = new URL(attemptUrl);
    await expect(page).toHaveURL(
        (url) =>
            url.pathname === original.pathname &&
            ['defense', 'type', 'search'].every(
                (key) =>
                    url.searchParams.get(key) ===
                    original.searchParams.get(key),
            ),
    );
    await expect(documents(page)).toBeVisible();
    await expect(
        documents(page).getByRole('link', {
            name: `Detail ${file.fileName}`,
            exact: true,
        }),
    ).toBeVisible();
}

for (const type of ['sempro', 'sidang'] as const) {
    test(`${type}: examiner opens exactly the older files the student selected`, async ({
        browser,
    }, info) => {
        test.setTimeout(240_000);
        await withDefenseDocuments(
            browser,
            type,
            async ({ student, examiner, examinerContext }) => {
                await selectOlderFiles(student, type);
                // An accidental workspace URL would now return different bytes.
                replaceSelectedWorkspaceBytes(type);
                await openAttempt(examiner, type);
                await expectSelectedMetadata(examiner, type);
                for (const file of defenseDocumentFiles(type)) {
                    await expectSnapshotBytes(examiner, examinerContext, file);
                    await expectSnapshotDetail(examiner, file, info, type);
                }
                await captureState(
                    examiner,
                    info,
                    `${type}-documents-populated`,
                );
                await expectChatContext(examiner, type, info);
                await openAttempt(examiner, type, 1);
                await expect(
                    documents(examiner).getByRole('listitem'),
                ).toHaveCount(1);
                await expect(
                    documents(examiner).getByRole('link', {
                        name: `Buka ${fixture.priorFileName}`,
                        exact: true,
                    }),
                ).toBeVisible();
                for (const file of defenseDocumentFiles(type)) {
                    await expect(
                        documents(examiner).getByText(file.fileName, {
                            exact: true,
                        }),
                    ).toHaveCount(0);
                }
            },
        );
    });

    test(`${type}: unselected attempt stays explicitly empty despite workspace and previous-attempt files`, async ({
        browser,
    }, info) => {
        test.setTimeout(120_000);
        await withDefenseDocuments(browser, type, async ({ examiner }) => {
            await openAttempt(examiner, type);
            await expect(
                documents(examiner).getByText('Belum ada berkas ujian.', {
                    exact: true,
                }),
            ).toBeVisible();
            await expect(documents(examiner).getByRole('listitem')).toHaveCount(
                0,
            );
            await expect(documents(examiner).getByRole('link')).toHaveCount(0);
            await captureState(examiner, info, `${type}-documents-empty`);
            await examiner.reload();
            await openAttempt(examiner, type);
            await expect(
                documents(examiner).getByText('Belum ada berkas ujian.', {
                    exact: true,
                }),
            ).toBeVisible();
        });
    });
}
