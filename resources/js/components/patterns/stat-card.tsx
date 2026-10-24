import { type LucideIcon } from 'lucide-react';

import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { toneClassName, type StatusTone } from '@/lib/status-styles';
import { cn } from '@/lib/utils';

interface StatCardProps {
    title: string;
    value: string;
    description?: string;
    icon: LucideIcon;
    className?: string;
    variant?: 'default' | 'snapshot';
    tone?: StatusTone;
}

const valueClassNames: Record<StatusTone, string> = {
    neutral: 'text-foreground',
    primary: 'text-primary',
    success: 'text-success',
    warning: 'text-warning-muted-foreground',
    danger: 'text-danger',
    info: 'text-info',
};

export function StatCard({
    title,
    value,
    description,
    icon: Icon,
    className,
    variant = 'default',
    tone = 'primary',
}: StatCardProps) {
    if (variant === 'snapshot') {
        return (
            <Card className={cn('h-full gap-4 p-4 xl:p-5', className)}>
                <CardHeader className="flex flex-row items-start justify-between gap-2 p-0">
                    <span
                        className={cn(
                            'inline-flex size-9 shrink-0 items-center justify-center rounded-xl xl:size-10',
                            toneClassName(tone),
                        )}
                    >
                        <Icon className="size-5" aria-hidden="true" />
                    </span>
                    <CardTitle
                        className={cn(
                            'min-w-0 text-right text-2xl leading-none font-semibold tracking-tight break-words tabular-nums xl:text-4xl',
                            valueClassNames[tone],
                        )}
                    >
                        {value}
                    </CardTitle>
                </CardHeader>
                <CardContent className="grid gap-2 p-0">
                    <h3 className="text-sm leading-snug font-semibold xl:text-base">
                        {title}
                    </h3>
                    {description && (
                        <p className="text-xs leading-relaxed text-muted-foreground">
                            {description}
                        </p>
                    )}
                </CardContent>
            </Card>
        );
    }

    return (
        <Card className={cn('h-full', className)}>
            <CardHeader className="p-6 pb-2">
                <CardDescription className="font-medium text-muted-foreground">
                    {title}
                </CardDescription>
                <CardTitle className="text-3xl font-bold tracking-tight tabular-nums">
                    {value}
                </CardTitle>
            </CardHeader>
            {description && (
                <CardContent className="flex items-start gap-4 p-6 pt-0">
                    <span className="inline-flex size-10 items-center justify-center rounded-lg bg-primary-muted text-primary-muted-foreground">
                        <Icon className="size-5" aria-hidden="true" />
                    </span>
                    <p className="text-sm leading-snug text-muted-foreground">
                        {description}
                    </p>
                </CardContent>
            )}
        </Card>
    );
}
