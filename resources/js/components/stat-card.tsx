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
}

export function StatCard({
    title,
    value,
    description,
    icon: Icon,
    className,
}: StatCardProps) {
    return (
        <Card className={cn('shadow-sm', className)}>
            <CardHeader className="p-6 pb-2">
                <CardDescription className="font-medium text-muted-foreground">
                    {title}
                </CardDescription>
                <CardTitle className="text-3xl font-bold tracking-tight">
                    {value}
                </CardTitle>
            </CardHeader>
            {description && (
                <CardContent className="flex items-start gap-4 p-6 pt-0">
                    <span className="inline-flex size-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <Icon className="size-5" />
                    </span>
                    <p className="text-sm leading-snug text-muted-foreground">
                        {description}
                    </p>
                </CardContent>
            )}
        </Card>
    );
}
