import { execFileSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';

import { expect, test, type Locator, type Page } from '@playwright/test';

import { playwrightEnvironment, repositoryRoot } from '../support/db';

function php(code: string): void {
    execFileSync('php', ['artisan', 'tinker', '--execute', code], {
        cwd: repositoryRoot,
        env: playwrightEnvironment(),
        stdio: 'pipe',
    });
}

const studentNames = [
    'Alya Mahasiswa Dashboard',
    'Bima Mahasiswa Dashboard',
    'Citra Mahasiswa Dashboard',
    'Dara Mahasiswa Dashboard',
];

const actions = [
    {
        label: 'Beri penilaian',
        count: 1,
        href: '/dosen/seminar-proposal?status=pending',
    },
    {
        label: 'Verifikasi revisi',
        count: 1,
        href: '/dosen/seminar-proposal?status=revisi',
    },
    {
        label: 'Putuskan jadwal',
        count: 5,
        href: '/dosen/jadwal-bimbingan',
    },
    {
        label: 'Tandai selesai',
        count: 1,
        href: '/dosen/jadwal-bimbingan?upcomingFilter=lewat',
    },
    {
        label: 'Review dokumen',
        count: 4,
        href: '/dosen/dokumen-revisi?status=Perlu%20Review',
    },
    {
        label: 'Lihat mahasiswa',
        count: 3,
        href: '/dosen/mahasiswa-bimbingan?activeAttention=inactive',
    },
];

const summaries = [
    {
        label: 'Mahasiswa bimbingan aktif',
        count: 4,
        href: '/dosen/mahasiswa-bimbingan',
    },
    {
        label: 'Ujian 7 hari ke depan',
        count: 1,
        href: '/dosen/seminar-proposal',
    },
    {
        label: 'Revisi menunggu verifikasi',
        count: 1,
        href: '/dosen/seminar-proposal?status=revisi',
    },
    {
        label: 'Dokumen menunggu review',
        count: 4,
        href: '/dosen/dokumen-revisi?status=Perlu%20Review',
    },
];

// Use an isolated lecturer, not shared seeded students whose state earlier specs
// may change. All setup is transactional; finally removes only these users.
async function withDashboard(
    page: Page,
    populated: boolean,
    scenario: () => Promise<void>,
): Promise<void> {
    const token = `dashboard-${randomUUID()}`;
    const lecturerEmail = `${token}-lecturer@sita.test`;
    const emails = [
        lecturerEmail,
        ...studentNames.map((_, i) => `${token}-${i}@sita.test`),
    ];

    try {
        php(`
            Illuminate\\Support\\Facades\\DB::transaction(function () {
                $program = App\\Models\\ProgramStudi::query()->firstOrFail();
                $lecturer = App\\Models\\User::factory()->asDosen()->create(['name' => 'Dosen Dashboard', 'email' => '${lecturerEmail}', 'phone_number' => '081234567891', 'phone_number_confirmed_at' => now()]);
                App\\Models\\DosenProfile::factory()->create(['user_id' => $lecturer->id, 'program_studi_id' => $program->id, 'is_active' => true]);
                if (!${populated ? 'true' : 'false'}) {
                    return;
                }
                foreach (${JSON.stringify(studentNames)} as $index => $name) {
                    $student = App\\Models\\User::factory()->asMahasiswa()->create(['name' => $name, 'email' => '${token}-'.$index.'@sita.test', 'phone_number' => '081234567890', 'phone_number_confirmed_at' => now()]);
                    App\\Models\\MahasiswaProfile::factory()->create(['user_id' => $student->id, 'program_studi_id' => $program->id, 'is_active' => true]);
                    $project = App\\Models\\ThesisProject::query()->create(['student_user_id' => $student->id, 'program_studi_id' => $program->id, 'phase' => 'research', 'state' => 'active', 'started_at' => now()->subDays(90), 'created_by' => $student->id]);
                    $title = App\\Models\\ThesisProjectTitle::query()->create(['project_id' => $project->id, 'version_no' => 1, 'title_id' => 'Riset Dashboard '.$name, 'status' => 'approved', 'submitted_by_user_id' => $student->id, 'submitted_at' => now()->subDays(90)]);
                    App\\Models\\ThesisSupervisorAssignment::query()->create(['project_id' => $project->id, 'lecturer_user_id' => $lecturer->id, 'role' => 'primary', 'status' => 'active', 'started_at' => now()->subDays(90), 'assigned_by' => $lecturer->id]);
                    App\\Models\\MentorshipDocument::factory()->create(['student_user_id' => $student->id, 'lecturer_user_id' => $lecturer->id, 'uploaded_by_user_id' => $student->id, 'title' => 'Naskah perlu review '.$name, 'status' => 'submitted', 'created_at' => now()->subDays(40), 'updated_at' => now()->subDays(40)]);
                    App\\Models\\MentorshipSchedule::factory()->create(['student_user_id' => $student->id, 'lecturer_user_id' => $lecturer->id, 'created_by_user_id' => $student->id, 'topic' => 'Permintaan '.$name, 'status' => 'pending', 'requested_for' => now()->addDays(3), 'scheduled_for' => null]);
                    if ($index !== 0) {
                        continue;
                    }
                    // Two requests by one student must not duplicate their preview.
                    App\\Models\\MentorshipSchedule::factory()->create(['student_user_id' => $student->id, 'lecturer_user_id' => $lecturer->id, 'created_by_user_id' => $student->id, 'topic' => 'Permintaan kedua Alya', 'status' => 'pending', 'requested_for' => now()->addDays(4), 'scheduled_for' => null]);
                    foreach ([-2 => 'Bimbingan lewat dashboard', 1 => 'Bimbingan mendatang dashboard'] as $days => $topic) {
                        App\\Models\\MentorshipSchedule::factory()->create(['student_user_id' => $student->id, 'lecturer_user_id' => $lecturer->id, 'created_by_user_id' => $student->id, 'topic' => $topic, 'status' => 'approved', 'requested_for' => now()->addDays($days)->setTime(10, 0), 'scheduled_for' => now()->addDays($days)->setTime(10, 0), 'location' => 'Ruang Dashboard']);
                    }
                    foreach ([-1, 2] as $days) {
                        $defense = App\\Models\\ThesisDefense::query()->create(['project_id' => $project->id, 'title_version_id' => $title->id, 'type' => 'sempro', 'attempt_no' => $days === -1 ? 1 : 2, 'status' => 'scheduled', 'result' => 'pending', 'scheduled_for' => now()->addDays($days)->setTime(11, 0), 'location' => 'Ruang Ujian Dashboard', 'mode' => 'offline']);
                        App\\Models\\ThesisDefenseExaminer::query()->create(['defense_id' => $defense->id, 'lecturer_user_id' => $lecturer->id, 'role' => 'examiner', 'order_no' => 1, 'decision' => 'pending']);
                    }
                    App\\Models\\ThesisRevision::query()->create(['project_id' => $project->id, 'defense_id' => $defense->id, 'requested_by_user_id' => $lecturer->id, 'status' => 'submitted', 'notes' => 'Perbaikan dashboard', 'submitted_at' => now()]);
                    // An approved document must not appear in the review destination.
                    App\\Models\\MentorshipDocument::factory()->create(['student_user_id' => $student->id, 'lecturer_user_id' => $lecturer->id, 'uploaded_by_user_id' => $student->id, 'title' => 'Naskah sudah disetujui dashboard', 'status' => 'approved', 'created_at' => now()->subDays(40), 'updated_at' => now()->subDays(40)]);
                }
            });
        `);
        await page.goto('/login');
        await page.getByLabel('Email', { exact: true }).fill(lecturerEmail);
        await page.getByLabel('Kata sandi', { exact: true }).fill('password');
        await page.getByRole('button', { name: /log in|masuk/i }).click();
        await expect(page).toHaveURL(/\/dosen\/dashboard$/);
        await scenario();
    } finally {
        php(
            `App\\Models\\User::query()->whereIn('email', ${JSON.stringify(emails)})->get()->each->delete();`,
        );
    }
}

async function expectDestination(page: Page, href: string): Promise<void> {
    const expected = new URL(href, 'http://sita.test');
    await expect(page).toHaveURL(
        (url) =>
            url.pathname === expected.pathname &&
            url.searchParams.toString() === expected.searchParams.toString(),
    );
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
}

async function expectSummary(page: Page, populated: boolean): Promise<void> {
    const summary = page.getByRole('navigation', {
        name: 'Ringkasan bimbingan',
    });
    await expect(summary.getByRole('link')).toHaveCount(4);
    for (const item of summaries) {
        const tile = summary.getByRole('link', {
            name: new RegExp(item.label),
        });
        const count = populated ? item.count : 0;
        const value =
            item.label === 'Mahasiswa bimbingan aktif'
                ? new RegExp(`^${count}\\s*/\\s*\\d+$`)
                : new RegExp(`^${count}$`);
        await expect(tile.getByText(value)).toBeVisible();
    }
}

async function expectTargetSize(links: Locator): Promise<void> {
    for (const link of await links.all()) {
        const box = await link.boundingBox();
        expect(box).not.toBeNull();
        expect(box!.width).toBeGreaterThanOrEqual(44);
        expect(box!.height).toBeGreaterThanOrEqual(44);
    }
}

test('populated dashboard shows real counts, one primary preview and compact secondary actions at mobile and desktop widths', async ({
    page,
}) => {
    await withDashboard(page, true, async () => {
        const queue = page.getByRole('region', {
            name: 'Perlu tindakan',
            exact: true,
        });
        const summary = page.getByRole('navigation', {
            name: 'Ringkasan bimbingan',
        });
        const agenda = page.getByRole('region', {
            name: 'Hari ini & 7 hari ke depan',
        });
        await expectSummary(page, true);
        const rows = queue
            .getByRole('list', { name: 'Perlu tindakan', exact: true })
            .locator(':scope > li');
        await expect(rows).toHaveCount(actions.length);
        for (const [index, item] of actions.entries()) {
            const row = rows.nth(index);
            await expect(
                row.getByText(String(item.count), { exact: true }),
            ).toBeVisible();
            const previews = row
                .locator('p')
                .filter({ hasText: /^Mahasiswa:/ });
            if (index === 0) {
                await expect(previews).toHaveCount(1);
                expect(
                    (await previews.innerText())
                        .replace(/^Mahasiswa:\s*/, '')
                        .trim(),
                ).toBe(studentNames[0]);
            } else {
                await expect(previews).toHaveCount(0);
            }
            for (const [studentIndex, name] of studentNames.entries()) {
                if (index === 0 && studentIndex === 0) {
                    continue;
                }
                await expect(row.getByText(name, { exact: false })).toHaveCount(
                    0,
                );
            }
        }
        await expect(agenda.getByRole('link')).toHaveCount(2);
        const firstDay = agenda.locator('section').first();
        const firstHeading = (await firstDay
            .getByRole('heading')
            .boundingBox())!;
        const firstEvent = (await firstDay
            .getByRole('listitem')
            .first()
            .boundingBox())!;
        expect(firstEvent.y - firstHeading.y).toBeLessThanOrEqual(48);
        const days = agenda.locator('section');
        const firstDayBox = (await days.first().boundingBox())!;
        const secondDayBox = (await days.nth(1).boundingBox())!;
        expect(
            secondDayBox.y - (firstDayBox.y + firstDayBox.height),
        ).toBeLessThanOrEqual(32);
        await expect(
            agenda.getByText('Bimbingan mendatang dashboard', { exact: true }),
        ).toBeVisible();
        const exam = agenda
            .getByRole('link')
            .filter({ hasText: 'Ruang Ujian Dashboard' });
        await expect(exam).toHaveCount(1);
        await expect(exam).toContainText(studentNames[0]);
        await expect(exam).toContainText(/Sempro|Sinopsis|Seminar Proposal/);
        await expect(
            agenda.getByText('Bimbingan lewat dashboard', { exact: true }),
        ).toHaveCount(0);
        await expect(
            page
                .getByRole('region', { name: 'Progres mahasiswa' })
                .getByRole('link')
                .filter({ hasText: /Mahasiswa Dashboard/ }),
        ).toHaveCount(4);
        const studentLink = page
            .getByRole('region', { name: 'Progres mahasiswa' })
            .getByRole('link')
            .filter({ hasText: /Mahasiswa Dashboard/ })
            .first();
        const studentRow = studentLink.locator('..');
        const attention = studentRow.getByRole('list').first();
        const idleBackground = await studentRow.evaluate(
            (element) => getComputedStyle(element).backgroundColor,
        );
        await studentLink.hover();
        await expect
            .poll(() =>
                studentRow.evaluate(
                    (element) => getComputedStyle(element).backgroundColor,
                ),
            )
            .not.toBe(idleBackground);
        const hoverBackground = await studentRow.evaluate(
            (element) => getComputedStyle(element).backgroundColor,
        );
        await attention.hover();
        await expect
            .poll(() =>
                studentRow.evaluate(
                    (element) => getComputedStyle(element).backgroundColor,
                ),
            )
            .toBe(hoverBackground);
        await studentLink.focus();
        await expect
            .poll(() =>
                studentRow.evaluate(
                    (element) => getComputedStyle(element).backgroundColor,
                ),
            )
            .toBe(hoverBackground);
        await expect(page.getByText('Aksi cepat', { exact: true })).toHaveCount(
            0,
        );

        for (const width of [390, 1280]) {
            await page.setViewportSize({ width, height: 900 });
            await expect(queue).toBeVisible();
            await expect(summary).toBeVisible();
            await expect
                .poll(() =>
                    page.evaluate(
                        () =>
                            document.documentElement.scrollWidth <=
                            window.innerWidth,
                    ),
                )
                .toBe(true);
            const queueBox = (await queue.boundingBox())!;
            const summaryBox = (await summary.boundingBox())!;
            if (width === 390) {
                // Users reach the work queue before the summary on a phone.
                expect(queueBox.y + queueBox.height).toBeLessThanOrEqual(
                    summaryBox.y,
                );
            } else {
                expect(summaryBox.y + summaryBox.height).toBeLessThanOrEqual(
                    queueBox.y,
                );
                const agendaBox = (await agenda.boundingBox())!;
                expect(agendaBox.x).toBeGreaterThanOrEqual(
                    queueBox.x + queueBox.width,
                );
                expect(agendaBox.y).toBeLessThan(queueBox.y + queueBox.height);
                expect(agendaBox.y + agendaBox.height).toBeGreaterThan(
                    queueBox.y,
                );
            }
            await expectTargetSize(queue.getByRole('link'));
            await expectTargetSize(summary.getByRole('link'));
            await expectTargetSize(agenda.getByRole('link'));
        }
    });
});

test('all-clear dashboard keeps all four zero summary destinations and empty regions', async ({
    page,
}) => {
    await withDashboard(page, false, async () => {
        const queue = page.getByRole('region', {
            name: 'Perlu tindakan',
            exact: true,
        });
        await expect(
            queue.getByText('Semua beres', { exact: true }),
        ).toBeVisible();
        await expect(queue.getByRole('link')).toHaveCount(0);
        await expectSummary(page, false);
        await expect(
            page.getByRole('region', { name: 'Hari ini & 7 hari ke depan' }),
        ).toContainText('Belum ada bimbingan atau ujian terjadwal');
        await expect(
            page.getByRole('region', { name: 'Progres mahasiswa' }),
        ).toContainText('Belum ada mahasiswa aktif');
        for (const item of summaries) {
            await page
                .getByRole('navigation', { name: 'Ringkasan bimbingan' })
                .getByRole('link', { name: new RegExp(item.label) })
                .click();
            await expectDestination(page, item.href);
            await page.goto('/dosen/dashboard');
        }
    });
});

test('every populated action and summary opens its complete destination including filters', async ({
    page,
}) => {
    await withDashboard(page, true, async () => {
        for (const item of actions) {
            await page
                .getByRole('region', { name: 'Perlu tindakan', exact: true })
                .getByRole('link', { name: item.label, exact: true })
                .click();
            await expectDestination(page, item.href);
            if (item.label === 'Review dokumen') {
                await expect(
                    page
                        .getByRole('row')
                        .filter({ hasText: 'Naskah perlu review' }),
                ).toHaveCount(4);
                await expect(
                    page.getByRole('row').filter({
                        hasText: 'Naskah sudah disetujui dashboard',
                    }),
                ).toHaveCount(0);
            }
            await page.goto('/dosen/dashboard');
        }
        for (const item of summaries) {
            await page
                .getByRole('navigation', { name: 'Ringkasan bimbingan' })
                .getByRole('link', { name: new RegExp(item.label) })
                .click();
            await expectDestination(page, item.href);
            await page.goto('/dosen/dashboard');
        }
    });
});
