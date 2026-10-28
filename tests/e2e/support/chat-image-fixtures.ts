import fs from 'node:fs/promises';

import { type Page, type TestInfo } from '@playwright/test';

/** Synthetic camera/document fixture, deterministic pixels and no external assets. */
export async function cameraFixture(
    page: Page,
    info: TestInfo,
): Promise<string> {
    const data = await page.evaluate(() => {
        const canvas = document.createElement('canvas');
        canvas.width = 3200;
        canvas.height = 2400;
        const context = canvas.getContext('2d')!;
        const image = context.createImageData(3200, 2400);
        let seed = 173174;
        for (let i = 0; i < image.data.length; i += 4) {
            seed = (1664525 * seed + 1013904223) >>> 0;
            const noise = (seed >>> 24) / 6;
            const x = (i / 4) % 3200;
            const y = Math.floor(i / 4 / 3200);
            image.data[i] = 180 + 35 * Math.sin(x / 400) + noise;
            image.data[i + 1] = 190 + 25 * Math.cos(y / 300) + noise;
            image.data[i + 2] = 170 + 30 * Math.sin((x + y) / 600) + noise;
            image.data[i + 3] = 255;
        }
        context.putImageData(image, 0, 0);
        context.fillStyle = 'white';
        context.fillRect(340, 260, 2520, 1880);
        context.fillStyle = '#142130';
        context.font = 'bold 88px sans-serif';
        context.fillText('SiTA • Bukti bimbingan', 480, 450);
        context.font = '52px sans-serif';
        for (let i = 0; i < 17; i++)
            context.fillText(
                `${i + 1}. Revisi Bab 3: metode, data, dan evaluasi 2026.`,
                480,
                600 + i * 76,
            );
        context.fillStyle = '#2563eb';
        context.fillRect(480, 1940, 1100, 32);
        return canvas.toDataURL('image/jpeg', 0.98).split(',')[1];
    });
    const file = info.outputPath('large-camera.jpg');
    await fs.writeFile(file, Buffer.from(data, 'base64'));
    return file;
}
