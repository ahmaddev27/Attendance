'use client';

import * as React from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { CheckCircle2, RotateCcw, Send, XCircle } from 'lucide-react';
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
import { ApprovalTimeline } from '@/components/requests/approval-timeline';
import { FormDataViewer } from '@/components/requests/form-data-viewer';
import { canCurrentUserAct } from '@/components/requests/request-detail-dialog';
import { RequestActionDialog, type RequestActionKind } from '@/components/requests/request-action-dialog';
import { RequestStatusBadge } from '@/components/requests/request-status-badge';
import { RequestTypeBadge } from '@/components/requests/request-type-badge';
import { requestTypesApi } from '@/lib/api/endpoints/request-types';
import { myRequestsApi } from '@/lib/api/endpoints/requests';
import { CANCELLABLE_REQUEST_STATUSES } from '@/lib/constants/request-options';
import { formatDateTime } from '@/lib/request-format';
import { useAuthStore } from '@/lib/stores/auth-store';
import type { RequestDetail } from '@/lib/api/types';

function Row({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="flex items-start justify-between gap-4 border-b border-hairline py-2.5 last:border-b-0">
      <span className="shrink-0 text-xs font-medium text-muted">{label}</span>
      <span className="text-sm text-ink">{children}</span>
    </div>
  );
}

/** The last approval is the decision of record once the request is closed. */
function DecisionMeta({ detail }: { detail: RequestDetail }) {
  const last = detail.approvals[detail.approvals.length - 1];
  if (!detail.completed_at || !last) return null;

  return (
    <div>
      <h3 className="mb-2 text-sm font-semibold text-ink">القرار</h3>
      <Row label="تم القرار بواسطة">{last.approver.full_name}</Row>
      <Row label="تاريخ القرار">
        <span className="num" dir="ltr">
          {formatDateTime(detail.completed_at)}
        </span>
      </Row>
    </div>
  );
}

/** Full-page body for one request; `mode` decides which actions appear. */
export function RequestDetailView({ detail, mode }: { detail: RequestDetail; mode: 'admin' | 'employee' }) {
  const user = useAuthStore((s) => s.user);
  const queryClient = useQueryClient();
  const [actionKind, setActionKind] = React.useState<RequestActionKind | null>(null);
  const [cancelOpen, setCancelOpen] = React.useState(false);

  // The form schema lives on the request TYPE; fetching it lets form_data
  // render with real labels and type-aware values instead of raw keys.
  const { data: requestType } = useQuery({
    queryKey: ['request-types', detail.request_type.id],
    queryFn: async () => (await requestTypesApi.get(detail.request_type.id)).data.data,
  });

  const cancelMutation = useMutation({
    mutationFn: () => myRequestsApi.cancel(detail.id),
    onSuccess: () => {
      toast.success('تم إلغاء الطلب');
      queryClient.invalidateQueries({ queryKey: ['my-requests'] });
      setCancelOpen(false);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'تعذر إلغاء الطلب');
    },
  });

  const step = detail.current_step;
  const canAct = mode === 'admin' && !!step && canCurrentUserAct(detail, user);
  const canCancel = mode === 'employee' && CANCELLABLE_REQUEST_STATUSES.includes(detail.status);

  return (
    <>
      <Card className="border-hairline bg-surface shadow-none">
        <CardContent className="space-y-5 p-5">
          <div className="flex flex-wrap items-center gap-3 border-b border-hairline pb-4">
            {mode === 'admin' && (
              <>
                <EmployeeAvatar employee={detail.employee} size={40} />
                <p className="truncate font-medium text-ink">{detail.employee.full_name}</p>
              </>
            )}
            <RequestTypeBadge requestType={detail.request_type} />
            <span className="num text-xs text-muted" dir="ltr">
              #{detail.request_number}
            </span>
            <RequestStatusBadge status={detail.status} className="ms-auto" />
          </div>

          <div>
            <Row label="تاريخ الإرسال">
              <span className="num" dir="ltr">
                {formatDateTime(detail.submitted_at)}
              </span>
            </Row>
            {step && <Row label="الخطوة الحالية">{step.name}</Row>}
          </div>

          <div>
            <h3 className="mb-2 text-sm font-semibold text-ink">بيانات الطلب</h3>
            <FormDataViewer formData={detail.form_data} schema={requestType?.form_schema} />
          </div>

          <div>
            <h3 className="mb-2 text-sm font-semibold text-ink">سجل الاعتمادات</h3>
            <ApprovalTimeline approvals={detail.approvals} />
          </div>

          <DecisionMeta detail={detail} />

          {(canAct || canCancel) && (
            <div className="flex flex-wrap items-center gap-2 border-t border-hairline pt-4">
              {canAct && step && (
                <>
                  <Button
                    type="button"
                    className="gap-1.5 bg-success text-white hover:bg-success/90"
                    onClick={() => setActionKind('approve')}
                  >
                    <CheckCircle2 className="h-4 w-4" />
                    موافقة
                  </Button>
                  {step.can_reject && (
                    <Button
                      type="button"
                      variant="outline"
                      className="gap-1.5 text-danger hover:bg-danger-soft hover:text-danger"
                      onClick={() => setActionKind('reject')}
                    >
                      <XCircle className="h-4 w-4" />
                      رفض
                    </Button>
                  )}
                  {step.can_return && (
                    <Button
                      type="button"
                      variant="outline"
                      className="gap-1.5 text-warn hover:bg-warn-soft"
                      onClick={() => setActionKind('return')}
                    >
                      <RotateCcw className="h-4 w-4" />
                      إرجاع
                    </Button>
                  )}
                  {step.can_forward && (
                    <Button
                      type="button"
                      variant="outline"
                      className="gap-1.5 text-brand-ink hover:bg-brand-soft"
                      onClick={() => setActionKind('forward')}
                    >
                      <Send className="h-4 w-4" />
                      تحويل
                    </Button>
                  )}
                </>
              )}
              {canCancel && (
                <Button
                  type="button"
                  variant="outline"
                  className="text-danger hover:bg-danger-soft hover:text-danger"
                  onClick={() => setCancelOpen(true)}
                >
                  إلغاء الطلب
                </Button>
              )}
            </div>
          )}
        </CardContent>
      </Card>

      <RequestActionDialog
        open={!!actionKind}
        onOpenChange={(open) => !open && setActionKind(null)}
        action={actionKind}
        request={detail}
      />

      <AlertDialog open={cancelOpen} onOpenChange={setCancelOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>إلغاء الطلب</AlertDialogTitle>
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
