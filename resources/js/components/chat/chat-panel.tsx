import { Link } from '@inertiajs/react';
import { ArrowLeft, ChevronDown, ChevronUp, Search } from 'lucide-react';
import { type ReactNode } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { type UserProfileSummary } from '@/types';

import { chatStyles } from './styles';
import { ThreadAvatar } from './thread-avatar';
import { type ChatThread, type MobileView } from './types';
import { type MessageSearchState } from './use-message-search';

type ChatPanelProps = {
    /** Message list; renders its own scroll area. */
    children: ReactNode;
    /** Title shown while no thread is selected. */
    emptyTitle: string;
    /** Composer or read-only notice beneath the history. */
    footer: ReactNode;
    isMobile: boolean;
    mobileView: MobileView;
    onBack: () => void;
    /** Other participant of a private thread, when the thread is private. */
    privateTarget: UserProfileSummary | null;
    search: MessageSearchState;
    thread: ChatThread | null;
    /** Profile linked from the title of a group thread, if any. */
    titleProfile: UserProfileSummary | null;
    /** Title of a group thread without a linked profile. */
    titleText: string;
};

/** Chat column: thread header with in-thread search, body and footer slot. */
export function ChatPanel({
    children,
    emptyTitle,
    footer,
    isMobile,
    mobileView,
    onBack,
    privateTarget,
    search,
    thread,
    titleProfile,
    titleText,
}: ChatPanelProps) {
    const profile = privateTarget ?? titleProfile;
    return (
        <Card
            className={cn(
                chatStyles.chatCard(isMobile),
                mobileView === 'threads' && 'hidden lg:flex',
            )}
        >
            <CardHeader className={chatStyles.chatHeader(isMobile)}>
                {thread ? (
                    <>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label="Kembali ke daftar percakapan"
                            className="pointer-events-auto absolute top-3 left-2 shrink-0 max-md:size-11 lg:hidden"
                            onClick={onBack}
                        >
                            <ArrowLeft className="size-5" />
                        </Button>
                        <div className="mx-auto flex max-w-[calc(100%-7rem)] flex-col items-center gap-1.5 text-center">
                            {profile ? (
                                <Link
                                    href={profile.profileUrl}
                                    className="pointer-events-auto rounded-full focus-visible:ring-2 focus-visible:ring-ring"
                                    aria-label={`Profil ${profile.name}`}
                                >
                                    <ThreadAvatar thread={thread} header />
                                </Link>
                            ) : (
                                <ThreadAvatar thread={thread} header />
                            )}
                            <div className="pointer-events-auto flex max-w-full items-center justify-center gap-2">
                                <CardTitle
                                    className="min-w-0 truncate text-sm leading-5"
                                    title={profile?.name ?? titleText}
                                >
                                    {profile ? (
                                        <Link
                                            href={profile.profileUrl}
                                            className="hover:text-primary"
                                        >
                                            {profile.name}
                                        </Link>
                                    ) : (
                                        titleText
                                    )}
                                </CardTitle>
                                <Badge
                                    variant="soft"
                                    className={cn(
                                        'shrink-0 font-medium',
                                        thread.threadType === 'pembimbing'
                                            ? 'bg-primary-muted text-primary-muted-foreground'
                                            : 'bg-muted text-muted-foreground',
                                    )}
                                >
                                    {thread.threadLabel}
                                </Badge>
                            </div>
                            <CardDescription className="sr-only">
                                {privateTarget?.email ??
                                    thread.members.join(', ')}
                            </CardDescription>
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label="Cari di percakapan ini"
                            aria-expanded={search.isOpen}
                            className="pointer-events-auto absolute top-3 right-2 size-9 text-muted-foreground hover:text-foreground max-md:size-11"
                            onClick={search.toggle}
                        >
                            <Search className="size-4" />
                        </Button>
                        {search.isOpen ? (
                            <div className="pointer-events-auto mt-3 space-y-2 rounded-lg bg-[var(--chat-surface)]">
                                <div className="flex items-center gap-2">
                                    <Input
                                        value={search.query}
                                        onChange={(event) =>
                                            search.changeQuery(
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Cari di percakapan ini..."
                                        className="h-9 flex-1 max-md:h-11"
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        aria-label="Hasil sebelumnya"
                                        className="size-9 max-md:size-11"
                                        disabled={search.matchCount === 0}
                                        onClick={() => search.step(-1)}
                                    >
                                        <ChevronUp className="size-4" />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        aria-label="Hasil berikutnya"
                                        className="size-9 max-md:size-11"
                                        disabled={search.matchCount === 0}
                                        onClick={() => search.step(1)}
                                    >
                                        <ChevronDown className="size-4" />
                                    </Button>
                                </div>
                                {search.query.trim() ? (
                                    <p className="text-xs text-muted-foreground">
                                        {search.matchCount > 0
                                            ? `${search.activeMatchIndex + 1} dari ${search.matchCount} hasil`
                                            : 'Tidak ada hasil yang cocok'}
                                    </p>
                                ) : null}
                            </div>
                        ) : null}
                    </>
                ) : (
                    <div className="pointer-events-auto flex items-center gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label="Kembali ke daftar percakapan"
                            className="-ml-2 max-md:size-11 lg:hidden"
                            onClick={onBack}
                        >
                            <ArrowLeft className="size-5" />
                        </Button>
                        <CardTitle>{emptyTitle}</CardTitle>
                    </div>
                )}
            </CardHeader>

            {children}

            {footer}
        </Card>
    );
}
