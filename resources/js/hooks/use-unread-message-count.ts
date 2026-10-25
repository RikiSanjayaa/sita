import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

import { unread } from '@/routes/chat';
import { type SharedData } from '@/types';

/** The server owns unread state; events only invalidate the current account's snapshot. */
export function useUnreadMessageCount(): number {
    const { props, url } = usePage<SharedData>();
    const { auth, unreadMessageCount } = props;
    const scope = `${auth.user?.id ?? ''}:${auth.activeRole ?? ''}`;
    const enabled = Boolean(
        auth.user?.id &&
        ['mahasiswa', 'dosen', 'kaprodi'].includes(auth.activeRole ?? ''),
    );
    const [snapshot, setSnapshot] = useState({
        scope,
        source: props,
        value: unreadMessageCount,
    });

    useEffect(() => {
        if (!enabled) {
            return;
        }

        const controller = new AbortController();
        let pending = false;
        let dirty = false;

        async function refresh(): Promise<void> {
            if (pending) {
                dirty = true;
                return;
            }

            pending = true;
            do {
                dirty = false;
                try {
                    const response = await fetch(unread().url, {
                        headers: { Accept: 'application/json' },
                        credentials: 'same-origin',
                        signal: controller.signal,
                    });
                    if (!response.ok) {
                        break;
                    }
                    const data: { unreadMessageCount: number } =
                        await response.json();
                    if (!controller.signal.aborted) {
                        setSnapshot({
                            scope,
                            source: props,
                            value: data.unreadMessageCount,
                        });
                    }
                } catch (error) {
                    if (!controller.signal.aborted) {
                        console.error(
                            'Unread messages could not be refreshed',
                            error,
                        );
                    }
                }
            } while (dirty && !controller.signal.aborted);
            pending = false;
        }

        const invalidate = () => void refresh();
        const onVisible = () => {
            if (document.visibilityState === 'visible') {
                invalidate();
            }
        };
        const echo = window.Echo;
        const channelName = `chat.unread.${auth.user.id}`;
        const channel = echo?.private(channelName);
        channel?.listen('.chat.unread.updated', invalidate);
        channel?.subscribed(invalidate);
        window.addEventListener('focus', invalidate);
        window.addEventListener('chat:read', invalidate);
        document.addEventListener('visibilitychange', onVisible);
        invalidate();

        return () => {
            controller.abort();
            channel?.stopListening('.chat.unread.updated', invalidate);
            echo?.leave(channelName);
            window.removeEventListener('focus', invalidate);
            window.removeEventListener('chat:read', invalidate);
            document.removeEventListener('visibilitychange', onVisible);
        };
    }, [auth.user?.id, enabled, props, scope, url]);

    if (!enabled) {
        return 0;
    }

    return snapshot.scope === scope && snapshot.source === props
        ? snapshot.value
        : unreadMessageCount;
}
