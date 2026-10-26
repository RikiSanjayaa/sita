import { expect, test } from '@playwright/test';

import { runArtisan } from '../support/db';
import {
    authStatePath,
    type PlaywrightAccountKey,
} from '../support/thesis-fixtures';

const prefix = 'UX148 agenda overflow ';

// Only these added schedules are removed; shared account/profile state stays unchanged.
test.beforeAll(() => {
    runArtisan([
        'tinker',
        '--execute',
        `
        $student = App\\Models\\User::where('email', 'mahasiswa@sita.test')->firstOrFail();
        $lecturer = App\\Models\\User::where('email', 'dosen@sita.test')->firstOrFail();
        $project = App\\Models\\ThesisProject::where('student_user_id', $student->id)->current()->firstOrFail();
        $attempt = (int) App\\Models\\ThesisDefense::where('project_id', $project->id)->where('type', 'sempro')->max('attempt_no');
        foreach (range(1, 16) as $index) {
            App\\Models\\MentorshipSchedule::factory()->create([
                'student_user_id' => $student->id, 'lecturer_user_id' => $lecturer->id,
                'created_by_user_id' => $student->id, 'status' => 'approved',
                'topic' => '${prefix}'.$index.' '.str_repeat('Catatan pembahasan metode dan hasil penelitian ', 3),
                'requested_for' => now()->addDay()->setTime(9, 0)->addMinutes($index),
                'scheduled_for' => now()->addDay()->setTime(9, 0)->addMinutes($index),
                'location' => 'Ruang bimbingan UX',
            ]);
            App\\Models\\ThesisDefense::create([
                'project_id' => $project->id,
                'title_version_id' => $project->titles()->latest('version_no')->value('id'),
                'type' => 'sempro', 'attempt_no' => $attempt + $index,
                'status' => 'scheduled', 'result' => 'pending',
                'scheduled_for' => now()->addDay()->setTime(10, 0)->addMinutes($index),
                'location' => 'Ruang ujian UX', 'mode' => 'offline',
                'notes' => '${prefix}'.$index,
            ]);
        }
    `,
    ]);
});

test.afterAll(() => {
    runArtisan([
        'tinker',
        '--execute',
        `App\\Models\\MentorshipSchedule::where('topic', 'like', '${prefix}%')->delete();
         App\\Models\\ThesisDefense::where('notes', 'like', '${prefix}%')->delete();`,
    ]);
});

const dashboards: {
    account: PlaywrightAccountKey;
    path: string;
    actions: string;
    agenda: string;
    list: string;
    overflowing: boolean;
}[] = [
    {
        account: 'mahasiswa',
        path: '/mahasiswa/dashboard',
        actions: 'section[aria-label="Tindakan Anda"]',
        agenda: 'section[aria-label="Agenda mendatang"]',
        list: '[aria-label="Daftar agenda mendatang"]',
        overflowing: true,
    },
    {
        account: 'dosen1',
        path: '/dosen/dashboard',
        actions: 'section[aria-label="Perlu tindakan"]',
        agenda: 'section[aria-label="Hari ini & 7 hari ke depan"]',
        list: '[aria-label="Daftar agenda mendatang"]',
        overflowing: true,
    },
    {
        account: 'kaprodi',
        path: '/kaprodi/dashboard',
        actions: 'section[aria-label="Perlu tindakan"]',
        agenda: 'section[aria-label="Hari ini & 7 hari ke depan"]',
        list: '[aria-label="Daftar agenda mendatang"]',
        overflowing: true,
    },
    {
        account: 'admin',
        path: '/admin',
        actions: '.sita-admin-actions',
        agenda: '.sita-admin-agenda',
        list: '.sita-agenda-scroll',
        overflowing: true,
    },
];

for (const dashboard of dashboards) {
    for (const width of [1280, 390]) {
        test(`${dashboard.account} agenda follows actions at ${width}px without losing content`, async ({
            browser,
        }) => {
            const context = await browser.newContext({
                storageState: authStatePath(dashboard.account),
                viewport: { width, height: 900 },
            });
            try {
                const page = await context.newPage();
                await page.goto(dashboard.path);
                const actions = page.locator(dashboard.actions);
                const agenda = page.locator(dashboard.agenda);
                const list = page.locator(dashboard.list);
                await expect(actions).toBeVisible();
                await expect(agenda).toBeVisible();
                await expect(list).toBeVisible();
                await page.evaluate(() => document.fonts.ready);
                const { actionBox, agendaBox, metrics } = await page.evaluate(
                    ({ actions, agenda, list }) => {
                        const actionRect = document
                            .querySelector(actions)!
                            .getBoundingClientRect();
                        const agendaRect = document
                            .querySelector(agenda)!
                            .getBoundingClientRect();
                        const element = document.querySelector(list)!;

                        return {
                            actionBox: {
                                y: actionRect.y,
                                height: actionRect.height,
                            },
                            agendaBox: {
                                y: agendaRect.y,
                                height: agendaRect.height,
                            },
                            metrics: {
                                height: element.clientHeight,
                                content: element.scrollHeight,
                                overflow: getComputedStyle(element).overflowY,
                            },
                        };
                    },
                    dashboard,
                );
                if (width === 1280) {
                    expect(
                        Math.abs(actionBox.y - agendaBox.y),
                    ).toBeLessThanOrEqual(1);
                    expect(agendaBox.height).toBeLessThanOrEqual(
                        actionBox.height + 1,
                    );
                    expect(metrics.overflow).toBe('auto');
                    if (dashboard.overflowing) {
                        expect(metrics.content).toBeGreaterThan(
                            metrics.height + 20,
                        );
                        await list.focus();
                        await list.press('End');
                        await expect
                            .poll(() =>
                                list.evaluate((element) => element.scrollTop),
                            )
                            .toBeGreaterThan(20);
                        await expect(
                            list.getByRole('listitem').last(),
                        ).toBeInViewport();
                    }
                } else {
                    expect(agendaBox.y).toBeGreaterThanOrEqual(
                        actionBox.y + actionBox.height,
                    );
                    expect(metrics.content).toBeLessThanOrEqual(
                        metrics.height + 1,
                    );
                    expect(
                        await page.evaluate(
                            () => document.documentElement.scrollWidth,
                        ),
                    ).toBeLessThanOrEqual(width);
                }
            } finally {
                await context.close();
            }
        });
    }
}
