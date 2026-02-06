declare module '@/routes' {
    export type WayfinderLocation = {
        url: string;
        [key: string]: unknown;
    };

    export const home: (...args: unknown[]) => WayfinderLocation;
    export const dashboard: (...args: unknown[]) => WayfinderLocation;
    export const editProfile: (...args: unknown[]) => WayfinderLocation;
    export const tugasAkhir: (...args: unknown[]) => WayfinderLocation;
    export const jadwalBimbingan: (...args: unknown[]) => WayfinderLocation;
    export const uploadDokumen: (...args: unknown[]) => WayfinderLocation;
    export const pesan: (...args: unknown[]) => WayfinderLocation;
    export const panduan: (...args: unknown[]) => WayfinderLocation;
    export const settingNotifikasi: (...args: unknown[]) => WayfinderLocation;
    export const login: (...args: unknown[]) => WayfinderLocation;
    export const register: (...args: unknown[]) => WayfinderLocation;
    export const logout: (...args: unknown[]) => WayfinderLocation;
}

declare module '@/routes/*' {
    export type WayfinderLocation = {
        url: string;
        [key: string]: unknown;
    };

    export type WayfinderRoute = ((...args: unknown[]) => WayfinderLocation) & {
        url: (...args: unknown[]) => string;
        form: () => Record<string, unknown>;
    };

    export const index: WayfinderRoute;
    export const show: WayfinderRoute;
    export const create: WayfinderRoute;
    export const store: WayfinderRoute;
    export const edit: WayfinderRoute;
    export const update: WayfinderRoute;
    export const destroy: WayfinderRoute;

    export const send: WayfinderRoute;
    export const email: WayfinderRoute;
    export const request: WayfinderRoute;

    export const enable: WayfinderRoute;
    export const disable: WayfinderRoute;
    export const confirm: WayfinderRoute;
    export const qrCode: WayfinderRoute;
    export const recoveryCodes: WayfinderRoute;
    export const secretKey: WayfinderRoute;
    export const regenerateRecoveryCodes: WayfinderRoute;

    const _default: Record<string, unknown>;
    export default _default;
}
