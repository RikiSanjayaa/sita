import { router } from '@inertiajs/react';
import { useEffect, useRef, type CSSProperties } from 'react';
import { Toaster } from 'sonner';

import { useAppearance } from '@/hooks/use-appearance';
import { showFlash } from '@/lib/toast';
import { type SharedData } from '@/types';

const colors = {
    '--normal-bg': 'var(--card)',
    '--normal-text': 'var(--card-foreground)',
    '--normal-border': 'var(--border)',
    '--success-bg': 'var(--success-muted)',
    '--success-text': 'var(--success-muted-foreground)',
    '--success-border': 'var(--success)',
    '--error-bg': 'var(--danger-muted)',
    '--error-text': 'var(--danger-muted-foreground)',
    '--error-border': 'var(--danger)',
    '--info-bg': 'var(--card)',
    '--info-text': 'var(--card-foreground)',
    '--info-border': 'var(--border)',
} as CSSProperties;

export function AppToaster({
    initialFlash,
}: {
    initialFlash: SharedData['flash'];
}) {
    const { resolvedAppearance } = useAppearance();
    const seen = useRef(new Set<string>());

    useEffect(() => {
        const display = (flash: SharedData['flash']) => {
            if (!flash?.id || seen.current.has(flash.id)) return;
            seen.current.add(flash.id);
            showFlash(flash);
        };

        display(initialFlash);
        const stopSuccess = router.on('success', ({ detail }) => {
            display(detail.page.props.flash as SharedData['flash']);
        });
        const stopNavigate = router.on('navigate', ({ detail }) => {
            display(detail.page.props.flash as SharedData['flash']);
        });
        return () => {
            stopSuccess();
            stopNavigate();
        };
    }, [initialFlash]);

    return (
        <Toaster
            theme={resolvedAppearance}
            position="top-right"
            offset={{ top: 80, right: 24 }}
            mobileOffset={{ top: 80, left: 16, right: 16 }}
            closeButton
            richColors
            duration={4500}
            containerAriaLabel="Umpan balik"
            style={colors}
            toastOptions={{
                closeButtonAriaLabel: 'Tutup notifikasi',
                classNames: {
                    toast: 'font-sans rounded-xl! shadow-lg! text-sm!',
                    title: 'font-semibold!',
                    description: 'text-inherit! opacity-90',
                    actionButton:
                        'bg-primary! text-primary-foreground! focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none max-md:min-h-11',
                    closeButton:
                        'focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none max-md:size-8!',
                },
            }}
        />
    );
}
