import { expect, test, type Page, type Response } from '@playwright/test';

import { daysFromNow, toDateTimeLocalString } from '../support/dates';
import { runArtisan, seedPlaywrightScenarios } from '../support/db';
import {
    authStatePath,
    playwrightAccounts,
    thesisScenarios,
} from '../support/thesis-fixtures';

// Examiner grading in the dosen portal (dosen/seminar-proposal) and revision
// resolution. admin-sempro.spec.ts drives most decisions through tinker; this
// spec drives them through the UI. It uses dedicated students (two sempro
// examiners, one scheduled sidang panel), reset in beforeAll.
const sempro = thesisScenarios.semproTwoExaminers;
const sidang = thesisScenarios.sidangWithPanel;
const sinopsis = thesisScenarios.sinopsisObe;

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

    // The calendar grid also has rows naming the student; only the exam list shows the attempt ("#1").
    const row = page
        .getByRole('row')
        .filter({ hasText: studentName })
        .filter({ hasText: /#\d+/ })
        .first();
    await expect(row).toBeVisible();
    // Click the first cell: the whole row opens the sheet, but the centre of
    // the row can land on a nested link or on a not yet hydrated element.
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
    }: {
        decisionLabel: RegExp;
        score?: string;
        decisionNotes: string;
        revisionNotes?: string;
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

function verifyResponse(response: Response): boolean {
    return (
        response.request().method() === 'POST' &&
        /\/dosen\/seminar-proposal\/revisions\/\d+\/verify$/.test(
            response.url(),
        )
    );
}

// The mahasiswa answers every open item of the current revision round and sends it.
async function answerRevisionItems(
    page: Page,
    responses: string[],
): Promise<void> {
    await page.goto('/mahasiswa/tugas-akhir');
    await page
        .getByRole('row')
        .filter({ hasText: 'Perlu ditanggapi' })
        .first()
        .click();
    const form = page.getByTestId('revision-response-form');
    await expect(form).toBeVisible();

    for (const [index, response] of responses.entries()) {
        await form
            .getByRole('checkbox', {
                name: `Butir ${index + 1} sudah diperbaiki`,
            })
            .click();
        await form.getByLabel(`Tanggapan butir ${index + 1}`).fill(response);
    }

    await form.getByLabel(/^Naskah perbaikan/).setInputFiles({
        name: 'naskah-perbaikan.pdf',
        mimeType: 'application/pdf',
        buffer: Buffer.from('%PDF-1.4\n%%EOF\n'),
    });

    await Promise.all([
        page.waitForResponse(
            (res) =>
                res.request().method() === 'POST' &&
                /\/mahasiswa\/tugas-akhir\/revisi\/\d+\/perbaikan$/.test(
                    res.url(),
                ),
        ),
        form.getByRole('button', { name: 'Kirim perbaikan' }).click(),
    ]);
    await expect(page.getByTestId('revision-response-form')).toHaveCount(0);
}

function finalizeSempro(studentEmail: string, revisionDueAt: string): void {
    runArtisan([
        'tinker',
        `--execute=$project = App\\Models\\ThesisProject::query()->whereHas("student", fn($q) => $q->where("email", "${studentEmail}"))->where("state", "active")->latest("started_at")->firstOrFail(); $admin = App\\Models\\User::query()->where("email", "admin@sita.test")->firstOrFail(); app(App\\Services\\ThesisProjectAdminService::class)->finalizeSempro(project: $project, decidedBy: $admin->id, result: "pass_with_revision", notes: "Lulus dengan revisi metodologi.", revisionDueAt: "${revisionDueAt}");`,
    ]);
}

test.describe('Dosen penilaian ujian', () => {
    test.describe.configure({ mode: 'serial' });

    test.beforeAll(() => {
        seedPlaywrightScenarios();
    });

    test('two examiners grade a sempro in the UI, admin finalizes, and the examiner verifies the revision over two rounds', async ({
        browser,
    }) => {
        const examinerOneContext = await browser.newContext({
            storageState: authStatePath(sempro.examinerKeys[0]),
        });
        const examinerTwoContext = await browser.newContext({
            storageState: authStatePath(sempro.examinerKeys[1]),
        });
        const studentContext = await browser.newContext({
            storageState: authStatePath(sempro.studentKey),
        });

        try {
            const examinerOnePage = await examinerOneContext.newPage();
            const examinerTwoPage = await examinerTwoContext.newPage();
            const studentPage = await studentContext.newPage();

            await openDefenseSheet(
                examinerOnePage,
                sempro.studentName,
                'Sempro / Sinopsis',
            );
            await submitDecision(examinerOnePage, {
                decisionLabel: /^Lulus$/,
                decisionNotes: 'Proposal layak lanjut setelah revisi.',
                revisionNotes: 'Perjelas metrik evaluasi pada bab tiga.',
            });

            // The decision is final for the examiner: no second submission.
            await openDefenseSheet(
                examinerOnePage,
                sempro.studentName,
                'Sempro / Sinopsis',
            );
            await expect(
                examinerOnePage.getByRole('button', {
                    name: 'Input Keputusan Saya',
                }),
            ).toHaveCount(0);

            await openDefenseSheet(
                examinerTwoPage,
                sempro.studentName,
                'Sempro / Sinopsis',
            );
            await submitDecision(examinerTwoPage, {
                decisionLabel: /^Lulus$/,
                decisionNotes: 'Topik sudah matang.',
            });

            await examinerTwoPage.goto('/dosen/seminar-proposal');
            await examinerTwoPage
                .getByRole('radio', { name: 'Sempro / Sinopsis', exact: true })
                .click();
            await examinerTwoPage
                .getByPlaceholder(/cari nama/i)
                .fill(sempro.studentName);
            await expect(
                examinerTwoPage
                    .getByRole('row')
                    .filter({ hasText: sempro.studentName })
                    .filter({ hasText: /#\d+/ })
                    .first(),
            ).toContainText('Menunggu Finalisasi');

            finalizeSempro(
                playwrightAccounts[sempro.studentKey].email,
                toDateTimeLocalString(daysFromNow(5)),
            );

            await studentPage.goto('/mahasiswa/tugas-akhir');
            await expect(
                studentPage.getByText('Revisi Sempro').first(),
            ).toBeVisible();

            // Round 1: the mahasiswa answers the item and uploads the corrected manuscript.
            await answerRevisionItems(studentPage, [
                'Metrik sudah dijelaskan.',
            ]);

            // Only the examiner who asked for the revision verifies it: reject the
            // item and add a new one, which opens round 2.
            await openDefenseSheet(
                examinerOnePage,
                sempro.studentName,
                'Sempro / Sinopsis',
            );
            await examinerOnePage
                .getByRole('button', { name: 'Belum butir 1' })
                .click();
            await examinerOnePage
                .getByLabel('Komentar butir 1')
                .fill('Metrik evaluasi masih belum lengkap.');
            await examinerOnePage
                .getByRole('button', { name: 'Tambah Butir' })
                .click();
            await examinerOnePage
                .getByRole('textbox', { name: 'Uraian' })
                .fill('Tambahkan uji signifikansi.');
            await Promise.all([
                examinerOnePage.waitForResponse(verifyResponse),
                examinerOnePage
                    .getByRole('button', {
                        name: 'Simpan dan buka putaran berikutnya',
                    })
                    .click(),
            ]);

            // Round 2: both the reopened and the new item need an answer.
            await studentPage.goto('/mahasiswa/tugas-akhir');
            await expect(
                studentPage.getByText('Perlu ditanggapi').first(),
            ).toBeVisible();
            await answerRevisionItems(studentPage, [
                'Metrik dilengkapi dengan F1 dan recall.',
                'Uji signifikansi ditambahkan.',
            ]);

            await openDefenseSheet(
                examinerOnePage,
                sempro.studentName,
                'Sempro / Sinopsis',
            );
            await examinerOnePage
                .getByRole('button', { name: 'Diterima butir 1' })
                .click();
            await examinerOnePage
                .getByRole('button', { name: 'Diterima butir 2' })
                .click();
            await Promise.all([
                examinerOnePage.waitForResponse(verifyResponse),
                examinerOnePage
                    .getByRole('button', {
                        name: 'Simpan dan selesaikan revisi',
                    })
                    .click(),
            ]);

            await studentPage.goto('/mahasiswa/tugas-akhir');
            await expect(
                studentPage.getByText('Sempro Selesai').first(),
            ).toBeVisible();
        } finally {
            await examinerOneContext.close();
            await examinerTwoContext.close();
            await studentContext.close();
        }
    });

    test('an OBE student takes Ujian Sinopsis: the examiner must give a score and a comment', async ({
        browser,
    }) => {
        const examinerContext = await browser.newContext({
            storageState: authStatePath(sinopsis.examinerKeys[0]),
        });
        const studentContext = await browser.newContext({
            storageState: authStatePath(sinopsis.studentKey),
        });

        try {
            const examinerPage = await examinerContext.newPage();
            const studentPage = await studentContext.newPage();

            await openDefenseSheet(
                examinerPage,
                sinopsis.studentName,
                'Sempro / Sinopsis',
            );
            await expect(
                examinerPage.getByRole('dialog').getByText('Sinopsis').first(),
            ).toBeVisible();

            await examinerPage
                .getByRole('button', { name: 'Input Keputusan Saya' })
                .click();
            await examinerPage.getByRole('button', { name: /^Lulus$/ }).click();

            // Score and comment are both required before the form can be sent.
            const submitButton = examinerPage.getByRole('button', {
                name: 'Submit Keputusan',
            });
            await expect(submitButton).toBeDisabled();
            await examinerPage.getByLabel(/^Nilai/).fill('90');
            await expect(submitButton).toBeDisabled();
            await examinerPage
                .getByLabel(/^Catatan Keputusan/)
                .fill('Sinopsis jelas dan terukur.');
            await expect(submitButton).toBeEnabled();

            await Promise.all([
                examinerPage.waitForResponse(
                    (response) =>
                        response.request().method() === 'POST' &&
                        /\/dosen\/seminar-proposal\/\d+\/decision$/.test(
                            response.url(),
                        ),
                ),
                submitButton.click(),
            ]);

            await studentPage.goto('/mahasiswa/tugas-akhir');
            await expect(
                studentPage.getByText(/Ujian Sinopsis|Sinopsis/).first(),
            ).toBeVisible();
            await expect(studentPage.getByText('Seminar Proposal')).toHaveCount(
                0,
            );
        } finally {
            await examinerContext.close();
            await studentContext.close();
        }
    });

    test('a sidang panel member grades with a conditional pass, and a dosen outside the panel does not see the exam', async ({
        browser,
    }) => {
        const panelContext = await browser.newContext({
            storageState: authStatePath(sidang.panelKeys[0]),
        });
        const outsiderContext = await browser.newContext({
            storageState: authStatePath('dosen5'),
        });

        try {
            const panelPage = await panelContext.newPage();
            const outsiderPage = await outsiderContext.newPage();

            await openDefenseSheet(
                panelPage,
                sidang.studentName,
                'Sidang Akhir',
            );
            await submitDecision(panelPage, {
                decisionLabel: /^Lulus dengan Syarat$/,
                score: '78',
                decisionNotes: 'Naskah layak dengan perbaikan.',
                revisionNotes: 'Lengkapi pembahasan keterbatasan penelitian.',
            });

            const row = panelPage
                .getByRole('row')
                .filter({ hasText: sidang.studentName })
                .filter({ hasText: /#\d+/ })
                .first();
            await expect(row).toContainText('78');

            await outsiderPage.goto('/dosen/seminar-proposal');
            await outsiderPage
                .getByRole('radio', { name: 'Sidang Akhir', exact: true })
                .click();
            await outsiderPage
                .getByPlaceholder(/cari nama/i)
                .fill(sidang.studentName);
            await expect(
                outsiderPage
                    .getByRole('row')
                    .filter({ hasText: sidang.studentName })
                    .filter({ hasText: /#\d+/ }),
            ).toHaveCount(0);
        } finally {
            await panelContext.close();
            await outsiderContext.close();
        }
    });
});

test('exam detail opens the existing group, or private student chat when no group exists', async ({
    browser,
}) => {
    seedPlaywrightScenarios();
    const context = await browser.newContext({
        storageState: authStatePath(sempro.examinerKeys[0]),
    });
    try {
        const page = await context.newPage();
        await openDefenseSheet(page, sempro.studentName, 'Sempro / Sinopsis');
        const group = page.getByRole('link', {
            name: 'Buka chat grup ujian',
            exact: true,
        });
        const href = await group.getAttribute('href');
        const threadId = new URL(href!, 'http://sita.test').searchParams.get(
            'thread',
        );
        const chatResponsePromise = page.waitForResponse(
            (response) =>
                response.request().method() === 'GET' &&
                new URL(response.url()).pathname === '/dosen/pesan',
        );
        await group.click();
        await expect(page).toHaveURL(
            new RegExp(`/dosen/pesan\\?thread=${threadId}`),
        );
        await expect(
            page.getByRole('heading', { name: 'Pesan', exact: true }),
        ).toBeVisible();
        await expect(
            page
                .locator('[data-slot="card-title"]')
                .filter({ hasText: sempro.studentName }),
        ).toBeVisible();
        const chatResponse = await chatResponsePromise;
        const chatPayload = (await chatResponse.json()) as {
            props: { threads: { id: number; threadType: string }[] };
        };
        const existingPrivateIds = new Set(
            chatPayload.props.threads
                .filter((thread) => thread.threadType === 'private')
                .map((thread) => thread.id),
        );
        let openedPrivateId: number | null = null;

        // Only detach this group's context temporarily; never delete shared messages.
        runArtisan([
            'tinker',
            '--execute',
            `App\\Models\\MentorshipChatThread::whereKey(${threadId})->update(['context_id' => null]);`,
        ]);
        try {
            await openDefenseSheet(
                page,
                sempro.studentName,
                'Sempro / Sinopsis',
            );
            await page
                .getByRole('button', {
                    name: 'Chat pribadi mahasiswa',
                    exact: true,
                })
                .click();
            await expect(page).toHaveURL(
                (url) =>
                    url.pathname === '/dosen/pesan' &&
                    url.searchParams.get('mode') === 'private' &&
                    /^\d+$/.test(url.searchParams.get('thread') ?? ''),
            );
            await expect(
                page.getByRole('heading', { name: 'Pesan', exact: true }),
            ).toBeVisible();
            await expect(
                page
                    .locator('[data-slot="card-title"]')
                    .filter({ hasText: sempro.studentName }),
            ).toBeVisible();
            openedPrivateId = Number(
                new URL(page.url()).searchParams.get('thread'),
            );
        } finally {
            runArtisan([
                'tinker',
                '--execute',
                `App\\Models\\MentorshipChatThread::whereKey(${threadId})->update(['context_id' => App\\Models\\ThesisDefense::whereHas('project.student', fn($q) => $q->where('name', '${sempro.studentName}'))->where('type', 'sempro')->value('id')]);`,
            ]);
            if (
                openedPrivateId !== null &&
                !existingPrivateIds.has(openedPrivateId)
            ) {
                runArtisan([
                    'tinker',
                    '--execute',
                    `App\\Models\\MentorshipChatThread::whereKey(${openedPrivateId})->where('type', 'private')->delete();`,
                ]);
            }
        }
    } finally {
        await context.close();
    }
});
