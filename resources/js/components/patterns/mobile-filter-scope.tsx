import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useRef,
    type ReactNode,
} from 'react';
import { createPortal } from 'react-dom';

type Field = {
    value: string;
    resetValue: string;
    commit: (value: string) => void;
};
export type FilterValues = Record<string, string>;
export type FilterScope = {
    searchTarget: HTMLElement | null;
    draft: FilterValues | null;
    register: (label: string, field: Field) => () => void;
    change: (label: string, value: string) => void;
};
export const MobileFilterContext = createContext<FilterScope | null>(null);

/** Controls keep their normal callbacks; the mobile dialog commits them only on Apply. */
export function useFilterField<T extends string>(
    label: string,
    value: T,
    resetValue: T,
    onChange: (value: T) => void,
): [T, (value: T) => void] {
    const scope = useContext(MobileFilterContext);
    const callback = useRef(onChange);
    useEffect(() => {
        callback.current = onChange;
    }, [onChange]);
    const register = scope?.register;
    useEffect(
        () =>
            register?.(label, {
                value,
                resetValue,
                commit: (next) => callback.current(next as T),
            }),
        [register, label, value, resetValue],
    );
    const change = useCallback(
        (next: T) => {
            if (scope) scope.change(label, next);
            else callback.current(next);
        },
        [scope, label],
    );
    return [(scope?.draft?.[label] ?? value) as T, change];
}

/** Search stays in the toolbar while the other controls live inside the dialog. */
export function FilterSearchSlot({ children }: { children: ReactNode }) {
    const scope = useContext(MobileFilterContext);
    return scope
        ? scope.searchTarget
            ? createPortal(children, scope.searchTarget)
            : null
        : children;
}
