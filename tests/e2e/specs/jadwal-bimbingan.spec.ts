import { expect, test, type Locator, type Page } from '@playwright/test';

import { daysFromNow, toDateTimeLocalString } from '../support/dates';
import { seedPlaywrightScenarios } from '../support/db';
import { authStatePath, thesisScenarios } from '../support/thesis-fixtures';

// Gaps left by bimbingan-chat.spec.ts, which already covers: mahasiswa request
// + dosen approve, reject with feedback, and approve of a recurring group.
// This spec covers reschedule, complete, cancel, rejecting a whole recurring
// group, and requesting from the second supervisor. It uses the dedicated
// two-supervisor student so it does not depend on the shared seeded accounts.
const student = thesisScenarios.twoSupervisors;

async function requestBimbingan(
    page: Page,
    {
        topic,
        lecturerName,
        requestedFor,
        recurringCount,
    }: {
        topic: string;
        lecturerName: string;
        requestedFor: string;
        recurringCount?: string;
    },
): Promise<void> {
    await page.goto('/mahasiswa/jadwal-bimbingan?open=ajukan');
    await expect(
        page.getByRole('dialog', { name: 'Ajukan Jadwal Bimbingan' }),
    ).toBeVisible();

    await page.getByLabel('Topik Bimbingan').fill(topic);
    await page.getByLabel('Dosen Tujuan').click();
    await page
        .getByRole('option', { name: new RegExp(lecturerName, 'i') })
        .click();
    await page.getByLabel('Tanggal & Waktu Preferensi').fill(requestedFor);

    if (recurringCount !== undefined) {
        await page.getByLabel('Jadwalkan Berulang').check();
        await page.getByLabel('Pola Pengulangan').click();
        await page
            .getByRole('option', { name: 'Mingguan', exact: true })
            .click();
        await page.getByLabel('Jumlah Pertemuan').click();
        await page
            .getByRole('option', {
                name: `${recurringCount} pertemuan`,
                exact: true,
            })
            .click();
    }

    await page.getByRole('button', { name: 'Kirim Permintaan' }).click();
    await expect(
        page.getByRole('dialog', { name: 'Ajukan Jadwal Bimbingan' }),
    ).not.toBeVisible();
}

// Pending request cards are plain containers without a role or label. The
// innermost container holding both the unique topic and the feedback field is
// the card of that request.
function pendingCard(page: Page, topic: string): Locator {
    return page
        .locator('div')
        .filter({ hasText: topic })
        .filter({ has: page.getByLabel(/^Feedback ke Mahasiswa/) })
        .last();
}

function historyRow(page: Page, topic: string): Locator {
    return page.getByRole('row').filter({ hasText: topic }).first();
}

