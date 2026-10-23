import { type PropsWithChildren, useLayoutEffect, useRef } from 'react';

import { cn } from '@/lib/utils';

const introPaths = [
    '/',
    '/topik',
    '/jadwal',
    '/pembimbing',
    '/mahasiswa-aktif',
];

// One entrance per browser document. Module state survives Inertia visits and
// component remounts, and a full reload starts a fresh module.
let documentClaimed = false;

/** True when the document the browser originally loaded is a public intro page that is still on screen. */
function isInitialPublicDocument(): boolean {
    const [entry] = performance.getEntriesByType('navigation');

    if (!entry) {
        return false;
    }

    try {
        const originalPath = new URL(entry.name, window.location.origin)
            .pathname;

        return (
            introPaths.includes(originalPath) &&
            originalPath === window.location.pathname
        );
    } catch {
        return false;
    }
}

/**
 * Wraps the title and description group of a public page. Only the first such
 * group on the initially loaded document plays a short entrance; later visits,
 * query updates and remounts on the same document stay settled.
 */
export function PublicIntro({
    children,
    className,
}: PropsWithChildren<{ className?: string }>) {
    const ref = useRef<HTMLDivElement>(null);

    useLayoutEffect(() => {
        const element = ref.current;

        if (!element) {
            return;
        }

        const reducedMotion = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        );

        // StrictMode replays setup after cleanup. A node that already owns the
        // entrance must keep it, not claim again or restart.
        if (element.dataset.publicIntro !== 'playing') {
            if (documentClaimed || !isInitialPublicDocument()) {
                return;
            }

            // Claimed even under reduced motion, so a later preference change
            // cannot start an entrance on this document.
            documentClaimed = true;

            if (reducedMotion.matches) {
                return;
            }

            element.dataset.publicIntro = 'playing';
        }

        const stopOnReducedMotion = (event: MediaQueryListEvent) => {
            if (event.matches) {
                delete element.dataset.publicIntro;
            }
        };

        reducedMotion.addEventListener('change', stopOnReducedMotion);

        return () =>
            reducedMotion.removeEventListener('change', stopOnReducedMotion);
    }, []);

    return (
        <div ref={ref} className={cn('public-intro', className)}>
            {children}
        </div>
    );
}
