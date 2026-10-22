import { Paperclip, Send } from 'lucide-react';
import { useRef } from 'react';

import { Button } from '@/components/ui/button';
import { CardFooter } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

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

    return (
        <CardFooter
            className={cn(
                'shrink-0 flex-col items-stretch gap-3 pt-4',
                isMobile ? 'px-4 pb-4' : 'p-6',
            )}
        >
            <input
                key={composer.inputKey}
                ref={fileRef}
                type="file"
                accept=".pdf,.doc,.docx"
                className="hidden"
                onChange={composer.pickAttachment}
            />
            <div className="flex items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    onClick={() => fileRef.current?.click()}
                    disabled={disabled}
                >
                    <Paperclip className="size-4" />
                </Button>
                <Input
                    value={composer.message}
                    onChange={(event) =>
                        composer.setMessage(event.target.value)
                    }
                    placeholder="Tulis pesan..."
                    disabled={disabled}
                    onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            composer.send();
                        }
                    }}
                />
                <Button
                    type="button"
                    onClick={composer.send}
                    disabled={!composer.canSend}
                    className="bg-primary text-primary-foreground hover:bg-primary/90"
                >
                    <Send className="size-4" />
                </Button>
            </div>
            {composer.attachmentName && (
                <div className="truncate text-xs text-muted-foreground">
                    Lampiran: {composer.attachmentName}
                </div>
            )}
            {composer.errors.attachment && (
                <p className="text-xs text-destructive">
                    {composer.errors.attachment}
                </p>
            )}
            {composer.errors.message && (
                <p className="text-xs text-destructive">
                    {composer.errors.message}
                </p>
            )}
        </CardFooter>
    );
}
