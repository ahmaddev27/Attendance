'use client';

import * as React from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { CheckCircle2, Paperclip, XCircle } from 'lucide-react';
import { toast } from 'sonner';

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { EmployeeAvatar } from '@/components/employees/employee-avatar';
import { ApproveLeaveDialog } from '@/components/leaves/approve-leave-dialog';
import { LeaveStatusBadge } from '@/components/leaves/leave-status-badge';
import { LeaveTypeBadge } from '@/components/leaves/leave-type-badge';
import { RejectLeaveDialog } from '@/components/leaves/reject-leave-dialog';
import { myLeavesApi } from '@/lib/api/endpoints/leaves';
import { formatDate } from '@/lib/attendance-format';
import { formatDateTime } from '@/lib/request-format';
import type { LeaveRequest } from '@/lib/api/types';

// Mirrors the my-leaves list: the API resource carries no `can_be_cancelled`
// flag, and the backend re-validates the notice window on cancel anyway.
const CANCELLABLE_STATUSES: LeaveRequest['status'][] = ['draft', 'pending', 'approved'];

function Row({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="flex items-start justify-between gap-4 border-b border-hairline py-2.5 last:border-b-0">
      <span className="shrink-0 text-xs font-medium text-muted">{label}</span>
      <span className="text-sm text-ink">{children}</span>
    </div>
  );
}

function Num({ children }: { children: React.ReactNode }) {
  return (
    <span className="num" dir="ltr">
      {children}
    </span>
  );
}

/** Full-page body for one leave request; `mode` decides which actions appear. */
export function LeaveDetailView({ leave, mode }: { leave: LeaveRequest; mode: 'admin' | 'employee' }) {
  const queryClient = useQueryClient();
  const [dialog, setDialog] = React.useState<'approve' | 'reject' | 'cancel' | null>(null);

  const cancelMutation = useMutation({
    mutationFn: () => myLeavesApi.cancel(leave.id),
    onSuccess: () => {
      toast.success('تم إلغاء طلب الإجازة');
      queryClient.invalidateQueries({ queryKey: ['my-leaves'] });
      queryClient.invalidateQueries({ queryKey: ['my-leave-balances'] });
      setDialog(null);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'تعذر إلغاء طلب الإجازة');
    },
  });

  const canDecide = mode === 'admin' && leave.status === 'pending';
  const canCancel = mode === 'employee' && CANCELLABLE_STATUSES.includes(leave.status);

  return (
    <>
      <Card className="border-hairline bg-surface shadow-none">
        <CardContent className="space-y-5 p-5">
          <div className="flex flex-wrap items-center gap-3 border-b border-hairline pb-4">
            {mode === 'admin' && <EmployeeAvatar employee={leave.employee} size={40} />}
            <div className="min-w-0 space-y-1">
              {mode === 'admin' && <p className="truncate font-medium text-ink">{leave.employee.full_name}</p>}
              <div className="flex flex-wrap items-center gap-2">
                <LeaveTypeBadge leaveType={leave.leave_type} />
                <span className="text-sm text-ink-2">
                  <Num>
                    {formatDate(leave.start_date)} – {formatDate(leave.end_date)}
                  </Num>
                </span>
              </div>
            </div>
            <LeaveStatusBadge status={leave.status} className="ms-auto" />
          </div>

          <div>
            <Row label="عدد الأيام">
              <Num>{leave.days}</Num>
            </Row>
            <Row label="السبب">{leave.reason || '—'}</Row>
            {leave.attachment_url && (
              <Row label="المرفق">
                <a
                  href={leave.attachment_url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center gap-1.5 text-brand-ink underline underline-offset-2"
                >
                  <Paperclip className="h-3.5 w-3.5" />
                  عرض المرفق
                </a>
              </Row>
            )}
            <Row label="تاريخ الإنشاء">
              <Num>{formatDateTime(leave.created_at)}</Num>
            </Row>
          </div>

          <div>
            <h3 className="mb-2 text-sm font-semibold text-ink">القرار</h3>
            {leave.reviewer || leave.reviewed_at ? (
              <>
                {leave.reviewer && <Row label="تمت المراجعة بواسطة">{leave.reviewer.name}</Row>}
                <Row label="تاريخ المراجعة">
                  <Num>{formatDateTime(leave.reviewed_at)}</Num>
                </Row>
                {leave.status === 'rejected' && leave.rejection_reason && (
                  <Row label="سبب الرفض">
                    <span className="text-danger">{leave.rejection_reason}</span>
                  </Row>
                )}
              </>
            ) : (
              <p className="text-sm text-muted">لم يتم اتخاذ قرار على هذا الطلب بعد</p>
            )}
          </div>

          {(canDecide || canCancel) && (
            <div className="flex flex-wrap items-center gap-2 border-t border-hairline pt-4">
              {canDecide && (
                <>
                  <Button
                    type="button"
                    className="gap-1.5 bg-success text-white hover:bg-success/90"
                    onClick={() => setDialog('approve')}
                  >
                    <CheckCircle2 className="h-4 w-4" />
                    موافقة
                  </Button>
                  <Button
                    type="button"
                    variant="outline"
                    className="gap-1.5 text-danger hover:bg-danger-soft hover:text-danger"
                    onClick={() => setDialog('reject')}
                  >
                    <XCircle className="h-4 w-4" />
                    رفض
                  </Button>
                </>
              )}
              {canCancel && (
                <Button
                  type="button"
                  variant="outline"
                  className="text-danger hover:bg-danger-soft hover:text-danger"
                  onClick={() => setDialog('cancel')}
                >
                  إلغاء
                </Button>
              )}
            </div>
          )}
        </CardContent>
      </Card>

      <ApproveLeaveDialog leaveRequest={leave} open={dialog === 'approve'} onOpenChange={(o) => !o && setDialog(null)} />
      <RejectLeaveDialog leaveRequest={leave} open={dialog === 'reject'} onOpenChange={(o) => !o && setDialog(null)} />

      <AlertDialog open={dialog === 'cancel'} onOpenChange={(o) => !o && setDialog(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>إلغاء طلب الإجازة</AlertDialogTitle>
            <AlertDialogDescription>هل أنت متأكد من إلغاء هذا الطلب؟</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={cancelMutation.isPending}>تراجع</AlertDialogCancel>
            <AlertDialogAction
              disabled={cancelMutation.isPending}
              className="bg-danger text-white hover:bg-danger/90"
              onClick={(e) => {
                e.preventDefault();
                cancelMutation.mutate();
              }}
            >
              تأكيد الإلغاء
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}
