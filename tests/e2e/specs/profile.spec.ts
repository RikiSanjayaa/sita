import { execFileSync } from 'node:child_process';

import { expect, test } from '@playwright/test';

import {
    playwrightEnvironment,
    repositoryRoot,
    runArtisan,
    seedPlaywrightScenarios,
} from '../support/db';
import { authStatePath, thesisScenarios } from '../support/thesis-fixtures';

const supervised = thesisScenarios.twoSupervisors;

/**
 * Gives the supervised scenario student a known number of completed sessions
 * per supervisor and returns the user ids used to open the profile pages.
 */
function seedMentorshipCounts(): { studentId: number; primaryId: number } {
    const script = [
        '$student = App\\Models\\User::query()->where("name", "' +
            supervised.studentName +
            '")->firstOrFail();',
        '$primary = App\\Models\\User::query()->where("email", "dosen@sita.test")->firstOrFail();',
        '$secondary = App\\Models\\User::query()->where("email", "dosen2@sita.test")->firstOrFail();',
        'App\\Models\\MentorshipSchedule::query()->where("student_user_id", $student->id)->delete();',
        'foreach ([[$primary, 2], [$secondary, 1]] as [$lecturer, $count]) { App\\Models\\MentorshipSchedule::factory()->count($count)->create(["student_user_id" => $student->id, "lecturer_user_id" => $lecturer->id, "status" => "completed", "requested_for" => now()->addMinute(), "scheduled_for" => now()->addMinute()]); }',
        'echo "IDS:" . $student->id . ":" . $primary->id;',
    ].join(' ');

    const output = execFileSync(
        'php',
        ['artisan', 'tinker', `--execute=${script}`],
        {
            cwd: repositoryRoot,
            env: playwrightEnvironment(),
            encoding: 'utf8',
        },
    );
    const match = output.match(/IDS:(\d+):(\d+)/);

    if (match === null) {
        throw new Error(`Could not seed mentorship counts: ${output}`);
    }

    return { studentId: Number(match[1]), primaryId: Number(match[2]) };
}

/**
 * Resolves a seeded user's id from its login email, for opening profile pages.
 */
function userIdByEmail(email: string): number {
    const script = `echo "ID:" . App\\Models\\User::query()->where("email", "${email}")->firstOrFail()->id;`;
    const output = execFileSync(
        'php',
        ['artisan', 'tinker', `--execute=${script}`],
        {
            cwd: repositoryRoot,
            env: playwrightEnvironment(),
            encoding: 'utf8',
        },
    );
    const match = output.match(/ID:(\d+)/);

    if (match === null) {
        throw new Error(`Could not resolve user ${email}: ${output}`);
    }

    return Number(match[1]);
}

function resetProfileForUser(): void {
    runArtisan([
        'tinker',
        '--execute=$user = App\\Models\\User::query()->whereIn("email", ["farhan@sita.test", "farhan.playwright@sita.test"])->firstOrFail(); $user->forceFill(["name" => "Farhan Maulana", "email" => "farhan@sita.test", "phone_number" => "081200000001", "phone_number_confirmed_at" => now()])->save();',
    ]);
}

test.describe('Profile settings', () => {
    test('user can update profile fields and see them persisted after reload', async ({
        browser,
    }) => {
        resetProfileForUser();

        const context = await browser.newContext({
            storageState: authStatePath('farhan'),
        });

        const nextName = 'Farhan Maulana Playwright';
        const nextEmail = 'farhan.playwright@sita.test';
        const nextPhone = '081234560001';

        try {
            const page = await context.newPage();

            await page.goto('/settings/profile');
            await expect(page).toHaveTitle(/Profil Saya/i);
            await expect(
                page.getByRole('heading', { name: 'Perbarui Profil' }),
            ).toBeVisible();

            await page.getByLabel('Nama lengkap').fill(nextName);
            await page
                .getByRole('textbox', { name: 'Email', exact: true })
                .fill(nextEmail);
            await page
                .getByRole('textbox', { name: 'Nomor HP', exact: true })
                .fill(nextPhone);
            await page
                .getByRole('button', { name: 'Simpan perubahan' })
                .click();

            await expect(page).toHaveURL(/\/settings\/profile/);
            await expect(page.getByLabel('Nama lengkap')).toHaveValue(nextName);
            await expect(
                page.getByRole('textbox', { name: 'Email', exact: true }),
            ).toHaveValue(nextEmail);
            await expect(
                page.getByRole('textbox', { name: 'Nomor HP', exact: true }),
            ).toHaveValue(nextPhone);

            await page.reload();

            await expect(page.getByLabel('Nama lengkap')).toHaveValue(nextName);
            await expect(
                page.getByRole('textbox', { name: 'Email', exact: true }),
            ).toHaveValue(nextEmail);
            await expect(
                page.getByRole('textbox', { name: 'Nomor HP', exact: true }),
            ).toHaveValue(nextPhone);

            await page
                .locator('[data-test="header-user-menu-trigger"]')
                .click();
            await expect(page.getByText(nextName).first()).toBeVisible();
            await expect(page.getByText(nextEmail).first()).toBeVisible();
        } finally {
            await context.close();
            resetProfileForUser();
        }
    });
});

