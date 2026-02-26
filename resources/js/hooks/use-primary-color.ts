import { useCallback, useSyncExternalStore } from 'react';

const STORAGE_KEY = 'sita_theme_preset';
const COOKIE_KEY = 'sita_theme_preset';

type ThemeScale = {
    background: string;
    foreground: string;
    card: string;
    muted: string;
    mutedForeground: string;
    accent: string;
    accentForeground: string;
    border: string;
    sidebar: string;
    sidebarForeground: string;
};

export type ThemePreset = {
    id: 'ubg-blue' | 'emerald' | 'sunset' | 'rose';
    label: string;
    description: string;
    primary: string;
    light: ThemeScale;
    dark: ThemeScale;
};

export const THEME_PRESETS: ThemePreset[] = [
    {
        id: 'ubg-blue',
        label: 'UBG Blue',
        description: 'Biru akademik resmi',
        primary: '#1f66ff',
        light: {
            background: '#f1f6ff',
            foreground: '#102b63',
            card: '#ffffff',
            muted: '#e1ecff',
            mutedForeground: '#3d5f98',
            accent: '#d9e7ff',
            accentForeground: '#123a87',
            border: '#c7dbff',
            sidebar: '#eaf2ff',
            sidebarForeground: '#11326f',
        },
        dark: {
            background: '#0a0b0c',
            foreground: '#e8efff',
            card: '#101112',
            muted: '#1a1b1c',
            mutedForeground: '#909499',
            accent: '#1e222c',
            accentForeground: '#cadcff',
            border: '#2b2c2f',
            sidebar: '#0c0d0e',
            sidebarForeground: '#e8efff',
        },
    },
    {
        id: 'emerald',
        label: 'Emerald',
        description: 'Hijau segar mahasiswa',
        primary: '#059669',
        light: {
            background: '#f4fbf8',
            foreground: '#173a32',
            card: '#ffffff',
            muted: '#e7f6ef',
            mutedForeground: '#4d776c',
            accent: '#d7f0e4',
            accentForeground: '#173a32',
            border: '#cde8db',
            sidebar: '#eaf8f1',
            sidebarForeground: '#173a32',
        },
        dark: {
            background: '#0a0c0b',
            foreground: '#e8f7f1',
            card: '#101211',
            muted: '#1a1c1b',
            mutedForeground: '#909995',
            accent: '#1e2c24',
            accentForeground: '#d6f2e6',
            border: '#2b2f2d',
            sidebar: '#0c0e0d',
            sidebarForeground: '#e8f7f1',
        },
    },
    {
        id: 'sunset',
        label: 'Sunset',
        description: 'Oranye hangat energik',
        primary: '#ea580c',
        light: {
            background: '#fff8f2',
            foreground: '#4d2a17',
            card: '#ffffff',
            muted: '#ffe9dc',
            mutedForeground: '#8f6249',
            accent: '#ffd9c1',
            accentForeground: '#4d2a17',
            border: '#f7d8c4',
            sidebar: '#fff0e3',
            sidebarForeground: '#4d2a17',
        },
        dark: {
            background: '#0c0b0a',
            foreground: '#ffefe4',
            card: '#121110',
            muted: '#1c1b1a',
            mutedForeground: '#999490',
            accent: '#2c221e',
            accentForeground: '#ffe5d2',
            border: '#2f2c2b',
            sidebar: '#0e0c0b',
            sidebarForeground: '#ffefe4',
        },
    },
    {
        id: 'rose',
        label: 'Rose',
        description: 'Magenta modern elegan',
        primary: '#e11d48',
        light: {
            background: '#fff6f9',
            foreground: '#4b1f2d',
            card: '#ffffff',
            muted: '#ffe6ef',
            mutedForeground: '#8d5a6a',
            accent: '#ffd9e7',
            accentForeground: '#4b1f2d',
            border: '#f6cfde',
            sidebar: '#ffebf3',
            sidebarForeground: '#4b1f2d',
        },
        dark: {
            background: '#0c0a0b',
            foreground: '#ffeaf1',
            card: '#121011',
            muted: '#1c1a1b',
            mutedForeground: '#999094',
            accent: '#2c1e22',
            accentForeground: '#ffe4ef',
            border: '#2f2b2c',
            sidebar: '#0e0b0c',
            sidebarForeground: '#ffeaf1',
        },
    },
];

const DEFAULT_PRESET_ID: ThemePreset['id'] = 'ubg-blue';

const listeners = new Set<() => void>();
let currentPresetId: ThemePreset['id'] = DEFAULT_PRESET_ID;
let classObserver: MutationObserver | null = null;

