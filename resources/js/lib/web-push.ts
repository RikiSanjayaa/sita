import PushSubscriptionController from '@/actions/App/Http/Controllers/Settings/PushSubscriptionController';

/**
 * Browser side of Web Push. Nothing here runs on its own: only the functions
 * documented as "gesture" may prompt or subscribe, and only a click handler
 * calls them. The service worker (public/sw.js) shares the IndexedDB owner
 * record below, so keep DB_NAME / STORE / OWNER_KEY equal in both files.
 */

const SW_URL = '/sw.js';
const SW_SCOPE = '/';
const DB_NAME = 'sita-web-push';
const STORE = 'meta';
const OWNER_KEY = 'owner';

export type PushState =
    | 'loading'
    | 'unsupported'
    | 'insecure'
    | 'ios-install'
    | 'unavailable'
    | 'denied'
    | 'inactive'
    | 'active'
    | 'error';

export type PushPermission = NotificationPermission | 'unsupported';

export type PushStatus = {
    state: PushState;
    permission: PushPermission;
    /** The browser holds a subscription that the server does not accept for this user and session. */
    hasLocalSubscription: boolean;
    message?: string;
};

export class PushError extends Error {
    constructor(message: string) {
        super(message);
        this.name = 'PushError';
    }
}

type PushConfig = { enabled: boolean; publicKey: string | null };

type EnvironmentIssue = Extract<
    PushState,
    'unsupported' | 'insecure' | 'ios-install'
>;

function isIos(): boolean {
    const { userAgent, platform, maxTouchPoints } = navigator;

    return (
        /iPad|iPhone|iPod/.test(userAgent) ||
        (platform === 'MacIntel' && maxTouchPoints > 1)
    );
}

function isStandalone(): boolean {
    return (
        window.matchMedia('(display-mode: standalone)').matches ||
        (navigator as Navigator & { standalone?: boolean }).standalone === true
    );
}

export function permissionState(): PushPermission {
    if (typeof window === 'undefined' || !('Notification' in window)) {
        return 'unsupported';
    }

    return window.Notification.permission;
}

function environmentIssue(): EnvironmentIssue | null {
    if (typeof window === 'undefined') {
        return 'unsupported';
    }

    if (!window.isSecureContext) {
        return 'insecure';
    }

    // iOS only exposes Push to a site added to the Home Screen.
    if (isIos() && !isStandalone()) {
        return 'ios-install';
    }

    if (
        !('serviceWorker' in navigator) ||
        !('PushManager' in window) ||
        !('Notification' in window)
    ) {
        return 'unsupported';
    }

    return null;
}

async function api<T>(
    url: string,
    method: 'GET' | 'POST' | 'DELETE',
    body?: unknown,
): Promise<T> {
    let response: Response;

    try {
        response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content') ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: body === undefined ? undefined : JSON.stringify(body),
        });
    } catch {
        throw new PushError(
            'Tidak bisa menghubungi server. Periksa koneksi lalu coba lagi.',
        );
    }

    if (response.status === 409) {
        throw new PushError(
            'Browser ini sudah terhubung ke akun lain. Perangkat tidak dipindahkan ke akun ini.',
        );
    }

    if (response.status === 401 || response.status === 419) {
        throw new PushError(
            'Sesi berakhir. Muat ulang halaman lalu coba lagi.',
        );
    }

    if (response.status === 429) {
        throw new PushError(
            'Terlalu banyak percobaan. Tunggu sebentar lalu coba lagi.',
        );
    }

    if (!response.ok) {
        throw new PushError(
            'Server menolak permintaan notifikasi. Coba lagi nanti.',
        );
    }

    return (await response.json()) as T;
}

async function fetchConfig(): Promise<PushConfig> {
    return api<PushConfig>(PushSubscriptionController.index.url(), 'GET');
}

function urlBase64ToUint8Array(value: string): Uint8Array<ArrayBuffer> {
    const padding = '='.repeat((4 - (value.length % 4)) % 4);
    const raw = atob((value + padding).replace(/-/g, '+').replace(/_/g, '/'));
    const output = new Uint8Array(new ArrayBuffer(raw.length));

    for (let index = 0; index < raw.length; index += 1) {
        output[index] = raw.charCodeAt(index);
    }

    return output;
}

