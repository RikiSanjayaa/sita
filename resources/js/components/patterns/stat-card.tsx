import { type LucideIcon } from 'lucide-react';

import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';

interface StatCardProps {
    title: string;
    value: string;
    description?: string;
    icon: LucideIcon;
    className?: string;
    compact?: boolean;
}

export function StatCard({
    title,
    value,
    description,
    icon: Icon,
    className,
    compact = false,
}: StatCardProps) {
    return (
        <Card className={cn('h-full', className)}>
            <CardHeader className={cn('p-6 pb-2', compact && 'p-4 pb-2')}>
                <CardDescription className="font-medium text-muted-foreground">
                    {title}
                </CardDescription>
                <CardTitle className="text-3xl font-bold tracking-tight tabular-nums">
                    {value}
                </CardTitle>
            </CardHeader>
            {description && (
                <CardContent
                    className={cn(
                        'flex items-start gap-4 p-6 pt-0',
                        compact && 'p-4 pt-0',
                    )}
                >
                    {!compact && (
                        <span className="inline-flex size-10 items-center justify-center rounded-lg bg-primary-muted text-primary-muted-foreground">
                            <Icon className="size-5" aria-hidden="true" />
                        </span>
                    )}
                    <p
                        className={cn(
                            'text-sm leading-snug text-muted-foreground',
                            compact && 'text-xs',
                        )}
                    >
                        {description}
                    </p>
                </CardContent>
            )}
        </Card>
    );
}
