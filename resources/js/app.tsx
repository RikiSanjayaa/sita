import '../css/app.css';
import './echo';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';

import { AppToaster } from './components/app-toaster';
import { initializeTheme } from './hooks/use-appearance';
import { initializeThemeScheme } from './hooks/use-theme-scheme';
import { reconcilePushOwner } from './lib/web-push';
import { type SharedData } from './types';

type InitialPage = {
    props?: {
        name?: string;
    };
};

const appName = (() => {
    const rootElement = document.getElementById('app');
    const page = rootElement?.dataset.page;

    if (!page) {
        return 'Laravel';
    }

    try {
        const initialPage = JSON.parse(page) as InitialPage;

        return typeof initialPage.props?.name === 'string'
            ? initialPage.props.name
            : 'Laravel';
    } catch {
        return 'Laravel';
    }
})();

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob('./pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);
        guardPushOwner(
            props.initialPage.props.auth as SharedData['auth'] | undefined,
        );

        root.render(
            <StrictMode>
                <App {...props} />
                <AppToaster
                    initialFlash={
                        props.initialPage.props.flash as SharedData['flash']
                    }
                />
            </StrictMode>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
initializeThemeScheme();

// Drops a stored push owner that is not the signed-in user. Runs for the first
// page and every Inertia navigation (logout, account switch); it never
// prompts or subscribes. Calls are serialized so a stale one cannot win.
let pushGuardQueue: Promise<void> = Promise.resolve();
let guardedUserId: number | string | null | undefined;

function guardPushOwner(auth: SharedData['auth'] | undefined): void {
    if (!auth) {
        return;
    }

    const userId = auth.user?.id ?? null;

    if (userId === guardedUserId) {
        return;
    }

    guardedUserId = userId;
    pushGuardQueue = pushGuardQueue
        .then(() => reconcilePushOwner(userId))
        .catch(() => undefined);
}

router.on('navigate', (event) => {
    const pageProps = event.detail.page.props as unknown as Partial<SharedData>;

    guardPushOwner(pageProps.auth);
});
