import { Badge } from '@/components/ui/badge';
import { EMPLOYEE_STATUS_LABELS } from '@/lib/constants/employee-options';
import type { EmployeeStatus } from '@/lib/api/types';
import { cn } from '@/lib/utils';

const STATUS_STYLES: Record<EmployeeStatus, string> = {
  active: 'bg-success-soft text-success',
  inactive: 'bg-surface-2 text-muted',
  on_leave: 'bg-warn-soft text-warn-ink',
  terminated: 'bg-danger-soft text-danger',
};

export function EmployeeStatusBadge({ status }: { status: EmployeeStatus }) {
  return (
    <Badge className={cn('border-transparent font-medium', STATUS_STYLES[status])}>
      {EMPLOYEE_STATUS_LABELS[status]}
    </Badge>
  );
}
