import { Link, usePage } from '@inertiajs/react';
import { Users } from 'lucide-react';
import { useEffect, useRef, useState, type ReactNode } from 'react';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    Popover,
    PopoverAnchor,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { getInitials } from '@/lib/initials';
import { type SharedData } from '@/types';

import { type ChatThread } from './types';

/** Never expands membership beyond the server-authorized thread payload. */
export function ThreadMembers({
    thread,
    children,
}: {
    thread: ChatThread;
    children: ReactNode;
}) {
    const { auth } = usePage<SharedData>().props;
    const [open, setOpen] = useState(false);
    const closeTimer = useRef<number | undefined>(undefined);
    const openedByHover = useRef(false);
    const members = thread.memberProfiles;
    const visible = members.slice(0, 2);
    const remaining = members.length - visible.length;

    function cancelClose() {
        window.clearTimeout(closeTimer.current);
    }

    function deferClose() {
        cancelClose();
        closeTimer.current = window.setTimeout(() => setOpen(false), 180);
    }

    useEffect(
        () => () => {
            window.clearTimeout(closeTimer.current);
        },
        [],
    );

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverAnchor asChild>
                <div className="pointer-events-auto mx-auto flex w-fit max-w-[calc(100%-6rem)] items-center gap-2 rounded-full bg-card pr-3 text-left text-card-foreground">
                    <PopoverTrigger asChild>
                        <button
                            type="button"
                            aria-label={`Lihat ${members.length} anggota percakapan`}
                            className="pointer-events-auto flex min-h-11 shrink-0 items-center -space-x-3 rounded-full focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            onPointerEnter={(event) => {
                                if (event.pointerType === 'touch') return;
                                openedByHover.current = true;
                                cancelClose();
                                setOpen(true);
                            }}
                            onClick={(event) => {
                                if (open) event.preventDefault();
                            }}
                            onPointerLeave={deferClose}
                            onFocus={cancelClose}
                            onKeyDown={(event) => {
                                if (
                                    event.key === 'Enter' ||
                                    event.key === ' '
                                ) {
                                    openedByHover.current = false;
                                }
                            }}
                        >
                            {visible.map((member) => (
                                <span
                                    key={member.id}
                                    className="flex size-11 items-center justify-center"
                                >
                                    <Avatar className="size-8 border-2 border-card">
                                        <AvatarImage
                                            src={member.avatar ?? undefined}
                                            alt=""
                                        />
                                        <AvatarFallback className="bg-primary-muted text-sm font-medium text-primary-muted-foreground">
                                            {getInitials(member.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                </span>
                            ))}
                            {remaining > 0 && (
                                <span className="flex size-11 items-center justify-center">
                                    <span className="relative flex size-8 items-center justify-center rounded-full border-2 border-card bg-muted text-xs font-semibold text-foreground">
                                        +{remaining}
                                    </span>
                                </span>
                            )}
                            {visible.length === 0 && (
                                <span className="flex size-11 items-center justify-center">
                                    <span className="flex size-8 items-center justify-center rounded-full bg-card text-muted-foreground">
                                        <Users aria-hidden className="size-4" />
                                    </span>
                                </span>
                            )}
                        </button>
                    </PopoverTrigger>
                    {children}
                </div>
            </PopoverAnchor>
            <PopoverContent
                aria-label="Anggota percakapan"
                className="pointer-events-auto w-80 max-w-[calc(100vw-2rem)] rounded-2xl p-2"
                sideOffset={8}
                onOpenAutoFocus={(event) => {
                    if (openedByHover.current) event.preventDefault();
                }}
                onPointerEnter={cancelClose}
                onPointerLeave={(event) => {
                    if (!event.currentTarget.contains(document.activeElement))
                        deferClose();
                }}
                onFocusCapture={cancelClose}
                onBlurCapture={(event) => {
                    if (!event.currentTarget.contains(event.relatedTarget))
                        deferClose();
                }}
            >
                <ul className="max-h-72 overflow-y-auto overscroll-contain">
                    {members.map((member) => (
                        <li key={member.id}>
                            <Link
                                href={member.profileUrl}
                                className="flex min-h-14 items-center gap-3 rounded-xl px-3 py-2 hover:bg-muted focus-visible:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <Avatar className="size-10 shrink-0">
                                    <AvatarImage
                                        src={member.avatar ?? undefined}
                                        alt=""
                                    />
                                    <AvatarFallback className="bg-primary-muted text-primary-muted-foreground">
                                        {getInitials(member.name)}
                                    </AvatarFallback>
                                </Avatar>
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-sm font-medium">
                                        {member.name}
                                    </span>
                                    <span className="block truncate text-xs text-muted-foreground">
                                        {member.roleLabel}
                                        {member.id === auth.user.id
                                            ? ' · Anda'
                                            : ''}
                                    </span>
                                </span>
                            </Link>
                        </li>
                    ))}
                </ul>
            </PopoverContent>
        </Popover>
    );
}
