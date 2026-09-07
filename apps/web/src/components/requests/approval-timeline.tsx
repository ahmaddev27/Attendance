import { CheckCircle2, RotateCcw, Send, XCircle } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { EmployeeAvatar } from '@/components/employees/employee-avatar';
import { APPROVAL_ACTION_BADGE_CLASSNAME, APPROVAL_ACTION_LABELS } from '@/lib/constants/request-options';
import { formatDateTime } from '@/lib/request-format';
import type { ApprovalAction, RequestApproval } from '@/lib/api/types';
import { cn } from '@/lib/utils';

const ACTION_ICONS: Record<ApprovalAction, typeof CheckCircle2> = {
  approved: CheckCircle2,
  rejected: XCircle,
  returned: RotateCcw,
  forwarded: Send,
};

const ACTION_ICON_CLASSNAME: Record<ApprovalAction, string> = {
  approved: 'text-success',
  rejected: 'text-danger',
  returned: 'text-warn',
  forwarded: 'text-brand-ink',
};

/** Vertical history of every decision made on a request, oldest first. */
export function ApprovalTimeline({ approvals }: { approvals: RequestApproval[] }) {
  if (approvals.length === 0) {
    return <p className="text-sm text-muted">لم يتم اتخاذ أي إجراء على هذا الطلب بعد</p>;
  }

  return (
    <ol className="space-y-4">
      {approvals.map((approval) => {
        const Icon = ACTION_ICONS[approval.action];
        return (
          <li key={approval.id} className="flex gap-3">
            <div className={cn('mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-full bg-surface-2', ACTION_ICON_CLASSNAME[approval.action])}>
              <Icon className="h-4 w-4" />
            </div>
            <div className="min-w-0 flex-1 space-y-1 border-b border-hairline pb-4 last:border-b-0 last:pb-0">
              <div className="flex flex-wrap items-center gap-2">
                <EmployeeAvatar employee={approval.approver} size={22} />
                <p className="font-medium text-ink">{approval.approver.full_name}</p>
                <Badge className={cn(APPROVAL_ACTION_BADGE_CLASSNAME[approval.action], 'font-medium')}>
                  {APPROVAL_ACTION_LABELS[approval.action]}
                </Badge>
                <span className="text-xs text-muted">— {approval.workflow_step.name}</span>
              </div>
              {approval.action === 'forwarded' && approval.forwarded_to && (
                <p className="text-xs text-muted">حُوّل إلى {approval.forwarded_to.full_name}</p>
              )}
              {approval.comment && <p className="text-sm text-ink-2">{approval.comment}</p>}
              <p className="num text-xs text-muted">{formatDateTime(approval.decided_at)}</p>
            </div>
          </li>
        );
      })}
    </ol>
  );
}
