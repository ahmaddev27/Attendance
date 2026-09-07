import { X } from 'lucide-react';

import type { TaskTag } from '@/lib/api/types';
import { withAlpha } from '@/lib/task-format';
import { cn } from '@/lib/utils';

type TaskTagBadgeProps = {
  tag: Pick<TaskTag, 'name' | 'color'>;
  onRemove?: () => void;
  className?: string;
};

/** Small colored pill for a task tag; optionally removable (tags editor). */
export function TaskTagBadge({ tag, onRemove, className }: TaskTagBadgeProps) {
  return (
    <span
      className={cn('inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium', className)}
      style={{ backgroundColor: withAlpha(tag.color, 0.14), color: tag.color }}
    >
      <span className="truncate">{tag.name}</span>
      {onRemove && (
        <button
          type="button"
          onClick={onRemove}
          aria-label={`إزالة الوسم ${tag.name}`}
          className="shrink-0 rounded-full hover:opacity-70"
        >
          <X className="h-3 w-3" />
        </button>
      )}
    </span>
  );
}
