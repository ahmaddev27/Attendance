import type { TaskPriority } from '@/lib/api/types';
import { cn } from '@/lib/utils';

type TaskPriorityProps = {
  priority: Pick<TaskPriority, 'name' | 'color'>;
  className?: string;
};

/** Bare colored dot — used on the Kanban card where space is tight. */
export function TaskPriorityDot({ priority, className }: TaskPriorityProps) {
  return (
    <span
      className={cn('inline-block h-2.5 w-2.5 shrink-0 rounded-full', className)}
      style={{ backgroundColor: priority.color }}
      role="img"
      aria-label={priority.name}
      title={priority.name}
    />
  );
}

/** Dot + name — used in the list table and task detail sidebar. */
export function TaskPriorityBadge({ priority, className }: TaskPriorityProps) {
  return (
    <span className={cn('inline-flex items-center gap-2', className)}>
      <TaskPriorityDot priority={priority} />
      <span className="truncate text-ink">{priority.name}</span>
    </span>
  );
}
