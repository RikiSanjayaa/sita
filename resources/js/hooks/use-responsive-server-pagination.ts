import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

import { useIsMobile } from '@/hooks/use-mobile';

type PageSize = {
    key: string;
    pageKey: string;
    value: number;
    desktop: number;
};
type Preference = { manual: boolean; value: number };
const sizes = [5, 8, 10, 15, 25, 50, 100];

function storageKey(key: string): string {
    return `sita:page-size:${window.location.pathname}:${key}`;
}
function preference(config: PageSize): Preference {
    const query = new URLSearchParams(window.location.search);
    const explicit = query.has(config.key)
        ? Number(query.get(config.key))
        : null;
    try {
        const stored = JSON.parse(
            sessionStorage.getItem(storageKey(config.key)) ?? 'null',
        ) as Preference | null;
        if (
            stored &&
            typeof stored.manual === 'boolean' &&
            sizes.includes(stored.value) &&
            (explicit === null || explicit === stored.value)
        )
            return stored;
    } catch {
        /* Storage is optional; explicit query values still win. */
    }
    return {
        manual: explicit !== null && sizes.includes(explicit),
        value: explicit ?? config.value,
    };
}
function remember(key: string, value: Preference): void {
    try {
        sessionStorage.setItem(storageKey(key), JSON.stringify(value));
    } catch {
        /* Keep the current visit usable when storage is denied. */
    }
}

/** Request a complete server page; never slice one fetched page and lose rows. */
export function useResponsiveServerPagination(configs: readonly PageSize[]) {
    const mobile = useIsMobile();
    const preferences = useRef(new Map<string, Preference>());
    const pending = useRef('');
    const signature = JSON.stringify(configs);
    useEffect(() => {
        const current = JSON.parse(signature) as PageSize[];
        const url = new URL(window.location.href);
        let changed = false;
        for (const config of current) {
            let choice = preferences.current.get(config.key);
            if (!choice) {
                choice = preference(config);
                preferences.current.set(config.key, choice);
            }
            const desired = choice.manual
                ? choice.value
                : mobile
                  ? 5
                  : config.desktop;
            if (!choice.manual) {
                choice.value = desired;
                remember(config.key, choice);
            }
            if (config.value !== desired) {
                url.searchParams.set(config.key, String(desired));
                url.searchParams.set(config.pageKey, '1');
                changed = true;
            }
        }
        if (changed && pending.current !== url.href) {
            pending.current = url.href;
            router.get(url.pathname, Object.fromEntries(url.searchParams), {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        } else if (!changed) pending.current = '';
    }, [mobile, signature]);

    return (key: string, value: number): void => {
        const config = configs.find((entry) => entry.key === key);
        if (!config || !sizes.includes(value)) return;
        const choice = { manual: true, value };
        preferences.current.set(key, choice);
        remember(key, choice);
        const url = new URL(window.location.href);
        url.searchParams.set(key, String(value));
        url.searchParams.set(config.pageKey, '1');
        router.get(url.pathname, Object.fromEntries(url.searchParams), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };
}
