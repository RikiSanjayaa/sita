import { Head, router, usePage } from '@inertiajs/react';
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
    activeFirst,
    buildThreadStateKey,
    privateTargetProfile,
    resolveInitialMobileView,
    sortThreads,
    syncThreadSearchParam,
} from '@/components/chat/utils';
import { FilterPills } from '@/components/patterns/filter-pills';
import { MentorshipChatFrame } from '@/components/mentorship-chat-layout';
import { type PrivateRecipientOption } from '@/components/private-recipient-combobox';
import { useIsMobile } from '@/hooks/use-mobile';
import { useUrlState } from '@/hooks/use-url-state';
import DosenLayout from '@/layouts/dosen-layout';
import { neutralAcademicTerminology as n } from '@/lib/academic-terminology';
import { read as markAsRead } from '@/routes/dosen/pesan';

import {
    type BreadcrumbItem,
    type SharedData,
    type UserProfileSummary,
} from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dosen/dashboard' },
    { title: 'Pesan', href: '/dosen/pesan' },
];

const clearedUrlParams = ['tab', 'filter'] as const;

type ThreadItem = ChatThread & {
    student: string;
    studentProfile: UserProfileSummary | null;
    unread: number;
    isEscalated: boolean;
    isArchived: boolean;
};

type GroupThreadFilter = 'semua' | 'pembimbing' | 'sempro' | 'sidang';

const groupThreadFilterTabs: { label: string; value: GroupThreadFilter }[] = [
    { label: 'Semua', value: 'semua' },
    { label: 'Bimbingan', value: 'pembimbing' },
    { label: n.proposalExamShort, value: 'sempro' },
    { label: n.finalExam, value: 'sidang' },
];

type PesanBimbinganProps = {
    threads: ThreadItem[];
    privateRecipients: PrivateRecipientOption[];
};

type PesanBimbinganContentProps = Pick<SharedData, 'auth'> & {
    initialThreads: ThreadItem[];
    privateRecipients: PrivateRecipientOption[];
};

function getVisibleThreads(
    threads: ThreadItem[],
    search: string,
    mode: ThreadMode,
    groupFilter: GroupThreadFilter,
): ThreadItem[] {
    return activeFirst(sortThreads(threads)).filter((thread) => {
        const matchesFilter =
            mode === 'private'
                ? thread.threadType === 'private'
                : thread.threadType !== 'private' &&
                  (groupFilter === 'semua' ||
                      thread.threadType === groupFilter);

        return (
            matchesFilter &&
            thread.student.toLowerCase().includes(search.trim().toLowerCase())
        );
    });
}

function threadBadgeClassName(thread: ThreadItem): string {
    if (thread.threadType === 'pembimbing') {
        return 'bg-primary-muted text-primary-muted-foreground hover:bg-primary-muted/70';
    }

    return thread.threadType === 'private'
        ? 'bg-success-muted text-success-muted-foreground hover:bg-success-muted/70'
        : 'bg-muted text-muted-foreground hover:bg-muted/80';
}

export default function DosenPesanBimbinganPage() {
    const {
        threads: initialThreads,
        privateRecipients,
        auth,
    } = usePage<SharedData & PesanBimbinganProps>().props;

    return (
        <DosenPesanBimbinganContent
            key={buildThreadStateKey(initialThreads)}
            initialThreads={initialThreads}
            privateRecipients={privateRecipients}
            auth={auth}
        />
    );
}

