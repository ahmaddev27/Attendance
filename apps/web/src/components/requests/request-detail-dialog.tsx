'use client';

import * as React from 'react';
import { useQuery } from '@tanstack/react-query';
import { CheckCircle2, RotateCcw, Send, XCircle } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { EmployeeAvatar } from '@/components/employees/employee-avatar';
import { ApproverRefDisplay } from '@/components/workflow/approver-ref-display';
import { ApprovalTimeline } from '@/components/requests/approval-timeline';
import { FormDataViewer } from '@/components/requests/form-data-viewer';
import { RequestActionDialog, type RequestActionKind } from '@/components/requests/request-action-dialog';
import { RequestStatusBadge } from '@/components/requests/request-status-badge';
import { RequestTypeBadge } from '@/components/requests/request-type-badge';
import { myRequestsApi, requestsApi } from '@/lib/api/endpoints/requests';
import { requestTypesApi } from '@/lib/api/endpoints/request-types';
import { APPROVER_TYPE_LABELS, ACTIONABLE_REQUEST_STATUSES } from '@/lib/constants/request-options';
import { formatDateTime } from '@/lib/request-format';
import { useAuthStore } from '@/lib/stores/auth-store';
import type { RequestDetail } from '@/lib/api/types';

function canCurrentUserAct(detail: RequestDetail, user: { id: number; roles?: string[]; permissions?: string[] } | null): boolean {
  if (!user || !detail.current_step) return false;
  if (!ACTIONABLE_REQUEST_STATUSES.includes(detail.status)) return false;

  // A persisted zustand session from before spatie/permission was wired up
  // rehydrates with `roles`/`permissions` undefined — dereferencing them
  // crashes the dialog. Guard with local arrays instead of trusting the type.
  const roles = Array.isArray(user.roles) ? user.roles : [];

  const step = detail.current_step;
  switch (step.approver_type) {
    case 'specific_employee':
      return step.approver_ref === String(user.id);
    case 'specific_role':
      return !!step.approver_ref && roles.includes(step.approver_ref);
    case 'form_field': {
      if (!step.approver_ref) return false;
      return String(detail.form_data[step.approver_ref]) === String(user.id);
    }
    case 'direct_manager':
    case 'department_manager':
    default:
      // Not verifiable client-side without the employee's org chart. We used
      // to grant every management-tier role a green light here, but the audit
      // found the resulting 403s on the action endpoints were worse UX than
      // simply hiding the buttons. The approvals inbox, whose items are
      // pre-vetted server-side, passes `forceActionable` explicitly.
      return false;
  }
}

function DetailRow({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="flex items-start justify-between gap-4 border-b border-hairline py-2.5 last:border-b-0">
      <span className="shrink-0 text-xs font-medium text-muted">{label}</span>
      <span className="text-sm text-ink">{children}</span>
    </div>
  );
}

type RequestDetailDialogProps = {
  requestId: number | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Employee's "my requests" view never shows admin decision actions. */
  mode: 'admin' | 'employee';
  /**
   * Skips the client-side authorization heuristic and always shows the
   * decision buttons in admin mode — used by the approvals inbox, whose
   * items are already server-confirmed to belong to the current user, so
   * the (necessarily approximate) `canCurrentUserAct` guess isn't needed.
   */
  forceActionable?: boolean;
};

/**
 * Read-only "view" dialog for a single request — form data, approval
 * history, current step — shared by the admin requests page and the
 * employee's my-requests page. Only the admin mode renders decision actions.
 */
