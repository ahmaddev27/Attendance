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
    // Capped at ~640px so on a wide desktop each day cell reads as a
    // calendar square (~80px), not the giant sofa-sized tiles it drifted
    // into on 1400px+ screens. Grid stays fluid below that width.
    <div className="mx-auto max-w-2xl">
      <div className="grid grid-cols-7 gap-1 text-center text-[11px] font-semibold text-muted">
        {ARABIC_WEEKDAYS_SHORT.map((label) => (
          <div key={label} className="py-0.5">
            {label}
          </div>
        ))}
      </div>
      <div className="mt-1 grid grid-cols-7 gap-1">
        {cells.map((cell, idx) => {
          if (!cell) return <div key={`blank-${idx}`} />;
          const status = statusByDate[cell.iso];
          const meta = status ? ATTENDANCE_STATUS_META[status] : null;
          return (
            <div
              key={cell.iso}
              title={meta?.label}
              className={cn(
                'flex aspect-square flex-col items-center justify-center rounded-md border text-xs font-medium',
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
