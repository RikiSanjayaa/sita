import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useRef,
    type ReactNode,
} from 'react';
import { createPortal } from 'react-dom';

export type FilterOption = {
    value: string;
    label: string;
    count?: number;
    disabled?: boolean;
};
export type FilterField = {
    value: string;
    resetValue: string;
    options: readonly FilterOption[];
    commit: (value: string) => void;
};
export type FilterValues = Record<string, string>;
export type FilterScope = {
    searchTarget: HTMLElement | null;
    register: (label: string, field: FilterField) => void;
    unregister: (label: string) => void;
};
export const MobileFilterContext = createContext<FilterScope | null>(null);

/** Register mobile dropdowns while desktop controls keep their normal callbacks. */
export function useFilterField<T extends string>(
    label: string,
    value: T,
    resetValue: T,
    onChange: (value: T) => void,
    options: readonly FilterOption[],
): boolean {
    const scope = useContext(MobileFilterContext);
    const callback = useRef(onChange);
    useEffect(() => {
        callback.current = onChange;
    }, [onChange]);
    const register = scope?.register;
    const unregister = scope?.unregister;
    const commit = useCallback(
        (next: string) => callback.current(next as T),
        [],
    );
    useEffect(() => {
        register?.(label, { value, resetValue, options, commit });
    }, [register, label, value, resetValue, options, commit]);
    useEffect(() => () => unregister?.(label), [unregister, label]);
    return scope !== null;
}

/** Keep search beside the toggle even when the filter panel is collapsed. */
export function FilterSearchSlot({ children }: { children: ReactNode }) {
    const scope = useContext(MobileFilterContext);
    return scope
        ? scope.searchTarget
            ? createPortal(children, scope.searchTarget)
            : null
        : children;
}
