import { execFileSync } from 'node:child_process';

import { expect, test } from '@playwright/test';

import {
    playwrightEnvironment,
    repositoryRoot,
    runArtisan,
    seedPlaywrightScenarios,
} from '../support/db';
import { authStatePath, thesisScenarios } from '../support/thesis-fixtures';

// Covers issue #27: chat of a completed thesis moves to a read-only "Selesai"
// section during the grace period, and sita:archive-completed-theses deletes
// mentorship data after it while the final manuscript, the bimbingan count and
// the last activity stay. Both students come from PlaywrightScenarioSeeder:
// `completedInGrace` finished 10 days ago, `completedPastGrace` 45 days ago.
const inGrace = thesisScenarios.completedInGrace;
const pastGrace = thesisScenarios.completedPastGrace;

function tinker(code: string): string {
    return execFileSync('php', ['artisan', 'tinker', '--execute', code], {
        cwd: repositoryRoot,
        env: playwrightEnvironment(),
        encoding: 'utf8',
    }).trim();
}

test.describe('arsip tugas akhir', () => {
    test.describe.configure({ mode: 'serial' });

    test.beforeAll(() => {
        seedPlaywrightScenarios();
    });

    test.describe('dosen', () => {
        test.use({ storageState: authStatePath('dosen1') });

        test('chat tugas akhir yang selesai ada di bagian Selesai yang diciutkan dan read-only', async ({
            page,
        }) => {
            await page.goto('/dosen/pesan');

            const section = page.getByTestId('completed-threads');
            const header = section.getByRole('button', {
                name: /^Selesai \(\d+\)/,
            });
            await expect(header).toBeVisible();
            await expect(header).toHaveAttribute('aria-expanded', 'false');
            await expect(section.getByText(inGrace.studentName)).toHaveCount(0);

            await header.click();
            await expect(header).toHaveAttribute('aria-expanded', 'true');
            await section.getByText(inGrace.studentName).click();

            await expect(
                page.getByText(
                    /sudah selesai\. Percakapan ini hanya dapat dibaca/,
                ),
            ).toBeVisible();
            await expect(page.getByPlaceholder('Tulis pesan...')).toHaveCount(
                0,
            );
        });
    });

    test.describe('mahasiswa dalam masa tenggang', () => {
        test.use({ storageState: authStatePath(inGrace.studentKey) });

        test('melihat chat read-only dan data tidak tersentuh command arsip', async ({
            page,
        }) => {
            await page.goto('/mahasiswa/pesan');

            await expect(
                page.getByText(
                    /sudah selesai\. Percakapan ini hanya dapat dibaca/,
                ),
            ).toBeVisible();
            await expect(page.getByPlaceholder('Tulis pesan...')).toHaveCount(
                0,
            );
            await expect(
                page.getByText('bab 4 sudah saya kirim').first(),
            ).toBeVisible();

            runArtisan(['sita:archive-completed-theses']);

            await page.reload();
            await expect(
                page.getByText('bab 4 sudah saya kirim').first(),
            ).toBeVisible();
            await page.goto('/mahasiswa/jadwal-bimbingan');
            await expect(
                page.getByTestId('archived-mentorship-notice'),
            ).toHaveCount(0);
        });
    });

    test.describe('setelah masa tenggang', () => {
        test.use({ storageState: authStatePath('dosen1') });

        test('arsip menghapus data bimbingan, angka progres dan naskah akhir tetap', async ({
            browser,
            page,
        }) => {
            test.setTimeout(240_000);
            // Re-seed: the previous test already ran the command once for this student.
            seedPlaywrightScenarios();

            const progress = async () => {
                await page.goto('/progres-mahasiswa?search=Arif+Lulus');
                const row = page
                    .locator('tbody tr')
                    .filter({ hasText: pastGrace.studentName });
                await expect(row).toHaveCount(1);
                return {
                    lastActivity: (
                        await row
                            .locator('td[data-label="Aktivitas terakhir"] p')
                            .innerText()
                    ).trim(),
                    inactive: await row.getByText('Tidak aktif').count(),
                };
            };
            const bimbinganCount = async () => {
                await page.goto('/dosen/mahasiswa-bimbingan');
                const row = page
                    .locator('tr')
                    .filter({ hasText: pastGrace.studentName })
                    .first();
                return (
                    await row
                        .getByText(/\d+ bimbingan/)
                        .first()
                        .innerText()
                ).trim();
            };

            const before = {
                progress: await progress(),
                count: await bimbinganCount(),
            };
            expect(before.count).toBe('2 bimbingan');
            expect(before.progress.inactive).toBe(1);

            const finalId = tinker(
                "echo App\\Models\\ThesisDocument::query()->where('kind', 'final_manuscript')->whereHas('project.student', fn ($q) => $q->where('email', 'e2e-lulus-lama@sita.test'))->value('id');",
            );
            expect(finalId).toMatch(/^\d+$/);

            runArtisan(['sita:archive-completed-theses']);
            // Running it twice must not fail or delete anything more.
            runArtisan(['sita:archive-completed-theses']);

            const after = {
                progress: await progress(),
                count: await bimbinganCount(),
            };
            expect(after.count).toBe(before.count);
            expect(after.progress.lastActivity).toBe(
                before.progress.lastActivity,
            );
            expect(after.progress.inactive).toBe(0);

            const student = await browser.newContext({
                storageState: authStatePath(pastGrace.studentKey),
            });
            try {
                const studentPage = await student.newPage();
                await studentPage.goto('/mahasiswa/jadwal-bimbingan');
                await expect(
                    studentPage.getByText('Data bimbingan sudah diarsipkan'),
                ).toBeVisible();
                await studentPage.goto('/mahasiswa/upload-dokumen');
                await expect(
                    studentPage.getByText('Data bimbingan sudah diarsipkan'),
                ).toBeVisible();
                await studentPage.goto('/mahasiswa/pesan');
                await expect(
                    studentPage.getByText('Belum ada percakapan'),
                ).toBeVisible();
                await expect(
                    studentPage.getByText('bab 4 sudah saya kirim'),
                ).toHaveCount(0);

                const download = await studentPage.request.get(
                    `/files/thesis-documents/${finalId}/download`,
                );
                expect(download.status()).toBe(200);
                expect(await download.text()).toBe('final manuscript');
            } finally {
                await student.close();
            }
        });
    });
});
