/** Same ceiling the server enforces (StoreChatMessageRequest: 10240 KB). */
export const CHAT_ATTACHMENT_MAX_BYTES = 10 * 1024 * 1024;

const DOCUMENT_EXTENSIONS = ['pdf', 'doc', 'docx'] as const;
const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'] as const;

const ALLOWED_EXTENSIONS: readonly string[] = [
    ...DOCUMENT_EXTENSIONS,
    ...IMAGE_EXTENSIONS,
];

export const CHAT_ATTACHMENT_ACCEPT = ALLOWED_EXTENSIONS.map(
    (extension) => `.${extension}`,
).join(',');

const TYPE_ERROR =
    'Format file tidak didukung. Gunakan PDF, DOC, DOCX, JPG, PNG, atau WEBP.';
const SIZE_ERROR = 'Ukuran file maksimal 10 MB.';

function extensionOf(fileName: string): string {
    return fileName.split('.').pop()?.toLowerCase() ?? '';
}

export function isImageFile(file: File): boolean {
    return (IMAGE_EXTENSIONS as readonly string[]).includes(
        extensionOf(file.name),
    );
}

/** Returns the message to show for a rejected file, or null when it is allowed. */
export function validateChatAttachment(file: File): string | null {
    if (!ALLOWED_EXTENSIONS.includes(extensionOf(file.name))) {
        return TYPE_ERROR;
    }

    if (file.size > CHAT_ATTACHMENT_MAX_BYTES) {
        return SIZE_ERROR;
    }

    return null;
}

export function formatFileSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${Math.round(bytes / 1024)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