const subscribe = (callback: () => void) => {
    listeners.add(callback);
    return () => listeners.delete(callback);
};

const notify = (): void => {
    listeners.forEach((listener) => {
        listener();
    });
};

const setCookie = (name: string, value: string, days = 365): void => {
    if (typeof document === 'undefined') return;

    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const getPresetById = (id: string | null | undefined): ThemePreset => {
    return (
        THEME_PRESETS.find((preset) => preset.id === id) ??
        THEME_PRESETS.find((preset) => preset.id === DEFAULT_PRESET_ID)!
    );
};

const getContrastForeground = (hex: string): string => {
    const value = hex.replace('#', '');
    if (value.length !== 6) return '#ffffff';

    const r = Number.parseInt(value.slice(0, 2), 16) / 255;
    const g = Number.parseInt(value.slice(2, 4), 16) / 255;
    const b = Number.parseInt(value.slice(4, 6), 16) / 255;

    const luminance = 0.2126 * r + 0.7152 * g + 0.0722 * b;
    return luminance > 0.58 ? '#0f172a' : '#ffffff';
};

const applyPresetToCssVariables = (preset: ThemePreset): void => {
    if (typeof document === 'undefined') return;

    const root = document.documentElement;
    const useDarkScale = root.classList.contains('dark');
    const scale = useDarkScale ? preset.dark : preset.light;
    const primaryForeground = getContrastForeground(preset.primary);

    root.style.setProperty('--primary', preset.primary);
    root.style.setProperty('--primary-foreground', primaryForeground);
    root.style.setProperty('--ring', preset.primary);
    root.style.setProperty('--sidebar-primary', preset.primary);
    root.style.setProperty('--sidebar-primary-foreground', primaryForeground);

    root.style.setProperty('--background', scale.background);
    root.style.setProperty('--foreground', scale.foreground);
    root.style.setProperty('--card', scale.card);
    root.style.setProperty('--card-foreground', scale.foreground);
    root.style.setProperty('--popover', scale.card);
    root.style.setProperty('--popover-foreground', scale.foreground);
    root.style.setProperty('--secondary', scale.muted);
    root.style.setProperty('--secondary-foreground', scale.foreground);
    root.style.setProperty('--muted', scale.muted);
    root.style.setProperty('--muted-foreground', scale.mutedForeground);
    root.style.setProperty('--accent', scale.accent);
    root.style.setProperty('--accent-foreground', scale.accentForeground);
    root.style.setProperty('--border', scale.border);
    root.style.setProperty('--input', scale.border);
    root.style.setProperty('--sidebar', scale.sidebar);
    root.style.setProperty('--sidebar-foreground', scale.sidebarForeground);
    root.style.setProperty('--sidebar-accent', scale.muted);
    root.style.setProperty('--sidebar-accent-foreground', scale.foreground);
    root.style.setProperty('--sidebar-border', scale.border);
    root.style.setProperty('--sidebar-ring', preset.primary);
};

const applyCurrentPreset = (): void => {
    applyPresetToCssVariables(getPresetById(currentPresetId));
};

const setupAppearanceObserver = (): void => {
    if (typeof document === 'undefined' || typeof window === 'undefined')
        return;
    if (classObserver) classObserver.disconnect();

    classObserver = new MutationObserver(() => {
        applyCurrentPreset();
        notify();
    });

    classObserver.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });
};

export function initializePrimaryColor(): void {
    if (typeof window === 'undefined') return;

    const storedPresetId = window.localStorage.getItem(STORAGE_KEY);
    currentPresetId = getPresetById(storedPresetId).id;
    applyCurrentPreset();
    setupAppearanceObserver();
}

export function usePrimaryColor() {
    const presetId = useSyncExternalStore(
        subscribe,
        () => currentPresetId,
        () => DEFAULT_PRESET_ID,
    );

    const updatePreset = useCallback(
        (nextPresetId: ThemePreset['id']): void => {
            const nextPreset = getPresetById(nextPresetId);
            currentPresetId = nextPreset.id;
            localStorage.setItem(STORAGE_KEY, nextPreset.id);
            setCookie(COOKIE_KEY, nextPreset.id);
            applyPresetToCssVariables(nextPreset);
            notify();
        },
        [],
    );

    const resetPreset = useCallback((): void => {
        currentPresetId = DEFAULT_PRESET_ID;
        localStorage.removeItem(STORAGE_KEY);
        setCookie(COOKIE_KEY, DEFAULT_PRESET_ID);
        applyCurrentPreset();
        notify();
    }, []);

    return {
        presetId,
        presets: THEME_PRESETS,
        updatePreset,
        resetPreset,
    } as const;
}
