import { type UserProfileSummary } from '@/types';

export type ChatAttachmentKind = 'image' | 'document';
export type ChatOwnReview = {
    id: number;
    status: string;
    detailUrl: string;
};

export type ChatReadSnapshot = {
    userId: number;
    activeRole: string | null;
    threadId: number;
    threadUnreadCount: number;
    unreadMessageCount: number;
    readNotificationIds: string[];
};

export type ChatReply =
    | { available: false }
    | {
          available: true;
          id: number;
          author: string;
          message: string;
          documentName: string | null;
      };

export type ChatMessage = {
    id: number;
    /** Transient send correlation, shared by HTTP acknowledgement and Echo. */
    clientMessageId?: string;
    editedAt?: string | null;
    reply?: ChatReply | null;
    quoteAvailable?: boolean;
    senderUserId: number | null;
    author: string;
    authorAvatar: string | null;
    authorProfileUrl: string | null;
    message: string;
    /** Original send instant; calendar grouping always uses Asia/Makassar. */
    sentAt: string;
    time: string;
    type: string;
    documentName: string | null;
    documentUrl: string | null;
    attachmentKind: ChatAttachmentKind | null;
    attachmentSizeKb: number | null;
    /** Inline image URL; null for documents and for other rows. */
    previewUrl: string | null;
    relatedDocumentId: number | null;
    /** MentorshipDocument.status at the time the row was built. */
    reviewStatus: string | null;
    /**
     * The viewing lecturer's own copy of the document, set only for dosen payloads built
     * for a recipient. Null in broadcasts and for everyone else.
     */
    /** For document_event rows: the student's upload announcement or a lecturer's review/status event. */
    documentEventKind?: 'upload' | 'review' | null;
    ownReview?: ChatOwnReview | null;
};

export type ChatSendAcknowledgement = {
    threadId: number;
    clientMessageId: string;
    messages: ChatMessage[];
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
    /** The thread's thesis is completed: listed under "Selesai" and read-only. */
    isCompleted?: boolean;
    isArchived?: boolean;
};

export type MobileView = 'threads' | 'chat';

/**
 * Visual flavour of the panels. `raised` is the mahasiswa look (bordered,
 * shadowed cards); `flat` is the dosen look (separator-divided panels).
 */
export type ChatTone = 'raised' | 'flat';
