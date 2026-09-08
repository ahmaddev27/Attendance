'use client';

import { useDraggable } from '@dnd-kit/core';
import { CSS } from '@dnd-kit/utilities';
import { MessageSquare, Paperclip } from 'lucide-react';

import { EmployeeAvatar } from '@/components/employees/employee-avatar';
import { TaskPriorityDot } from '@/components/tasks/task-priority-badge';
import { TaskTagBadge } from '@/components/tasks/task-tag-badge';
import { formatDate } from '@/lib/attendance-format';
import { DUE_DATE_URGENCY_CLASSNAME, getDueDateUrgency } from '@/lib/task-format';
import type { Task } from '@/lib/api/types';
import { cn } from '@/lib/utils';

type TaskCardProps = {
  task: Task;
  onClick?: () => void;
  /** Rendered inside a <DragOverlay> — no drag listeners of its own. */
  dragOverlay?: boolean;
};

const MAX_VISIBLE_TAGS = 3;

export function TaskCard({ task, onClick, dragOverlay = false }: TaskCardProps) {
  const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({
    id: task.id,
    disabled: dragOverlay,
  });

  const urgency = getDueDateUrgency(task.due_date, task.completed_at);
  const visibleTags = task.tags.slice(0, MAX_VISIBLE_TAGS);

  return (
    <div
      ref={dragOverlay ? undefined : setNodeRef}
      style={!dragOverlay && transform ? { transform: CSS.Translate.toString(transform) } : undefined}
      {...(dragOverlay ? {} : attributes)}
      {...(dragOverlay ? {} : listeners)}
      onClick={onClick}
      role="button"
      tabIndex={0}
      onKeyDown={(e) => {
        if (e.key === 'Enter') onClick?.();
      }}
      className={cn(
        'space-y-2 rounded-lg border border-hairline bg-surface p-3 text-start shadow-sm outline-none transition-shadow hover:shadow-md focus-visible:ring-1 focus-visible:ring-ring',
        !dragOverlay && 'cursor-pointer',
        isDragging && 'opacity-40',
        dragOverlay && 'rotate-2 cursor-grabbing shadow-lg'
      )}
    >
      <div className="flex items-start justify-between gap-2">
        <p className="line-clamp-2 flex-1 text-sm font-medium text-ink">{task.title}</p>
        <TaskPriorityDot priority={task.priority} className="mt-1" />
      </div>

      {visibleTags.length > 0 && (
        <div className="flex flex-wrap gap-1">
          {visibleTags.map((tag) => (
            <TaskTagBadge key={tag.id} tag={tag} />
          ))}
        </div>
      )}

      <div className="flex items-center justify-between gap-2">
        <div className="flex items-center gap-2.5 text-xs text-muted">
          <span className="flex items-center gap-1">
            <MessageSquare className="h-3.5 w-3.5" />
            <span className="num" dir="ltr">
              {task.comments_count}
            </span>
          </span>
          <span className="flex items-center gap-1">
            <Paperclip className="h-3.5 w-3.5" />
            <span className="num" dir="ltr">
              {task.attachments_count}
            </span>
          </span>
          {task.due_date && (
            <span className={cn('num', DUE_DATE_URGENCY_CLASSNAME[urgency])} dir="ltr">
              {formatDate(task.due_date)}
            </span>
          )}
        </div>
        {task.assignee && <EmployeeAvatar employee={task.assignee} size={24} />}
      </div>
    </div>
  );
}
