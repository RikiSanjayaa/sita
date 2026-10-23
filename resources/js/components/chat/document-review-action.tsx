import { useForm } from '@inertiajs/react';
import { ClipboardCheck } from 'lucide-react';
import { useState, type FormEvent } from 'react';

import { type ChatOwnReview } from '@/components/chat/types';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { review } from '@/routes/dosen/dokumen-revisi';

type DocumentReviewActionProps = {
    /** Classes for the icon-only trigger, so it matches its neighbours. */
    className: string;
    document: ChatOwnReview;
    documentName: string;
};

type ReviewFormData = {
    status: 'needs_revision' | 'approved';
    revision_notes: string;
};

/**
 * Lets the lecturer who received a chat upload review their own copy.
 * Posts to the same endpoint as Dokumen & Revisi, so both screens stay in sync.
 */
export function DocumentReviewAction({
    className,
    document,
    documentName,
}: DocumentReviewActionProps) {
    const [isOpen, setIsOpen] = useState(false);
    const form = useForm<ReviewFormData>({
        status: 'needs_revision',
        revision_notes: '',
    });

    function closeDialog(): void {
        setIsOpen(false);
        form.reset();
        form.clearErrors();
    }

    function submitApprove(): void {
        form.transform((data) => ({
            ...data,
            status: 'approved',
            revision_notes: '',
        }));
        form.post(review.url(document.id), {
            preserveScroll: true,
            onSuccess: closeDialog,
        });
    }

    function submitRevision(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            status: 'needs_revision',
            revision_notes: data.revision_notes.trim(),
        }));
        form.post(review.url(document.id), {
            preserveScroll: true,
            onSuccess: closeDialog,
        });
    }

    return (
        <>
            <Tooltip>
                <TooltipTrigger asChild>
                    <button
                        type="button"
                        className={className}
                        onClick={() => setIsOpen(true)}
                        aria-label={`Tinjau ${documentName}`}
                    >
                        <ClipboardCheck className="size-4" />
                    </button>
                </TooltipTrigger>
                <TooltipContent>Tinjau dokumen</TooltipContent>
            </Tooltip>

            <Dialog
                open={isOpen}
                onOpenChange={(open) =>
                    open ? setIsOpen(true) : closeDialog()
                }
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Kirim Catatan Revisi</DialogTitle>
                        <DialogDescription>
                            Tulis detail revisi yang perlu diperbaiki oleh
                            mahasiswa untuk lampiran{' '}
                            <span className="font-medium text-foreground">
                                {documentName}
                            </span>
                            .
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submitRevision} className="grid gap-3">
                        <div className="grid gap-2">
                            <Label
                                htmlFor={`chat-revision-notes-${document.id}`}
                            >
                                Catatan Revisi
                            </Label>
                            <Textarea
                                id={`chat-revision-notes-${document.id}`}
                                value={form.data.revision_notes}
                                onChange={(event) =>
                                    form.setData(
                                        'revision_notes',
                                        event.target.value,
                                    )
                                }
                                rows={4}
                                placeholder="Contoh: perbaiki sitasi pada bab 1"
                            />
                            {form.errors.revision_notes && (
                                <p className="text-sm text-destructive">
                                    {form.errors.revision_notes}
                                </p>
                            )}
                        </div>

                        {form.errors.status && (
                            <p className="text-sm text-destructive">
                                {form.errors.status}
                            </p>
                        )}

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={form.processing}
                                onClick={submitApprove}
                            >
                                Setujui Dokumen
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing
                                    ? 'Menyimpan...'
                                    : 'Kirim Catatan'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
