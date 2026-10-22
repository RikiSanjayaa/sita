import { TriangleAlert } from 'lucide-react';

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export type MentorshipProgressRow = {
    lecturer: {
        id: number;
        name: string;
        role: string;
    };
    completed: number;
    minimum: number;
    met: boolean;
};

export function MentorshipProgressList({
    progress,
    className,
}: {
    progress: MentorshipProgressRow[];
    className?: string;
}) {
    if (progress.length === 0) {
        return null;
    }

    return (
        <ul
            aria-label="Progres bimbingan per pembimbing"
            data-testid="mentorship-progress"
            className={cn('grid gap-1.5', className)}
        >
            {progress.map((row) => (
                <li
                    key={row.lecturer.id}
                    className="flex flex-wrap items-center justify-between gap-2 text-sm"
                >
                    <span className="min-w-0">
                        <span className="text-muted-foreground">
                            {row.lecturer.role}:
                        </span>{' '}
                        {row.lecturer.name}
                    </span>
                    <Badge variant={row.met ? 'secondary' : 'outline'}>
                        {row.completed}/{row.minimum}
                    </Badge>
                </li>
            ))}
        </ul>
    );
}

export function MentorshipMinimumAlert({
    progress,
}: {
    progress: MentorshipProgressRow[];
}) {
    const unmet = progress.filter((row) => !row.met);

    if (unmet.length === 0) {
        return null;
    }

    return (
        <Alert variant="warning" data-testid="mentorship-minimum-warning">
            <TriangleAlert />
            <AlertTitle>Bimbingan belum memenuhi minimal</AlertTitle>
            <AlertDescription>
                <p>
                    Pembimbing berikut belum memenuhi minimal bimbingan selesai.
                    Penjadwalan tetap dapat disimpan.
                </p>
                <ul className="list-inside list-disc">
                    {unmet.map((row) => (
                        <li key={row.lecturer.id}>
                            {row.lecturer.role}: {row.lecturer.name} (
                            {row.completed}/{row.minimum})
                        </li>
                    ))}
                </ul>
            </AlertDescription>
        </Alert>
    );
}
