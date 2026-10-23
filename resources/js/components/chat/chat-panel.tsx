import { Link } from '@inertiajs/react';
import { ArrowLeft, ChevronDown, ChevronUp, Search } from 'lucide-react';
import { type ReactNode } from 'react';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';
import { type UserProfileSummary } from '@/types';

import { chatStyles } from './styles';
import { type ChatThread, type ChatTone, type MobileView } from './types';
import { type MessageSearchState } from './use-message-search';

type ChatPanelProps = {
    /** Message list; renders its own scroll area. */
    children: ReactNode;
    /** Title shown while no thread is selected. */
    emptyTitle: string;
    /** Composer or a read-only notice; rendered under a separator. */
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
    tone: ChatTone;
};

function ProfileAvatar({ profile }: { profile: UserProfileSummary }) {
    const getInitials = useInitials();

    return (
        <Avatar className="size-10 border-2 border-background bg-background shadow-xs">
            <AvatarImage src={profile.avatar ?? undefined} alt={profile.name} />
            <AvatarFallback className="bg-primary-muted text-xs text-primary-muted-foreground">
                {getInitials(profile.name)}
            </AvatarFallback>
        </Avatar>
    );
}

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
    tone,
}: ChatPanelProps) {
    const isFlat = tone === 'flat';

    return (
        <Card
            className={cn(
                chatStyles.chatCard(tone, isMobile),
                mobileView === 'threads' && 'hidden lg:flex',
            )}
        >
            <CardHeader className={chatStyles.chatHeader(tone, isMobile)}>
                {thread ? (
                    <>
                        <div className="flex min-w-0 items-center gap-4">
                            <div className="flex min-w-0 flex-1 items-center gap-3 sm:gap-4">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Kembali ke daftar percakapan"
                                    className="-ml-2 shrink-0 max-md:size-11 lg:hidden"
                                    onClick={onBack}
                                >
                                    <ArrowLeft className="size-5" />
                                </Button>

                                {thread.threadType === 'private' &&
                                privateTarget ? (
                                    <Link
                                        href={privateTarget.profileUrl}
                                        className="hidden shrink-0 sm:block"
                                    >
                                        <ProfileAvatar
                                            profile={privateTarget}
                                        />
                                    </Link>
                                ) : thread.memberProfiles.length > 0 ? (
                                    <TooltipProvider delayDuration={0}>
                                        <div className="hidden shrink-0 items-center -space-x-3 sm:flex">
                                            {thread.memberProfiles.map(
                                                (member) => (
                                                    <Tooltip key={member.id}>
                                                        <TooltipTrigger asChild>
                                                            <Link
                                                                href={
                                                                    member.profileUrl
                                                                }
                                                                className="relative z-0 transition-transform hover:z-10 hover:scale-110"
                                                            >
                                                                <ProfileAvatar
                                                                    profile={
                                                                        member
                                                                    }
                                                                />
                                                            </Link>
                                                        </TooltipTrigger>
                                                        <TooltipContent className="px-3 py-1.5 text-xs">
                                                            <p className="font-semibold">
                                                                {member.name}
                                                            </p>
                                                            {member.subtitle && (
                                                                <p className="opacity-80">
                                                                    {
                                                                        member.subtitle
                                                                    }
                                                                </p>
                                                            )}
                                                        </TooltipContent>
                                                    </Tooltip>
                                                ),
                                            )}
                                        </div>
                                    </TooltipProvider>
                                ) : null}

                                <div className="min-w-0 flex-1 overflow-hidden">
                                    <div className="flex min-w-0 items-center gap-2">
                                        <CardTitle className="min-w-0 truncate">
                                            {thread.threadType === 'private' &&
                                            privateTarget ? (
                                                <Link
                                                    href={
                                                        privateTarget.profileUrl
                                                    }
                                                    className="transition hover:text-primary"
                                                >
                                                    {privateTarget.name}
                                                </Link>
                                            ) : titleProfile ? (
                                                <Link
                                                    href={
                                                        titleProfile.profileUrl
                                                    }
                                                    className="transition hover:text-primary"
                                                >
                                                    {titleProfile.name}
                                                </Link>
                                            ) : (
                                                titleText
                                            )}
                                        </CardTitle>
                                        <Badge
                                            variant="soft"
                                            className={cn(
                                                'shrink-0 font-medium',
                                                thread.threadType ===
                                                    'pembimbing'
                                                    ? 'bg-primary-muted text-primary-muted-foreground'
                                                    : 'bg-warning-muted text-warning-muted-foreground',
                                            )}
                                        >
                                            {thread.threadLabel}
                                        </Badge>
                                    </div>
                                    <CardDescription className="truncate">
                                        {thread.threadType === 'private' &&
                                        privateTarget
                                            ? privateTarget.email
                                            : thread.members.join(', ')}
                                    </CardDescription>
                                </div>
                            </div>

                            <div className="flex shrink-0 items-center gap-1">
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label="Cari di percakapan ini"
                                    className="size-9 text-muted-foreground hover:text-foreground max-md:size-11"
                                    onClick={search.toggle}
                                >
                                    <Search className="size-4" />
                                </Button>
                            </div>
                        </div>
                        {search.isOpen ? (
                            <div className="mt-3 space-y-2">
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
                    <div className="flex items-center gap-2">
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
            {isFlat ? <Separator /> : null}

            {children}

            <Separator />

            {footer}
        </Card>
    );
}
