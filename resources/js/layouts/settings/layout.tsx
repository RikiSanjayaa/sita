import { type PropsWithChildren } from 'react';

import { cn } from '@/lib/utils';

type SettingsLayoutProps = PropsWithChildren<{
    width?: 'full' | 'compact';
}>;

export default function SettingsLayout({
    children,
    width = 'full',
}: SettingsLayoutProps) {
    return (
        <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-4 py-6 md:px-6">
            <section
                className={cn(
                    'w-full space-y-12',
                    width === 'compact' && 'max-w-xl',
                )}
            >
                {children}
            </section>
        </div>
    );
}
