import { usePage } from '@inertiajs/react';
import { UserRound, Users } from 'lucide-react';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { getInitials } from '@/lib/initials';
import { cn } from '@/lib/utils';
import { type SharedData } from '@/types';

import { type ChatThread } from './types';

/** Only uses participants already authorized in the thread payload. */
export function ThreadAvatar({
    thread,
    className,
}: {
    thread: ChatThread;
    className?: string;
}) {
    const { auth } = usePage<SharedData>().props;
    const members = thread.memberProfiles.filter(
        (member) => member.id !== auth.user.id,
    );
    const isPrivate = thread.threadType === 'private';
    const visible = members.slice(0, isPrivate ? 1 : 2);
    const Icon = isPrivate ? UserRound : Users;

    return (
        <span
            aria-hidden="true"
            className={cn('relative block h-11 w-14 shrink-0', className)}
        >
            {visible.length === 0 ? (
                <span className="absolute top-0 left-1/2 flex size-11 -translate-x-1/2 items-center justify-center rounded-full bg-muted text-muted-foreground">
                    <Icon className="size-5" />
                </span>
            ) : (
                visible.map((member, index) => (
                    <Avatar
                        key={member.id}
                        className={cn(
                            'absolute border-2 border-card',
                            visible.length === 1
                                ? 'inset-y-0 left-1/2 size-11 -translate-x-1/2'
                                : 'size-8',
                            visible.length > 1 && index === 0 && 'top-0 left-0',
                            visible.length > 1 &&
                                index === 1 &&
                                'right-0 bottom-0',
                        )}
                    >
                        <AvatarImage src={member.avatar ?? undefined} alt="" />
                        <AvatarFallback className="bg-primary-muted text-xs font-medium text-primary-muted-foreground">
                            <span>{getInitials(member.name)}</span>
                        </AvatarFallback>
                    </Avatar>
                ))
            )}
        </span>
    );
}
