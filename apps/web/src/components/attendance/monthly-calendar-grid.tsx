import { cn } from '@/lib/utils';
import { ARABIC_WEEKDAYS_SHORT } from '@/lib/attendance-format';
import type { AttendanceStatus } from '@/lib/api/types';
import { ATTENDANCE_STATUS_META } from './attendance-status-badge';

type CalendarCell = { day: number; iso: string };

/** Month-view grid where each day cell is colored by its attendance status. */
export function MonthlyCalendarGrid({
  year,
  month,
  statusByDate,
}: {
  /** 1-indexed calendar month, matching the rest of the M3 API (…/monthly/{year}/{month}). */
  year: number;
  month: number;
  statusByDate: Record<string, AttendanceStatus>;
}) {
  const pad = (n: number) => String(n).padStart(2, '0');
  const daysInMonth = new Date(Date.UTC(year, month, 0)).getUTCDate();
  const leadingBlanks = new Date(Date.UTC(year, month - 1, 1)).getUTCDay();

  const cells: Array<CalendarCell | null> = [
    ...Array.from({ length: leadingBlanks }, () => null),
    ...Array.from({ length: daysInMonth }, (_, i) => ({
      day: i + 1,
      iso: `${year}-${pad(month)}-${pad(i + 1)}`,
    })),
  ];

  return (
    <div>
      <div className="grid grid-cols-7 gap-1.5 text-center text-xs font-semibold text-muted">
        {ARABIC_WEEKDAYS_SHORT.map((label) => (
          <div key={label} className="py-1">
            {label}
          </div>
        ))}
      </div>
      <div className="mt-1 grid grid-cols-7 gap-1.5">
        {cells.map((cell, idx) => {
          if (!cell) return <div key={`blank-${idx}`} />;
          const status = statusByDate[cell.iso];
          const meta = status ? ATTENDANCE_STATUS_META[status] : null;
          return (
            <div
              key={cell.iso}
              title={meta?.label}
              className={cn(
                'flex aspect-square flex-col items-center justify-center rounded-lg border text-sm font-medium',
                meta ? meta.className : 'border-hairline bg-surface text-ink-2'
              )}
            >
              <span className="num">{cell.day}</span>
            </div>
          );
        })}
      </div>
    </div>
  );
}
