'use client';

import * as React from 'react';
import { cn } from '@/lib/utils';

const DAY_LABELS = ['أحد', 'اثنين', 'ثلاثاء', 'أربعاء', 'خميس', 'جمعة', 'سبت'];
const HOUR_LABELS = Array.from({ length: 24 }, (_, i) => i);

type Props = {
  matrix: number[][]; // [dow][hour]
  max: number;
};

/**
 * 7×24 heatmap of check-in counts.
 *
 * Colour intensity is proportional to `count / max` — a single blank
 * (max=0) render stays fully readable rather than every cell going
 * `bg-brand` at 100%. The hour axis stays LTR so 08 → 09 → 10 reads
 * left-to-right visually even inside an RTL page (matches how people
 * read a clock).
 */
export function AttendanceHeatmap({ matrix, max }: Props) {
  const cell = (count: number) => {
    if (max === 0 || count === 0) return 'bg-surface-2';
    const intensity = count / max;
    // Discrete steps read better than a continuous gradient — the eye
    // can distinguish 5 buckets, not 100.
    if (intensity < 0.2) return 'bg-brand/10';
    if (intensity < 0.4) return 'bg-brand/25';
    if (intensity < 0.6) return 'bg-brand/45';
    if (intensity < 0.8) return 'bg-brand/70';
    return 'bg-brand text-white';
  };

  return (
    <div className="rounded-xl border border-hairline bg-surface p-4">
      <div className="mb-3 flex items-center justify-between">
        <h3 className="text-sm font-semibold text-ink">توزيع أوقات الحضور</h3>
        <p className="text-[11px] text-muted">أيام الأسبوع × ساعات اليوم</p>
      </div>

      <div className="overflow-x-auto" dir="ltr">
        <table className="text-[10px]">
          <thead>
            <tr>
              <th className="w-10" />
              {HOUR_LABELS.map((h) => (
                <th key={h} className="px-1 pb-1 font-mono text-muted">
                  {h.toString().padStart(2, '0')}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {DAY_LABELS.map((day, dow) => (
              <tr key={day}>
                <td className="pr-2 text-end font-medium text-ink-2">{day}</td>
                {HOUR_LABELS.map((h) => {
                  const count = matrix[dow]?.[h] ?? 0;
                  return (
                    <td key={h} className="p-0.5">
                      <div
                        className={cn('h-6 w-6 rounded transition-colors', cell(count))}
                        title={`${day} ${h.toString().padStart(2, '0')}:00 — ${count} تسجيل`}
                      >
                        {count > 0 && (
                          <span className="num flex h-full w-full items-center justify-center text-[9px] font-medium">
                            {count}
                          </span>
                        )}
                      </div>
                    </td>
                  );
                })}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Legend */}
      <div className="mt-3 flex items-center justify-end gap-2 text-[10px] text-muted">
        <span>أقل</span>
        <div className="h-3 w-3 rounded bg-surface-2" />
        <div className="h-3 w-3 rounded bg-brand/10" />
        <div className="h-3 w-3 rounded bg-brand/25" />
        <div className="h-3 w-3 rounded bg-brand/45" />
        <div className="h-3 w-3 rounded bg-brand/70" />
        <div className="h-3 w-3 rounded bg-brand" />
        <span>أكثر</span>
      </div>
    </div>
  );
}
