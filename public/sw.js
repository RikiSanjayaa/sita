/*
 * SiTA push worker. It shows generic notifications and routes clicks. It does
 * not intercept fetches and keeps no caches, so no page or API response is
 * ever stored offline. DB_NAME / STORE / OWNER_KEY match resources/js/lib/web-push.ts.
 */
const DB_NAME = 'sita-web-push';
const STORE = 'meta';
const OWNER_KEY = 'owner';

const GENERIC_TITLE = 'SITA';
const GENERIC_BODY = 'Ada pembaruan di SITA. Buka aplikasi untuk melihatnya.';
const ICON = '/icons/icon-192.png';

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

function readOwner() {
    return new Promise((resolve) => {
        const open = indexedDB.open(DB_NAME, 1);

        open.onupgradeneeded = () => {
            open.result.createObjectStore(STORE);
        };
        open.onerror = () => resolve(null);
        open.onsuccess = () => {
            const db = open.result;
            const request = db
                .transaction(STORE, 'readonly')
                .objectStore(STORE)
                .get(OWNER_KEY);

            request.onsuccess = () => {
                db.close();
                resolve(
                    typeof request.result === 'string' ? request.result : null,
                );
            };
            request.onerror = () => {
                db.close();
                resolve(null);
            };
        };
    });
}

// Same-origin relative paths only. Anything else falls back to the app root.
function safeUrl(value) {
    if (
        typeof value !== 'string' ||
        value.length === 0 ||
        value.length > 2048 ||
        value[0] !== '/' ||
        value[1] === '/' ||
        /[\\\u0000-\u001f\u007f]/.test(value)
    ) {
        return '/';
    }

    try {
        const url = new URL(value, self.location.origin);

        return url.origin === self.location.origin
            ? url.pathname + url.search + url.hash
            : '/';
    } catch {
        return '/';
    }
}

function parsePayload(event) {
    try {
        const payload = event.data ? event.data.json() : null;

        return payload && typeof payload === 'object' ? payload : null;
    } catch {
        return null;
    }
}

function ownerMatches(owner, data) {
    return (
        owner !== null &&
        data !== null &&
        typeof data === 'object' &&
        data.userId !== undefined &&
        data.userId !== null &&
        String(data.userId) === owner
    );
}

async function handlePush(event) {
    const payload = parsePayload(event);
    const data = payload && payload.data;
    const owner = await readOwner();

    // A push for another account (or none) shows nothing and opens nothing.
    if (!ownerMatches(owner, data)) {
        return;
    }

    // web.dev: a focused app already shows the in-app toast, so skip the OS one.
    const windows = await self.clients.matchAll({
        type: 'window',
        includeUncontrolled: true,
    });

    if (
        windows.some(
            (client) => client.visibilityState === 'visible' && client.focused,
        )
    ) {
        return;
    }

    const tag =
        typeof payload.tag === 'string' && payload.tag.length > 0
            ? payload.tag.slice(0, 200)
            : 'sita-update';

    // Title and body are fixed here whatever the payload says, so a lock
    // screen never shows names, message text or panel titles.
    await self.registration.showNotification(GENERIC_TITLE, {
        body: GENERIC_BODY,
        icon: ICON,
        tag,
        renotify: true,
        data: {
            url: safeUrl(data.url),
            userId: String(data.userId),
            notificationId:
                typeof data.notificationId === 'string'
                    ? data.notificationId
                    : null,
        },
    });
}

self.addEventListener('push', (event) => {
    event.waitUntil(handlePush(event));
});

async function openTarget(notification) {
    const data = notification.data;
    const owner = await readOwner();

    if (!ownerMatches(owner, data)) {
        return;
    }

    const target = new URL(safeUrl(data.url), self.location.origin).href;
    const windows = await self.clients.matchAll({
        type: 'window',
        includeUncontrolled: true,
    });
    const existing =
        windows.find((client) => client.url === target) ||
        windows.find(
            (client) => new URL(client.url).origin === self.location.origin,
        );

    if (existing) {
        try {
            const focused = await existing.focus();

            if (focused.url !== target) {
                await focused.navigate(target);
            }

            return;
        } catch {
            // Fall through and open a new window.
        }
    }

    await self.clients.openWindow(target);
}

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    event.waitUntil(openTarget(event.notification));
});
