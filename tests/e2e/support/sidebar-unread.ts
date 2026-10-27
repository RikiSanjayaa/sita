import { execFileSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';

import {
    expect,
    type Browser,
    type BrowserContext,
    type Locator,
    type Page,
    type Response,
} from '@playwright/test';

import { playwrightEnvironment, repositoryRoot } from './db';

/**
 * Isolated fixtures for the sidebar unread indicator on "Pesan" (issue #138).
 *
 * Every fixture owns one faculty, one program studi and four accounts (a
 * student, a second unrelated student, a lecturer and a kaprodi) plus three
 * private chat threads between them. Nothing from the shared Playwright seed
 * is read or written, and cleanup() removes everything again (users, threads,
 * messages, read markers, participants, notifications, program and faculty).
 *
 * The state under test is the real chat receipt (mentorship_chat_reads and
 * the messages that come after it). The helpers below that write to the
 * database (seedIncomingMessage, insertNotification, ...) never broadcast,
 * so they are meant for "page loads with this state" setups and for the
 * screenshot runs. Specs that assert realtime behaviour must send through the
 * real chat UI instead.
 */
export type SidebarUnreadAccountKey =
    | 'student'
    | 'otherStudent'
    | 'lecturer'
    | 'kaprodi';

export type SidebarUnreadThreadKey =
    | 'studentLecturer'
    | 'otherStudentLecturer'
    | 'studentKaprodi';

export type SidebarUnreadAccount = {
    key: SidebarUnreadAccountKey;
    id: number;
    name: string;
    email: string;
    password: string;
    role: 'mahasiswa' | 'dosen' | 'kaprodi';
    /** Where a successful login lands. */
    homePath: string;
    /** The href of the "Pesan" sidebar link for this account's workspace. */
    pesanPath: '/mahasiswa/pesan' | '/dosen/pesan';
    /** An Inertia page without chat, handy for "outside Pesan" checks. */
    otherPagePath: string;
};

export type SidebarUnreadThread = {
    key: SidebarUnreadThreadKey;
    id: number;
    participants: [SidebarUnreadAccountKey, SidebarUnreadAccountKey];
};

export type SidebarUnreadFixture = {
    token: string;
    accounts: Record<SidebarUnreadAccountKey, SidebarUnreadAccount>;
    threads: Record<SidebarUnreadThreadKey, SidebarUnreadThread>;
    cleanup: () => void;
};

export function unreadPesanLabel(count = 1): string {
    return `Pesan, ${count} pesan belum dibaca`;
}
export const readPesanLabel = 'Pesan';
export const mobileViewport = { width: 390, height: 844 } as const;
export const desktopViewport = { width: 1280, height: 800 } as const;

const otherPages = {
    mahasiswa: '/mahasiswa/dashboard',
    dosen: '/dosen/jadwal-bimbingan',
    kaprodi: '/kaprodi/mahasiswa',
} as const;

function php(code: string): string {
    return execFileSync('php', ['artisan', 'tinker', '--execute', code], {
        cwd: repositoryRoot,
        env: playwrightEnvironment(),
        encoding: 'utf8',
        stdio: ['ignore', 'pipe', 'pipe'],
    });
}

function decodeSnippet(config: unknown): string {
    const encoded = Buffer.from(JSON.stringify(config)).toString('base64');

    return `$cfg = json_decode(base64_decode('${encoded}'), true);`;
}

function setupCode(token: string, names: Record<string, string>): string {
    return `
        ${decodeSnippet({ token, names })}
        $out = Illuminate\\Support\\Facades\\DB::transaction(function () use ($cfg) {
            $token = $cfg['token'];
            $faculty = App\\Models\\Faculty::factory()->create(['name' => 'Fakultas '.$token]);
            $program = App\\Models\\ProgramStudi::factory()->create(['faculty_id' => $faculty->id, 'name' => 'Prodi '.substr($token, -8), 'slug' => $token]);
            $phone = ['phone_number' => '081234567890', 'phone_number_confirmed_at' => now()];
            $student = App\\Models\\User::factory()->asMahasiswa()->create([...$phone, 'name' => $cfg['names']['student'], 'email' => $token.'-student@sita.test']);
            App\\Models\\MahasiswaProfile::factory()->create(['user_id' => $student->id, 'program_studi_id' => $program->id, 'is_active' => true]);
            $other = App\\Models\\User::factory()->asMahasiswa()->create([...$phone, 'name' => $cfg['names']['otherStudent'], 'email' => $token.'-other@sita.test']);
            App\\Models\\MahasiswaProfile::factory()->create(['user_id' => $other->id, 'program_studi_id' => $program->id, 'is_active' => true]);
            $lecturer = App\\Models\\User::factory()->asDosen()->create([...$phone, 'name' => $cfg['names']['lecturer'], 'email' => $token.'-lecturer@sita.test']);
            App\\Models\\DosenProfile::factory()->create(['user_id' => $lecturer->id, 'program_studi_id' => $program->id, 'is_active' => true, 'supervision_quota' => 5]);
            $kaprodi = App\\Models\\User::factory()->asKaprodi()->create([...$phone, 'name' => $cfg['names']['kaprodi'], 'email' => $token.'-kaprodi@sita.test']);
            App\\Models\\KaprodiAssignment::factory()->create(['program_studi_id' => $program->id, 'user_id' => $kaprodi->id]);
            $chat = app(App\\Services\\PrivateChatService::class);

            return [
                'accounts' => ['student' => $student->id, 'otherStudent' => $other->id, 'lecturer' => $lecturer->id, 'kaprodi' => $kaprodi->id],
                'threads' => [
                    'studentLecturer' => $chat->findOrCreateThread($student, $lecturer)->id,
                    'otherStudentLecturer' => $chat->findOrCreateThread($other, $lecturer)->id,
                    'studentKaprodi' => $chat->findOrCreateThread($student, $kaprodi)->id,
                ],
            ];
        });
        echo 'SIDEBAR_UNREAD_FIXTURE='.json_encode($out).PHP_EOL;
    `;
}

function cleanupCode(token: string): string {
    return `
        ${decodeSnippet({ token })}
        Illuminate\\Support\\Facades\\DB::transaction(function () use ($cfg) {
            $token = $cfg['token'];
            $ids = App\\Models\\User::query()->where('email', 'like', $token.'%')->pluck('id')->all();
            $threadIds = App\\Models\\MentorshipChatThread::query()->whereIn('student_user_id', $ids)->pluck('id')
                ->merge(App\\Models\\MentorshipChatThreadParticipant::query()->whereIn('user_id', $ids)->pluck('thread_id'))
                ->unique()->values()->all();
            App\\Models\\MentorshipChatMessage::query()->whereIn('mentorship_chat_thread_id', $threadIds)->delete();
            App\\Models\\MentorshipChatRead::query()->whereIn('mentorship_chat_thread_id', $threadIds)->orWhereIn('user_id', $ids)->delete();
            App\\Models\\MentorshipChatThreadParticipant::query()->whereIn('thread_id', $threadIds)->delete();
            App\\Models\\MentorshipChatThread::query()->whereIn('id', $threadIds)->delete();
            Illuminate\\Support\\Facades\\DB::table('notifications')->where('notifiable_type', App\\Models\\User::class)->whereIn('notifiable_id', $ids)->delete();
            App\\Models\\User::query()->whereIn('id', $ids)->get()->each->delete();
            $program = App\\Models\\ProgramStudi::query()->where('slug', $token)->first();
            $facultyId = $program?->faculty_id;
            $program?->delete();
            if ($facultyId !== null) {
                App\\Models\\Faculty::query()->whereKey($facultyId)->delete();
            }
        });
    `;
}

export function createSidebarUnreadFixture(): SidebarUnreadFixture {
    const token = `sidebar-unread-${randomUUID()}`;
    const suffix = token.slice(-6);
    const names = {
        student: `Mahasiswa Unread ${suffix}`,
        otherStudent: `Mahasiswa Lain ${suffix}`,
        lecturer: `Dosen Unread ${suffix}`,
        kaprodi: `Kaprodi Unread ${suffix}`,
    };
    let output: string;

    try {
        output = php(setupCode(token, names));
    } catch (error) {
        // A failed transaction rolls back by itself; this only clears leftovers.
        php(cleanupCode(token));
        throw error;
    }

    const match = output.match(/SIDEBAR_UNREAD_FIXTURE=(\{.*\})/);

    if (match === null) {
        php(cleanupCode(token));
        throw new Error(`Fixture setup returned no ids: ${output}`);
    }

    const ids = JSON.parse(match[1]) as {
        accounts: Record<SidebarUnreadAccountKey, number>;
        threads: Record<SidebarUnreadThreadKey, number>;
    };
    const account = (
        key: SidebarUnreadAccountKey,
        role: SidebarUnreadAccount['role'],
    ): SidebarUnreadAccount => ({
        key,
        id: ids.accounts[key],
        name: names[key],
        email: `${token}-${key === 'otherStudent' ? 'other' : key}@sita.test`,
        password: 'password',
        role,
        homePath: `/${role}/dashboard`,
        pesanPath: role === 'mahasiswa' ? '/mahasiswa/pesan' : '/dosen/pesan',
        otherPagePath: otherPages[role],
    });

    return {
        token,
        accounts: {
            student: account('student', 'mahasiswa'),
            otherStudent: account('otherStudent', 'mahasiswa'),
            lecturer: account('lecturer', 'dosen'),
            kaprodi: account('kaprodi', 'kaprodi'),
        },
        threads: {
            studentLecturer: {
                key: 'studentLecturer',
                id: ids.threads.studentLecturer,
                participants: ['student', 'lecturer'],
            },
            otherStudentLecturer: {
                key: 'otherStudentLecturer',
                id: ids.threads.otherStudentLecturer,
                participants: ['otherStudent', 'lecturer'],
            },
            studentKaprodi: {
                key: 'studentKaprodi',
                id: ids.threads.studentKaprodi,
                participants: ['student', 'kaprodi'],
            },
        },
        cleanup: () => php(cleanupCode(token)),
    };
}

/* ------------------------------------------------------------------ */
/* Database setup helpers. They never broadcast.                        */
/* ------------------------------------------------------------------ */

/** Stores a text message from `sender` in the thread, as an unread message for the other side. */
export function seedIncomingMessage(
    fixture: SidebarUnreadFixture,
    threadKey: SidebarUnreadThreadKey,
    senderKey: SidebarUnreadAccountKey,
    text: string,
): void {
    php(`
        ${decodeSnippet({
            thread: fixture.threads[threadKey].id,
            sender: fixture.accounts[senderKey].id,
            text,
        })}
        App\\Models\\MentorshipChatMessage::query()->create(['mentorship_chat_thread_id' => $cfg['thread'], 'sender_user_id' => $cfg['sender'], 'message_type' => 'text', 'message' => $cfg['text'], 'sent_at' => now()]);
    `);
}

/** Stores `count` text messages from `sender` in the thread in one database round trip. */
export function seedIncomingMessages(
    fixture: SidebarUnreadFixture,
    threadKey: SidebarUnreadThreadKey,
    senderKey: SidebarUnreadAccountKey,
    count: number,
    prefix: string,
): void {
    php(`
        ${decodeSnippet({
            thread: fixture.threads[threadKey].id,
            sender: fixture.accounts[senderKey].id,
            count,
            prefix,
        })}
        for ($i = 1; $i <= $cfg['count']; $i++) {
            App\\Models\\MentorshipChatMessage::query()->create(['mentorship_chat_thread_id' => $cfg['thread'], 'sender_user_id' => $cfg['sender'], 'message_type' => 'text', 'message' => $cfg['prefix'].' '.$i, 'sent_at' => now()]);
        }
    `);
}

/** Writes the same read marker the Pesan page writes once a thread is opened. */
export function markThreadReadInDatabase(
    fixture: SidebarUnreadFixture,
    threadKey: SidebarUnreadThreadKey,
    readerKey: SidebarUnreadAccountKey,
): void {
    php(`
        ${decodeSnippet({
            thread: fixture.threads[threadKey].id,
            reader: fixture.accounts[readerKey].id,
        })}
        $last = App\\Models\\MentorshipChatMessage::query()->where('mentorship_chat_thread_id', $cfg['thread'])->max('created_at');
        App\\Models\\MentorshipChatRead::query()->updateOrCreate(['mentorship_chat_thread_id' => $cfg['thread'], 'user_id' => $cfg['reader']], ['last_read_at' => $last ?? now()]);
    `);
}

/** Inserts one unread database notification (the header bell), unrelated to chat read state. */
export function insertNotification(
    fixture: SidebarUnreadFixture,
    accountKey: SidebarUnreadAccountKey,
    title: string,
    url?: string,
): void {
    const account = fixture.accounts[accountKey];

    php(`
        ${decodeSnippet({
            user: account.id,
            title,
            url: url ?? account.otherPagePath,
        })}
        App\\Models\\User::query()->findOrFail($cfg['user'])->notifications()->create(['id' => (string) Illuminate\\Support\\Str::uuid(), 'type' => App\\Notifications\\RealtimeNotification::class, 'data' => ['title' => $cfg['title'], 'description' => 'Pemberitahuan uji sidebar.', 'url' => $cfg['url'], 'icon' => 'bell'], 'read_at' => null, 'created_at' => now(), 'updated_at' => now()]);
    `);
}

/** Switches the "Pesan baru" notification preference and clears stored notifications. */
export function resetNotifications(
    fixture: SidebarUnreadFixture,
    accountKey: SidebarUnreadAccountKey,
    pesanBaru: boolean,
): void {
    php(`
        ${decodeSnippet({
            user: fixture.accounts[accountKey].id,
            pesanBaru,
        })}
        $user = App\\Models\\User::query()->findOrFail($cfg['user']);
        $user->notifications()->delete();
        $preferences = $user->resolvedNotificationPreferences();
        $preferences['pesanBaru'] = $cfg['pesanBaru'];
        $user->forceFill(['notification_preferences' => $preferences])->save();
    `);
}

/* ------------------------------------------------------------------ */
/* Browser helpers                                                     */
/* ------------------------------------------------------------------ */

export type SidebarUnreadSession = {
    account: SidebarUnreadAccount;
    context: BrowserContext;
    page: Page;
    /** Number of real `chat.unread.updated` websocket frames this session received. */
    unreadEvents: () => number;
    close: () => Promise<void>;
};

function trackUnreadEvents(page: Page): () => number {
    let count = 0;

    page.on('websocket', (socket) => {
        socket.on('framereceived', (frame) => {
            if (String(frame.payload).includes('chat.unread.updated')) {
                count += 1;
            }
        });
    });

    return () => count;
}

export async function loginAs(
    page: Page,
    account: SidebarUnreadAccount,
): Promise<void> {
    await page.context().clearCookies();
    await page.goto('/login');
    await page.getByLabel('Email', { exact: true }).fill(account.email);
    await page.getByLabel('Kata sandi', { exact: true }).fill(account.password);
    await page.getByRole('button', { name: /log in|masuk/i }).click();
    await expect(page).toHaveURL(/\/(mahasiswa|dosen|kaprodi)\/dashboard$/);
}

/** A fresh browser context signed in as the fixture account, at the given viewport. */
export async function openSession(
    browser: Browser,
    account: SidebarUnreadAccount,
    viewport: { width: number; height: number } = desktopViewport,
): Promise<SidebarUnreadSession> {
    const context = await browser.newContext({
        storageState: { cookies: [], origins: [] },
        viewport,
    });
    const page = await context.newPage();
    const unreadEvents = trackUnreadEvents(page);

    await loginAs(page, account);

    return {
        account,
        context,
        page,
        unreadEvents,
        close: () => context.close(),
    };
}

/**
 * Waits for the real Reverb private channel `chat.unread.{userId}` to be
 * subscribed by the page's Echo client. No event is ever dispatched by hand.
 */
export async function waitForUnreadChannel(
    page: Page,
    account: SidebarUnreadAccount,
): Promise<void> {
    await page.waitForFunction((userId) => {
        const pusher = window.Echo?.connector?.pusher;

        return (
            pusher?.connection?.state === 'connected' &&
            pusher.channel(`private-chat.unread.${userId}`)?.subscribed === true
        );
    }, account.id);
}

/** The shared count as the authenticated endpoint reports it. */
export async function fetchUnreadCount(page: Page): Promise<unknown> {
    const response = await page.context().request.get('/chat/unread', {
        headers: { Accept: 'application/json' },
    });

    expect(response.status()).toBe(200);

    return response.json();
}

export function sidebarScope(page: Page, drawer = false): Locator {
    return drawer
        ? page.locator('[data-slot="sidebar"][data-mobile="true"]')
        : page.locator('[data-slot="sidebar"][data-state]');
}

export function pesanLink(
    page: Page,
    account: SidebarUnreadAccount,
    drawer = false,
): Locator {
    return sidebarScope(page, drawer).locator(`a[href="${account.pesanPath}"]`);
}

export async function openSidebarDrawer(page: Page): Promise<void> {
    await page.locator('[data-slot="sidebar-trigger"]').click();
    await expect(sidebarScope(page, true)).toBeVisible();
}

export async function closeSidebarDrawer(page: Page): Promise<void> {
    await page.keyboard.press('Escape');
    await expect(sidebarScope(page, true)).toHaveCount(0);
}

/** Visible badge text: the exact count up to 99, "99+" above it. */
export function badgeText(count: number): string {
    return count > 99 ? '99+' : String(count);
}

/** The numeric badge: the only aria-hidden span that is a direct child of the link. */
export function pesanBadge(link: Locator): Locator {
    return link.locator(':scope > span[aria-hidden="true"]');
}

/** The "Pesan" label: the direct child span that is not the badge. */
export function pesanLabel(link: Locator): Locator {
    return link.locator(':scope > span:not([aria-hidden])');
}

/** Unread: data-unread, exact accessible count label, one numeric aria-hidden badge. */
export async function expectPesanUnread(
    link: Locator,
    count = 1,
): Promise<void> {
    await expect(link).toHaveAttribute('data-unread', 'true');
    await expect(link).toHaveAttribute('aria-label', unreadPesanLabel(count));
    await expect(pesanBadge(link)).toHaveCount(1);
    await expect(pesanBadge(link)).toHaveText(badgeText(count));
    await expect(pesanLabel(link)).toHaveText('Pesan');
    expect(((await link.textContent()) ?? '').replace(/\s+/g, '')).toBe(
        `Pesan${badgeText(count)}`,
    );
}

/** Read: no flag, no badge (never a "0"), plain "Pesan" label and name. */
export async function expectPesanRead(link: Locator): Promise<void> {
    await expect(link).not.toHaveAttribute('data-unread', 'true');
    await expect(link).not.toHaveAttribute('aria-label', /^Pesan, /);
    await expect(pesanBadge(link)).toHaveCount(0);
    expect(((await link.textContent()) ?? '').trim()).toBe('Pesan');
}

async function colors(locator: Locator): Promise<{ bg: string; fg: string }> {
    return locator.evaluate((element) => {
        const style = getComputedStyle(element);

        return { bg: style.backgroundColor, fg: style.color };
    });
}

/**
 * The Pesan badge renders with the same destructive background and foreground
 * as the header bell badge. Needs an unrelated unread notification so the bell
 * badge exists.
 */
export async function expectBadgeMatchesBell(
    page: Page,
    link: Locator,
): Promise<void> {
    const bellBadge = page
        .getByRole('button', { name: 'Open notifications' })
        .locator('span')
        .first();

    await expect(bellBadge).toBeVisible();
    const bell = await colors(bellBadge);
    const badge = await colors(pesanBadge(link));

    expect(bell.bg).not.toBe('rgba(0, 0, 0, 0)');
    expect(badge).toEqual(bell);
}

/** Expanded sidebar / drawer: the badge sits next to the label, vertically centred on it. */
export async function expectBadgeBesideLabel(link: Locator): Promise<void> {
    const measurements = await link.evaluate((element) => {
        const badge = element
            .querySelector(':scope > span[aria-hidden="true"]')!
            .getBoundingClientRect();
        const label = element
            .querySelector(':scope > span:not([aria-hidden])')!
            .getBoundingClientRect();

        return {
            gap: badge.left - label.right,
            verticalOffset: Math.abs(
                (badge.top + badge.bottom - label.top - label.bottom) / 2,
            ),
        };
    });
    const { gap, verticalOffset } = measurements;

    expect(gap).toBeGreaterThanOrEqual(0);
    expect(gap).toBeLessThanOrEqual(8);
    expect(verticalOffset).toBeLessThanOrEqual(2);
}

/** Geometry that must not move when the count changes: the link target, its label and its icon. */
export async function pesanGeometry(link: Locator): Promise<{
    link: unknown;
    label: unknown;
    icon: unknown;
}> {
    return {
        link: await link.boundingBox(),
        label: await pesanLabel(link)
            .boundingBox()
            .catch(() => null),
        icon: await link.locator('svg').first().boundingBox(),
    };
}

/** Clicks the sidebar trigger only when the desktop sidebar is not already in `state`. */
export async function setSidebarState(
    page: Page,
    state: 'expanded' | 'collapsed',
): Promise<void> {
    const sidebar = page.locator('[data-slot="sidebar"][data-state]');

    if ((await sidebar.getAttribute('data-state')) !== state) {
        await page.locator('[data-slot="sidebar-trigger"]').click();
    }

    await expect(sidebar).toHaveAttribute('data-state', state);
    await page.waitForFunction(() =>
        document
            .getAnimations()
            .every(
                (animation) =>
                    animation.effect?.getTiming().iterations === Infinity ||
                    animation.playState !== 'running',
            ),
    );
}

/** Header bell count (text of the destructive badge), 0 when the bell has none. */
export async function bellCount(page: Page): Promise<number> {
    const text =
        (await page
            .getByRole('button', { name: 'Open notifications' })
            .textContent()) ?? '';

    return Number(text.trim() || '0');
}

export function readRequestPattern(threadId: number): RegExp {
    return new RegExp(`/pesan/${threadId}/read$`);
}

/**
 * Opens a private thread through the unified list: search the partner, then
 * choose the intended conversation even when a group has the same name.
 * With `expectRead` it also waits for the successful receipt request that
 * marks the thread read.
 */
export async function openPrivateThread(
    page: Page,
    fixture: SidebarUnreadFixture,
    viewerKey: SidebarUnreadAccountKey,
    threadKey: SidebarUnreadThreadKey,
    { expectRead = true }: { expectRead?: boolean } = {},
): Promise<void> {
    const viewer = fixture.accounts[viewerKey];
    const thread = fixture.threads[threadKey];
    const partnerKey = thread.participants.find((key) => key !== viewerKey);

    if (partnerKey === undefined) {
        throw new Error(`${viewerKey} is not a participant of ${threadKey}`);
    }

    const partner = fixture.accounts[partnerKey];

    // The Pesan page reads the visible thread by itself (also right after load),
    // so record every successful receipt for this thread instead of racing one click.
    let receiptSaved = false;
    const onResponse = (response: Response): void => {
        if (
            response.request().method() === 'POST' &&
            readRequestPattern(thread.id).test(response.url()) &&
            response.ok()
        ) {
            receiptSaved = true;
        }
    };

    page.on('response', onResponse);

    try {
        await page.goto(viewer.pesanPath);
        await page
            .getByRole('button', { name: 'Cari percakapan', exact: true })
            .click();
        await page.getByPlaceholder('Cari percakapan...').fill(partner.name);

        const row = page.locator(`[data-thread-id="${thread.id}"]`);
        await expect(row).toBeVisible();
        await row.click();
        await expect(page.getByPlaceholder('Tulis pesan...')).toBeEnabled();

        if (expectRead) {
            await expect.poll(() => receiptSaved).toBe(true);
        }
    } finally {
        page.off('response', onResponse);
    }
}

/** Sends a message through the real composer of the thread. */
export async function sendMessageFromUi(
    page: Page,
    fixture: SidebarUnreadFixture,
    senderKey: SidebarUnreadAccountKey,
    threadKey: SidebarUnreadThreadKey,
    text: string,
): Promise<void> {
    await openPrivateThread(page, fixture, senderKey, threadKey, {
        expectRead: false,
    });

    const submit = page.waitForResponse(
        (response) =>
            response.request().method() === 'POST' &&
            new RegExp(
                `/pesan/${fixture.threads[threadKey].id}/messages$`,
            ).test(response.url()),
    );
    const input = page.getByPlaceholder('Tulis pesan...');

    await input.fill(text);
    await input.press('Enter');
    await submit;
    await expect(page.getByText(text).last()).toBeVisible();
}

/** Opens the drawer, runs `check` against the in-drawer Pesan link, then closes it again. */
export async function withDrawerPesan(
    page: Page,
    account: SidebarUnreadAccount,
    check: (link: Locator) => Promise<void>,
): Promise<void> {
    await openSidebarDrawer(page);
    await check(pesanLink(page, account, true));
    await closeSidebarDrawer(page);
}