function DosenPesanBimbinganContent({
    initialThreads,
    privateRecipients,
    auth,
}: PesanBimbinganContentProps) {
    const requestedRecipientId =
        typeof window === 'undefined'
            ? null
            : new URLSearchParams(window.location.search).get('recipient');
    const initialRecipientId = privateRecipients.some(
        (recipient) => String(recipient.id) === requestedRecipientId,
    )
        ? (requestedRecipientId ?? '')
        : '';
    const isMobile = useIsMobile();

    const [mobileView, setMobileView] = useState<MobileView>(() =>
        initialRecipientId !== ''
            ? 'threads'
            : resolveInitialMobileView(initialThreads),
    );
    const [search, setSearch] = useUrlState('search', '');
    const [threadMode, setThreadMode] = useUrlState<ThreadMode>(
        'mode',
        typeof window !== 'undefined' &&
            new URLSearchParams(window.location.search).get('filter') ===
                'private'
            ? 'private'
            : 'group',
    );
    const [groupThreadFilter, setGroupThreadFilter] =
        useUrlState<GroupThreadFilter>('filter', 'semua');
    const [isGroupFilterOpen, setIsGroupFilterOpen] = useState(false);

    const privateThread = usePrivateThread(
        '/dosen/pesan/private',
        initialRecipientId,
    );

    const selectVisible = useCallback(
        (threads: ThreadItem[]) =>
            getVisibleThreads(threads, search, threadMode, groupThreadFilter),
        [groupThreadFilter, search, threadMode],
    );

    const chat = useChatThread<ThreadItem>({
        initialThreads,
        userId: auth.user?.id,
        selectVisible,
        sendUrl: (threadId) => `/dosen/pesan/${threadId}/messages`,
        readUrl: (threadId) => markAsRead(threadId).url,
        isChatVisible: !isMobile || mobileView === 'chat',
        clearParams: clearedUrlParams,
        onDocumentEvent: () => router.reload({ only: ['threads'] }),
        onSent: () => setMobileView('chat'),
    });
    const messageSearch = useMessageSearch(chat.activeMessages);

    const { activeThread } = chat;
    const activePrivateTarget = privateTargetProfile(
        activeThread,
        auth.user?.id,
        activeThread?.studentProfile,
    );

    function selectThread(threadId: number) {
        chat.setActiveThreadId(threadId);
        messageSearch.reset();
        syncThreadSearchParam(threadId, clearedUrlParams);
        setMobileView('chat');
    }

    /** Keeps the requested thread if still listed, else moves to the first. */
    function reconcileActiveThread(nextVisibleThreads: ThreadItem[]) {
        const nextActiveThreadId = nextVisibleThreads.some(
            (thread) => thread.id === chat.requestedThreadId,
        )
            ? chat.requestedThreadId
            : (nextVisibleThreads[0]?.id ?? null);

        if (nextActiveThreadId === chat.requestedThreadId) {
            return;
        }

        chat.setActiveThreadId(nextActiveThreadId);
        messageSearch.reset();
        syncThreadSearchParam(nextActiveThreadId, clearedUrlParams);
    }

    function handleSearchChange(nextSearch: string) {
        setSearch(nextSearch);
        reconcileActiveThread(
            getVisibleThreads(
                chat.threadItems,
                nextSearch,
                threadMode,
                groupThreadFilter,
            ),
        );
    }

    function changeThreadMode(nextMode: ThreadMode) {
        setThreadMode(nextMode);
        setIsGroupFilterOpen(false);
        privateThread.close();
        reconcileActiveThread(
            getVisibleThreads(
                chat.threadItems,
                search,
                nextMode,
                groupThreadFilter,
            ),
        );
    }

    function handleGroupThreadFilterChange(nextFilter: GroupThreadFilter) {
        setGroupThreadFilter(nextFilter);
        reconcileActiveThread(
            getVisibleThreads(chat.threadItems, search, threadMode, nextFilter),
        );
    }

    return (
        <DosenLayout
            breadcrumbs={breadcrumbs}
            title="Pesan"
            subtitle="Percakapan akademik"
        >
            <Head title="Pesan Dosen" />

            <h1 className="sr-only">Pesan</h1>
            <MentorshipChatFrame isMobile={isMobile}>
                <ThreadList
                    activeThreadId={activeThread?.id ?? null}
                    badgeClassName={threadBadgeClassName}
                    emptyState={
                        threadMode === 'private'
                            ? {
                                  title: 'Belum ada chat pribadi',
                                  description:
                                      'Mulai dari tombol tambah kontak.',
                              }
                            : {
                                  title: 'Tidak ada grup yang sesuai',
                                  description:
                                      'Cek pencarian atau filter grup.',
                              }
                    }
                    groupExtra={
                        isGroupFilterOpen ? (
                            <FilterPills
                                label="Filter jenis grup"
                                value={groupThreadFilter}
                                onValueChange={handleGroupThreadFilterChange}
                                options={groupThreadFilterTabs}
                                itemClassName="max-md:h-11 max-md:px-4"
                            />
                        ) : null
                    }
                    groupFilterToggle={() =>
                        setIsGroupFilterOpen((current) => !current)
                    }
                    hasUnread={(thread) => thread.unread > 0}
                    isMobile={isMobile}
                    messagesFor={chat.messagesFor}
                    mobileView={mobileView}
                    mode={threadMode}
                    onModeChange={changeThreadMode}
                    onSearchChange={handleSearchChange}
                    onSelect={selectThread}
                    privateRecipients={privateRecipients}
                    privateThread={privateThread}
                    search={search}
                    threadTitle={(thread) => thread.student}
                    threads={chat.visibleThreads}
                    title="Ruang Chat"
                    tone="flat"
                />

                <ChatPanel
                    emptyTitle="Pilih Grup Bimbingan"
                    footer={
                        activeThread?.isCompleted ? (
                            <div className="shrink-0 p-4 text-center">
                                <p className="text-sm font-medium text-muted-foreground">
                                    {n.finalWork} sudah selesai. Percakapan ini
                                    hanya dapat dibaca dan dihapus setelah masa
                                    arsip.
                                </p>
                            </div>
                        ) : activeThread?.isArchived ? (
                            <div className="shrink-0 p-4 text-center">
                                <p className="text-sm font-medium text-muted-foreground">
                                    Sesi bimbingan telah selesai. Percakapan ini
                                    diarsipkan dan tidak dapat diperbarui.
                                </p>
                            </div>
                        ) : (
                            <Composer
                                composer={chat.composer}
                                disabled={activeThread === null}
                                isMobile={isMobile}
                            />
                        )
                    }
                    isMobile={isMobile}
                    mobileView={mobileView}
                    onBack={() => setMobileView('threads')}
                    privateTarget={activePrivateTarget}
                    search={messageSearch}
                    thread={activeThread}
                    titleProfile={activeThread?.studentProfile ?? null}
                    titleText={activeThread?.student ?? ''}
                    tone="flat"
                >
                    <MessageList
                        isMobile={isMobile}
                        messages={chat.activeMessages}
                        search={messageSearch}
                        threadId={activeThread?.id ?? null}
                        tone="flat"
                        userId={auth.user?.id}
                    />
                </ChatPanel>
            </MentorshipChatFrame>
        </DosenLayout>
    );
}
