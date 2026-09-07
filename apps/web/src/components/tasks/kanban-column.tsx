'use client';

import { useDroppable } from '@dnd-kit/core';

import { TaskCard } from '@/components/tasks/task-card';
import type { Task, TaskStatus } from '@/lib/api/types';
import { withAlpha } from '@/lib/task-format';
import { cn } from '@/lib/utils';

type KanbanColumnProps = {
  status: TaskStatus;
  tasks: Task[];
  onTaskClick: (task: Task) => void;
};

/** One Kanban column — a droppable zone (by `status.code`) listing its tasks. */
export function KanbanColumn({ status, tasks, onTaskClick }: KanbanColumnProps) {
  const { setNodeRef, isOver } = useDroppable({ id: status.code });

  return (
    <div className="flex w-72 shrink-0 flex-col rounded-xl border border-hairline bg-surface">
      <div
        className="flex items-center justify-between rounded-t-xl border-b border-hairline px-3 py-2.5"
        style={{ backgroundColor: withAlpha(status.color, 0.1) }}
      >
        <div className="flex min-w-0 items-center gap-2">
          <span className="h-2 w-2 shrink-0 rounded-full" style={{ backgroundColor: status.color }} aria-hidden="true" />
          <span className="truncate text-sm font-semibold text-ink">{status.name}</span>
        </div>
        <span className="num shrink-0 rounded-full bg-surface px-2 py-0.5 text-xs font-semibold text-ink-2">
          {tasks.length}
        </span>
      </div>

      <div
        ref={setNodeRef}
        className={cn(
          'flex min-h-[140px] flex-1 flex-col gap-2 overflow-y-auto p-2.5 transition-colors',
          isOver && 'bg-brand-soft/50'
        )}
      >
        {tasks.length === 0 && <p className="py-8 text-center text-xs text-muted">لا مهام</p>}
        {tasks.map((task) => (
          <TaskCard key={task.id} task={task} onClick={() => onTaskClick(task)} />
        ))}
      </div>
    </div>
  );
}
