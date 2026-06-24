import { id } from 'date-fns/locale';
import { CalendarIcon } from 'lucide-react';
import { type DateRange } from 'react-day-picker';

import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

type DateRangePickerProps = {
    startDate: string;
    endDate: string;
    onChange: (range: { from: string; to: string }) => void;
    minDate?: string;
    disabled?: boolean;
    error?: string;
    className?: string;
};

const dateFormatter = new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

function dateFromInput(value: string): Date | undefined {
    if (value === '') {
        return undefined;
    }

    const [year, month, day] = value.split('-').map(Number);

    if (!year || !month || !day) {
        return undefined;
    }

    return new Date(year, month - 1, day);
}

function inputFromDate(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function formatRange(range: DateRange | undefined): string {
    if (!range?.from) {
        return 'Pilih rentang tanggal';
    }

    if (!range.to || inputFromDate(range.from) === inputFromDate(range.to)) {
        return dateFormatter.format(range.from);
    }

    return `${dateFormatter.format(range.from)} – ${dateFormatter.format(range.to)}`;
}

function DateRangePicker({
    startDate,
    endDate,
    onChange,
    minDate,
    disabled = false,
    error,
    className,
}: DateRangePickerProps) {
    const selectedRange: DateRange | undefined = dateFromInput(startDate)
        ? {
              from: dateFromInput(startDate),
              to: dateFromInput(endDate) ?? dateFromInput(startDate),
          }
        : undefined;
    const minimumDate = minDate ? dateFromInput(minDate) : undefined;

    return (
        <div className={cn('grid gap-1.5', className)}>
            <Popover>
                <PopoverTrigger asChild>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={disabled}
                        aria-invalid={Boolean(error)}
                        className={cn(
                            'w-full justify-start px-3 text-left font-normal',
                            !selectedRange?.from && 'text-muted-foreground',
                        )}
                    >
                        <CalendarIcon className="size-4" />
                        <span className="truncate">
                            {formatRange(selectedRange)}
                        </span>
                    </Button>
                </PopoverTrigger>
                <PopoverContent
                    align="start"
                    className="w-auto p-0"
                    portalled={false}
                >
                    <Calendar
                        mode="range"
                        selected={selectedRange}
                        defaultMonth={selectedRange?.from}
                        locale={id}
                        numberOfMonths={2}
                        disabled={
                            minimumDate
                                ? {
                                      before: minimumDate,
                                  }
                                : undefined
                        }
                        onSelect={(range) => {
                            if (!range?.from) {
                                return;
                            }

                            const to = range.to ?? range.from;

                            onChange({
                                from: inputFromDate(range.from),
                                to: inputFromDate(to),
                            });
                        }}
                    />
                </PopoverContent>
            </Popover>
            {error ? <p className="text-xs text-destructive">{error}</p> : null}
        </div>
    );
}

export { DateRangePicker };
