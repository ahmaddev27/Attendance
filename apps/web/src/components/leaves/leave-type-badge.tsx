import type { LeaveType } from '@/lib/api/types';
import { cn } from '@/lib/utils';

type LeaveTypeBadgeProps = {
  leaveType: Pick<LeaveType, 'name' | 'color'>;
  className?: string;
};

/** Colored dot + name — used everywhere a leave type is referenced in a table or list. */
export function LeaveTypeBadge({ leaveType, className }: LeaveTypeBadgeProps) {
  return (
    <span className={cn('inline-flex items-center gap-2', className)}>
      <span
        className="h-2.5 w-2.5 shrink-0 rounded-full"
        style={{ backgroundColor: leaveType.color }}
        aria-hidden="true"
      />
      <span className="truncate text-ink">{leaveType.name}</span>
    </span>
  );
}