test.describe('Jumlah bimbingan di profil (issue #70)', () => {
    let studentId = 0;
    let primaryId = 0;

    test.beforeAll(() => {
        seedPlaywrightScenarios();
        ({ studentId, primaryId } = seedMentorshipCounts());
    });

    test('student profile shows x bimbingan per active supervisor, as a plain count', async ({
        browser,
    }) => {
        const context = await browser.newContext({
            storageState: authStatePath('dosen3'),
        });

        try {
            const page = await context.newPage();
            await page.goto(`/profil/${studentId}`);

            const budi = page.getByRole('link', {
                name: /Dr\. Budi Santoso/,
            });
            const ratna = page.getByRole('link', {
                name: /Dr\. Ratna Kusuma/,
            });
            await expect(budi.getByText('2 bimbingan')).toBeVisible();
            await expect(ratna.getByText('1 bimbingan')).toBeVisible();
            await expect(page.getByText('/6')).toHaveCount(0);
        } finally {
            await context.close();
        }
    });

    test('supervisor profile shows x bimbingan per supervised student', async ({
        browser,
    }) => {
        const context = await browser.newContext({
            storageState: authStatePath('dosen3'),
        });

        try {
            const page = await context.newPage();
            await page.goto(`/profil/${primaryId}`);

            const student = page.getByRole('link', {
                name: new RegExp(supervised.studentName),
            });
            await expect(student.getByText('2 bimbingan')).toBeVisible();
            await expect(student.getByText('Riset')).toBeVisible();
            await expect(page.getByText('/6')).toHaveCount(0);
        } finally {
            await context.close();
        }
    });

    test('lecturer profile shows phase per student and no count for a student only examined', async ({
        browser,
    }) => {
        const examinerId = userIdByEmail('dosen3@sita.test');
        const context = await browser.newContext({
            storageState: authStatePath('dosen3'),
        });

        try {
            const page = await context.newPage();
            await page.goto(`/profil/${examinerId}`);

            const examined = page.getByRole('link', {
                name: new RegExp(
                    thesisScenarios.semproTwoExaminers.studentName,
                ),
            });
            await expect(examined.getByText('Tahap Proposal')).toBeVisible();
            await expect(examined.getByText(/bimbingan/)).toHaveCount(0);
        } finally {
            await context.close();
        }
    });
});

test('profile identity fields copy their exact values and WhatsApp remains distinct from internal chat', async ({
    browser,
}) => {
    const context = await browser.newContext({
        storageState: authStatePath('dosen1'),
        permissions: ['clipboard-read', 'clipboard-write'],
    });
    try {
        const page = await context.newPage();
        await page.goto(`/profil/${userIdByEmail('mahasiswa@sita.test')}`);
        for (const label of ['NIM', 'Email', 'Nomor HP']) {
            const button = page.getByRole('button', {
                name: `Salin ${label}`,
                exact: true,
            });
            const field = button.locator('..');
            const value = (
                await field.locator(':scope > span').first().innerText()
            ).trim();
            await button.focus();
            await button.press('Enter');
            expect(
                await page.evaluate(() => navigator.clipboard.readText()),
            ).toBe(value);
            await expect(field.getByRole('status')).toHaveText(
                `${label} disalin`,
            );
        }
        await expect(
            page.getByRole('link', { name: 'WhatsApp', exact: true }),
        ).toHaveAttribute('href', /^https:\/\/wa\.me\//);
        await expect(
            page.getByRole('button', { name: 'Chat Pribadi', exact: true }),
        ).toBeVisible();
        await page.setViewportSize({ width: 390, height: 844 });
        const emailCopy = page.getByRole('button', {
            name: 'Salin Email',
            exact: true,
        });
        const box = (await emailCopy.boundingBox())!;
        expect(box.width).toBeGreaterThanOrEqual(44);
        expect(box.height).toBeGreaterThanOrEqual(44);
        await page.goto('/settings/profile');
        const email = await page
            .getByLabel('Email', { exact: true })
            .inputValue();
        await page
            .getByRole('button', { name: 'Salin Email', exact: true })
            .first()
            .click();
        expect(await page.evaluate(() => navigator.clipboard.readText())).toBe(
            email,
        );
    } finally {
        await context.close();
    }
});
