import { expect, test } from '@playwright/test';

type ManifestIcon = { src: string; sizes: string; purpose?: string };

type ImageProbe = {
    status: number;
    width: number;
    height: number;
    opaqueCorners: boolean;
};

test.use({ storageState: { cookies: [], origins: [] } });

test('the manifest leaves the status bar colour to the page and ships maskable icons', async ({
    request,
}) => {
    const response = await request.get('/manifest.webmanifest');
    expect(response.ok()).toBe(true);
    const manifest = await response.json();

    // Firefox prefers manifest theme_color over the page meta, which would pin
    // the status bar to one colour whatever the app scheme and mode are.
    expect(manifest).not.toHaveProperty('theme_color');
    expect(manifest.display).toBe('standalone');

    const icons = (manifest.icons as ManifestIcon[]).map(
        ({ sizes, purpose }) => `${purpose ?? 'any'} ${sizes}`,
    );
    expect(icons).toEqual(
        expect.arrayContaining([
            'any 192x192',
            'any 512x512',
            'maskable 192x192',
            'maskable 512x512',
        ]),
    );
});

test('every launcher icon loads at its declared size; maskable and Apple icons are full-bleed', async ({
    page,
    request,
}) => {
    const manifest = await (await request.get('/manifest.webmanifest')).json();
    await page.goto('/');
    const appleHref = await page
        .locator('link[rel="apple-touch-icon"]')
        .getAttribute('href');
    expect(appleHref).not.toBeNull();

    const probe = (src: string): Promise<ImageProbe> =>
        page.evaluate(async (url) => {
            const response = await fetch(url, { cache: 'no-store' });
            if (!response.ok) {
                return {
                    status: response.status,
                    width: 0,
                    height: 0,
                    opaqueCorners: false,
                };
            }
            const bitmap = await createImageBitmap(await response.blob());
            const canvas = new OffscreenCanvas(bitmap.width, bitmap.height);
            const context = canvas.getContext('2d')!;
            context.drawImage(bitmap, 0, 0);
            const last = bitmap.width - 1;
            const corners = [
                [0, 0],
                [last, 0],
                [0, last],
                [last, last],
            ].map(([x, y]) => context.getImageData(x, y, 1, 1).data[3]);

            return {
                status: response.status,
                width: bitmap.width,
                height: bitmap.height,
                opaqueCorners: corners.every((alpha) => alpha === 255),
            };
        }, src);

    for (const icon of manifest.icons as ManifestIcon[]) {
        const [width, height] = icon.sizes.split('x').map(Number);
        const result = await probe(icon.src);

        expect(result, icon.src).toMatchObject({ status: 200, width, height });
        if (icon.purpose === 'maskable') {
            // Launchers crop maskable icons to any shape; transparent corners
            // would show the launcher background around the brand tile.
            expect(result.opaqueCorners, icon.src).toBe(true);
        }
    }

    // iOS fills transparent pixels of the home screen icon with black.
    expect(await probe(appleHref!)).toMatchObject({
        status: 200,
        width: 180,
        height: 180,
        opaqueCorners: true,
    });
});
