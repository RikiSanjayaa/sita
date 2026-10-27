import { FileText, X } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

import { formatFileSize } from './attachment';

type AttachmentPreviewProps = {
    /** Null when the last pick was rejected and nothing is attached. */
    file: File | null;
    /** Validation or server error for the attachment. */
    error: string | null;
    /** Object URL for image files; null for documents. */
    previewUrl: string | null;
    /** Whether the "Nama dokumen" field applies to this file. */
    showDocumentName: boolean;
    documentName: string;
    onDocumentNameChange: (value: string) => void;
    onCancel: () => void;
    onRemove: () => void;
    onReplace: () => void;
    /** True while the message is uploading; locks edits and shows progress. */
    processing: boolean;
    /** Upload percentage from Inertia, or null before the first report. */
    progress: number | null;
};

/** Staged attachment above the composer: preview, name, progress, and errors. */
export function AttachmentPreview({
    documentName,
    error,
    file,
    onCancel,
    onDocumentNameChange,
    onRemove,
    onReplace,
    previewUrl,
    processing,
    progress,
    showDocumentName,
}: AttachmentPreviewProps) {
    if (file === null) {
        return (
            <div
                role="alert"
                className="rounded-xl border border-destructive/40 bg-destructive/5 px-3 py-2 text-xs text-destructive"
            >
                {error}
            </div>
        );
    }

    const percentage = progress ?? 0;

    return (
        <div className="flex flex-col gap-3 rounded-xl border bg-card p-3">
            <div className="flex items-center gap-3">
                <div className="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-muted text-primary">
                    {previewUrl !== null ? (
                        <img
                            src={previewUrl}
                            alt=""
                            className="size-full object-cover"
                        />
                    ) : (
                        <FileText className="size-6" />
                    )}
                </div>
                <div className="min-w-0 flex-1">
                    <p
                        className="truncate text-sm font-medium"
                        title={file.name}
                    >
                        {file.name}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {formatFileSize(file.size)}
                    </p>
                </div>
                <div className="flex shrink-0 items-center gap-1">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={onReplace}
                        disabled={processing}
                    >
                        Ganti
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Hapus lampiran"
                        onClick={onRemove}
                        disabled={processing}
                    >
                        <X className="size-4" />
                    </Button>
                </div>
            </div>

            {showDocumentName && (
                <div className="grid gap-1.5">
                    <Label htmlFor="chat-document-name" className="text-xs">
                        Nama dokumen
                    </Label>
                    <Input
                        id="chat-document-name"
                        value={documentName}
                        onChange={(event) =>
                            onDocumentNameChange(event.target.value)
                        }
                        placeholder={file.name}
                        maxLength={150}
                        disabled={processing}
                    />
                    <p className="text-xs text-muted-foreground">
                        Kosongkan untuk memakai nama file. Keterangan ditulis di
                        kolom pesan.
                    </p>
                </div>
            )}

            {processing && (
                <div className="flex items-center gap-3">
                    <div
                        role="progressbar"
                        aria-label="Progres unggah"
                        aria-valuemin={0}
                        aria-valuemax={100}
                        aria-valuenow={percentage}
                        className="h-1.5 flex-1 overflow-hidden rounded-full bg-muted"
                    >
                        <div
                            className="h-full bg-primary transition-[width]"
                            style={{ width: `${percentage}%` }}
                        />
                    </div>
                    <span className="w-10 text-right text-xs text-muted-foreground tabular-nums">
                        {percentage}%
                    </span>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={onCancel}
                    >
                        Batalkan
                    </Button>
                </div>
            )}

            {error !== null && (
                <p role="alert" className="text-xs text-destructive">
                    {error}
                </p>
            )}
        </div>
    );
}
