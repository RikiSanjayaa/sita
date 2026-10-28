import { useFilterField } from '@/components/patterns/mobile-filter-scope';
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
    /** Extra classes for each pill, e.g. a taller touch target on phones. */
    itemClassName?: string;
}

export function FilterPills<T extends string>({
    label,
    value,
    onValueChange,
    options,
    className,
    itemClassName,
}: FilterPillsProps<T>) {
    const mobile = useFilterField(
        label,
        value,
        options[0]?.value ?? value,
        onValueChange,
        options,
    );
    if (mobile) return null;
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
                    className={cn(
                        'group h-7 gap-1.5 rounded-full bg-muted px-3 text-xs text-muted-foreground first:rounded-full last:rounded-full hover:bg-muted/80 hover:text-foreground data-[state=on]:bg-primary data-[state=on]:text-primary-foreground max-md:min-h-11',
                        itemClassName,
                    )}
                >
                    {option.label}
                    {option.count !== undefined ? (
                        <span className="rounded-full bg-background px-1.5 py-0.5 text-xs leading-none font-bold text-foreground tabular-nums group-data-[state=on]:bg-primary-foreground group-data-[state=on]:text-primary">
                            {option.count}
                        </span>
                    ) : null}
                </ToggleGroupItem>
            ))}
        </ToggleGroup>
    );
}
