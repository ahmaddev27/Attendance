import { cn } from '@/lib/utils';

type TaskProgressBarProps = {
  value: number;
  className?: string;
};

/**
 * Lightweight progress bar (no Radix dependency needed — a styled div is
 * enough for a read-only percentage indicator). Used in the list table and
 * the task detail sidebar, next to the editable progress slider.
 */
export function TaskProgressBar({ value, className }: TaskProgressBarProps) {
  const clamped = Math.min(100, Math.max(0, value));
  return (
    <div
      className={cn('h-1.5 w-full overflow-hidden rounded-full bg-surface-2', className)}
      role="progressbar"
      aria-valuenow={clamped}
      aria-valuemin={0}
      aria-valuemax={100}
    >
      <div className="h-full rounded-full bg-brand transition-all" style={{ width: `${clamped}%` }} />
    </div>
  );
}
