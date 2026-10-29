import { createHash } from 'node:crypto';
import fs from 'node:fs/promises';
import path from 'node:path';

import { expect, test, type Page, type Locator } from '@playwright/test';

import { cameraFixture } from '../support/chat-image-fixtures';
import { runArtisan } from '../support/db';
import { attachDocumentStates } from '../support/document-detail';
import {
    createSidebarUnreadFixture,
    openPrivateThread,
    openSession,
} from '../support/sidebar-unread';

const row = (page: Page, id: number) =>
    page.locator(`[data-message-id="${id}"]`);
async function action(page: Page, bubble: Locator, name: string) {
    await bubble
        .getByRole('button', { name: 'Aksi pesan', exact: true })
        .locator('..')
        .hover();
    await bubble
        .getByRole('button', { name: 'Aksi pesan', exact: true })
        .click();
    await page.getByRole('menuitem', { name, exact: true }).click();
}
async function send(
    page: Page,
    text: string,
): Promise<
    {
        id: number;
        documentUrl: string | null;
        attachmentSizeKb: number | null;
    }[]
> {
    await page.getByPlaceholder('Tulis pesan...').fill(text);
    const saved = page.waitForResponse(
        (response) =>
            response.request().method() === 'POST' &&
            /\/messages$/.test(response.url()),
    );
    await page
        .getByRole('button', { name: 'Kirim pesan', exact: true })
        .click();
    const response = await saved;
    expect(response.status()).toBe(201);
    await expect(page.getByPlaceholder('Tulis pesan...')).toHaveValue('');
    return (await response.json()).messages;
}

