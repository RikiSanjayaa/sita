import { readFileSync } from 'node:fs';
import path from 'node:path';

import {
    expect,
    test,
    type Browser,
    type BrowserContext,
    type Locator,
    type Page,
} from '@playwright/test';

import {
    daysFromNow,
    pickCalendarDay,
    toDateTimeLocalString,
} from '../support/dates';
import {
    attachStageScreenshot,
    goldenPathStudent,
    newGoldenStudentContext,
    openAdminProject,
    openWorkflowAction,
    pickFilamentSelect,
    removeGoldenPathStudent,
    seedGoldenPathStudent,
} from '../support/golden-path';
import { authStatePath, thesisFixtures } from '../support/thesis-fixtures';

// Gladi demo (issue #39): one fresh student walks from the title proposal to
// graduation through every role, in separate browser contexts, using only what
// each role can do in the UI. Run it before every demo:
//   npx playwright test tests/e2e/specs/golden-path.spec.ts
// Every transition under test is a click or form entry. The only setup is the
// student itself (GoldenPathScenarioSeeder); lecturers, kaprodi and admin are
// the shared seeded accounts.
const fixtureFile = path.join(
    process.cwd(),
    thesisFixtures.thesisDocumentUploadPath,
);
const attachmentName = 'lampiran-sempro-gladi.pdf';
const attachment = {
    name: attachmentName,
    mimeType: 'application/pdf',
    buffer: readFileSync(fixtureFile),
};

const lecturers = {
    budi: { key: 'dosen1', name: 'Dr. Budi Santoso, M.Kom.', query: 'Budi' },
    ratna: { key: 'dosen2', name: 'Dr. Ratna Kusuma, M.Kom.', query: 'Ratna' },
    ahmad: {
        key: 'dosen3',
        name: 'Prof. Ahmad Hidayat, Ph.D.',
        query: 'Ahmad',
    },
    dewi: { key: 'dosen4', name: 'Dr. Dewi Lestari, M.T.', query: 'Dewi' },
    fajar: { key: 'dosen5', name: 'Dr. Fajar Nugraha, M.Kom.', query: 'Fajar' },
} as const;

const thesis = {
    titleId:
        'Pelacakan Kemajuan Skripsi Berbasis Peristiwa untuk Program Studi',
    titleEn: 'Event-Based Thesis Progress Tracking for a Study Program',
    summary:
        'Proposal gladi demo ini merancang pelacakan kemajuan skripsi yang mencatat setiap peristiwa pembimbingan dan ujian sebagai riwayat yang dapat diaudit.',
    semproLocation: 'Ruang Seminar Gladi Demo',
    sidangLocation: 'Ruang Sidang Gladi Demo',
    revisionItems: [
        {
            location: 'Bab 1, hlm. 3',
            description: 'Rumusan masalah belum dapat diukur.',
        },
        {
            location: 'Bab 3, hlm. 12',
            description: 'Tambahkan metrik evaluasi yang dipakai.',
        },
    ],
};

type Cast = Record<string, BrowserContext>;

async function newStaffContext(
    browser: Browser,
    key: Parameters<typeof authStatePath>[0],
): Promise<BrowserContext> {
    return browser.newContext({ storageState: authStatePath(key) });
}

function studentRowIn(page: Page, studentName: string): Locator {
    return page.locator('tr').filter({ hasText: studentName }).first();
}

async function pickLecturer(
    page: Page,
    inputIndex: number,
    query: string,
    lecturerName: string | RegExp,
): Promise<void> {
    await page
        .getByRole('dialog')
        .getByPlaceholder('Ketik min. 2 karakter...')
        .nth(inputIndex)
        .fill(query);
    await page.getByRole('button', { name: lecturerName }).click();
}

async function chooseStudentInDialog(
    page: Page,
    studentName: string,
): Promise<void> {
    const dialog = page.getByRole('dialog');
    const option = dialog.locator('option').filter({ hasText: studentName });
    await dialog
        .getByRole('combobox')
        .first()
        .selectOption(await option.elementHandle());
}

