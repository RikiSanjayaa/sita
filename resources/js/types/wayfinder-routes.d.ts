declare module '@/routes' {
    export type WayfinderLocation = {
        url: string;
        [key: string]: unknown;
    };

    export const home: (...args: unknown[]) => WayfinderLocation;
    export const dashboard: (...args: unknown[]) => WayfinderLocation;
    export const tugasAkhir: (...args: unknown[]) => WayfinderLocation;
    export const login: (...args: unknown[]) => WayfinderLocation;
    export const register: (...args: unknown[]) => WayfinderLocation;
    export const logout: (...args: unknown[]) => WayfinderLocation;
}

declare module '@/routes/*' {
    export type WayfinderLocation = {
        url: string;
        [key: string]: unknown;
    };

    export const index: (...args: unknown[]) => WayfinderLocation;
    export const show: (...args: unknown[]) => WayfinderLocation;
    export const create: (...args: unknown[]) => WayfinderLocation;
    export const store: { form: () => Record<string, unknown> } & Record<
        string,
        unknown
    >;
    export const edit: (...args: unknown[]) => WayfinderLocation;
    export const update: (...args: unknown[]) => WayfinderLocation;
    export const destroy: (...args: unknown[]) => WayfinderLocation;

    export const send: (...args: unknown[]) => WayfinderLocation;
    export const email: (...args: unknown[]) => WayfinderLocation;
    export const request: (...args: unknown[]) => WayfinderLocation;

    export const enable: (...args: unknown[]) => WayfinderLocation;
    export const disable: (...args: unknown[]) => WayfinderLocation;
    export const confirm: (...args: unknown[]) => WayfinderLocation;
    export const qrCode: (...args: unknown[]) => WayfinderLocation;
    export const recoveryCodes: (...args: unknown[]) => WayfinderLocation;
    export const secretKey: (...args: unknown[]) => WayfinderLocation;
    export const regenerateRecoveryCodes: (
        ...args: unknown[]
    ) => WayfinderLocation;

    const _default: Record<string, unknown>;
    export default _default;
}
