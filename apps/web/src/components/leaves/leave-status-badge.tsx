import { Badge } from '@/components/ui/badge';
import { LEAVE_STATUS_META } from '@/lib/constants/leave-options';
import type { LeaveStatus } from '@/lib/api/types';
import { cn } from '@/lib/utils';

export function LeaveStatusBadge({ status, className }: { status: LeaveStatus; className?: string }) {
  const meta = LEAVE_STATUS_META[status];
  return <Badge className={cn(meta.className, 'font-medium', className)}>{meta.label}</Badge>;
}