function sameKey(buffer: ArrayBuffer | null, key: Uint8Array): boolean {
    if (!buffer) {
        return false;
    }

    const current = new Uint8Array(buffer);

    return (
        current.length === key.length &&
        current.every((byte, index) => byte === key[index])
    );
}

// ---- IndexedDB owner record (shared with the service worker) ----

function openDb(): Promise<IDBDatabase> {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, 1);

        request.onupgradeneeded = () => {
            request.result.createObjectStore(STORE);
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function withStore<T>(
    mode: IDBTransactionMode,
    run: (store: IDBObjectStore) => IDBRequest<T>,
): Promise<T> {
    const db = await openDb();

    try {
        return await new Promise<T>((resolve, reject) => {
            const transaction = db.transaction(STORE, mode);
            const request = run(transaction.objectStore(STORE));

            transaction.oncomplete = () => resolve(request.result);
            transaction.onerror = () => reject(transaction.error);
            transaction.onabort = () => reject(transaction.error);
        });
    } finally {
        db.close();
    }
}

async function readOwner(): Promise<string | null> {
    try {
        const value = await withStore('readonly', (store) =>
            store.get(OWNER_KEY),
        );

        return typeof value === 'string' ? value : null;
    } catch {
        return null;
    }
}

async function writeOwner(userId: number | string): Promise<void> {
    await withStore('readwrite', (store) =>
        store.put(String(userId), OWNER_KEY),
    );
}

async function clearOwner(): Promise<void> {
    try {
        await withStore('readwrite', (store) => store.delete(OWNER_KEY));
    } catch {
        // Nothing stored or storage blocked: the worker then drops every push.
    }
}

// ---- Service worker and subscription helpers ----

async function getLocalSubscription(): Promise<PushSubscription | null> {
    const registration =
        await navigator.serviceWorker.getRegistration(SW_SCOPE);

    return registration
        ? await registration.pushManager.getSubscription()
        : null;
}

async function registerWorker(): Promise<ServiceWorkerRegistration> {
    try {
        await navigator.serviceWorker.register(SW_URL, {
            scope: SW_SCOPE,
            updateViaCache: 'none',
        });

        // Resolves once a worker is active for the scope.
        return await navigator.serviceWorker.ready;
    } catch {
        throw new PushError(
            'Service worker tidak bisa dipasang di browser ini.',
        );
    }
}

function serializeSubscription(subscription: PushSubscription): {
    endpoint: string;
    keys: { p256dh: string; auth: string };
    contentEncoding: 'aes128gcm';
} {
    const json = subscription.toJSON();
    const p256dh = json.keys?.p256dh;
    const auth = json.keys?.auth;

    if (!json.endpoint || !p256dh || !auth) {
        throw new PushError('Browser tidak memberikan kunci langganan.');
    }

    return {
        endpoint: json.endpoint,
        keys: { p256dh, auth },
        contentEncoding: 'aes128gcm',
    };
}

async function serverSubscribed(endpoint: string): Promise<boolean> {
    const result = await api<{ subscribed: boolean }>(
        PushSubscriptionController.status.url(),
        'POST',
        { endpoint },
    );

    return result.subscribed === true;
}

// ---- Public API ----

/**
 * Read-only status. Never prompts, subscribes, registers a worker or writes to
 * the server. `active` needs permission, a local subscription, a server row
 * owned by this user and session, and the worker owner record for this user.
 */
export async function readPushStatus(
    userId: number | string,
): Promise<PushStatus> {
    const permission = permissionState();
    const issue = environmentIssue();

    if (issue) {
        return { state: issue, permission, hasLocalSubscription: false };
    }

    let hasLocalSubscription = false;

    try {
        const subscription = await getLocalSubscription();
        hasLocalSubscription = subscription !== null;
        const config = await fetchConfig();

        if (!config.enabled || !config.publicKey) {
            return { state: 'unavailable', permission, hasLocalSubscription };
        }

        if (permission === 'denied') {
            return { state: 'denied', permission, hasLocalSubscription };
        }

        if (!subscription || permission !== 'granted') {
            return { state: 'inactive', permission, hasLocalSubscription };
        }

        const [subscribed, owner] = await Promise.all([
            serverSubscribed(subscription.endpoint),
            readOwner(),
        ]);

        return {
            state:
                subscribed && owner === String(userId) ? 'active' : 'inactive',
            permission,
            hasLocalSubscription,
        };
    } catch (error) {
        return {
            state: 'error',
            permission,
            hasLocalSubscription,
            message:
                error instanceof PushError
                    ? error.message
                    : 'Status notifikasi perangkat tidak bisa dibaca.',
        };
    }
}

async function removeServerRow(endpoint: string): Promise<void> {
    await api<{ subscribed: boolean }>(
        PushSubscriptionController.destroy.url(),
        'DELETE',
        { endpoint },
    );
}

/**
 * Gesture only. Call straight from a click handler: the permission prompt has
 * to start before the first await or iOS rejects it.
 */
export async function enablePush(userId: number | string): Promise<PushStatus> {
    const issue = environmentIssue();

    if (issue) {
        throw new PushError('Browser ini belum bisa menerima notifikasi push.');
    }

    const permission =
        window.Notification.permission === 'default'
            ? await window.Notification.requestPermission()
            : window.Notification.permission;

    if (permission !== 'granted') {
        return readPushStatus(userId);
    }

    const config = await fetchConfig();

    if (!config.enabled || !config.publicKey) {
        throw new PushError('Notifikasi push belum disiapkan di server.');
    }

    const key = urlBase64ToUint8Array(config.publicKey);
    const registration = await registerWorker();

    const subscribe = async (): Promise<{
        subscription: PushSubscription;
        created: boolean;
    }> => {
        let existing = await registration.pushManager.getSubscription();

        if (existing && !sameKey(existing.options.applicationServerKey, key)) {
            await existing.unsubscribe();
            existing = null;
        }

        if (existing) {
            return { subscription: existing, created: false };
        }

        try {
            return {
                subscription: await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: key,
                }),
                created: true,
            };
        } catch {
            throw new PushError(
                'Browser gagal membuat langganan push. Coba lagi nanti.',
            );
        }
    };

    const { subscription, created } = await subscribe();

    const store = (current: PushSubscription) =>
        api<{ subscribed: boolean }>(
            PushSubscriptionController.store.url(),
            'POST',
            serializeSubscription(current),
        );

    try {
        await store(subscription);

        try {
            await writeOwner(userId);
        } catch {
            await removeServerRow(subscription.endpoint).catch(() => {});
            throw new PushError(
                'Penyimpanan browser menolak data perangkat. Notifikasi tidak diaktifkan.',
            );
        }
    } catch (error) {
        if (created) {
            await subscription.unsubscribe().catch(() => false);
        }

        throw error;
    }

    return readPushStatus(userId);
}

