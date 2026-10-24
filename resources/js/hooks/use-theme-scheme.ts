import { useCallback, useSyncExternalStore } from 'react';

// The scheme only chooses which `[data-theme]` block of resources/css/app.css
// applies. This file never writes a colour value.
const STORAGE_KEY = 'sita_theme_preset';
const COOKIE_KEY = 'sita_theme_preset';

export type ThemeScheme = {
    id:
        | 'ubg-blue'
        | 'indigo'
        | 'graphite'
        | 'aubergine'
        | 'plum'
        | 'olive'
        | 'sepia';
    label: string;
    description: string;
};

// Keep equal to the `[data-theme]` blocks in app.css and to
// App\Support\ThemeSchemes::IDS.
export const THEME_SCHEMES: readonly ThemeScheme[] = [
    {
        id: 'ubg-blue',
        label: 'UBG Blue',
        description: 'Biru akademik resmi',
    },
    {
        id: 'indigo',
        label: 'Indigo',
        description: 'Ungu-biru yang tenang',
    },
    {
        id: 'graphite',
        label: 'Grafit',
        description: 'Abu-abu netral tanpa rona',
    },
    {
        id: 'aubergine',
        label: 'Ungu Akademik',
        description: 'Ungu dalam yang tenang dan formal',
    },
    {
        id: 'plum',
        label: 'Plum',
        description: 'Ungu terung redup, bukan merah muda',
    },
    {
        id: 'olive',
        label: 'Zaitun',
        description: 'Hijau zaitun netral dan bersahaja',
    },
    {
        id: 'sepia',
        label: 'Sepia',
        description: 'Abu-abu hangat seperti batu alam',
    },
];

export type ThemeSchemeId = ThemeScheme['id'];

const DEFAULT_SCHEME_ID: ThemeSchemeId = 'ubg-blue';

const listeners = new Set<() => void>();
let currentSchemeId: ThemeSchemeId = DEFAULT_SCHEME_ID;

const subscribe = (callback: () => void) => {
    listeners.add(callback);
    return () => listeners.delete(callback);
};

const notify = (): void => {
    listeners.forEach((listener) => {
        listener();
    });
};

export const isThemeSchemeId = (
    value: string | null | undefined,
): value is ThemeSchemeId =>
    THEME_SCHEMES.some((scheme) => scheme.id === value);

const setCookie = (value: ThemeSchemeId, days = 365): void => {
    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${COOKIE_KEY}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const readStoredScheme = (): ThemeSchemeId | null => {
    try {
        const stored = window.localStorage.getItem(STORAGE_KEY);

        return isThemeSchemeId(stored) ? stored : null;
    } catch {
        return null;
    }
};

const writeStoredScheme = (id: ThemeSchemeId): void => {
    try {
        window.localStorage.setItem(STORAGE_KEY, id);
    } catch {
        // Storage is blocked; the cookie still carries the choice.
    }
};

const applyScheme = (id: ThemeSchemeId): void => {
    document.documentElement.dataset.theme = id;
};

/**
 * Align the document, storage and cookie on load. Blade has already set
 * `data-theme` from the cookie (or from storage when the cookie is missing),
 * so this only repairs a stale or missing copy.
 */
export function initializeThemeScheme(): void {
    if (typeof window === 'undefined') return;

    const rendered = document.documentElement.dataset.theme;
    currentSchemeId =
        readStoredScheme() ??
        (isThemeSchemeId(rendered) ? rendered : DEFAULT_SCHEME_ID);

    applyScheme(currentSchemeId);
    writeStoredScheme(currentSchemeId);
    setCookie(currentSchemeId);
    notify();
}

export function useThemeScheme() {
    const schemeId = useSyncExternalStore(
        subscribe,
        () => currentSchemeId,
        () => DEFAULT_SCHEME_ID,
    );

    const updateScheme = useCallback((next: ThemeSchemeId): void => {
        currentSchemeId = next;
        writeStoredScheme(next);
        setCookie(next);
        applyScheme(next);
        notify();
    }, []);

    return { schemeId, schemes: THEME_SCHEMES, updateScheme } as const;
}
