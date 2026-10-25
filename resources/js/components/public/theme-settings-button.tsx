import { Settings2 } from 'lucide-react';

import { ThemePicker } from '@/components/theme-picker';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

export function ThemeSettingsButton() {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button
                    type="button"
                    size="icon"
                    variant="outline"
                    className="size-11 md:size-10"
                    aria-label="Buka pengaturan tampilan"
                >
                    <Settings2 className="size-5" />
                </Button>
            </DialogTrigger>

            <DialogContent className="top-0 left-0 h-dvh max-w-none translate-x-0 translate-y-0 rounded-none border-0 p-0 sm:top-[50%] sm:left-[50%] sm:h-auto sm:max-h-[85vh] sm:w-full sm:max-w-2xl sm:translate-x-[-50%] sm:translate-y-[-50%] sm:rounded-lg sm:border sm:p-6">
                <div className="flex h-full flex-col overflow-hidden sm:block">
                    <DialogHeader>
                        <div className="border-b px-5 pt-12 pb-4 sm:border-none sm:px-0 sm:pt-0 sm:pb-0">
                            <DialogTitle>Pengaturan Tampilan</DialogTitle>
                            <DialogDescription>
                                Ubah mode terang-gelap dan skema warna tanpa
                                mengganggu fokus halaman publik.
                            </DialogDescription>
                        </div>
                    </DialogHeader>

                    <div className="flex-1 overflow-y-auto px-5 py-5 sm:max-h-[70vh] sm:px-0 sm:py-0 sm:pt-4">
                        <ThemePicker headingLevel="h3" />
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
