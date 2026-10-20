import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';

interface FilterPillOption<T extends string> {
    value: T;
    label: string;
    count?: number;
    disabled?: boolean;
}

interface FilterPillsProps<T extends string> {
    /** Accessible name of the whole group. */
    label: string;
    value: T;
    onValueChange: (value: T) => void;
    options: readonly FilterPillOption<T>[];
    className?: string;
}

export function FilterPills<T extends string>({
    label,
    value,
    onValueChange,
    options,
    className,
}: FilterPillsProps<T>) {
    return (
        <ToggleGroup
            type="single"
            value={value}
            aria-label={label}
            onValueChange={(next) => {
                // Radix reports '' when the active item is pressed again;
                // a filter must always keep one selection.
                if (next !== '') {
                    onValueChange(next as T);
                }
            }}
            className={cn('flex-wrap gap-1', className)}
        >
            {options.map((option) => (
                <ToggleGroupItem
                    key={option.value}
                    value={option.value}
                    disabled={option.disabled}
                    className="group h-7 gap-1.5 rounded-full bg-muted px-3 text-xs text-muted-foreground first:rounded-full last:rounded-full hover:bg-muted/80 hover:text-foreground data-[state=on]:bg-primary data-[state=on]:text-primary-foreground data-[state=on]:shadow-sm"
                >
                    {option.label}
                    {option.count !== undefined ? (
                        <span className="rounded-full bg-foreground/10 px-1.5 py-0.5 text-[10px] leading-none font-bold tabular-nums group-data-[state=on]:bg-primary-foreground/20">
                            {option.count}
                        </span>
                    ) : null}
                </ToggleGroupItem>
            ))}
        </ToggleGroup>
    );
}
