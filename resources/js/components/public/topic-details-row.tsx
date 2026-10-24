import { type ReactNode, useLayoutEffect, useRef, useState } from 'react';

type TopicDetailsRowProps = {
    id: string;
    open: boolean;
    /** True only for the latest pointer/touch disclosure action. */
    animate: boolean;
    children: ReactNode;
};

/**
 * Detail row for one public topic. It owns only presence: the row stays
 * mounted while its own CSS transition runs after `open` turns false, then
 * unmounts. Which topic is open belongs to the caller.
 */
export function TopicDetailsRow({
    id,
    open,
    animate,
    children,
}: TopicDetailsRowProps) {
    const [present, setPresent] = useState(open);
    const contentRef = useRef<HTMLDivElement>(null);

    // Opening needs the row mounted in this same render, before paint. Entry
    // itself comes from the CSS starting style.
    if (open && !present) {
        setPresent(true);
    }

    useLayoutEffect(() => {
        if (open || !present) {
            return;
        }

        // Each run owns its own completion; a reopen, a newer action or an
        // unmount invalidates it so an old exit can never remove a newer row.
        let current = true;
        const content = contentRef.current;
        const transitions =
            animate &&
            content !== null &&
            typeof content.getAnimations === 'function'
                ? // getAnimations() applies the closed style first, so the
                  // transitions it returns are the ones this close started.
                  content
                      .getAnimations()
                      .filter(
                          (animation) =>
                              typeof CSSTransition !== 'undefined' &&
                              animation instanceof CSSTransition,
                      )
                : [];

        if (transitions.length === 0) {
            // Nothing to wait for (keyboard, reduced motion, no support).
            // Removing before paint avoids a frame of stale closed content.
            // eslint-disable-next-line react-hooks/set-state-in-effect
            setPresent(false);

            return;
        }

        // Rejected or cancelled transitions (reduced-motion change, retarget)
        // still settle here; `current` decides whether to remove the row.
        void Promise.allSettled(
            transitions.map((transition) => transition.finished),
        ).then(() => {
            if (current) {
                setPresent(false);
            }
        });

        return () => {
            current = false;
        };
    }, [open, animate, present]);

    if (!open && !present) {
        return null;
    }

    return (
        <tr
            id={id}
            className="bg-muted/40 max-md:block"
            aria-hidden={open ? undefined : true}
            inert={!open}
        >
            <td colSpan={6} className="p-0 max-md:block">
                <div
                    ref={contentRef}
                    className="public-topic-details"
                    data-state={open ? 'open' : 'closed'}
                    data-motion={animate ? 'on' : 'off'}
                >
                    <div className="public-topic-details-inner px-4 pt-1 pb-5">
                        {children}
                    </div>
                </div>
            </td>
        </tr>
    );
}