/**
 * Gesture only. The server row goes first; if that fails the browser keeps its
 * subscription so the state stays truthful and the user can retry.
 */
export async function disablePush(
    userId: number | string,
): Promise<PushStatus> {
    if (environmentIssue()) {
        return readPushStatus(userId);
    }

    const subscription = await getLocalSubscription();

    if (subscription) {
        await removeServerRow(subscription.endpoint);

        const removed = await subscription.unsubscribe().catch(() => false);

        if (!removed) {
            throw new PushError(
                'Server sudah dilepas, tetapi browser belum membatalkan langganan. Coba lagi.',
            );
        }
    }

    await clearOwner();

    return readPushStatus(userId);
}

/**
 * Account guard run on bootstrap and on every Inertia navigation. When the
 * stored owner is not the signed-in user (logout, account switch) the worker
 * owner is cleared and the old local subscription is dropped. It never hands a
 * subscription to the new user, never prompts and never subscribes.
 */
export async function reconcilePushOwner(
    authUserId: number | string | null,
): Promise<void> {
    if (environmentIssue()) {
        return;
    }

    const owner = await readOwner();

    if (
        owner === null ||
        (authUserId !== null && owner === String(authUserId))
    ) {
        return;
    }

    await clearOwner();

    try {
        const subscription = await getLocalSubscription();
        await subscription?.unsubscribe();
    } catch {
        // The worker already drops pushes because the owner record is gone.
    }
}
