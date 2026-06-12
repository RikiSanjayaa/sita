import {
    useCallback,
    useEffect,
    useState,
    type Dispatch,
    type SetStateAction,
} from 'react';

type UrlValue = string | number;

function readParam<T extends UrlValue>(key: string, fallback: T): T {
    if (typeof window === 'undefined') {
        return fallback;
    }

    const value = new URLSearchParams(window.location.search).get(key);

    if (value === null || value === '') {
        return fallback;
    }

    return (typeof fallback === 'number' ? Number(value) : value) as T;
}

function writeParam<T extends UrlValue>(key: string, value: T, fallback: T) {
    if (typeof window === 'undefined') {
        return;
    }

    const url = new URL(window.location.href);
    const nextValue = String(value);
    const defaultValue = String(fallback);

    if (nextValue === defaultValue || nextValue === '') {
        url.searchParams.delete(key);
    } else {
        url.searchParams.set(key, nextValue);
    }

    window.history.replaceState(window.history.state, '', url);
}

export function useUrlState(
    key: string,
    fallback: string,
): [string, Dispatch<SetStateAction<string>>];
export function useUrlState(
    key: string,
    fallback: number,
): [number, Dispatch<SetStateAction<number>>];
export function useUrlState<T extends string>(
    key: string,
    fallback: T,
): [T, Dispatch<SetStateAction<T>>];
export function useUrlState<T extends UrlValue>(
    key: string,
    fallback: T,
): [T, Dispatch<SetStateAction<T>>] {
    const [value, setValue] = useState<T>(() => readParam(key, fallback));

    useEffect(() => {
        writeParam(key, value, fallback);
    }, [fallback, key, value]);

    useEffect(() => {
        function handlePopState() {
            setValue(readParam(key, fallback));
        }

        window.addEventListener('popstate', handlePopState);

        return () => window.removeEventListener('popstate', handlePopState);
    }, [fallback, key]);

    const setUrlValue = useCallback<Dispatch<SetStateAction<T>>>(
        (nextValue) => {
            setValue(nextValue);
        },
        [],
    );

    return [value, setUrlValue];
}