async function openDefenseSheet(
    page: Page,
    studentName: string,
    listLabel: 'Sempro / Sinopsis' | 'Sidang Akhir',
): Promise<void> {
    await page.goto('/dosen/seminar-proposal');
    await page.waitForLoadState('networkidle');
    await page.getByRole('radio', { name: listLabel, exact: true }).click();
    await page.waitForLoadState('networkidle');
    await page.getByPlaceholder(/cari nama/i).fill(studentName);

    // The calendar grid also names the student; only the exam list shows the attempt ("#1").
    const row = page
        .getByRole('row')
        .filter({ hasText: studentName })
        .filter({ hasText: /#\d+/ })
        .first();
    await expect(row).toBeVisible();
    await expect(async () => {
        await row.locator('td').first().click();
        await expect(page.getByRole('dialog')).toBeVisible({ timeout: 2_000 });
    }).toPass({ timeout: 15_000 });
}

async function submitDecision(
    page: Page,
    {
        decisionLabel,
        score,
        decisionNotes,
        revisionNotes,
        revisionItems = [],
    }: {
        decisionLabel: RegExp;
        score?: string;
        decisionNotes: string;
        revisionNotes?: string;
        revisionItems?: Array<{ location: string; description: string }>;
    },
): Promise<void> {
    await page.getByRole('button', { name: 'Input Keputusan Saya' }).click();
    await page.getByRole('button', { name: decisionLabel }).click();

    if (score === undefined) {
        // Non-OBE sempro is decision-only: the score field is not rendered.
        await expect(page.getByLabel(/^Nilai/)).toHaveCount(0);
    } else {
        await page.getByLabel(/^Nilai/).fill(score);
    }

    await page.getByLabel('Catatan Keputusan').fill(decisionNotes);

    if (revisionNotes !== undefined) {
        await page.getByLabel(/^Catatan Revisi/).fill(revisionNotes);
    }

    for (const [index, item] of revisionItems.entries()) {
        await page.getByRole('button', { name: 'Tambah Butir' }).click();
        await page.getByLabel(`Butir ${index + 1}: lokasi`).fill(item.location);
        await page
            .getByRole('textbox', { name: 'Uraian' })
            .nth(index)
            .fill(item.description);
    }

    await Promise.all([
        page.waitForResponse(
            (response) =>
                response.request().method() === 'POST' &&
                /\/dosen\/seminar-proposal\/\d+\/decision$/.test(
                    response.url(),
                ),
        ),
        page.getByRole('button', { name: 'Submit Keputusan' }).click(),
    ]);
}

async function expectAwaitingFinalization(
    page: Page,
    studentName: string,
    listLabel: 'Sempro / Sinopsis' | 'Sidang Akhir',
): Promise<void> {
    await page.goto('/dosen/seminar-proposal');
    await page.getByRole('radio', { name: listLabel, exact: true }).click();
    await page.getByPlaceholder(/cari nama/i).fill(studentName);
    await expect(
        page
            .getByRole('row')
            .filter({ hasText: studentName })
            .filter({ hasText: /#\d+/ })
            .first(),
    ).toContainText('Menunggu Finalisasi');
}

async function openDosenGroupThread(
    page: Page,
    studentName: string,
    filterLabel: string,
): Promise<void> {
    await page.goto('/dosen/pesan');

    await page.getByRole('button', { name: 'Grup', exact: true }).click();
    const searchInput = page.getByPlaceholder('Cari grup...');
    await searchInput.fill(studentName);
    await searchInput.locator('..').locator('..').getByRole('button').click();
    await page.getByRole('radio', { name: new RegExp(filterLabel) }).click();
    await page
        .locator('button')
        .filter({ hasText: studentName })
        .filter({ hasText: filterLabel })
        .first()
        .click();
}

async function requestBimbingan(
    page: Page,
    topic: string,
    lecturerName: string,
    requestedFor: string,
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
    await page.getByRole('button', { name: 'Kirim Permintaan' }).click();
    await expect(
        page.getByRole('dialog', { name: 'Ajukan Jadwal Bimbingan' }),
    ).not.toBeVisible();
}

function bimbinganRow(page: Page, topic: string): Locator {
    return page.getByRole('row').filter({ hasText: topic }).first();
}

async function approveBimbingan(page: Page, topic: string): Promise<void> {
    await page.goto('/dosen/jadwal-bimbingan');

    const card = page
        .getByRole('list', { name: 'Permintaan menunggu konfirmasi' })
        .getByRole('listitem')
        .filter({ hasText: topic });
    await expect(card).toBeVisible();
    await card.getByRole('button', { name: 'Tanggapi' }).click();
    await card
        .getByLabel(/^Feedback ke Mahasiswa/)
        .fill('Disetujui, bawa draft bab yang dibahas.');
    await card.getByRole('button', { name: 'Konfirmasi', exact: true }).click();
}

async function completeBimbingan(page: Page, topic: string): Promise<void> {
    await page.goto('/dosen/jadwal-bimbingan');
    const calendarToggle = page.getByRole('button', {
        name: /^(Buka|Ciutkan) kalender$/,
    });
    await expect(calendarToggle).toBeVisible();
    if ((await calendarToggle.getAttribute('aria-expanded')) === 'false') {
        await calendarToggle.click();
    }
    await page.getByRole('button', { name: 'Daftar', exact: true }).click();
    await bimbinganRow(page, topic).click();
    await page.getByRole('button', { name: 'Tandai Selesai' }).click();
    await expect(
        page.getByRole('button', { name: 'Tandai Selesai' }),
    ).toHaveCount(0);
}

test.describe('Golden path: from title proposal to graduation', () => {
    test.beforeAll(() => {
        seedGoldenPathStudent();
    });

    // Pass or fail, the student and everything it owns go away, so the other
    // specs see the same users and lists as before this one ran.
    test.afterAll(() => {
        removeGoldenPathStudent();
    });

    test('one student passes every role from the title proposal until the title is public', async ({
        browser,
    }, testInfo) => {
        test.setTimeout(15 * 60 * 1000);

        const cast: Cast = {};
        const student = goldenPathStudent.name;
        const stamp = Date.now();
        const nextMonth = new Date();
        nextMonth.setMonth(nextMonth.getMonth() + 1, 15);

        try {
            cast.student = await newGoldenStudentContext(browser);
            cast.admin = await newStaffContext(browser, 'superadmin');
            cast.kaprodi = await newStaffContext(browser, 'kaprodi');
            for (const lecturer of Object.values(lecturers)) {
                cast[lecturer.key] = await newStaffContext(
                    browser,
                    lecturer.key,
                );
            }
            for (const context of Object.values(cast)) {
                context.setDefaultTimeout(15_000);
                context.setDefaultNavigationTimeout(30_000);
            }

            const studentPage = await cast.student.newPage();
            const adminPage = await cast.admin.newPage();
            const kaprodiPage = await cast.kaprodi.newPage();
            const pageOf = {
                budi: await cast[lecturers.budi.key].newPage(),
                ratna: await cast[lecturers.ratna.key].newPage(),
                ahmad: await cast[lecturers.ahmad.key].newPage(),
                dewi: await cast[lecturers.dewi.key].newPage(),
                fajar: await cast[lecturers.fajar.key].newPage(),
            };

            await test.step('1. Mahasiswa submits the title, the proposal and a discussion lecturer', async () => {
                await studentPage.goto('/mahasiswa/tugas-akhir');
                await studentPage
                    .getByLabel('Judul Skripsi (Bahasa Indonesia)')
                    .fill(thesis.titleId);
                await studentPage
                    .getByLabel('Judul Skripsi (Bahasa Inggris)')
                    .fill(thesis.titleEn);
                await studentPage
                    .getByLabel('Ringkasan Naskah Proposal')
                    .fill(thesis.summary);

                const discussionPicker = studentPage
                    .getByText('Dosen Diskusi Proposal', { exact: true })
                    .locator('..');
                await discussionPicker
                    .getByPlaceholder('Ketik min. 2 karakter...')
                    .fill(lecturers.budi.query);
                await discussionPicker.getByText(lecturers.budi.name).click();
                await expect(
                    discussionPicker.getByText(lecturers.budi.name),
                ).toBeVisible();

                await studentPage
                    .getByLabel('File Naskah Proposal (PDF)')
                    .setInputFiles(fixtureFile);
                await studentPage
                    .getByRole('button', { name: 'Ajukan Sekarang' })
                    .click();

                await expect(
                    studentPage.getByText(thesis.titleId).first(),
                ).toBeVisible();
                await expect(
                    studentPage.getByText('Menunggu Persetujuan').first(),
                ).toBeVisible();
                await attachStageScreenshot(
                    testInfo,
                    studentPage,
                    '1-mahasiswa-judul-diajukan',
                );
            });

            await test.step('2. Admin approves the title', async () => {
                await adminPage.goto(thesisFixtures.adminProjectsPath);
                await expect(
                    studentRowIn(adminPage, student).first(),
                ).toContainText('Review Judul');

                await openAdminProject(adminPage, student);
                const modal = await openWorkflowAction(
                    adminPage,
                    'Setujui Judul',
                );
                await modal.getByRole('button', { name: 'Submit' }).click();
                await expect(modal).toBeHidden();

                await studentPage.goto('/mahasiswa/tugas-akhir');
                await expect(
                    studentPage.getByText('Judul Disetujui').first(),
                ).toBeVisible();
                await attachStageScreenshot(
                    testInfo,
                    studentPage,
                    '2-mahasiswa-judul-disetujui',
                );
            });

            await test.step('3. Kaprodi schedules the sempro with two examiners', async () => {
                await kaprodiPage.goto('/kaprodi/sempro-sidang');
                await kaprodiPage
                    .getByRole('button', { name: /^Jadwalkan Sempro/ })
                    .click();

                const dialog = kaprodiPage.getByRole('dialog', {
                    name: /^Jadwalkan Sempro/,
                });
                await expect(dialog).toBeVisible();

                await chooseStudentInDialog(kaprodiPage, student);
                await pickCalendarDay(dialog, nextMonth);
                await dialog.locator('input[type="time"]').fill('09:30');
                await dialog
                    .getByPlaceholder('Ruang ujian / tautan meeting')
                    .fill(thesis.semproLocation);
                await pickLecturer(
                    kaprodiPage,
                    0,
                    lecturers.ahmad.query,
                    lecturers.ahmad.name,
                );
                await pickLecturer(
                    kaprodiPage,
                    1,
                    lecturers.dewi.query,
                    lecturers.dewi.name,
                );
                await dialog
                    .getByRole('button', { name: 'Simpan Jadwal' })
                    .click();
                await expect(kaprodiPage.getByRole('dialog')).toHaveCount(0);

                await kaprodiPage
                    .getByPlaceholder('Cari nama, NIM, judul, atau dosen...')
                    .fill(student);
                const row = studentRowIn(kaprodiPage, student);
                await expect(row).toContainText('#1');
                await expect(row).toContainText(lecturers.ahmad.name);
                await expect(row).toContainText(lecturers.dewi.name);

                await studentPage.goto('/mahasiswa/tugas-akhir');
                await expect(
                    studentPage.getByText(thesis.semproLocation).first(),
                ).toBeVisible();
                await expect(
                    studentPage.getByText('Sempro Dijadwalkan').first(),
                ).toBeVisible();
                await attachStageScreenshot(
                    testInfo,
                    studentPage,
                    '3-mahasiswa-sempro-dijadwalkan',
                );
            });

            await test.step('4. Mahasiswa uploads a document and a chat attachment, then sees the examiner review', async () => {
                const revisionNote = `Perjelas sitasi pada bab pembuka ${stamp}`;
                const uploadTitle = 'Naskah Sempro Gladi Demo';
                await studentPage.goto('/mahasiswa/upload-dokumen?open=unggah');
                const uploadDialog = studentPage.getByRole('dialog', {
                    name: 'Upload Dokumen',
                });
                await expect(uploadDialog).toBeVisible();
                await uploadDialog
                    .getByLabel('Judul Dokumen')
                    .fill(uploadTitle);
                await uploadDialog.getByLabel('Kategori Dokumen').click();
                await studentPage
                    .getByRole('option', { name: 'Draft Skripsi' })
                    .click();
                await uploadDialog
                    .getByLabel('File Dokumen')
                    .setInputFiles(fixtureFile);
                await uploadDialog
                    .getByRole('button', { name: 'Upload', exact: true })
                    .click();
                await expect(uploadDialog).toBeHidden();
                await expect(
                    studentPage.getByRole('link', {
                        name: `Unduh dokumen ${uploadTitle}`,
                    }),
                ).toBeVisible();
                await attachStageScreenshot(
                    testInfo,
                    studentPage,
                    '4-mahasiswa-dokumen-diunggah',
                );

                await studentPage.goto('/mahasiswa/pesan');
                await studentPage
                    .locator('button')
                    .filter({ hasText: 'Sempro' })
                    .first()
                    .click();
                await expect(
                    studentPage.getByPlaceholder('Tulis pesan...'),
                ).toBeEnabled();
                await studentPage
                    .locator('input[type="file"]')
                    .setInputFiles(attachment);
                await expect(
                    studentPage.getByLabel('Nama dokumen'),
                ).toBeVisible();

                await Promise.all([
                    studentPage.waitForResponse(
                        (response) =>
                            response.request().method() === 'POST' &&
                            response.url().includes('/mahasiswa/pesan/'),
                    ),
                    studentPage
                        .getByPlaceholder('Tulis pesan...')
                        .press('Enter'),
                ]);

                await openDosenGroupThread(pageOf.ahmad, student, 'Sempro');
                const reviewButton = pageOf.ahmad
                    .getByRole('button', { name: `Tinjau ${attachmentName}` })
                    .last();
                await reviewButton.scrollIntoViewIfNeeded();
                await reviewButton.click();

                const reviewDialog = pageOf.ahmad.getByRole('dialog', {
                    name: 'Kirim Catatan Revisi',
                });
                await expect(reviewDialog).toBeVisible();
                await reviewDialog
                    .getByRole('textbox', { name: 'Catatan Revisi' })
                    .fill(revisionNote);
                await reviewDialog
                    .getByRole('button', { name: 'Kirim Catatan' })
                    .click();
                await expect(reviewDialog).toBeHidden();

                // Demo case #10: the mahasiswa sees the review result.
                await studentPage.goto('/mahasiswa/upload-dokumen');
                await studentPage
                    .getByPlaceholder('Cari judul atau file...')
                    .fill(attachmentName);
                const documentRow = studentPage
                    .locator('tr')
                    .filter({ hasText: attachmentName })
                    .first();
                await expect(documentRow).toContainText('Perlu Revisi');
                await documentRow
                    .getByRole('button', { name: /^Detail/ })
                    .click();
                await expect(studentPage.getByRole('dialog')).toContainText(
                    revisionNote,
                );
                await attachStageScreenshot(
                    testInfo,
                    studentPage,
                    '4-mahasiswa-hasil-review-lampiran',
                );
                await studentPage.keyboard.press('Escape');
            });

            await test.step('5. Both examiners grade the sempro, one asks for revisions', async () => {
                await openDefenseSheet(
                    pageOf.ahmad,
                    student,
                    'Sempro / Sinopsis',
                );
                await submitDecision(pageOf.ahmad, {
                    decisionLabel: /^Lulus$/,
                    decisionNotes: 'Proposal layak lanjut setelah revisi.',
                    revisionNotes:
                        'Perjelas rumusan masalah dan metrik evaluasi.',
                    revisionItems: thesis.revisionItems,
                });

                await openDefenseSheet(
                    pageOf.dewi,
                    student,
                    'Sempro / Sinopsis',
                );
                await submitDecision(pageOf.dewi, {
                    decisionLabel: /^Lulus$/,
                    decisionNotes: 'Topik sudah matang.',
                });

                await expectAwaitingFinalization(
                    pageOf.dewi,
                    student,
                    'Sempro / Sinopsis',
                );
                await attachStageScreenshot(
                    testInfo,
                    pageOf.dewi,
                    '5-penguji-menunggu-finalisasi',
                );
            });

            await test.step('6. Admin finalizes the sempro with a revision and the mahasiswa sees the items', async () => {
                await openAdminProject(adminPage, student);
                const modal = await openWorkflowAction(
                    adminPage,
                    /^Catat Hasil/,
                );
                await pickFilamentSelect(modal, 0, 'pass_with_revision');
                await modal
                    .getByLabel('Batas Revisi')
                    .fill(toDateTimeLocalString(daysFromNow(14)));
                await modal
                    .locator('textarea')
                    .fill('Lulus dengan revisi rumusan masalah dan metrik.');
                await modal.getByRole('button', { name: 'Submit' }).click();
                await expect(modal).toBeHidden();

                await studentPage.goto('/mahasiswa/tugas-akhir');
                await expect(
                    studentPage.getByText('Revisi Sempro').first(),
                ).toBeVisible();
                await studentPage
                    .getByRole('row')
                    .filter({ hasText: thesis.semproLocation })
                    .getByRole('button', { name: 'Lihat detail', exact: true })
                    .click();
                for (const item of thesis.revisionItems) {
                    await expect(
                        studentPage.getByText(item.description).first(),
                    ).toBeVisible();
                }
                await attachStageScreenshot(
                    testInfo,
                    studentPage,
                    '6-mahasiswa-butir-revisi',
                );
            });

            await test.step('7. Mahasiswa answers the revision and the examiner verifies it', async () => {
                const form = studentPage.getByTestId('revision-response-form');
                await expect(form).toBeVisible();

                for (const [index] of thesis.revisionItems.entries()) {
                    const number = index + 1;
                    await form
                        .getByRole('checkbox', {
                            name: `Butir ${number} sudah diperbaiki`,
                        })
                        .click();
                    await form
                        .getByLabel(`Tanggapan butir ${number}`)
                        .fill(
                            `Butir ${number} sudah diperbaiki di naskah baru.`,
                        );
                }
                await form
                    .getByLabel('Naskah perbaikan (PDF atau Word)')
                    .setInputFiles(fixtureFile);
                await Promise.all([
                    studentPage.waitForResponse(
                        (response) =>
                            response.request().method() === 'POST' &&
                            /\/mahasiswa\/tugas-akhir\/revisi\/\d+\/perbaikan$/.test(
                                response.url(),
                            ),
                    ),
                    form
                        .getByRole('button', { name: 'Kirim perbaikan' })
                        .click(),
                ]);
                await expect(
                    studentPage
                        .getByText('Menunggu verifikasi penguji')
                        .first(),
                ).toBeVisible();

                await openDefenseSheet(
                    pageOf.ahmad,
                    student,
                    'Sempro / Sinopsis',
                );
                const verifyForm = pageOf.ahmad.getByTestId(
                    'revision-verify-form',
                );
                await expect(verifyForm).toBeVisible();
                for (const [index] of thesis.revisionItems.entries()) {
                    await verifyForm
                        .getByRole('button', {
                            name: `Diterima butir ${index + 1}`,
                        })
                        .click();
                }
                await Promise.all([
                    pageOf.ahmad.waitForResponse(
                        (response) =>
                            response.request().method() === 'POST' &&
                            /\/dosen\/seminar-proposal\/revisions\/\d+\/verify$/.test(
                                response.url(),
                            ),
                    ),
                    verifyForm
                        .getByRole('button', {
                            name: 'Simpan dan selesaikan revisi',
                        })
                        .click(),
                ]);

                await studentPage.goto('/mahasiswa/tugas-akhir');
                await expect(
                    studentPage.getByText('Sempro Selesai').first(),
                ).toBeVisible();
                await attachStageScreenshot(
                    testInfo,
                    studentPage,
                    '7-mahasiswa-sempro-selesai',
                );
            });

            await test.step('8. Kaprodi assigns two supervisors', async () => {
                await kaprodiPage.goto('/kaprodi/mahasiswa');
                await kaprodiPage
                    .getByPlaceholder('Cari nama atau NIM...')
                    .fill(student);
                await studentRowIn(kaprodiPage, student)
                    .getByRole('button', { name: 'Atur pembimbing' })
                    .click();

                const dialog = kaprodiPage.getByRole('dialog', {
                    name: 'Atur Pembimbing',
                });
                await expect(dialog).toBeVisible();
                // The proposed discussion lecturer is already selected as Pembimbing 1.
                await expect(
                    dialog.getByText(lecturers.budi.name, { exact: true }),
                ).toBeVisible();
                await pickLecturer(
                    kaprodiPage,
                    1,
                    lecturers.ratna.query,
                    lecturers.ratna.name,
                );
                await dialog
                    .getByRole('button', { name: 'Simpan Pembimbing' })
                    .click();
                await expect(kaprodiPage.getByRole('dialog')).toHaveCount(0);

                await studentPage.goto('/mahasiswa/tugas-akhir');
                await expect(
                    studentPage.getByText(lecturers.budi.name).first(),
                ).toBeVisible();
                await expect(
                    studentPage.getByText(lecturers.ratna.name).first(),
                ).toBeVisible();
                await expect(
                    studentPage.getByText('Pembimbing Ditetapkan').first(),
                ).toBeVisible();
                await attachStageScreenshot(
                    testInfo,
                    studentPage,
                    '8-mahasiswa-pembimbing-ditetapkan',
                );
            });

            await test.step('9. Bimbingan: requests approved and completed, plus one schedule made by the dosen', async () => {
                const budiTopic = `Bimbingan bab dua ${stamp}`;
                const ratnaTopic = `Bimbingan bab tiga ${stamp}`;
                const dosenTopic = `Bimbingan dari dosen ${stamp}`;

                await requestBimbingan(
                    studentPage,
                    budiTopic,
                    lecturers.budi.name,
                    toDateTimeLocalString(daysFromNow(3)),
                );
                await approveBimbingan(pageOf.budi, budiTopic);
                await completeBimbingan(pageOf.budi, budiTopic);

                await requestBimbingan(
                    studentPage,
                    ratnaTopic,
                    lecturers.ratna.name,
                    toDateTimeLocalString(daysFromNow(4)),
                );
                await approveBimbingan(pageOf.ratna, ratnaTopic);
                await completeBimbingan(pageOf.ratna, ratnaTopic);

                // Lecturer-made schedule (issue #19), also marked completed.
                await pageOf.budi.goto('/dosen/jadwal-bimbingan');
                await pageOf.budi
                    .getByRole('button', { name: 'Buat Jadwal', exact: true })
                    .click();
                const scheduleDialog = pageOf.budi.getByRole('dialog', {
                    name: 'Buat Jadwal Bimbingan',
                });
                await expect(scheduleDialog).toBeVisible();
                await scheduleDialog.getByLabel('Mahasiswa').click();
                await pageOf.budi
                    .getByRole('option', { name: new RegExp(student, 'i') })
                    .click();
                await scheduleDialog
                    .getByLabel('Topik Bimbingan')
                    .fill(dosenTopic);
                await scheduleDialog
                    .getByLabel('Tanggal & Waktu')
                    .fill(toDateTimeLocalString(daysFromNow(5)));
                await scheduleDialog
                    .getByLabel('Lokasi')
                    .fill('Ruang Dosen Gladi Demo');
                await scheduleDialog
                    .getByRole('button', { name: 'Buat Jadwal', exact: true })
                    .click();
                await expect(scheduleDialog).not.toBeVisible();
                await completeBimbingan(pageOf.budi, dosenTopic);

                await studentPage.goto('/mahasiswa/jadwal-bimbingan');
                await studentPage
                    .getByRole('radio', { name: 'Selesai', exact: true })
                    .click();
                for (const topic of [budiTopic, ratnaTopic, dosenTopic]) {
                    await expect(
                        bimbinganRow(studentPage, topic),
                    ).toContainText('Selesai');
                }
                await expect(
                    bimbinganRow(studentPage, dosenTopic),
                ).toContainText('Dijadwalkan dosen');

                await studentPage.goto('/mahasiswa/tugas-akhir');
                await expect(
                    studentPage.getByText('2 bimbingan').first(),
                ).toBeVisible();
                await expect(
                    studentPage.getByText('1 bimbingan').first(),
                ).toBeVisible();
                await attachStageScreenshot(
                    testInfo,
                    studentPage,
                    '9-mahasiswa-bimbingan-selesai',
                );
            });

            await test.step('10. Kaprodi schedules the sidang and sees the minimum bimbingan warning', async () => {
                const sidangDay = new Date();
                sidangDay.setMonth(sidangDay.getMonth() + 1, 16);

                await kaprodiPage.goto('/kaprodi/sempro-sidang');
                await kaprodiPage
                    .getByRole('button', { name: /^Jadwalkan Sidang/ })
                    .click();

                const dialog = kaprodiPage.getByRole('dialog', {
                    name: /^Jadwalkan Sidang/,
                });
                await expect(dialog).toBeVisible();
                await chooseStudentInDialog(kaprodiPage, student);

                // Fewer than 6 completed bimbingan: warn, never block (issue #20).
                const warning = dialog.getByTestId(
                    'mentorship-minimum-warning',
                );
                await expect(warning).toContainText(
                    'Bimbingan belum memenuhi minimal',
                );
                await expect(warning).toContainText(
                    `${lecturers.budi.name} (2/6)`,
                );
                await expect(warning).toContainText(
                    `${lecturers.ratna.name} (1/6)`,
                );
                await attachStageScreenshot(
                    testInfo,
                    kaprodiPage,
                    '10-kaprodi-peringatan-bimbingan-minimal',
                );

                await pickCalendarDay(dialog, sidangDay);
                await dialog.locator('input[type="time"]').fill('13:00');
                await dialog
                    .getByPlaceholder('Ruang ujian / tautan meeting')
                    .fill(thesis.sidangLocation);
                await dialog
                    .getByPlaceholder('Cari dosen penguji (min. 2 karakter)...')
                    .fill(lecturers.fajar.query);
                await kaprodiPage
                    .getByRole('button', { name: lecturers.fajar.name })
                    .click();
                await dialog
                    .getByRole('button', { name: 'Simpan Jadwal' })
                    .click();
                await expect(kaprodiPage.getByRole('dialog')).toHaveCount(0);

                await kaprodiPage
                    .getByPlaceholder('Cari nama, NIM, judul, atau dosen...')
                    .fill(student);
                const row = studentRowIn(kaprodiPage, student);
                await expect(row).toContainText('Dr. Budi Santoso');
                await expect(row).toContainText('Dr. Ratna Kusuma');
                await expect(row).toContainText('Dr. Fajar Nugraha');

                await studentPage.goto('/mahasiswa/tugas-akhir');
                await expect(
                    studentPage.getByText(thesis.sidangLocation).first(),
                ).toBeVisible();
                // The additional examiner stays secret until the sidang is finished (issue #9).
                await expect(
                    studentPage.getByText(lecturers.fajar.name),
                ).toHaveCount(0);
                await attachStageScreenshot(
                    testInfo,
                    studentPage,
                    '10-mahasiswa-sidang-dijadwalkan',
                );
            });

            await test.step('11. The sidang panel grades', async () => {
                const panel = [
                    { page: pageOf.budi, score: '88' },
                    { page: pageOf.ratna, score: '90' },
                    { page: pageOf.fajar, score: '85' },
                ];

                for (const member of panel) {
                    await openDefenseSheet(
                        member.page,
                        student,
                        'Sidang Akhir',
                    );
                    await submitDecision(member.page, {
                        decisionLabel: /^Lulus$/,
                        score: member.score,
                        decisionNotes:
                            'Naskah dan presentasi memenuhi standar.',
                    });
                }

                await expectAwaitingFinalization(
                    pageOf.fajar,
                    student,
                    'Sidang Akhir',
                );
                await attachStageScreenshot(
                    testInfo,
                    pageOf.fajar,
                    '11-panel-sidang-menunggu-finalisasi',
                );
            });

            await test.step('12. Admin completes the sidang and the mahasiswa graduates', async () => {
                await openAdminProject(adminPage, student);
                const modal = await openWorkflowAction(
                    adminPage,
                    /^Selesaikan Sidang/,
                );
                await pickFilamentSelect(modal, 0, 'pass');
                await modal
                    .locator('textarea')
                    .last()
                    .fill('Lulus sidang tanpa revisi.');
                await modal.getByRole('button', { name: 'Submit' }).click();
                await expect(modal).toBeHidden();

                await studentPage.goto('/mahasiswa/tugas-akhir');
                await expect(
                    studentPage.getByText('Sidang Skripsi Selesai').first(),
                ).toBeVisible();
                await attachStageScreenshot(
                    testInfo,
                    studentPage,
                    '12-mahasiswa-lulus',
                );
            });

            await test.step('13. The title is public on /topik', async () => {
                const publicContext = await browser.newContext();
                cast.public = publicContext;
                const publicPage = await publicContext.newPage();

                await publicPage.goto('/topik');
                await publicPage
                    .getByPlaceholder(
                        'Cari judul, ringkasan, mahasiswa, atau NIM...',
                    )
                    .fill(thesis.titleId);
                const row = publicPage
                    .getByRole('row')
                    .filter({ hasText: thesis.titleId })
                    .first();
                await expect(row).toBeVisible();
                await expect(
                    row.getByText('Selesai', { exact: true }),
                ).toBeVisible();
                await attachStageScreenshot(
                    testInfo,
                    publicPage,
                    '13-publik-topik',
                );
            });
        } finally {
            for (const context of Object.values(cast)) {
                await context.close();
            }
        }
    });
});
