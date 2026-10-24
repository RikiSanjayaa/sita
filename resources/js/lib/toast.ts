import { toast as sonner, type ExternalToast } from 'sonner';

import { type SharedData } from '@/types';

export function showFlash(flash: SharedData['flash']): void {
    if (!flash?.id) return;

    if (flash.success) {
        sonner.success(flash.success, { id: `flash:${flash.id}:success` });
    }
    if (flash.error) {
        sonner.error(flash.error, {
            id: `flash:${flash.id}:error`,
            duration: 8000,
        });
    }
}

export function showNotification(
    title: Parameters<typeof sonner.info>[0],
    options: ExternalToast,
): void {
    sonner.info(title, {
        duration: 6000,
        testId: 'notification-toast',
        ...options,
    });
}

export function dismissNotification(id: string): void {
    sonner.dismiss(`notification:${id}`);
}
