'use client';

import * as React from 'react';
import { useQuery } from '@tanstack/react-query';
import { CheckCircle2, Eye, Inbox as InboxIcon, XCircle } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { EmployeeAvatar } from '@/components/employees/employee-avatar';
import { RequestActionDialog, type RequestActionKind } from '@/components/requests/request-action-dialog';
import { RequestDetailDialog } from '@/components/requests/request-detail-dialog';
import { RequestTypeBadge } from '@/components/requests/request-type-badge';
import { approvalsInboxApi } from '@/lib/api/endpoints/approvals';
import { formatRelativeTime } from '@/lib/request-format';
import type { RequestSummary } from '@/lib/api/types';

export default function ApprovalsInboxPage() {
  const { data: inbox, isLoading } = useQuery({
    queryKey: ['approvals-inbox'],
    queryFn: async () => (await approvalsInboxApi.list()).data.data,
  });

  const [viewingRequestId, setViewingRequestId] = React.useState<number | null>(null);
  const [actionTarget, setActionTarget] = React.useState<{ request: RequestSummary; action: RequestActionKind } | null>(null);

  return (
    <div className="space-y-6">
      <div>
        <p className="text-xs font-medium text-muted">مسارات العمل</p>
        <h1 className="mt-1 text-2xl font-bold text-ink">صندوق الموافقات</h1>
      </div>

      {isLoading && (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {Array.from({ length: 3 }).map((_, i) => (
            <Skeleton key={i} className="h-40 rounded-xl" />
          ))}
        </div>
      )}

      {!isLoading && (inbox?.length ?? 0) === 0 && (
        <div className="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-hairline bg-surface py-16 text-muted">
          <InboxIcon className="h-8 w-8" />
          <p className="text-sm">لا توجد طلبات بانتظار موافقتك</p>
        </div>
      )}

      {!isLoading && (inbox?.length ?? 0) > 0 && (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {inbox!.map((request) => (
            <div key={request.id} className="flex flex-col gap-3 rounded-xl border border-hairline bg-surface p-4">
              <div className="flex items-center justify-between gap-2">
                <RequestTypeBadge requestType={request.request_type} />
                <span className="num text-xs text-muted" dir="ltr">
                  #{request.request_number}
                </span>
              </div>

              <div className="flex items-center gap-2">
                <EmployeeAvatar employee={request.employee} size={32} />
                <div className="min-w-0">
                  <p className="truncate font-medium text-ink">{request.employee.full_name}</p>
                  <p className="text-xs text-muted">{formatRelativeTime(request.submitted_at)}</p>
                </div>
              </div>

              <div className="mt-auto flex items-center gap-1.5 border-t border-hairline pt-3">
                <Button type="button" variant="outline" size="sm" className="flex-1 gap-1.5" onClick={() => setViewingRequestId(request.id)}>
                  <Eye className="h-3.5 w-3.5" />
                  عرض
                </Button>
                <Button
                  type="button"
                  size="sm"
                  className="flex-1 gap-1.5 bg-success text-white hover:bg-success/90"
                  onClick={() => setActionTarget({ request, action: 'approve' })}
                >
                  <CheckCircle2 className="h-3.5 w-3.5" />
                  موافقة
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  className="flex-1 gap-1.5 text-danger hover:bg-danger-soft hover:text-danger"
                  onClick={() => setActionTarget({ request, action: 'reject' })}
                >
                  <XCircle className="h-3.5 w-3.5" />
                  رفض
                </Button>
              </div>
            </div>
          ))}
        </div>
      )}

      <RequestDetailDialog
        requestId={viewingRequestId}
        open={viewingRequestId != null}
        onOpenChange={(open) => !open && setViewingRequestId(null)}
        mode="admin"
        forceActionable
      />

      <RequestActionDialog
        open={!!actionTarget}
        onOpenChange={(open) => !open && setActionTarget(null)}
        action={actionTarget?.action ?? null}
        request={actionTarget?.request ?? null}
      />
    </div>
  );
}
