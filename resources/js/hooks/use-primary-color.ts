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
    id: 'ubg-blue' | 'emerald' | 'sunset' | 'rose' | 'indigo' | 'teal';
    label: string;
    description: string;
    primary: string;
    darkPrimary: string;
    light: ThemeScale;
    dark: ThemeScale;
};

export const THEME_PRESETS: ThemePreset[] = [
    {
        id: 'ubg-blue',
        label: 'UBG Blue',
        description: 'Biru akademik resmi',
        primary: '#2563eb',
        darkPrimary: '#1d4ed8',
        light: {
            background: '#f8fafc',
            foreground: '#0f172a',
            card: '#ffffff',
            muted: '#f1f5f9',
            mutedForeground: '#64748b',
            accent: '#e0ecff',
            accentForeground: '#1e3a5f',
            border: '#e2e8f0',
            sidebar: '#f8fafc',
            sidebarForeground: '#0f172a',
        },
        dark: {
            background: '#0b0f14',
            foreground: '#eaf2f7',
            card: '#11161c',
            muted: '#151c23',
            mutedForeground: '#9fb0bd',
            accent: '#17202a',
            accentForeground: '#c2d0da',
            border: '#202a35',
            sidebar: '#0f141a',
            sidebarForeground: '#eaf2f7',
        },
    },
    {
        id: 'emerald',
        label: 'Emerald',
        description: 'Hijau segar mahasiswa',
        primary: '#059669',
        darkPrimary: '#047857',
        light: {
            background: '#f8fcfa',
            foreground: '#0c1f17',
            card: '#ffffff',
            muted: '#ecfdf5',
            mutedForeground: '#5b8a76',
            accent: '#d1fae5',
            accentForeground: '#14432a',
            border: '#d1e7dd',
            sidebar: '#f8fcfa',
            sidebarForeground: '#0c1f17',
        },
        dark: {
            background: '#0b0f14',
            foreground: '#e9f3ee',
            card: '#11171b',
            muted: '#151d21',
            mutedForeground: '#a1b5aa',
            accent: '#16211c',
            accentForeground: '#c6d7cf',
            border: '#223129',
            sidebar: '#0f1518',
            sidebarForeground: '#e9f3ee',
        },
    },
    {
        id: 'sunset',
        label: 'Sunset',
        description: 'Oranye hangat energik',
        primary: '#ea580c',
        darkPrimary: '#c2410c',
        light: {
            background: '#fdfaf7',
            foreground: '#27150b',
            card: '#ffffff',
            muted: '#fff4ed',
            mutedForeground: '#9a7055',
            accent: '#ffe6d2',
            accentForeground: '#4d2a14',
            border: '#f0ddd0',
            sidebar: '#fdfaf7',
            sidebarForeground: '#27150b',
        },
        dark: {
            background: '#0b0f14',
            foreground: '#f1ece7',
            card: '#15171a',
            muted: '#1a1d21',
            mutedForeground: '#b2aaa1',
            accent: '#211b18',
            accentForeground: '#d8ccc0',
            border: '#332923',
            sidebar: '#121417',
            sidebarForeground: '#f1ece7',
        },
    },
    {
        id: 'rose',
        label: 'Rose',
        description: 'Magenta modern elegan',
        primary: '#e11d48',
        darkPrimary: '#be123c',
        light: {
            background: '#fdf8f9',
            foreground: '#2a0e18',
            card: '#ffffff',
            muted: '#fff1f3',
            mutedForeground: '#9f6070',
            accent: '#ffd6de',
            accentForeground: '#4a1525',
            border: '#f1d5db',
            sidebar: '#fdf8f9',
            sidebarForeground: '#2a0e18',
        },
        dark: {
            background: '#0b0f14',
            foreground: '#f0e9ed',
            card: '#15161a',
            muted: '#1b1c21',
            mutedForeground: '#b1a6ad',
            accent: '#211920',
            accentForeground: '#d4c6cf',
            border: '#32232c',
            sidebar: '#121317',
            sidebarForeground: '#f0e9ed',
        },
    },
    {
        id: 'indigo',
        label: 'Indigo',
        description: 'Violet-biru premium',
        primary: '#6366f1',
        darkPrimary: '#4f46e5',
        light: {
            background: '#f9f8fd',
            foreground: '#1a1538',
            card: '#ffffff',
            muted: '#f0eeff',
            mutedForeground: '#7a73a8',
            accent: '#e0ddff',
            accentForeground: '#2d2870',
            border: '#ddd8f0',
            sidebar: '#f9f8fd',
            sidebarForeground: '#1a1538',
        },
        dark: {
            background: '#0b0f14',
            foreground: '#ecebfb',
            card: '#13151d',
            muted: '#181b24',
            mutedForeground: '#a8a8c2',
            accent: '#1a1d2a',
            accentForeground: '#c9c8df',
            border: '#262b3d',
            sidebar: '#10131b',
            sidebarForeground: '#ecebfb',
        },
    },
    {
        id: 'teal',
        label: 'Teal',
        description: 'Syan-hijau sejuk profesional',
        primary: '#0d9488',
        darkPrimary: '#0f766e',
        light: {
            background: '#f7fcfb',
            foreground: '#0c1e1c',
            card: '#ffffff',
            muted: '#ecfcfa',
            mutedForeground: '#5a8f89',
            accent: '#ccfbf1',
            accentForeground: '#134e4a',
            border: '#c9e6e3',
            sidebar: '#f7fcfb',
            sidebarForeground: '#0c1e1c',
        },
        dark: {
            background: '#0b0f14',
            foreground: '#e7f3f1',
            card: '#11171a',
            muted: '#151d20',
            mutedForeground: '#9eb4b0',
            accent: '#162220',
            accentForeground: '#c3d6d3',
            border: '#213431',
            sidebar: '#0f1518',
            sidebarForeground: '#e7f3f1',
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
    const activePrimary = useDarkScale ? preset.darkPrimary : preset.primary;
    const primaryForeground = getContrastForeground(activePrimary);

    root.style.setProperty('--primary', activePrimary);
    root.style.setProperty('--primary-foreground', primaryForeground);
    root.style.setProperty('--ring', activePrimary);
    root.style.setProperty('--sidebar-primary', activePrimary);
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