test('two accounts reply to text and files and edit live text/captions with cancel error and reload', async ({
    browser,
}, info) => {
    const fixture = createSidebarUnreadFixture();
    const owner = await openSession(browser, fixture.accounts.student);
    const peer = await openSession(browser, fixture.accounts.lecturer);
    try {
        await openPrivateThread(
            owner.page,
            fixture,
            'student',
            'studentLecturer',
            { expectRead: false },
        );
        await openPrivateThread(
            peer.page,
            fixture,
            'lecturer',
            'studentLecturer',
            { expectRead: false },
        );
        await peer.page.waitForFunction(
            (id) =>
                window.Echo.connector.pusher.channel(
                    `private-mentorship.thread.${id}`,
                )?.subscribed,
            fixture.threads.studentLecturer.id,
        );
        const [source] = await send(owner.page, 'Metode penelitian versi awal');
        await expect(row(peer.page, source.id)).toContainText(
            'Metode penelitian versi awal',
        );
        await action(peer.page, row(peer.page, source.id), 'Balas');
        await attachDocumentStates(peer.page, info, 'dosen-reply-context');
        await peer.page.getByRole('button', { name: 'Batal balas' }).click();
        await expect(
            peer.page.getByRole('region', { name: 'Membalas pesan' }),
        ).toHaveCount(0);
        await action(peer.page, row(peer.page, source.id), 'Balas');
        const [reply] = await send(peer.page, 'Tambahkan pembahasan data.');
        await expect(
            row(owner.page, reply.id).getByRole('button', {
                name: 'Lihat pesan asal',
            }),
        ).toContainText('Metode penelitian versi awal');
        await owner.page
            .getByPlaceholder('Tulis pesan...')
            .fill('Draft pesan berikutnya');
        await action(
            owner.page,
            row(owner.page, source.id),
            'Edit teks / caption',
        );
        await owner.page
            .getByPlaceholder('Tulis pesan...')
            .fill('Edit dibatalkan');
        await owner.page.getByRole('button', { name: 'Batal edit' }).click();
        await expect(owner.page.getByPlaceholder('Tulis pesan...')).toHaveValue(
            'Draft pesan berikutnya',
        );
        await action(
            owner.page,
            row(owner.page, source.id),
            'Edit teks / caption',
        );
        const url = `**/pesan/${fixture.threads.studentLecturer.id}/messages/${source.id}`;
        await owner.page.route(url, (route) =>
            route.fulfill({
                status: 422,
                contentType: 'application/json',
                body: JSON.stringify({
                    errors: { message: ['Perubahan ditolak demo.'] },
                }),
            }),
        );
        await owner.page
            .getByPlaceholder('Tulis pesan...')
            .fill('Metode penelitian sudah diperbaiki');
        await owner.page
            .getByRole('button', { name: 'Simpan perubahan' })
            .click();
        await expect(
            owner.page.getByText('Perubahan ditolak demo.'),
        ).toBeVisible();
        await expect(owner.page.getByPlaceholder('Tulis pesan...')).toHaveValue(
            'Metode penelitian sudah diperbaiki',
        );
        await attachDocumentStates(owner.page, info, 'mahasiswa-edit-error');
        await owner.page.unroute(url);
        await owner.page
            .getByRole('button', { name: 'Simpan perubahan' })
            .click();
        await expect(row(peer.page, source.id)).toContainText('Diedit');
        await expect(
            row(peer.page, reply.id).getByRole('button', {
                name: 'Lihat pesan asal',
            }),
        ).toContainText('Metode penelitian sudah diperbaiki');
        await expect(row(peer.page, source.id)).toHaveCount(1);
        await row(peer.page, reply.id)
            .getByRole('button', { name: 'Lihat pesan asal' })
            .click();
        await expect(row(peer.page, source.id)).toBeInViewport();
        await owner.page.getByPlaceholder('Tulis pesan...').fill('');
        await owner.page
            .locator('input[type=file]')
            .setInputFiles(
                path.join(process.cwd(), 'tests/fixtures/chat-image.png'),
            );
        const [file, caption] = await send(owner.page, 'Caption gambar awal');
        await expect(row(peer.page, file.id).locator('img')).toBeVisible();
        await action(peer.page, row(peer.page, file.id), 'Balas');
        const [fileReply] = await send(peer.page, 'Gambar terbaca.');
        await expect(
            row(owner.page, fileReply.id).getByRole('button', {
                name: 'Lihat pesan asal',
            }),
        ).toContainText('chat-image.png');
        await action(
            owner.page,
            row(owner.page, caption.id),
            'Edit teks / caption',
        );
        await owner.page
            .getByPlaceholder('Tulis pesan...')
            .fill('Caption gambar diperbaiki');
        await attachDocumentStates(owner.page, info, 'mahasiswa-edit-caption');
        await owner.page
            .getByRole('button', { name: 'Simpan perubahan' })
            .click();
        await expect(row(peer.page, caption.id)).toContainText(
            'Caption gambar diperbaiki',
        );
        await peer.page.reload();
        await expect(row(peer.page, reply.id)).toContainText(
            'Metode penelitian sudah diperbaiki',
        );
        await expect(row(peer.page, caption.id)).toContainText(
            'Caption gambar diperbaiki',
        );
        await row(peer.page, source.id)
            .getByText('Metode penelitian sudah diperbaiki', { exact: true })
            .hover();
        await row(peer.page, source.id)
            .getByRole('button', { name: 'Aksi pesan' })
            .click();
        await expect(
            peer.page.getByRole('menuitem', { name: 'Edit teks / caption' }),
        ).toHaveCount(0);
        await peer.page.keyboard.press('Escape');
        await attachDocumentStates(
            peer.page,
            info,
            'dosen-live-edited-replies',
        );
        await attachDocumentStates(
            owner.page,
            info,
            'mahasiswa-live-edited-replies',
        );
    } finally {
        await owner.close();
        await peer.close();
        fixture.cleanup();
    }
});

