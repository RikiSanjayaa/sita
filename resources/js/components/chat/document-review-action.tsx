import { Link } from '@inertiajs/react';
import { ClipboardCheck } from 'lucide-react';

import { type ChatOwnReview } from '@/components/chat/types';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

type DocumentReviewActionProps = {
    /** Classes for the icon-only trigger, so it matches its neighbours. */
    className: string;
    document: ChatOwnReview;
    documentName: string;
};

export function DocumentReviewAction({
    className,
    document,
    documentName,
}: DocumentReviewActionProps) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Link
                    href={document.detailUrl}
                    className={className}
                    aria-label={`Tinjau ${documentName}`}
                >
                    <ClipboardCheck aria-hidden="true" className="size-4" />
                </Link>
            </TooltipTrigger>
            <TooltipContent>Tinjau dokumen</TooltipContent>
        </Tooltip>
    );
}
