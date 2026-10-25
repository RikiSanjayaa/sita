import '../css/app.css';
import './echo';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';

import { AppToaster } from './components/app-toaster';
import { initializeTheme } from './hooks/use-appearance';
import { initializeThemeScheme } from './hooks/use-theme-scheme';
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
