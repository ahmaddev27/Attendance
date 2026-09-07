import { resolveLucideIcon } from '@/lib/dynamic-icon';
import { cn } from '@/lib/utils';

type RequestTypeBadgeProps = {
  requestType: { name: string; color: string; icon: string | null };
  className?: string;
};

/** Icon (or colored dot fallback) + name — used everywhere a request type is referenced in a table or card. */
export function RequestTypeBadge({ requestType, className }: RequestTypeBadgeProps) {
  const Icon = resolveLucideIcon(requestType.icon);

  return (
    <span className={cn('inline-flex items-center gap-2', className)}>
      {Icon ? (
        <Icon className="h-4 w-4 shrink-0" style={{ color: requestType.color }} />
      ) : (
        <span
          className="h-2.5 w-2.5 shrink-0 rounded-full"
          style={{ backgroundColor: requestType.color }}
          aria-hidden="true"
        />
      )}
      <span className="truncate text-ink">{requestType.name}</span>
    </span>
  );
}
