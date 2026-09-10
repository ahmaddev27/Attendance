'use client';

import * as React from 'react';
import { format, parseISO, isValid } from 'date-fns';
import { ar } from 'date-fns/locale';
import { Calendar as CalendarIcon, X } from 'lucide-react';

import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';

type DatePickerProps = {
  /** ISO string `YYYY-MM-DD` (matches backend `date` columns) or empty. */
  value: string;
  onChange: (isoDate: string) => void;
  placeholder?: string;
  /** Constrain the pickable range (optional). */
  min?: string;
  max?: string;
  className?: string;
  disabled?: boolean;
  /** Show a small "clear" affordance next to the trigger text. */
  clearable?: boolean;
};

/**
 * Arabic RTL-friendly date picker. Trigger renders the formatted date in
 * Arabic (`٩ سبتمبر ٢٠٢٦`), calendar popover uses `react-day-picker` with
 * the ar locale so month + weekday headers land in Arabic and open on the
 * right for RTL layouts. On selection, the parent gets a plain ISO
 * `YYYY-MM-DD` string — the exact shape every Laravel `date` validator +
 * repository filter expects, so wiring this in place of a native
 * `<input type="date">` is a drop-in substitution.
 *
 * Motivation: native `<input type="date">` shows the browser's OS locale
 * placeholder (`mm/dd/yyyy` on English installs, ISO on others) and
 * doesn't respect the surrounding RTL flow — the mm/dd/yyyy hint lands
 * left-aligned inside an otherwise-RTL row. This component solves both.
 */
export function DatePicker({
  value,
  onChange,
  placeholder = 'اختر تاريخاً',
  min,
  max,
  className,
  disabled,
  clearable = true,
}: DatePickerProps) {
  const [open, setOpen] = React.useState(false);

  const parsed = React.useMemo(() => {
    if (!value) return undefined;
    const d = parseISO(value);
    return isValid(d) ? d : undefined;
  }, [value]);

  const displayLabel = parsed ? format(parsed, 'd MMMM yyyy', { locale: ar }) : placeholder;

  const handleClear = (e: React.MouseEvent) => {
    e.stopPropagation();
    onChange('');
  };

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          type="button"
          variant="outline"
          disabled={disabled}
          // The whole app is RTL-only, but Radix's Popover trigger can lose
          // the parent `dir` context in some portalled compositions — pin it
          // explicitly so the icon/label span reads right-to-start and the
          // clear affordance always lands on the visual "end" (left in RTL).
          dir="rtl"
          className={cn(
            'w-full justify-between font-normal',
            !parsed && 'text-muted',
            className,
          )}
        >
          <span className="flex min-w-0 items-center gap-2">
            <CalendarIcon className="h-4 w-4 shrink-0 opacity-60" />
            <span className="truncate">{displayLabel}</span>
          </span>
          {clearable && parsed && !disabled && (
            <span
              role="button"
              tabIndex={0}
              aria-label="مسح التاريخ"
              onClick={handleClear}
              onKeyDown={(e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                  e.preventDefault();
                  handleClear(e as unknown as React.MouseEvent);
                }
              }}
              className="ms-1 grid h-5 w-5 shrink-0 place-items-center rounded hover:bg-surface-2"
            >
              <X className="h-3.5 w-3.5 opacity-60" />
            </span>
          )}
        </Button>
      </PopoverTrigger>
      <PopoverContent align="start" className="w-auto p-0" dir="rtl">
        <Calendar
          mode="single"
          selected={parsed}
          onSelect={(day) => {
            if (day) {
              onChange(format(day, 'yyyy-MM-dd'));
              setOpen(false);
            }
          }}
          locale={ar}
          disabled={(day) => {
            // react-day-picker v9 dropped fromDate/toDate; express the
            // range clamp as a `disabled` predicate — same effect
            // (unpickable + visually dimmed).
            const minDate = min ? parseISO(min) : null;
            const maxDate = max ? parseISO(max) : null;
            if (minDate && day < minDate) return true;
            if (maxDate && day > maxDate) return true;
            return false;
          }}
          weekStartsOn={6}
          autoFocus
        />
      </PopoverContent>
    </Popover>
  );
}
