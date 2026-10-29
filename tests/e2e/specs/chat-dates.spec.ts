import { expect, test } from '@playwright/test';

import { runArtisan } from '../support/db';
import {
    createSidebarUnreadFixture,
    openPrivateThread,
    openSession,
} from '../support/sidebar-unread';

for (const role of ['student', 'lecturer'] as const) {
    test(`${role} reads sticky WITA dates and clock-only bubbles across month and midnight boundaries`, async ({
        browser,
    }, info) => {
        const fixture = createSidebarUnreadFixture();
        const session = await openSession(browser, fixture.accounts[role]);
        try {
            const thread = fixture.threads.studentLecturer.id;
            runArtisan([
                'tinker',
                `--execute=foreach (['2026-01-30', '2026-01-31', '2026-02-01'] as $day) { foreach (range(1, 9) as $index) { App\\Models\\MentorshipChatMessage::query()->create(['mentorship_chat_thread_id' => ${thread}, 'sender_user_id' => ${fixture.accounts.student.id}, 'message_type' => 'text', 'message' => $day.' catatan '.$index.PHP_EOL.'Pembahasan metode penelitian dan hasil bimbingan untuk dibaca dalam riwayat percakapan.', 'sent_at' => $day.' 09:05:00'])->forceFill(['created_at' => $day.' 09:05:00'])->saveQuietly(); } }`,
            ]);
            await session.page.clock.install({
                time: new Date('2026-01-31T16:05:00Z'),
            });
            for (const width of [1280, 390]) {
                await session.page.setViewportSize({ width, height: 844 });
                await openPrivateThread(
                    session.page,
                    fixture,
                    role,
                    'studentLecturer',
                    { expectRead: false },
                );
                const history = session.page.getByRole('region', {
                    name: 'Riwayat pesan',
                });
                const today = history.getByRole('region', {
                    name: 'Hari ini',
                    exact: true,
                });
                await expect(
                    today.locator('time[datetime="2026-02-01"]'),
                ).toBeInViewport();
                await expect(
                    today.locator('[data-message-id]').last().locator('time'),
                ).toHaveText('09.05');
                await session.page.screenshot({
                    path: info.outputPath(`${role}-${width}-today.png`),
                });

                for (const [label, day] of [
                    ['Kemarin', '2026-01-31'],
                    ['Jumat, 30 Januari 2026', '2026-01-30'],
                ]) {
                    const section = history.getByRole('region', {
                        name: label,
                        exact: true,
                    });
                    await section
                        .locator('[data-message-id]')
                        .nth(8)
                        .evaluate((element) => {
                            const viewport = element.closest(
                                '[aria-label="Riwayat pesan"]',
                            )!;
                            viewport.scrollTop +=
                                element.getBoundingClientRect().top -
                                viewport.getBoundingClientRect().top -
                                220;
                        });
                    const date = section.locator(`time[datetime="${day}"]`);
                    await expect(date).toBeInViewport();
                    const dateBox = await date.boundingBox();
                    const identityBox = await session.page
                        .getByRole('button', {
                            name: /Lihat \d+ anggota percakapan/,
                        })
                        .boundingBox();
                    const gap =
                        dateBox!.y - identityBox!.y - identityBox!.height;
                    expect(gap).toBeGreaterThanOrEqual(6);
                    expect(gap).toBeLessThanOrEqual(10);
                    for (const appearance of ['light', 'dark']) {
                        await session.page.evaluate(async (mode) => {
                            document.documentElement.classList.toggle(
                                'dark',
                                mode === 'dark',
                            );
                            await Promise.all(
                                document
                                    .getAnimations()
                                    .filter(
                                        (animation) =>
                                            animation.effect?.getTiming()
                                                .iterations !== Infinity,
                                    )
                                    .map((animation) =>
                                        animation.finished.catch(() => {}),
                                    ),
                            );
                        }, appearance);
                        await session.page.screenshot({
                            path: info.outputPath(
                                `${role}-${width}-${day}-${appearance}.png`,
                            ),
                        });
                    }
                    await session.page.evaluate(() =>
                        document.documentElement.classList.remove('dark'),
                    );
                }
                // Same sender/clock on adjacent days must start separate groups.
                for (const section of await history.getByRole('region').all()) {
                    await expect(
                        section.locator('[data-message-id]').first(),
                    ).toHaveAttribute('data-message-grouped', 'false');
                }
                await session.page
                    .getByRole('button', { name: 'Cari di percakapan ini' })
                    .click();
                await session.page
                    .getByPlaceholder('Cari di percakapan ini...')
                    .fill('2026-01-31 catatan 8');
                const match = history
                    .locator('[data-message-id]')
                    .filter({ hasText: '2026-01-31 catatan 8\n' });
                await expect(match).toBeInViewport();
                const searchBox = await session.page
                    .getByPlaceholder('Cari di percakapan ini...')
                    .boundingBox();
                const matchBox = await match.boundingBox();
                expect(matchBox!.y).toBeGreaterThan(
                    searchBox!.y + searchBox!.height,
                );
                await session.page
                    .getByRole('button', { name: 'Cari di percakapan ini' })
                    .click();
                await session.page
                    .getByRole('button', { name: 'Pesan terbaru' })
                    .click();
                await expect(
                    today.locator('time[datetime="2026-02-01"]'),
                ).toBeInViewport();
            }
            await session.page.clock.fastForward(32 * 60 * 60 * 1_000);
            await expect(
                session.page
                    .locator('time[datetime="2026-02-01"]')
                    .filter({ hasText: 'Kemarin' }),
            ).toBeVisible();
            await expect(
                session.page.getByRole('region', {
                    name: 'Sabtu, 31 Januari 2026',
                    exact: true,
                }),
            ).toHaveCount(1);
        } finally {
            try {
                await session.close();
            } finally {
                fixture.cleanup();
            }
        }
    });
}
