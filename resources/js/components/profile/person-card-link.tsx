import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
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

    // Build subtitle chips from subtitle string (split on " · " or "·")
    const chips = person.subtitle
        ? person.subtitle
              .split(/\s*·\s*/)
              .map((s) => s.trim())
              .filter(Boolean)
        : [];

    return (
        <Link
            href={person.profileUrl}
            className={cn(
                'group flex items-center gap-3 rounded-lg border-2 px-4 py-3 transition-colors hover:border-primary/40',
                className,
            )}
        >
            <Avatar className="size-10 shrink-0 border">
                <AvatarImage
                    src={person.avatar ?? undefined}
                    alt={person.name}
                />
                <AvatarFallback className="bg-primary/10 text-sm font-semibold text-primary">
                    {getInitials(person.name)}
                </AvatarFallback>
            </Avatar>

            <div className="min-w-0 flex-1 pl-1">
                {label && (
                    <p className="mb-0.5 text-[10px] font-semibold tracking-widest text-primary/70 uppercase">
                        {label}
                    </p>
                )}
                <p className="truncate text-sm font-semibold text-foreground transition-colors group-hover:text-primary">
                    {person.name}
                </p>
                {chips.length > 0 && (
                    <div className="mt-1 flex flex-wrap items-center gap-x-1.5 gap-y-0.5">
                        {chips.map((chip, i) => (
                            <span
                                key={i}
                                className="text-[11px] text-muted-foreground"
                            >
                                {chip}
                                {i < chips.length - 1 && (
                                    <span className="ml-1.5 text-muted-foreground/40">
                                        ·
                                    </span>
                                )}
                            </span>
                        ))}
                    </div>
                )}
                {chips.length === 0 && person.roleLabel && (
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        {person.roleLabel}
                    </p>
                )}
            </div>

            <ChevronRight className="size-4 shrink-0 text-muted-foreground/40 transition-all group-hover:translate-x-0.5 group-hover:text-primary/60" />
        </Link>
    );
}
