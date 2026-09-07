import type { TaskStatus } from '@/lib/api/types';
import { withAlpha } from '@/lib/task-format';
import { cn } from '@/lib/utils';

type TaskStatusBadgeProps = {
  status: Pick<TaskStatus, 'name' | 'color'>;
  className?: string;
};

/** Colored pill for a task's status — used in the list table and task detail sidebar. */
export function TaskStatusBadge({ status, className }: TaskStatusBadgeProps) {
  return (
    <span
      className={cn('inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold', className)}
      style={{ backgroundColor: withAlpha(status.color, 0.14), color: status.color }}
    >
      <span className="h-1.5 w-1.5 shrink-0 rounded-full" style={{ backgroundColor: status.color }} aria-hidden="true" />
      {status.name}
    </span>
  );
}