test('file drop and paste share measured image preparation; failed preparation and upload preserve drafts and retry', async ({
    browser,
}, info) => {
    const fixture = createSidebarUnreadFixture();
    const owner = await openSession(browser, fixture.accounts.student);
    try {
        await openPrivateThread(
            owner.page,
            fixture,
            'student',
            'studentLecturer',
            { expectRead: false },
        );
        const file = await cameraFixture(owner.page, info);
        const bytes = await fs.readFile(file);
        const received: Buffer[] = [];
        for (const entry of ['file', 'drop', 'paste'] as const) {
            if (entry === 'file')
                await owner.page
                    .locator('input[type=file]')
                    .setInputFiles(file);
            else
                await owner.page.evaluate(
                    ({ base64, entry }) => {
                        const bytes = Uint8Array.from(atob(base64), (char) =>
                            char.charCodeAt(0),
                        );
                        const transfer = new DataTransfer();
                        transfer.items.add(
                            new File([bytes], 'large-camera.jpg', {
                                type: 'image/jpeg',
                            }),
                        );
                        const input = document.querySelector(
                            'input[placeholder="Tulis pesan..."]',
                        )!;
                        if (entry === 'paste')
                            input.dispatchEvent(
                                new ClipboardEvent('paste', {
                                    clipboardData: transfer,
                                    bubbles: true,
                                    cancelable: true,
                                }),
                            );
                        else
                            input
                                .closest('[data-slot="card-footer"]')!
                                .dispatchEvent(
                                    new DragEvent('drop', {
                                        dataTransfer: transfer,
                                        bubbles: true,
                                        cancelable: true,
                                    }),
                                );
                    },
                    { base64: bytes.toString('base64'), entry },
                );
            await expect(
                owner.page.getByText('Menyiapkan gambar...'),
            ).toHaveCount(0);
            await expect(
                owner.page.getByRole('button', { name: 'Hapus lampiran' }),
            ).toBeVisible();
            if (entry === 'file') {
                const url = `**/pesan/${fixture.threads.studentLecturer.id}/messages`;
                await owner.page.route(url, (route) => route.abort('failed'));
                await owner.page
                    .getByPlaceholder('Tulis pesan...')
                    .fill('Bukti optimasi');
                await owner.page
                    .getByRole('button', { name: 'Kirim pesan' })
                    .click();
                await expect(
                    owner.page.getByText(
                        'Pesan belum terkirim. Periksa koneksi dan coba lagi.',
                    ),
                ).toBeVisible();
                await expect(
                    owner.page.getByPlaceholder('Tulis pesan...'),
                ).toHaveValue('Bukti optimasi');
                await attachDocumentStates(
                    owner.page,
                    info,
                    'mahasiswa-image-retry',
                );
                await owner.page.unroute(url);
            }
            const [image] = await send(owner.page, `Bukti optimasi ${entry}`);
            const response = await owner.page.request.get(image.documentUrl!);
            expect(response.status()).toBe(200);
            expect(response.headers()['content-type']).toContain('image/jpeg');
            const uploaded = await response.body();
            expect(uploaded.length).toBeLessThan(bytes.length);
            expect(image.attachmentSizeKb).toBe(
                Math.ceil(uploaded.length / 1024),
            );
            received.push(uploaded);
            const dimensions = await row(owner.page, image.id)
                .locator('img')
                .evaluate((image: HTMLImageElement) => ({
                    width: image.naturalWidth,
                    height: image.naturalHeight,
                }));
            expect(dimensions).toEqual({ width: 2048, height: 1536 });
        }
        expect(createHash('sha256').update(received[0]).digest('hex')).toBe(
            createHash('sha256').update(received[1]).digest('hex'),
        );
        expect(received[1].equals(received[2])).toBe(true);
        await info.attach('image-measurements', {
            body: JSON.stringify({
                sourceBytes: bytes.length,
                uploadedBytes: received.map((b) => b.length),
                width: 2048,
                height: 1536,
                quality: 0.84,
            }),
            contentType: 'application/json',
        });
        await owner.page
            .locator('input[type=file]')
            .setInputFiles(
                path.join(process.cwd(), 'tests/fixtures/chat-image.png'),
            );
        await owner.page
            .getByPlaceholder('Tulis pesan...')
            .fill('Draft harus bertahan');
        await owner.page.evaluate(() => {
            const original = window.createImageBitmap;
            window.createImageBitmap = () => {
                window.createImageBitmap = original;
                return Promise.reject(new Error('decode demo'));
            };
        });
        await owner.page.locator('input[type=file]').setInputFiles(file);
        await expect(
            owner.page.getByText('Gambar gagal disiapkan.', { exact: false }),
        ).toBeVisible();
        await expect(owner.page.getByPlaceholder('Tulis pesan...')).toHaveValue(
            'Draft harus bertahan',
        );
        await expect(
            owner.page.getByText('chat-image.png', { exact: true }).last(),
        ).toBeVisible();
        await expect(
            owner.page.getByRole('button', { name: 'Kirim pesan' }),
        ).toBeDisabled();
        await attachDocumentStates(
            owner.page,
            info,
            'mahasiswa-image-preparation-error',
        );
        await owner.page
            .getByRole('button', { name: 'Coba siapkan lagi' })
            .click();
        await expect(
            owner.page.getByText('Gambar gagal disiapkan.', { exact: false }),
        ).toHaveCount(0);
        await expect(owner.page.getByPlaceholder('Tulis pesan...')).toHaveValue(
            'Draft harus bertahan',
        );
        await attachDocumentStates(
            owner.page,
            info,
            'mahasiswa-prepared-image',
        );
        await send(owner.page, 'Bukti setelah retry');
        const pdf = path.join(
            process.cwd(),
            'tests/fixtures/panduan-template.pdf',
        );
        await owner.page.locator('input[type=file]').setInputFiles(pdf);
        const [document] = await send(owner.page, 'PDF tidak berubah');
        const downloaded = await (
            await owner.page.request.get(document.documentUrl!)
        ).body();
        expect(downloaded.equals(await fs.readFile(pdf))).toBe(true);
    } finally {
        await owner.close();
        fixture.cleanup();
    }
});

