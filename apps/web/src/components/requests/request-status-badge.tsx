import { Badge } from '@/components/ui/badge';
import { REQUEST_STATUS_META } from '@/lib/constants/request-options';
import type { RequestStatus } from '@/lib/api/types';
import { cn } from '@/lib/utils';

export function RequestStatusBadge({ status, className }: { status: RequestStatus; className?: string }) {
  const meta = REQUEST_STATUS_META[status];
  return <Badge className={cn(meta.className, 'font-medium', className)}>{meta.label}</Badge>;
}
