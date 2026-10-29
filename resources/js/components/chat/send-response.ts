import { type ChatMessage, type ChatSendAcknowledgement } from './types';

function nullableString(value: unknown): boolean {
    return value === null || typeof value === 'string';
}

function nullableNumber(value: unknown): boolean {
    return value === null || typeof value === 'number';
}

function isMessage(value: unknown): value is ChatMessage {
    if (value === null || typeof value !== 'object') return false;
    const review = 'ownReview' in value ? value.ownReview : undefined;
    return (
        'id' in value &&
        typeof value.id === 'number' &&
        Number.isSafeInteger(value.id) &&
        value.id > 0 &&
        'author' in value &&
        typeof value.author === 'string' &&
        'message' in value &&
        typeof value.message === 'string' &&
        'time' in value &&
        typeof value.time === 'string' &&
        'sentAt' in value &&
        typeof value.sentAt === 'string' &&
        'type' in value &&
        typeof value.type === 'string' &&
        'authorAvatar' in value &&
        nullableString(value.authorAvatar) &&
        'authorProfileUrl' in value &&
        nullableString(value.authorProfileUrl) &&
        'documentName' in value &&
        nullableString(value.documentName) &&
        'documentUrl' in value &&
        nullableString(value.documentUrl) &&
        'previewUrl' in value &&
        nullableString(value.previewUrl) &&
        'reviewStatus' in value &&
        nullableString(value.reviewStatus) &&
        'senderUserId' in value &&
        nullableNumber(value.senderUserId) &&
        'attachmentSizeKb' in value &&
        nullableNumber(value.attachmentSizeKb) &&
        'relatedDocumentId' in value &&
        nullableNumber(value.relatedDocumentId) &&
        (!('clientMessageId' in value) ||
            typeof value.clientMessageId === 'string') &&
        'attachmentKind' in value &&
        (value.attachmentKind === null ||
            value.attachmentKind === 'image' ||
            value.attachmentKind === 'document') &&
        (!('documentEventKind' in value) ||
            value.documentEventKind === null ||
            value.documentEventKind === 'upload' ||
            value.documentEventKind === 'review') &&
        (review === undefined ||
            review === null ||
            (typeof review === 'object' &&
                'id' in review &&
                typeof review.id === 'number' &&
                'status' in review &&
                typeof review.status === 'string'))
    );
}

export function isChatSendAcknowledgement(
    value: unknown,
): value is ChatSendAcknowledgement {
    return (
        value !== null &&
        typeof value === 'object' &&
        'threadId' in value &&
        typeof value.threadId === 'number' &&
        'clientMessageId' in value &&
        typeof value.clientMessageId === 'string' &&
        'messages' in value &&
        Array.isArray(value.messages) &&
        value.messages.length > 0 &&
        value.messages.every(isMessage)
    );
}

export function chatValidationErrors(value: unknown): Record<string, string[]> {
    if (
        value === null ||
        typeof value !== 'object' ||
        !('errors' in value) ||
        value.errors === null ||
        typeof value.errors !== 'object'
    )
        return {};
    const errors: Record<string, string[]> = {};
    for (const [key, messages] of Object.entries(value.errors)) {
        if (
            Array.isArray(messages) &&
            messages.every((message: unknown) => typeof message === 'string')
        ) {
            errors[key] = messages;
        }
    }
    return errors;
}
