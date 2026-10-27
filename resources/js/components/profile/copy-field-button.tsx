import { Check, Copy } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useClipboard } from '@/hooks/use-clipboard';

export function CopyFieldButton({
    label,
    value,
}: {
    label: string;
    value: string;
}) {
    const [copiedText, copy] = useClipboard();
    const [failed, setFailed] = useState(false);
    const copied = copiedText === value && !failed;
    const message = failed
        ? `Gagal menyalin ${label}. Pilih dan salin teks secara manual.`
        : copied
          ? `${label} disalin`
          : `Salin ${label}`;

    return (
        <>
            <Tooltip>
                <TooltipTrigger asChild>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-9 shrink-0 text-muted-foreground max-md:size-11"
                        aria-label={`Salin ${label}`}
                        onClick={async () => setFailed(!(await copy(value)))}
                    >
                        {copied ? (
                            <Check className="size-4" />
                        ) : (
                            <Copy className="size-4" />
                        )}
                    </Button>
                </TooltipTrigger>
                <TooltipContent>{message}</TooltipContent>
            </Tooltip>
            <span className="sr-only" role="status" aria-live="polite">
                {failed || copied ? message : ''}
            </span>
        </>
    );
}