test.describe('Jadwal bimbingan: mahasiswa request and dosen decisions', () => {
    test.describe.configure({ mode: 'serial' });

    test.beforeAll(() => {
        seedPlaywrightScenarios();
    });

    test('dosen reschedules a request, then marks the session completed', async ({
        browser,
    }) => {
        const topic = `Bimbingan jadwal ulang ${Date.now()}`;
        const location = 'Ruang Dosen Jadwal Ulang';
        const feedback = 'Waktu diundur karena ada rapat jurusan.';
        const newTime = toDateTimeLocalString(daysFromNow(6));

        const mahasiswaContext = await browser.newContext({
            storageState: authStatePath(student.studentKey),
        });
        const dosenContext = await browser.newContext({
            storageState: authStatePath('dosen1'),
        });

        try {
            const mahasiswaPage = await mahasiswaContext.newPage();
            const dosenPage = await dosenContext.newPage();

            await requestBimbingan(mahasiswaPage, {
                topic,
                lecturerName: student.supervisorNames[0],
                requestedFor: toDateTimeLocalString(daysFromNow(3)),
            });
            await expect(historyRow(mahasiswaPage, topic)).toContainText(
                'Menunggu Konfirmasi',
            );

            await dosenPage.goto('/dosen/jadwal-bimbingan');
            const card = pendingCard(dosenPage, topic);
            await expect(card).toBeVisible();
            await expect(card).toContainText(student.studentName);

            await card
                .getByLabel('Tanggal/Jam Konfirmasi atau Jadwal Ulang')
                .fill(newTime);
            await card.getByLabel('Lokasi').fill(location);
            await card.getByLabel(/^Feedback ke Mahasiswa/).fill(feedback);
            await Promise.all([
                dosenPage.waitForResponse(
                    (response) =>
                        response.request().method() === 'POST' &&
                        /\/dosen\/jadwal-bimbingan\/\d+\/decision$/.test(
                            response.url(),
                        ),
                ),
                card.getByRole('button', { name: 'Jadwalkan Ulang' }).click(),
            ]);

            await mahasiswaPage.goto('/mahasiswa/jadwal-bimbingan');
            const rescheduled = historyRow(mahasiswaPage, topic);
            await expect(rescheduled).toContainText('Terjadwal');
            await expect(rescheduled).toContainText(location);
            await expect(rescheduled).toContainText(feedback);

            // Upcoming sessions open a sheet with the complete action.
            await dosenPage.goto('/dosen/jadwal-bimbingan');
            await dosenPage
                .getByRole('button', { name: 'Buka kalender' })
                .click();
            await dosenPage
                .getByRole('button', { name: 'Daftar', exact: true })
                .click();
            await historyRow(dosenPage, topic).click();
            await dosenPage
                .getByRole('button', { name: 'Tandai Selesai' })
                .click();
            await expect(
                dosenPage.getByRole('button', { name: 'Tandai Selesai' }),
            ).toHaveCount(0);

            await mahasiswaPage.goto('/mahasiswa/jadwal-bimbingan');
            await mahasiswaPage
                .getByRole('button', { name: 'Selesai', exact: true })
                .click();
            await expect(historyRow(mahasiswaPage, topic)).toContainText(
                'Selesai',
            );
        } finally {
            await mahasiswaContext.close();
            await dosenContext.close();
        }
    });

    test('request to the second supervisor is approved, then cancelled by that dosen', async ({
        browser,
    }) => {
        const topic = `Bimbingan pembimbing dua ${Date.now()}`;
        const feedback = 'Disetujui, bawa draft bab tiga.';

        const mahasiswaContext = await browser.newContext({
            storageState: authStatePath(student.studentKey),
        });
        const firstSupervisorContext = await browser.newContext({
            storageState: authStatePath('dosen1'),
        });
        const secondSupervisorContext = await browser.newContext({
            storageState: authStatePath('dosen2'),
        });

        try {
            const mahasiswaPage = await mahasiswaContext.newPage();
            const firstSupervisorPage = await firstSupervisorContext.newPage();
            const secondSupervisorPage =
                await secondSupervisorContext.newPage();

            await requestBimbingan(mahasiswaPage, {
                topic,
                lecturerName: student.supervisorNames[1],
                requestedFor: toDateTimeLocalString(daysFromNow(4)),
            });

            // The request is addressed to Pembimbing 2 only.
            await firstSupervisorPage.goto('/dosen/jadwal-bimbingan');
            await expect(
                firstSupervisorPage.getByRole('heading', {
                    name: 'Permintaan Menunggu Konfirmasi',
                }),
            ).toBeVisible();
            await expect(firstSupervisorPage.getByText(topic)).toHaveCount(0);

            await secondSupervisorPage.goto('/dosen/jadwal-bimbingan');
            const card = pendingCard(secondSupervisorPage, topic);
            await expect(card).toBeVisible();
            await card.getByLabel(/^Feedback ke Mahasiswa/).fill(feedback);
            await card
                .getByRole('button', { name: 'Konfirmasi', exact: true })
                .click();

            await secondSupervisorPage
                .getByRole('button', { name: 'Buka kalender' })
                .click();
            await secondSupervisorPage
                .getByRole('button', { name: 'Daftar', exact: true })
                .click();
            await historyRow(secondSupervisorPage, topic).click();
            await secondSupervisorPage
                .getByRole('button', { name: 'Batalkan Jadwal' })
                .click();
            await expect(
                secondSupervisorPage.getByRole('button', {
                    name: 'Batalkan Jadwal',
                }),
            ).toHaveCount(0);

            await mahasiswaPage.goto('/mahasiswa/jadwal-bimbingan');
            await mahasiswaPage
                .getByRole('button', { name: 'Dibatalkan', exact: true })
                .click();
            await expect(historyRow(mahasiswaPage, topic)).toContainText(
                'Dibatalkan',
            );
        } finally {
            await mahasiswaContext.close();
            await firstSupervisorContext.close();
            await secondSupervisorContext.close();
        }
    });

    test('dosen rejects a whole recurring request group and mahasiswa sees every session rejected', async ({
        browser,
    }) => {
        const topic = `Bimbingan berulang ditolak ${Date.now()}`;
        const feedback =
            'Seri bimbingan ditunda sampai revisi bab dua selesai.';

        const mahasiswaContext = await browser.newContext({
            storageState: authStatePath(student.studentKey),
        });
        const dosenContext = await browser.newContext({
            storageState: authStatePath('dosen1'),
        });

        try {
            const mahasiswaPage = await mahasiswaContext.newPage();
            const dosenPage = await dosenContext.newPage();

            await requestBimbingan(mahasiswaPage, {
                topic,
                lecturerName: student.supervisorNames[0],
                requestedFor: toDateTimeLocalString(daysFromNow(5)),
                recurringCount: '3',
            });
            await expect(
                mahasiswaPage.getByRole('row').filter({ hasText: topic }),
            ).toHaveCount(3);

            await dosenPage.goto('/dosen/jadwal-bimbingan');
            const card = dosenPage
                .locator('div')
                .filter({ hasText: topic })
                .filter({ has: dosenPage.getByLabel(/^Feedback ke Mahasiswa/) })
                .last();
            await expect(card).toContainText('3 Pertemuan');
            await card.getByLabel(/^Feedback ke Mahasiswa/).fill(feedback);
            await card.getByRole('button', { name: 'Tolak Semua' }).click();

            await mahasiswaPage.goto('/mahasiswa/jadwal-bimbingan');
            await mahasiswaPage
                .getByRole('button', { name: 'Ditolak', exact: true })
                .click();
            const rejectedRows = mahasiswaPage
                .getByRole('row')
                .filter({ hasText: topic });
            await expect(rejectedRows).toHaveCount(3);
            await expect(rejectedRows.first()).toContainText(feedback);
        } finally {
            await mahasiswaContext.close();
            await dosenContext.close();
        }
    });
});
