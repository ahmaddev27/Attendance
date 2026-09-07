import { cn } from '@/lib/utils';
import { Badge } from '@/components/ui/badge';
import type { AttendanceStatus } from '@/lib/api/types';

/**
 * Single source of truth for how an attendance status is labeled (Arabic)
 * and colored. Reused by the status badge here and by the monthly calendar
 * grid, so the two never drift apart.
 */
export const ATTENDANCE_STATUS_META: Record<
  AttendanceStatus,
  { label: string; className: string; dotClassName: string }
> = {
  present: {
    label: 'حاضر',
    className: 'border-transparent bg-success-soft text-success',
    dotClassName: 'bg-success',
  },
  late: {
    label: 'متأخر',
    className: 'border-transparent bg-warn-soft text-warn-ink',
    dotClassName: 'bg-warn',
  },
  early_leave: {
    label: 'انصراف مبكر',
    className: 'border-transparent bg-warn-soft text-warn-ink',
    dotClassName: 'bg-warn',
  },
  absent: {
    label: 'غائب',
    className: 'border-transparent bg-danger-soft text-danger',
    dotClassName: 'bg-danger',
  },
  on_leave: {
    label: 'إجازة',
    className: 'border-transparent bg-brand-soft text-brand-ink',
    dotClassName: 'bg-brand',
  },
  holiday: {
    label: 'عطلة',
    className: 'border-transparent bg-surface-2 text-ink-2',
    dotClassName: 'bg-ink-2',
  },
  weekend: {
    label: 'نهاية أسبوع',
    className: 'border-transparent bg-surface-2 text-muted',
    dotClassName: 'bg-muted',
  },
  remote: {
    label: 'عمل عن بُعد',
    className: 'border-transparent bg-brand-soft text-brand',
    dotClassName: 'bg-brand',
  },
  business_mission: {
    label: 'مهمة عمل',
    className: 'border-transparent bg-brand-soft text-brand-ink',
    dotClassName: 'bg-brand',
  },
};

export function AttendanceStatusBadge({
  status,
  className,
}: {
  status: AttendanceStatus;
  className?: string;
}) {
  const meta = ATTENDANCE_STATUS_META[status];

  return (
    <Badge className={cn(meta.className, 'font-medium', className)}>{meta.label}</Badge>
  );
}
