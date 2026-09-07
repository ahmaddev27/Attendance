import { formatWeekday } from '@/lib/attendance-format';
import type { AttendanceTrendPoint } from '@/lib/api/types';

/**
 * Plain CSS bar chart for the 7-day attendance trend — per the M8 spec,
 * intentionally not a chart library, just heights driven by inline styles.
 */
export function AttendanceTrendChart({ data }: { data: AttendanceTrendPoint[] }) {
  if (data.length === 0) {
    return <p className="py-10 text-center text-sm text-muted">لا توجد بيانات كافية لعرض الاتجاه</p>;
  }

  const max = Math.max(1, ...data.map((point) => point.present));

  return (
    <div className="flex h-44 items-end justify-between gap-2 sm:gap-3">
      {data.map((point) => {
        const heightPct = Math.round((point.present / max) * 100);
        return (
          <div key={point.date} className="flex h-full flex-1 flex-col items-center gap-2">
            <span className="num text-xs font-semibold text-ink">{point.present}</span>
            <div className="flex w-full flex-1 items-end">
              <div
                className="w-full rounded-t-md bg-brand"
                style={{ height: `${Math.max(heightPct, point.present > 0 ? 4 : 0)}%` }}
              />
            </div>
            <span className="whitespace-nowrap text-[11px] text-muted">{formatWeekday(point.date)}</span>
          </div>
        );
      })}
    </div>
  );
}