export function RequestDetailDialog({ requestId, open, onOpenChange, mode, forceActionable }: RequestDetailDialogProps) {
  const user = useAuthStore((s) => s.user);
  const [actionKind, setActionKind] = React.useState<RequestActionKind | null>(null);

  const { data: detail, isLoading } = useQuery({
    queryKey: mode === 'admin' ? ['requests', requestId] : ['my-requests', requestId],
    queryFn: async () =>
      mode === 'admin'
        ? (await requestsApi.get(requestId as number)).data.data
        : (await myRequestsApi.get(requestId as number)).data.data,
    enabled: open && requestId != null,
  });

  // The dynamic form schema lives on the request TYPE, not the request
  // itself — fetched separately so form_data can be rendered with real
  // field labels instead of raw keys.
  const { data: requestType } = useQuery({
    queryKey: ['request-types', detail?.request_type.id],
    queryFn: async () => (await requestTypesApi.get(detail!.request_type.id)).data.data,
    enabled: open && !!detail,
    staleTime: 60_000,
  });

  const canAct = mode === 'admin' && !!detail && (forceActionable || canCurrentUserAct(detail, user));

  return (
    <>
      <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
          <DialogHeader>
            <DialogTitle>تفاصيل الطلب</DialogTitle>
          </DialogHeader>

          {isLoading && (
            <div className="space-y-3">
              <Skeleton className="h-12 w-full" />
              <Skeleton className="h-32 w-full" />
              <Skeleton className="h-24 w-full" />
            </div>
          )}

          {!isLoading && detail && (
            <div className="space-y-5">
              <div className="flex flex-wrap items-center gap-3 border-b border-hairline pb-4">
                <EmployeeAvatar employee={detail.employee} size={40} />
                <div className="min-w-0">
                  <p className="truncate font-medium text-ink">{detail.employee.full_name}</p>
                  <p className="num text-xs text-muted" dir="ltr">
                    {detail.employee.employee_number}
                  </p>
                </div>
                <RequestTypeBadge requestType={detail.request_type} />
                <span className="num text-xs text-muted" dir="ltr">
                  #{detail.request_number}
                </span>
                <RequestStatusBadge status={detail.status} className="ms-auto" />
              </div>

              <div>
                <DetailRow label="تاريخ الإرسال">
                  <span className="num" dir="ltr">
                    {formatDateTime(detail.submitted_at)}
                  </span>
                </DetailRow>
                {detail.completed_at && (
                  <DetailRow label="تاريخ الإكمال">
                    <span className="num" dir="ltr">
                      {formatDateTime(detail.completed_at)}
                    </span>
                  </DetailRow>
                )}
                {detail.current_step && (
                  <DetailRow label="الخطوة الحالية">
                    <div className="text-end">
                      <p>{detail.current_step.name}</p>
                      <p className="text-xs text-muted">
                        {APPROVER_TYPE_LABELS[detail.current_step.approver_type]}
                        {['specific_employee', 'specific_role', 'form_field'].includes(detail.current_step.approver_type) && (
                          <>
                            {' — '}
                            <ApproverRefDisplay
                              approver_type={detail.current_step.approver_type}
                              approver_ref={detail.current_step.approver_ref}
                            />
                          </>
                        )}
                      </p>
                    </div>
                  </DetailRow>
                )}
              </div>

              <div>
                <h3 className="mb-2 text-sm font-semibold text-ink">بيانات الطلب</h3>
                <FormDataViewer formData={detail.form_data} schema={requestType?.form_schema} />
              </div>

              <div>
                <h3 className="mb-2 text-sm font-semibold text-ink">سجل الاعتمادات</h3>
                <ApprovalTimeline approvals={detail.approvals} />
              </div>

              {canAct && detail.current_step && (
                <div className="flex flex-wrap items-center gap-2 border-t border-hairline pt-4">
                  <Button
                    type="button"
                    className="gap-1.5 bg-success text-white hover:bg-success/90"
                    onClick={() => setActionKind('approve')}
                  >
                    <CheckCircle2 className="h-4 w-4" />
                    موافقة
                  </Button>
                  {detail.current_step.can_reject && (
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
                  {detail.current_step.can_return && (
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
                  {detail.current_step.can_forward && (
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
                </div>
              )}
            </div>
          )}
        </DialogContent>
      </Dialog>

      <RequestActionDialog
        open={!!actionKind}
        onOpenChange={(nextOpen) => !nextOpen && setActionKind(null)}
        action={actionKind}
        request={detail ?? null}
        onActed={() => onOpenChange(false)}
      />
    </>
  );
}
