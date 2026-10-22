import { Fragment } from 'react';

import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

function truncateFilename(name: string | null, maxLength = 28) {
    if (!name) {
        return '';
    }

    if (name.length <= maxLength) {
        return name;
    }

    return `${name.slice(0, maxLength - 3)}...`;
}

function escapeRegExp(value: string) {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/** Wraps case-insensitive matches of `query` in `<mark>` for in-thread search. */
export function highlightText(text: string, query?: string, inverse = false) {
    const normalizedQuery = query?.trim() ?? '';

    if (!normalizedQuery) {
        return text;
    }

    const parts = text.split(
        new RegExp(`(${escapeRegExp(normalizedQuery)})`, 'gi'),
    );

    return parts.map((part, index) => {
        if (part.toLowerCase() !== normalizedQuery.toLowerCase()) {
            return <Fragment key={`${part}-${index}`}>{part}</Fragment>;
        }

        return (
            <mark
                key={`${part}-${index}`}
                className={cn(
                    'rounded px-0.5',
                    inverse
                        ? 'bg-primary-foreground/25 text-primary-foreground'
                        : 'bg-yellow-200/80 text-foreground dark:bg-yellow-500/30 dark:text-foreground',
                )}
            >
                {part}
            </mark>
        );
    });
}

/** File name that shortens with a tooltip showing the full name. */
export function FilenameLabel({
    name,
    className,
    searchTerm,
    inverse,
}: {
    name: string | null;
    className?: string;
    searchTerm?: string;
    inverse?: boolean;
}) {
    if (!name) {
        return null;
    }

    const shortName = truncateFilename(name);

    if (shortName === name) {
        return (
            <span className={className}>
                {highlightText(name, searchTerm, inverse)}
            </span>
        );
    }

    return (
        <TooltipProvider delayDuration={0}>
            <Tooltip>
                <TooltipTrigger asChild>
                    <span className={className}>
                        {highlightText(shortName, searchTerm, inverse)}
                    </span>
                </TooltipTrigger>
                <TooltipContent>
                    <p className="max-w-xs break-all">{name}</p>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}
