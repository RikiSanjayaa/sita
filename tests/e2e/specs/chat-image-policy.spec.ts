import fs from 'node:fs/promises';
import path from 'node:path';

import { expect, test } from '@playwright/test';

import { cameraFixture } from '../support/chat-image-fixtures';
import {
    createSidebarUnreadFixture,
    openPrivateThread,
    openSession,
} from '../support/sidebar-unread';

test('native preparation preserves EXIF orientation, alpha, animation and small-image bytes', async ({
    browser,
}, info) => {
    const fixture = createSidebarUnreadFixture();
    const owner = await openSession(browser, fixture.accounts.student);
    const page = owner.page;
    async function upload(name: string, mimeType: string, buffer: Buffer) {
        await page
            .locator('input[type=file]')
            .setInputFiles({ name, mimeType, buffer });
        await expect(
            page.getByRole('button', { name: 'Kirim pesan' }),
        ).toBeEnabled();
        const response = page.waitForResponse(
            (r) =>
                r.request().method() === 'POST' && /\/messages$/.test(r.url()),
        );
        await page.getByRole('button', { name: 'Kirim pesan' }).click();
        const saved = await response;
        expect(saved.status()).toBe(201);
        await expect(
            page.getByRole('button', { name: 'Hapus lampiran' }),
        ).toHaveCount(0);
        return (await saved.json()).messages[0] as {
            id: number;
            documentUrl: string;
        };
    }
    try {
        await openPrivateThread(page, fixture, 'student', 'studentLecturer', {
            expectRead: false,
        });
        const camera = await fs.readFile(await cameraFixture(page, info));
        // APP1 EXIF orientation 6, little endian: 90 degrees clockwise.
        const exif = Buffer.from(
            'ffe1002245786966000049492a0008000000010012010300010000000600000000000000',
            'hex',
        );
        const portrait = Buffer.concat([
            camera.subarray(0, 2),
            exif,
            camera.subarray(2),
        ]);
        const rotated = await upload('portrait.jpg', 'image/jpeg', portrait);
        await expect
            .poll(() =>
                page
                    .locator(`[data-message-id="${rotated.id}"] img`)
                    .evaluate((image: HTMLImageElement) => [
                        image.naturalWidth,
                        image.naturalHeight,
                    ]),
            )
            .toEqual([1536, 2048]);
        const transparent = await page.evaluate(() => {
            const c = document.createElement('canvas');
            c.width = 2600;
            c.height = 2200;
            const x = c.getContext('2d')!;
            const image = x.createImageData(2600, 2200);
            for (let i = 0; i < image.data.length; i += 4) {
                image.data[i] = Math.floor(((i / 4) % 2600) / 12);
                image.data[i + 1] = Math.floor(i / 4 / 2600 / 10);
                image.data[i + 2] = 180;
                image.data[i + 3] = (i / 4) % 2600 < 200 ? 0 : 140;
            }
            x.putImageData(image, 0, 0);
            return c.toDataURL('image/png').split(',')[1];
        });
        // A valid, large ancillary metadata chunk exercises preparation without
        // depending on the PHP runtime's lower multipart upload ceiling.
        const metadata = Buffer.from(
            'Comment\0' + 'SiTA alpha fixture '.repeat(40000),
        );
        const kind = Buffer.from('tEXt');
        let crc = 0xffffffff;
        for (const byte of Buffer.concat([kind, metadata])) {
            crc ^= byte;
            for (let bit = 0; bit < 8; bit++)
                crc = (crc >>> 1) ^ (crc & 1 ? 0xedb88320 : 0);
        }
        const size = Buffer.alloc(4);
        size.writeUInt32BE(metadata.length);
        const checksum = Buffer.alloc(4);
        checksum.writeUInt32BE((crc ^ 0xffffffff) >>> 0);
        const png = Buffer.from(transparent, 'base64');
        const alphaSource = Buffer.concat([
            png.subarray(0, 33),
            size,
            kind,
            metadata,
            checksum,
            png.subarray(33),
        ]);
        const alpha = await upload('alpha.png', 'image/png', alphaSource);
        expect(
            await page.evaluate(async (url) => {
                const bitmap = await createImageBitmap(
                    await (await fetch(url)).blob(),
                );
                const c = document.createElement('canvas');
                c.width = bitmap.width;
                c.height = bitmap.height;
                const x = c.getContext('2d')!;
                x.drawImage(bitmap, 0, 0);
                return [
                    x.getImageData(0, 0, 1, 1).data[3],
                    x.getImageData(1000, 1000, 1, 1).data[3],
                ];
            }, alpha.documentUrl),
        ).toEqual([0, 140]);
        const renamed = await upload('renamed.jpg', 'image/jpeg', alphaSource);
        const renamedResponse = await page.request.get(renamed.documentUrl);
        expect(renamedResponse.headers()['content-type']).toContain(
            'image/png',
        );
        expect((await renamedResponse.body()).equals(alphaSource)).toBe(true);
        const staticWebp = Buffer.from(
            await page.evaluate(() => {
                const c = document.createElement('canvas');
                c.width = 16;
                c.height = 16;
                const x = c.getContext('2d')!;
                x.fillStyle = 'red';
                x.fillRect(0, 0, 16, 16);
                return c.toDataURL('image/webp').split(',')[1];
            }),
            'base64',
        );
        const chunk = (name: string, body: Buffer) => {
            const header = Buffer.alloc(8);
            header.write(name);
            header.writeUInt32LE(body.length, 4);
            return Buffer.concat([header, body, Buffer.alloc(body.length % 2)]);
        };
        const extended = Buffer.alloc(10);
        extended[0] = 6;
        extended.writeUIntLE(15, 4, 3);
        extended.writeUIntLE(15, 7, 3);
        const frame = Buffer.alloc(16);
        frame.writeUIntLE(15, 6, 3);
        frame.writeUIntLE(15, 9, 3);
        frame.writeUIntLE(100, 12, 3);
        const body = Buffer.concat([
            Buffer.from('WEBP'),
            chunk('VP8X', extended),
            chunk('ANIM', Buffer.alloc(6)),
            chunk('ANMF', Buffer.concat([frame, staticWebp.subarray(12)])),
            chunk('XMP ', Buffer.alloc(600000, 32)),
        ]);
        const header = Buffer.alloc(8);
        header.write('RIFF');
        header.writeUInt32LE(body.length, 4);
        const animation = Buffer.concat([header, body]);
        const animated = await upload(
            'animation.webp',
            'image/webp',
            animation,
        );
        expect(
            (
                await (await page.request.get(animated.documentUrl)).body()
            ).equals(animation),
        ).toBe(true);
        const small = await fs.readFile(
            path.join(process.cwd(), 'tests/fixtures/chat-image.png'),
        );
        const tiny = await upload('small.png', 'image/png', small);
        expect(
            (await (await page.request.get(tiny.documentUrl)).body()).equals(
                small,
            ),
        ).toBe(true);
        const economical = Buffer.from(
            await page.evaluate(() => {
                const c = document.createElement('canvas');
                c.width = 2048;
                c.height = 2048;
                const x = c.getContext('2d')!;
                const data = x.createImageData(2048, 2048);
                let seed = 174;
                for (let i = 0; i < data.data.length; i += 4) {
                    seed = (seed * 1664525 + 1013904223) >>> 0;
                    data.data[i] = seed >>> 24;
                    data.data[i + 1] = seed >>> 16;
                    data.data[i + 2] = seed >>> 8;
                    data.data[i + 3] = 255;
                }
                x.putImageData(data, 0, 0);
                return c.toDataURL('image/jpeg', 0.6).split(',')[1];
            }),
            'base64',
        );
        expect(economical.length).toBeGreaterThan(512 * 1024);
        const retained = await upload(
            'already-compressed.jpg',
            'image/jpeg',
            economical,
        );
        expect(
            (
                await (await page.request.get(retained.documentUrl)).body()
            ).equals(economical),
        ).toBe(true);
        await page
            .getByPlaceholder('Tulis pesan...')
            .fill('Draft format tidak didukung');
        await page.locator('input[type=file]').setInputFiles({
            name: 'archive.zip',
            mimeType: 'application/zip',
            buffer: Buffer.from('ZIP fixture unchanged'),
        });
        await expect(page.getByRole('alert')).toContainText(
            'Format file tidak didukung',
        );
        await expect(page.getByPlaceholder('Tulis pesan...')).toHaveValue(
            'Draft format tidak didukung',
        );
    } finally {
        await owner.close();
        fixture.cleanup();
    }
});
