'use client';

import * as React from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { EmployeeSearchSelect } from '@/components/attendance/employee-search-select';
import { RequestTypeBadge } from '@/components/requests/request-type-badge';
import { requestsApi } from '@/lib/api/endpoints/requests';
import type { EmployeeSummary, RequestSummary } from '@/lib/api/types';

export type RequestActionKind = 'approve' | 'reject' | 'return' | 'forward';

const ACTION_META: Record<
  RequestActionKind,
  { title: string; confirmLabel: string; confirmClassName: string; commentRequired: boolean; commentLabel: string }
> = {
  approve: {
    title: 'الموافقة على الطلب',
    confirmLabel: 'موافقة',
    confirmClassName: 'bg-success text-white hover:bg-success/90',
    commentRequired: false,
    commentLabel: 'ملاحظة (اختياري)',
  },
  reject: {
    title: 'رفض الطلب',
    confirmLabel: 'رفض الطلب',
    confirmClassName: 'bg-danger text-white hover:bg-danger/90',
    commentRequired: true,
    commentLabel: 'سبب الرفض',
  },
  return: {
    title: 'إرجاع الطلب',
    confirmLabel: 'إرجاع الطلب',
    confirmClassName: 'bg-warn text-white hover:bg-warn/90',
    commentRequired: true,
    commentLabel: 'سبب الإرجاع',
  },
  forward: {
    title: 'تحويل الطلب',
    confirmLabel: 'تحويل الطلب',
    confirmClassName: 'bg-brand text-white hover:bg-brand-hover',
    commentRequired: false,
    commentLabel: 'ملاحظة (اختياري)',
  },
};

type RequestActionDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  action: RequestActionKind | null;
  request: RequestSummary | null;
  /** Called after a successful action, in addition to the built-in query invalidation — e.g. to also close a parent detail dialog. */
  onActed?: () => void;
};

/**
 * Shared approve/reject/return/forward dialog — used from both the admin
 * request detail modal and the approvals inbox quick actions, since all
 * four decisions post to the same `/requests/{id}/{action}` endpoints.
 */
export function RequestActionDialog({ open, onOpenChange, action, request, onActed }: RequestActionDialogProps) {
  const queryClient = useQueryClient();
  const [comment, setComment] = React.useState('');
  const [forwardTo, setForwardTo] = React.useState<EmployeeSummary | null>(null);
  const [error, setError] = React.useState<string | null>(null);

  React.useEffect(() => {
    if (open) {
      setComment('');
      setForwardTo(null);
      setError(null);
    }
  }, [open, request?.id, action]);

  const mutation = useMutation({
    mutationFn: async () => {
      if (!request || !action) throw new Error('Missing request/action');
      const trimmedComment = comment.trim() || undefined;
      switch (action) {
        case 'approve':
          return requestsApi.approve(request.id, trimmedComment ? { comment: trimmedComment } : undefined);
        case 'reject':
          return requestsApi.reject(request.id, { comment: comment.trim() });
        case 'return':
          return requestsApi.return(request.id, { comment: comment.trim() });
        case 'forward':
          return requestsApi.forward(request.id, { forwarded_to_id: forwardTo!.id, comment: trimmedComment });
      }
    },
    onSuccess: () => {
      toast.success('تم تنفيذ الإجراء بنجاح');
      queryClient.invalidateQueries({ queryKey: ['requests'] });
      queryClient.invalidateQueries({ queryKey: ['approvals-inbox'] });
      onOpenChange(false);
      onActed?.();
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'تعذر تنفيذ الإجراء');
    },
  });

  if (!action) return null;
  const meta = ACTION_META[action];

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);

    if (meta.commentRequired && !comment.trim()) {
      setError(`${meta.commentLabel} مطلوب`);
      return;
    }
    if (action === 'forward' && !forwardTo) {
      setError('يجب اختيار الموظف المحول إليه الطلب');
      return;
    }
    mutation.mutate();
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{meta.title}</DialogTitle>
          {request && (
            <DialogDescription asChild>
              <div className="flex items-center gap-2 pt-1">
                <span className="num" dir="ltr">
                  #{request.request_number}
                </span>
                <span>—</span>
                <RequestTypeBadge requestType={request.request_type} />
                <span>—</span>
                <span>{request.employee.full_name}</span>
              </div>
            </DialogDescription>
          )}
        </DialogHeader>

        <form onSubmit={submit} className="space-y-4">
          {action === 'forward' && (
            <div>
              <Label className="text-xs font-semibold text-ink-2">تحويل إلى</Label>
              <div className="mt-1.5">
                <EmployeeSearchSelect value={forwardTo} onChange={setForwardTo} placeholder="اختر الموظف..." />
              </div>
            </div>
          )}

          <div>
            <Label className="text-xs font-semibold text-ink-2">{meta.commentLabel}</Label>
            <Textarea
              value={comment}
              onChange={(e) => {
                setComment(e.target.value);
                if (error) setError(null);
              }}
              className="mt-1.5"
              rows={3}
              autoFocus={action !== 'forward'}
              placeholder="اكتب ملاحظتك هنا..."
            />
          </div>

          {error && <p className="text-xs text-danger">{error}</p>}

          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              إلغاء
            </Button>
            <Button type="submit" disabled={mutation.isPending} className={meta.confirmClassName}>
              {mutation.isPending && <Spinner className="text-white" />}
              {meta.confirmLabel}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
