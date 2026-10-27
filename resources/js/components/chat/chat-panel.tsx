import { Link } from '@inertiajs/react';
import { ArrowLeft, ChevronDown, ChevronUp, Search } from 'lucide-react';
import { type ReactNode } from 'react';

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
import { ThreadMembers } from './thread-members';
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
                            className="pointer-events-auto absolute top-2 left-2 shrink-0 max-md:size-11 lg:hidden"
                            onClick={onBack}
                        >
                            <ArrowLeft className="size-5" />
                        </Button>
                        <div className="pointer-events-auto mx-auto flex w-fit max-w-[calc(100%-6rem)] items-center gap-2 rounded-full bg-card pr-3 text-left text-card-foreground">
                            <ThreadMembers key={thread.id} thread={thread} />
                            <div className="flex min-h-11 max-w-full min-w-0 flex-col items-start justify-center py-1">
                                <CardTitle
                                    className="max-w-full min-w-0 truncate text-sm leading-5"
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
                                {thread.threadLabel !==
                                    (profile?.name ?? titleText) && (
                                    <span className="max-w-full truncate text-xs leading-4 text-muted-foreground">
                                        {thread.threadLabel}
                                    </span>
                                )}
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
                            className="pointer-events-auto absolute top-2 right-2 size-9 text-muted-foreground hover:text-foreground max-md:size-11"
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
                                        className="h-9 flex-1 bg-card max-md:h-11 dark:bg-card"
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
