import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';

import { StatusBadge } from '@/components/patterns/status-badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import { type UserProfileSummary } from '@/types';

type PersonCardLinkProps = {
    person: UserProfileSummary;
    label?: string;
    /** Supervision count with the viewing lecturer. Omit or null when no supervision applies. */
    mentorshipCount?: number | null;
    /**
     * `card` is a bordered box (default). `row` is a borderless full-width row
     * for divided lists inside a section that already has a border.
     */
    variant?: 'card' | 'row';
    className?: string;
};

export function PersonCardLink({
    person,
    label,
    mentorshipCount,
    variant = 'card',
    className,
}: PersonCardLinkProps) {
    const getInitials = useInitials();

    // Subtitle parts are separated by " · " or "·"; the card shows them on one truncated line.
    const meta = person.subtitle
        ? person.subtitle
              .split(/\s*·\s*/)
              .map((s) => s.trim())
              .filter(Boolean)
              .join(' · ')
        : (person.roleLabel ?? '');

    const hasPhase = Boolean(person.phase && person.phaseLabel);
    const hasCount = typeof mentorshipCount === 'number';

    return (
        <Link
            href={person.profileUrl}
            className={cn(
                'group flex items-center gap-3 px-4 py-3 transition-colors',
                variant === 'card'
                    ? 'rounded-lg border hover:border-primary/40'
                    : 'hover:bg-accent/60 focus-visible:bg-accent/60 focus-visible:outline-none max-md:min-h-11',
                className,
            )}
        >
            <div className="flex min-w-0 flex-1 flex-col gap-2">
                <div className="flex min-w-0 items-start gap-3">
                    <Avatar className="size-10 shrink-0 border">
                        <AvatarImage
                            src={person.avatar ?? undefined}
                            alt={person.name}
                        />
                        <AvatarFallback className="bg-primary-muted text-sm font-semibold text-primary-muted-foreground">
                            {getInitials(person.name)}
                        </AvatarFallback>
                    </Avatar>

                    <div className="min-w-0 flex-1">
                        {label && (
                            <p className="mb-0.5 text-xs font-medium text-muted-foreground">
                                {label}
                            </p>
                        )}
                        <p className="line-clamp-2 text-sm font-semibold break-words text-foreground transition-colors group-hover:underline">
                            {person.name}
                        </p>
                        {meta && (
                            <p
                                className="truncate text-xs text-muted-foreground"
                                title={meta}
                            >
                                {meta}
                            </p>
                        )}
                    </div>
                </div>

                {(hasPhase || hasCount) && (
                    <div className="flex min-w-0 items-center justify-between gap-2">
                        {hasPhase ? (
                            <StatusBadge domain="phase" status={person.phase}>
                                {person.phaseLabel}
                            </StatusBadge>
                        ) : (
                            <span />
                        )}
                        {hasCount && (
                            <p className="shrink-0 text-xs text-muted-foreground tabular-nums">
                                <span className="font-semibold text-foreground">
                                    {mentorshipCount}
                                </span>{' '}
                                bimbingan
                            </p>
                        )}
                    </div>
                )}
            </div>

            <ChevronRight className="size-4 shrink-0 text-muted-foreground/40 transition-all group-hover:translate-x-0.5 group-hover:text-primary/60" />
        </Link>
    );
}
