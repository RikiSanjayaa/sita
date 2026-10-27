import { ArrowUp, Plus } from 'lucide-react';
import { type DragEvent, useRef, useState } from 'react';

import { Button } from '@/components/ui/button';
import { CardFooter } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

import { CHAT_ATTACHMENT_ACCEPT } from './attachment';
import { AttachmentPreview } from './attachment-preview';
import { type ChatComposerState } from './use-chat-thread';

type ComposerProps = {
    composer: ChatComposerState;
    /** Disables input and attach button (no thread selected). */
    disabled: boolean;
    isMobile: boolean;
};

/** Message input with attachment picker and send button. */
export function Composer({ composer, disabled, isMobile }: ComposerProps) {
    const fileRef = useRef<HTMLInputElement | null>(null);
    const [isDragging, setIsDragging] = useState(false);
    const canAttach = !disabled && !composer.processing;

    function handleDragOver(event: DragEvent<HTMLDivElement>) {
        if (!canAttach) {
            return;
        }

        event.preventDefault();
        setIsDragging(true);
    }

    function handleDragLeave(event: DragEvent<HTMLDivElement>) {
        if (!event.currentTarget.contains(event.relatedTarget as Node | null)) {
            setIsDragging(false);
        }
    }

    function handleDrop(event: DragEvent<HTMLDivElement>) {
        event.preventDefault();
        setIsDragging(false);

        if (canAttach) {
            composer.pickAttachment(event.dataTransfer.files.item(0));
        }
    }

    return (
        <CardFooter
            onDragLeave={handleDragLeave}
            onDragOver={handleDragOver}
            onDrop={handleDrop}
            className={cn(
                'shrink-0 flex-col items-stretch gap-2 px-3 py-2.5 transition-colors',
                isMobile && 'px-2',
                isDragging && 'bg-primary/5 ring-2 ring-primary/40 ring-inset',
            )}
        >
            <input
                key={composer.inputKey}
                ref={fileRef}
                type="file"
                accept={CHAT_ATTACHMENT_ACCEPT}
                className="hidden"
                onChange={(event) =>
                    composer.pickAttachment(event.target.files?.item(0) ?? null)
                }
            />
            <div className="flex items-center gap-2">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="Lampirkan file"
                    className="shrink-0 rounded-full bg-muted max-md:size-11"
                    onClick={() => fileRef.current?.click()}
                    disabled={!canAttach}
                >
                    <Plus className="size-4" />
                </Button>
                <Input
                    value={composer.message}
                    onChange={(event) =>
                        composer.setMessage(event.target.value)
                    }
                    placeholder="Tulis pesan..."
                    className="min-w-0 rounded-full border-transparent bg-muted shadow-none max-md:h-11"
                    disabled={disabled}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            composer.send();
                        }
                    }}
                    onPaste={(event) => {
                        const file = event.clipboardData.files.item(0);

                        if (file !== null && canAttach) {
                            event.preventDefault();
                            composer.pickAttachment(file);
                        }
                    }}
                />
                <Button
                    type="button"
                    aria-label="Kirim pesan"
                    size="icon"
                    onClick={composer.send}
                    disabled={!composer.canSend}
                    className="shrink-0 rounded-full bg-primary text-primary-foreground hover:bg-primary/90 disabled:bg-muted disabled:text-muted-foreground disabled:opacity-100 max-md:size-11"
                >
                    <ArrowUp className="size-4" />
                </Button>
            </div>
            {(composer.attachment !== null || composer.errors.attachment) && (
                <AttachmentPreview
                    documentName={composer.documentName}
                    error={composer.errors.attachment}
                    file={composer.attachment}
                    onCancel={composer.cancelSend}
                    onDocumentNameChange={composer.setDocumentName}
                    onRemove={composer.removeAttachment}
                    onReplace={() => fileRef.current?.click()}
                    previewUrl={composer.previewUrl}
                    processing={composer.processing}
                    progress={composer.progress}
                    showDocumentName={composer.showDocumentName}
                />
            )}
            {composer.errors.message && (
                <p className="text-xs text-destructive">
                    {composer.errors.message}
                </p>
            )}
        </CardFooter>
    );
}
