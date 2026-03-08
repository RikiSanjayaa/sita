import { Link } from '@inertiajs/react';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import { type UserProfileSummary } from '@/types';

type PersonCardLinkProps = {
    person: UserProfileSummary;
    label?: string;
    className?: string;
};

export function PersonCardLink({
    person,
    label,
    className,
}: PersonCardLinkProps) {
    const getInitials = useInitials();

    return (
        <Link
            href={person.profileUrl}
            className={cn(
                'group flex items-start gap-3 rounded-xl border bg-background p-4 text-left transition hover:border-primary/30 hover:bg-muted/30',
                className,
            )}
        >
            <Avatar className="size-12 border">
                <AvatarImage
                    src={person.avatar ?? undefined}
                    alt={person.name}
                />
                <AvatarFallback className="bg-primary/10 text-primary">
                    {getInitials(person.name)}
                </AvatarFallback>
            </Avatar>

            <div className="min-w-0 flex-1 space-y-1">
                {label ? (
                    <p className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        {label}
                    </p>
                ) : null}
                <p className="truncate text-sm font-semibold text-foreground group-hover:text-primary">
                    {person.name}
                </p>
                {person.subtitle ? (
                    <p className="line-clamp-2 text-xs text-muted-foreground">
                        {person.subtitle}
                    </p>
                ) : null}
                <Badge variant="outline" className="mt-2 w-fit">
                    {person.roleLabel}
                </Badge>
            </div>
        </Link>
    );
}
