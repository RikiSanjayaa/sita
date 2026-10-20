import type { ReactNode } from 'react';

import { Badge } from '@/components/ui/badge';
import { statusClassName, type StatusDomain } from '@/lib/status-styles';
import { cn } from '@/lib/utils';

interface StatusBadgeProps {
    domain: StatusDomain;
    status: string | null | undefined;
    children: ReactNode;
    className?: string;
}

export function StatusBadge({
    domain,
    status,
    children,
    className,
}: StatusBadgeProps) {
    return (
        <Badge
            variant="outline"
            className={cn(
                'rounded-full border-transparent',
                statusClassName(domain, status),
                className,
            )}
        >
            {children}
        </Badge>
    );
}
