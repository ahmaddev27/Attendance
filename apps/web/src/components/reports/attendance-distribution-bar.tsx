import { cn } from '@/lib/utils';

type Distribution = {
  present: number;
  absent: number;
  leave: number;
  weekend: number;
  holiday: number;
};

const SEGMENTS: Array<{ key: keyof Distribution; label: string; colorClass: string }> = [
  { key: 'present', label: 'حاضر', colorClass: 'bg-success' },
  { key: 'absent', label: 'غائب', colorClass: 'bg-danger' },
  { key: 'leave', label: 'إجازة', colorClass: 'bg-brand' },
  { key: 'weekend', label: 'نهاية أسبوع', colorClass: 'bg-muted' },
  { key: 'holiday', label: 'عطلة', colorClass: 'bg-warn' },
];

/** Stacked distribution bar for the monthly-employee report: present/absent/leave/weekend/holiday. */
export function AttendanceDistributionBar({ distribution }: { distribution: Distribution }) {
  const total = SEGMENTS.reduce((sum, seg) => sum + distribution[seg.key], 0) || 1;

  return (
    <div>
      <div className="flex h-4 w-full overflow-hidden rounded-full bg-surface-2">
        {SEGMENTS.filter((seg) => distribution[seg.key] > 0).map((seg) => (
          <div
            key={seg.key}
            className={cn('h-full', seg.colorClass)}
            style={{ width: `${(distribution[seg.key] / total) * 100}%` }}
            title={`${seg.label}: ${distribution[seg.key]}`}
          />
        ))}
      </div>
      <div className="mt-3 flex flex-wrap gap-x-4 gap-y-2">
        {SEGMENTS.map((seg) => (
          <div key={seg.key} className="flex items-center gap-1.5 text-xs text-ink-2">
            <span className={cn('h-2.5 w-2.5 shrink-0 rounded-full', seg.colorClass)} />
            {seg.label}: <span className="num font-semibold text-ink">{distribution[seg.key]}</span>
          </div>
        ))}
      </div>
    </div>
  );
}
