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
            background: '#030712',
            foreground: '#c8cdd5',
            card: '#0a0f1a',
            muted: '#111827',
            mutedForeground: '#6b7280',
            accent: '#111d30',
            accentForeground: '#94a3b8',
            border: '#1e293b',
            sidebar: '#050a14',
            sidebarForeground: '#c8cdd5',
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
            background: '#030a06',
            foreground: '#c2cdc7',
            card: '#081410',
            muted: '#0c1f16',
            mutedForeground: '#6b8078',
            accent: '#0f2a1c',
            accentForeground: '#8fa89a',
            border: '#153023',
            sidebar: '#040c08',
            sidebarForeground: '#c2cdc7',
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
            background: '#0a0705',
            foreground: '#cdc5bd',
            card: '#130e09',
            muted: '#1c1510',
            mutedForeground: '#7d756e',
            accent: '#241c14',
            accentForeground: '#a09588',
            border: '#2a2018',
            sidebar: '#0c0906',
            sidebarForeground: '#cdc5bd',
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
            background: '#0a0507',
            foreground: '#cdc2c7',
            card: '#13090e',
            muted: '#1e0f16',
            mutedForeground: '#7d6e74',
            accent: '#241420',
            accentForeground: '#a0909a',
            border: '#2a1521',
            sidebar: '#0c060a',
            sidebarForeground: '#cdc2c7',
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
            background: '#070612',
            foreground: '#c5c3d0',
            card: '#0d0b18',
            muted: '#141225',
            mutedForeground: '#76748a',
            accent: '#1c193a',
            accentForeground: '#9896ad',
            border: '#221f3f',
            sidebar: '#08071a',
            sidebarForeground: '#c5c3d0',
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
            background: '#040a09',
            foreground: '#bfcdc9',
            card: '#081312',
            muted: '#0c1c1a',
            mutedForeground: '#687f7a',
            accent: '#12302c',
            accentForeground: '#8aa8a2',
            border: '#163330',
            sidebar: '#050c0b',
            sidebarForeground: '#bfcdc9',
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
