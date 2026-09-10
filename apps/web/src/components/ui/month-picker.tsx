'use client';

import * as React from 'react';
import { Calendar as CalendarIcon } from 'lucide-react';

import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';

/**
 * Arabic RTL-friendly month/year picker. Matches the `<DatePicker>` UX
 * (trigger button + popover) but shows a year/month dropdown instead of
 * a full day grid — for pages that filter by "which month" not "which day"
 * (attendance monthly report, payroll cycles, etc.).
 *
 * Value shape: `YYYY-MM` (matching the native `<input type="month">` the
 * component replaces, and matching the backend's `/monthly/{year}/{month}`
 * URL segments).
 */
const AR_MONTHS = [
  'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
  'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر',
];

type MonthPickerProps = {
  /** `YYYY-MM` — same shape as `<input type="month">`. */
  value: string;
  onChange: (yearMonth: string) => void;
  placeholder?: string;
  /** Number of years to offer around the current year. Default 5 before + 1 after. */
  yearsBefore?: number;
  yearsAfter?: number;
  className?: string;
  disabled?: boolean;
};

function pad(n: number): string {
  return String(n).padStart(2, '0');
}

function parseValue(value: string): { year: number; month: number } {
  const [yStr, mStr] = value.split('-');
  const y = Number(yStr);
  const m = Number(mStr);
  const currentYear = new Date().getFullYear();
  const currentMonth = new Date().getMonth() + 1;
  return {
    year: Number.isFinite(y) && y > 1900 ? y : currentYear,
    month: Number.isFinite(m) && m >= 1 && m <= 12 ? m : currentMonth,
  };
}

export function MonthPicker({
  value,
  onChange,
  placeholder = 'اختر الشهر',
  yearsBefore = 5,
  yearsAfter = 1,
  className,
  disabled,
}: MonthPickerProps) {
  const [open, setOpen] = React.useState(false);
  const { year, month } = parseValue(value);
  const now = new Date();
  const thisYear = now.getFullYear();

  const years = React.useMemo(() => {
    const list: number[] = [];
    for (let y = thisYear - yearsBefore; y <= thisYear + yearsAfter; y++) list.push(y);
    return list;
  }, [thisYear, yearsBefore, yearsAfter]);

  const label = value ? `${AR_MONTHS[month - 1]} ${year}` : placeholder;

  const pick = (nextYear: number, nextMonth: number) => {
    onChange(`${nextYear}-${pad(nextMonth)}`);
    setOpen(false);
  };

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          type="button"
          variant="outline"
          disabled={disabled}
          dir="rtl"
          className={cn('w-full justify-between font-normal', !value && 'text-muted', className)}
        >
          <span className="flex min-w-0 items-center gap-2">
            <CalendarIcon className="h-4 w-4 shrink-0 opacity-60" />
            <span className="truncate">{label}</span>
          </span>
        </Button>
      </PopoverTrigger>
      <PopoverContent align="start" className="w-64 p-3" dir="rtl">
        {/* Year row: horizontal scroll if the range is long; the current
            selection carries a solid brand background. */}
        <div className="mb-3">
          <p className="mb-1 text-[11px] font-semibold text-muted">السنة</p>
          <div className="flex flex-wrap gap-1">
            {years.map((y) => (
              <button
                key={y}
                type="button"
                onClick={() => pick(y, month)}
                className={cn(
                  'num rounded-md border px-2 py-1 text-xs transition-colors',
                  y === year
                    ? 'border-brand bg-brand text-white'
                    : 'border-hairline text-ink-2 hover:bg-surface-2',
                )}
              >
                {y}
              </button>
            ))}
          </div>
        </div>

        <div>
          <p className="mb-1 text-[11px] font-semibold text-muted">الشهر</p>
          <div className="grid grid-cols-3 gap-1">
            {AR_MONTHS.map((label, idx) => {
              const m = idx + 1;
              return (
                <button
                  key={m}
                  type="button"
                  onClick={() => pick(year, m)}
                  className={cn(
                    'rounded-md border py-1.5 text-xs transition-colors',
                    m === month
                      ? 'border-brand bg-brand text-white'
                      : 'border-hairline text-ink-2 hover:bg-surface-2',
                  )}
                >
                  {label}
                </button>
              );
            })}
          </div>
        </div>
      </PopoverContent>
    </Popover>
  );
}
