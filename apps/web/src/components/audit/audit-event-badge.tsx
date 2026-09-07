import { Badge } from '@/components/ui/badge';
import { AUDIT_EVENT_META } from '@/lib/constants/audit-options';
import { cn } from '@/lib/utils';

/** Colored badge for an audit log entry's event (created/updated/deleted/…). */
export function AuditEventBadge({ event, className }: { event: string | null; className?: string }) {
  if (!event) {
    return <Badge className={cn('border-transparent bg-surface-2 text-ink-2 font-medium', className)}>—</Badge>;
  }

  const meta = AUDIT_EVENT_META[event] ?? { label: event, className: 'border-transparent bg-surface-2 text-ink-2' };
  return <Badge className={cn(meta.className, 'font-medium', className)}>{meta.label}</Badge>;
}
