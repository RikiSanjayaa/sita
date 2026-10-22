import { type UserProfileSummary } from '@/types';

export type ChatMessage = {
    id: number;
    senderUserId: number | null;
    author: string;
    authorAvatar: string | null;
    authorProfileUrl: string | null;
    message: string;
    time: string;
    type: string;
    documentName: string | null;
    documentUrl: string | null;
};

/** Fields every chat thread must carry, regardless of the portal. */
export type ChatThread = {
    id: number;
    threadType: string;
    threadLabel: string;
    members: string[];
    memberProfiles: UserProfileSummary[];
    messages: ChatMessage[];
    preview: string;
    lastTime: string;
    latestActivityAt: string | null;
};

export type ThreadMode = 'group' | 'private';

export type MobileView = 'threads' | 'chat';

/**
 * Visual flavour of the panels. `raised` is the mahasiswa look (bordered,
 * shadowed cards); `flat` is the dosen look (separator-divided panels).
 */
export type ChatTone = 'raised' | 'flat';
