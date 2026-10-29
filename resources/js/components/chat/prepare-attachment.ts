import { isImageFile } from './attachment';

// Chosen with the 3200x2400 camera/document fixture; see README chat contract.
export const IMAGE_PREPARE_THRESHOLD = 512 * 1024;
export const IMAGE_MAX_DIMENSION = 2048;
export const IMAGE_QUALITY = 0.84;

function encodedMime(header: Uint8Array): string | null {
    if (header[0] === 0xff && header[1] === 0xd8 && header[2] === 0xff)
        return 'image/jpeg';
    if (
        [137, 80, 78, 71, 13, 10, 26, 10].every(
            (byte, index) => header[index] === byte,
        )
    )
        return 'image/png';
    if (
        String.fromCharCode(...header.subarray(0, 4)) === 'RIFF' &&
        String.fromCharCode(...header.subarray(8, 12)) === 'WEBP'
    )
        return 'image/webp';
    return null;
}

function animated(bytes: Uint8Array, mime: string): boolean {
    const view = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);
    if (mime === 'image/png') {
        for (let offset = 8; offset + 12 <= bytes.length; ) {
            const size = view.getUint32(offset);
            const chunk = String.fromCharCode(
                ...bytes.subarray(offset + 4, offset + 8),
            );
            if (chunk === 'acTL') return true;
            offset += 12 + size;
        }
    }
    if (mime === 'image/webp') {
        for (let offset = 12; offset + 8 <= bytes.length; ) {
            const size = view.getUint32(offset + 4, true);
            const chunk = String.fromCharCode(
                ...bytes.subarray(offset, offset + 4),
            );
            if (chunk === 'ANIM' || chunk === 'ANMF') return true;
            offset += 8 + size + (size % 2);
        }
    }
    return false;
}

/** One file/drop/paste path. Documents, small images and animations stay byte-identical. */
export async function prepareChatAttachment(file: File): Promise<File> {
    if (!isImageFile(file) || file.size <= IMAGE_PREPARE_THRESHOLD) return file;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type))
        return file;
    const mime = encodedMime(
        new Uint8Array(await file.slice(0, 12).arrayBuffer()),
    );
    // Never flatten an unrecognized image or a PNG renamed to .jpg into JPEG.
    if (mime === null) return file;
    if (mime !== file.type)
        return new File([file], file.name, {
            type: mime,
            lastModified: file.lastModified,
        });
    if (
        file.type !== 'image/jpeg' &&
        animated(new Uint8Array(await file.arrayBuffer()), file.type)
    )
        return file;

    // Native decoding applies EXIF orientation once; canvas exports normalized pixels.
    const bitmap = await createImageBitmap(file, {
        imageOrientation: 'from-image',
    });
    try {
        const scale = Math.min(
            1,
            IMAGE_MAX_DIMENSION / Math.max(bitmap.width, bitmap.height),
        );
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(bitmap.width * scale));
        canvas.height = Math.max(1, Math.round(bitmap.height * scale));
        const context = canvas.getContext('2d');
        if (context === null) throw new Error('Canvas tidak tersedia.');
        context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        // PNG remains lossless with alpha. WebP retains alpha; no JPEG flattening.
        const blob = await new Promise<Blob>((resolve, reject) => {
            canvas.toBlob(
                (result) =>
                    result
                        ? resolve(result)
                        : reject(new Error('Gambar gagal disiapkan.')),
                file.type,
                IMAGE_QUALITY,
            );
        });
        // Unsupported encoder fallback or a larger result must never replace the source.
        if (blob.type !== file.type || blob.size >= file.size) return file;
        return new File([blob], file.name, {
            type: blob.type,
            lastModified: file.lastModified,
        });
    } finally {
        bitmap.close();
    }
}
