import { Monitor, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';

const STORAGE_KEY = 'sita:desktop-notice-dismissed';
const SMALL_SCREEN_QUERY = '(max-width: 767px)';
const HEIGHT_VARIABLE = '--desktop-notice-height';

function readDismissed(): boolean {
    try {
        return window.sessionStorage.getItem(STORAGE_KEY) === '1';
    } catch {
        return false;
    }
}

/**
 * Tells phone users the portal is most comfortable on a laptop. Width is read
 * on the client only, so nothing renders during SSR or hydration.
 */
export function DesktopNotice() {
    const [visible, setVisible] = useState(false);
    const container = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const query = window.matchMedia(SMALL_SCREEN_QUERY);
        const update = () => setVisible(query.matches && !readDismissed());

        update();
        query.addEventListener('change', update);

        return () => query.removeEventListener('change', update);
    }, []);

    // Screens sized to the viewport (the chat) subtract this height so the
    // notice does not push their bottom edge out of view.
    useEffect(() => {
        const element = container.current;
        const root = document.documentElement;

        if (!visible || !element) {
            return;
        }

        const publish = () =>
            root.style.setProperty(
                HEIGHT_VARIABLE,
                `${element.offsetHeight}px`,
            );
        const observer = new ResizeObserver(publish);

        publish();
        observer.observe(element);

        return () => {
            observer.disconnect();
            root.style.removeProperty(HEIGHT_VARIABLE);
        };
    }, [visible]);

    if (!visible) {
        return null;
    }

    const dismiss = () => {
        try {
            window.sessionStorage.setItem(STORAGE_KEY, '1');
        } catch {
            // Storage unavailable: the notice only hides for this page view.
        }
        setVisible(false);
    };

    return (
        <div
            ref={container}
            className="px-4 pt-4 md:hidden"
            data-testid="desktop-notice"
        >
            <Alert
                role="status"
                className="has-[>svg]:grid-cols-[calc(var(--spacing)*4)_1fr_auto]"
            >
                <Monitor />
                <AlertDescription className="text-foreground">
                    Tampilan SiTA paling nyaman di laptop atau komputer. Di HP
                    semua fitur tetap bisa dipakai.
                </AlertDescription>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="-my-2 -mr-3 size-11"
                    aria-label="Tutup pemberitahuan"
                    onClick={dismiss}
                >
                    <X />
                </Button>
            </Alert>
        </div>
    );
}
