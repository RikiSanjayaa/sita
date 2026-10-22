import { Download } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';

type ImageLightboxProps = {
    /** Download URL; omitted while the image is still being sent. */
    downloadHref: string | null;
    name: string;
    onOpenChange: (open: boolean) => void;
    open: boolean;
    src: string;
};

/** Full-size view of an image attachment, opened from its thumbnail. */
export function ImageLightbox({
    downloadHref,
    name,
    onOpenChange,
    open,
    src,
}: ImageLightboxProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[90vh] max-w-[min(96vw,64rem)] flex-col gap-3 p-3 sm:p-4">
                <DialogTitle className="truncate pr-8 text-sm font-medium">
                    {name}
                </DialogTitle>
                <DialogDescription className="sr-only">
                    Pratinjau gambar lampiran
                </DialogDescription>
                <div className="flex min-h-0 flex-1 items-center justify-center overflow-hidden rounded-md bg-muted/40">
                    <img
                        src={src}
                        alt={name}
                        className="max-h-[70vh] w-auto max-w-full object-contain"
                    />
                </div>
                {downloadHref !== null && (
                    <div className="flex justify-end">
                        <Button asChild variant="outline" size="sm">
                            <a
                                href={downloadHref}
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <Download className="size-4" />
                                Unduh
                            </a>
                        </Button>
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
