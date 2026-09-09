'use client';

import { useDroppable } from '@dnd-kit/core';

import { TaskCard } from '@/components/tasks/task-card';
import type { Task, TaskStatus } from '@/lib/api/types';
import { withAlpha } from '@/lib/task-format';
import { cn } from '@/lib/utils';

type KanbanColumnProps = {
  status: TaskStatus;
  tasks: Task[];
  /**
   * True (unlimited) row count for this column — may exceed
   * `tasks.length` when the backend capped the payload. Defaults to
   * `tasks.length` so older payloads without the field still render.
   */
  countTotal?: number;
  onTaskClick: (task: Task) => void;
};

/** One Kanban column — a droppable zone (by `status.code`) listing its tasks. */
export function KanbanColumn({ status, tasks, countTotal, onTaskClick }: KanbanColumnProps) {
  const { setNodeRef, isOver } = useDroppable({ id: status.code });
  const total = countTotal ?? tasks.length;
  const hiddenCount = Math.max(0, total - tasks.length);

  return (
    <div className="flex h-full w-80 shrink-0 flex-col rounded-xl border border-hairline bg-surface shadow-sm">
      <div
        className="flex items-center justify-between rounded-t-xl border-b border-hairline px-3 py-3"
        style={{ backgroundColor: withAlpha(status.color, 0.1) }}
      >
        <div className="flex min-w-0 items-center gap-2">
          <span className="h-2.5 w-2.5 shrink-0 rounded-full" style={{ backgroundColor: status.color }} aria-hidden="true" />
          <span className="truncate text-sm font-semibold text-ink">{status.name}</span>
        </div>
        <span className="num shrink-0 rounded-full bg-surface px-2 py-0.5 text-xs font-semibold text-ink-2">
          {total}
        </span>
      </div>

      <div
        ref={setNodeRef}
        className={cn(
          'flex flex-1 flex-col gap-2 overflow-y-auto p-2.5 transition-colors',
          isOver && 'bg-brand-soft/50'
        )}
      >
        {tasks.length === 0 && (
          <div className="flex flex-1 items-center justify-center py-12 text-center text-xs text-muted">
            لا مهام
          </div>
        )}
        {tasks.map((task) => (
          <TaskCard key={task.id} task={task} onClick={() => onTaskClick(task)} />
        ))}
        {hiddenCount > 0 && (
          // Payload cap indicator — the column is truncated to the most
          // recently updated cards; the rest are still there in the DB
          // (use the list view or a search to reach them).
          <p className="num mt-1 border-t border-hairline pt-2 text-center text-[11px] text-muted" dir="ltr">
            + {hiddenCount} more
          </p>
        )}
      </div>
    </div>
  );
}