test('completed conversation retains live quotations without reply or edit controls', async ({
    browser,
}, info) => {
    const fixture = createSidebarUnreadFixture();
    const studentId = fixture.accounts.student.id;
    const lecturerId = fixture.accounts.lecturer.id;
    const threadId = fixture.threads.studentLecturer.id;
    runArtisan([
        'tinker',
        `--execute=$project = App\\Models\\ThesisProject::factory()->create(['student_user_id' => ${studentId}, 'program_studi_id' => App\\Models\\MahasiswaProfile::where('user_id', ${studentId})->value('program_studi_id'), 'state' => 'completed', 'phase' => 'completed', 'completed_at' => now()]); App\\Models\\ThesisSupervisorAssignment::create(['project_id' => $project->id, 'lecturer_user_id' => ${lecturerId}, 'role' => 'primary', 'status' => 'ended', 'started_at' => now()->subMonth(), 'ended_at' => now()]); $thread = App\\Models\\MentorshipChatThread::findOrFail(${threadId}); $thread->update(['type' => 'pembimbing', 'private_key' => null]); $source = $thread->messages()->create(['sender_user_id' => ${studentId}, 'message_type' => 'text', 'message' => 'Catatan sebelum selesai']); $thread->messages()->create(['sender_user_id' => ${lecturerId}, 'message_type' => 'text', 'message' => 'Balasan tersimpan', 'reply_to_id' => $source->id]);`,
    ]);
    for (const role of ['student', 'lecturer'] as const) {
        const session = await openSession(browser, fixture.accounts[role]);
        try {
            await session.page.goto(
                `${fixture.accounts[role].pesanPath}?thread=${threadId}`,
            );
            await expect(
                session.page.getByText('hanya dapat dibaca', { exact: false }),
            ).toBeVisible();
            await expect(
                session.page.getByPlaceholder('Tulis pesan...'),
            ).toHaveCount(0);
            await expect(
                session.page.getByRole('button', { name: 'Aksi pesan' }),
            ).toHaveCount(0);
            await expect(
                session.page.getByRole('button', { name: 'Lihat pesan asal' }),
            ).toContainText('Catatan sebelum selesai');
            await attachDocumentStates(
                session.page,
                info,
                `${role}-completed-chat`,
            );
        } finally {
            await session.close();
        }
    }
    fixture.cleanup();
});
