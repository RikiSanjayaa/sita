import { Fragment, type MouseEvent } from 'react';

const URL_PATTERN = /(?:https?:\/\/|www\.)[^\s<]+/gi;
const TRAILING_PUNCTUATION = /[.,;:!?)}\]]+$/;

export function normalizeUrl(url: string): string {
    return url.toLowerCase().startsWith('www.') ? `https://${url}` : url;
}

export function firstUrlInText(text: string): string | null {
    const match = text.match(URL_PATTERN)?.[0];

    return match ? normalizeUrl(match.replace(TRAILING_PUNCTUATION, '')) : null;
}

function isExternalUrl(url: string): boolean {
    if (typeof window === 'undefined') {
        return true;
    }

    return (
        new URL(url, window.location.origin).origin !== window.location.origin
    );
}

export function LinkifiedText({
    text,
    onLinkClick,
}: {
    text: string;
    onLinkClick?: () => void;
}) {
    const parts: Array<{ content: string; href?: string }> = [];
    let cursor = 0;

    for (const match of text.matchAll(URL_PATTERN)) {
        const index = match.index ?? 0;
        const matchedUrl = match[0];
        const cleanUrl = matchedUrl.replace(TRAILING_PUNCTUATION, '');
        const punctuation = matchedUrl.slice(cleanUrl.length);

        if (index > cursor) {
            parts.push({ content: text.slice(cursor, index) });
        }

        parts.push({ content: cleanUrl, href: normalizeUrl(cleanUrl) });

        if (punctuation) {
            parts.push({ content: punctuation });
        }

        cursor = index + matchedUrl.length;
    }

    if (cursor < text.length) {
        parts.push({ content: text.slice(cursor) });
    }

    if (parts.length === 0) {
        return text;
    }

    return parts.map((part, index) => {
        if (!part.href) {
            return <Fragment key={index}>{part.content}</Fragment>;
        }

        const external = isExternalUrl(part.href);

        return (
            <a
                key={`${part.href}-${index}`}
                href={part.href}
                target={external ? '_blank' : undefined}
                rel={external ? 'noopener noreferrer' : undefined}
                className="font-medium text-primary underline underline-offset-2 hover:text-primary/80"
                onClick={(event: MouseEvent<HTMLAnchorElement>) => {
                    event.stopPropagation();
                    onLinkClick?.();
                }}
            >
                {part.content}
            </a>
        );
    });
}
