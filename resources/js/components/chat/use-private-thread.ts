import { useForm } from '@inertiajs/react';
import { useState } from 'react';

export type PrivateThreadState = {
    close: () => void;
    isOpen: boolean;
    processing: boolean;
    recipientId: string;
    setRecipientId: (value: string) => void;
    start: () => void;
    toggle: () => void;
};

/** Picker state for opening a private thread with a chosen recipient. */
export function usePrivateThread(postUrl: string): PrivateThreadState {
    const [isOpen, setIsOpen] = useState(false);
    const form = useForm<{ recipient_id: string }>({ recipient_id: '' });

    return {
        close: () => setIsOpen(false),
        isOpen,
        processing: form.processing,
        recipientId: form.data.recipient_id,
        setRecipientId: (value) => form.setData('recipient_id', value),
        start: () => {
            if (form.data.recipient_id === '') {
                return;
            }

            form.post(postUrl, { preserveScroll: true });
        },
        toggle: () => setIsOpen((current) => !current),
    };
}
