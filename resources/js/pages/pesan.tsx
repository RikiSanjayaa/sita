import { Head, usePage } from '@inertiajs/react';
import { useCallback, useState } from 'react';

import { ChatPanel } from '@/components/chat/chat-panel';
import { Composer } from '@/components/chat/composer';
import { MessageList } from '@/components/chat/message-list';
import { ThreadList } from '@/components/chat/thread-list';
import {
    type ChatThread,
    type MobileView,
    type ThreadMode,
} from '@/components/chat/types';
import { useChatThread } from '@/components/chat/use-chat-thread';
import { useMessageSearch } from '@/components/chat/use-message-search';
import { usePrivateThread } from '@/components/chat/use-private-thread';
import {
    buildThreadStateKey,
    privateTargetProfile,
    resolveInitialMobileView,
    syncThreadSearchParam,
} from '@/components/chat/utils';
import { MentorshipChatFrame } from '@/components/mentorship-chat-layout';
import { type PrivateRecipientOption } from '@/components/private-recipient-combobox';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { useIsMobile } from '@/hooks/use-mobile';
import AppLayout from '@/layouts/app-layout';
import { dashboard, pesan } from '@/routes';
import { type BreadcrumbItem, type SharedData } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Pesan', href: pesan().url },
];

type ThreadItem = ChatThread & {
    name: string;
};

type PesanPageProps = {
    hasDosbing: boolean;
    threads: ThreadItem[];
    privateRecipients: PrivateRecipientOption[];
};

type PesanPageContentProps = Pick<SharedData, 'auth'> & {
    hasDosbing: boolean;
    initialThreads: ThreadItem[];
    privateRecipients: PrivateRecipientOption[];
};

function threadBadgeClassName(thread: ThreadItem): string {
    return thread.threadType === 'pembimbing'
        ? 'bg-primary-muted text-primary-muted-foreground'
        : 'bg-warning-muted text-warning-muted-foreground';
}

function initialThreadMode(threads: ThreadItem[]): ThreadMode {
    const queryThread =
        typeof window === 'undefined'
            ? null
            : Number(new URLSearchParams(window.location.search).get('thread'));

    return threads.find((thread) => thread.id === queryThread)?.threadType ===
        'private'
        ? 'private'
        : 'group';
}

export default function PesanPage() {
    const {
        threads: initialThreads,
        hasDosbing,
        privateRecipients,
        auth,
    } = usePage<SharedData & PesanPageProps>().props;

    return (
        <PesanPageContent
            key={buildThreadStateKey(initialThreads)}
            initialThreads={initialThreads}
            hasDosbing={hasDosbing}
            privateRecipients={privateRecipients}
            auth={auth}
        />
    );
}

function PesanPageContent({
    initialThreads,
    hasDosbing,
    privateRecipients,
    auth,
}: PesanPageContentProps) {
    const isMobile = useIsMobile();
    const { mentorshipTicket } = usePage<SharedData>().props;

    const [mobileView, setMobileView] = useState<MobileView>(() =>
        resolveInitialMobileView(initialThreads),
    );
    const [threadMode, setThreadMode] = useState<ThreadMode>(() =>
        initialThreadMode(initialThreads),
    );
    const [threadListSearch, setThreadListSearch] = useState('');

    const privateThread = usePrivateThread('/mahasiswa/pesan/private');

    const selectVisible = useCallback(
        (threads: ThreadItem[]) =>
            threads
                .filter((thread) =>
                    threadMode === 'private'
                        ? thread.threadType === 'private'
                        : thread.threadType !== 'private',
                )
                .filter((thread) =>
                    thread.name
                        .toLowerCase()
                        .includes(threadListSearch.trim().toLowerCase()),
                ),
        [threadListSearch, threadMode],
    );

    const chat = useChatThread<ThreadItem>({
        initialThreads,
        userId: auth.user?.id,
        supportsDocumentName: (thread) => thread.threadType !== 'private',
        selectVisible,
        sendUrl: (threadId) => `/mahasiswa/pesan/${threadId}/messages`,
        onSent: () => setMobileView('chat'),
    });
    const messageSearch = useMessageSearch(chat.activeMessages);

    const { activeThread } = chat;
    const activePrivateTarget = privateTargetProfile(
        activeThread,
        auth.user?.id,
    );

    function selectThread(threadId: number) {
        chat.setActiveThreadId(threadId);
        messageSearch.reset();
        syncThreadSearchParam(threadId);
        setMobileView('chat');
    }

    function changeThreadMode(nextMode: ThreadMode) {
        const nextThread = chat.threadItems.find((thread) =>
            nextMode === 'private'
                ? thread.threadType === 'private'
                : thread.threadType !== 'private',
        );

        setThreadMode(nextMode);
        privateThread.close();
        chat.setActiveThreadId(nextThread?.id ?? null);
        syncThreadSearchParam(nextThread?.id ?? null);
        setMobileView('threads');
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pesan" />

            <h1 className="sr-only">Pesan</h1>

            <MentorshipChatFrame isMobile={isMobile}>
                <ThreadList
                    activeThreadId={activeThread?.id ?? null}
                    badgeClassName={threadBadgeClassName}
                    emptyState={{
                        title: 'Belum ada percakapan',
                        description:
                            threadMode === 'private'
                                ? 'Mulai dari tombol tambah kontak.'
                                : 'Grup akan tampil setelah dosen pembimbing atau penguji ditetapkan.',
                    }}
                    isMobile={isMobile}
                    messagesFor={chat.messagesFor}
                    mobileView={mobileView}
                    mode={threadMode}
                    onModeChange={changeThreadMode}
                    onSearchChange={setThreadListSearch}
                    onSelect={selectThread}
                    privateRecipients={privateRecipients}
                    privateThread={privateThread}
                    search={threadListSearch}
                    threadTitle={(thread) => thread.name}
                    threads={chat.visibleThreads}
                    title="Pesan"
                    tone="raised"
                />

                <ChatPanel
                    emptyTitle="Pilih Percakapan"
                    footer={
                        <Composer
                            composer={chat.composer}
                            disabled={
                                activeThread === null ||
                                mentorshipTicket?.suspended === true
                            }
                            isMobile={isMobile}
                        />
                    }
                    isMobile={isMobile}
                    mobileView={mobileView}
                    onBack={() => setMobileView('threads')}
                    privateTarget={activePrivateTarget}
                    search={messageSearch}
                    thread={activeThread}
                    titleProfile={null}
                    titleText={activeThread?.name ?? ''}
                    tone="raised"
                >
                    <MessageList
                        isMobile={isMobile}
                        messages={chat.activeMessages}
                        notice={
                            !hasDosbing &&
                            activeThread?.threadType === 'pembimbing' ? (
                                <Alert className="mb-3 border-danger/30 bg-danger-muted text-danger-muted-foreground">
                                    <AlertTitle className="text-danger-muted-foreground">
                                        Belum ada Dosen Pembimbing
                                    </AlertTitle>
                                    <AlertDescription className="text-danger-muted-foreground">
                                        Anda belum memiliki dosen pembimbing
                                        aktif. Pesan akan tersedia setelah dosen
                                        pembimbing ditetapkan.
                                    </AlertDescription>
                                </Alert>
                            ) : null
                        }
                        search={messageSearch}
                        threadId={activeThread?.id ?? null}
                        tone="raised"
                        userId={auth.user?.id}
                    />
                </ChatPanel>
            </MentorshipChatFrame>
        </AppLayout>
    );
}
