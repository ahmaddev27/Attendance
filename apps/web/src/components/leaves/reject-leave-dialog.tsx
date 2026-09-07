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
import { Textarea } from '@/components/ui/textarea';
import { Spinner } from '@/components/ui/spinner';
import { LeaveTypeBadge } from '@/components/leaves/leave-type-badge';
import { leaveRequestsApi } from '@/lib/api/endpoints/leaves';
import { formatDate } from '@/lib/attendance-format';
import type { LeaveRequest } from '@/lib/api/types';

type RejectLeaveDialogProps = {
  leaveRequest: LeaveRequest | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
};

export function RejectLeaveDialog({ leaveRequest, open, onOpenChange }: RejectLeaveDialogProps) {
  const queryClient = useQueryClient();
  const [reason, setReason] = React.useState('');
  const [error, setError] = React.useState<string | null>(null);

  React.useEffect(() => {
    if (open) {
      setReason('');
      setError(null);
    }
  }, [open, leaveRequest?.id]);

  const mutation = useMutation({
    mutationFn: ({ id, rejectionReason }: { id: number; rejectionReason: string }) =>
      leaveRequestsApi.reject(id, rejectionReason),
    onSuccess: () => {
      toast.success('تم رفض طلب الإجازة');
      queryClient.invalidateQueries({ queryKey: ['leave-requests'] });
      onOpenChange(false);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'تعذر رفض الطلب');
    },
  });

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!reason.trim()) {
      setError('سبب الرفض مطلوب');
      return;
    }
    if (leaveRequest) mutation.mutate({ id: leaveRequest.id, rejectionReason: reason.trim() });
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>رفض طلب الإجازة</DialogTitle>
          <DialogDescription>يرجى توضيح سبب رفض هذا الطلب — سيظهر السبب للموظف.</DialogDescription>
        </DialogHeader>

        {leaveRequest && (
          <div className="rounded-lg border border-hairline bg-surface-2 p-3 text-sm text-ink">
            <p className="font-medium">{leaveRequest.employee.full_name}</p>
            <div className="mt-1">
              <LeaveTypeBadge leaveType={leaveRequest.leave_type} className="text-xs" />
            </div>
            <p className="num mt-1 text-xs text-muted">
              {formatDate(leaveRequest.start_date)} — {formatDate(leaveRequest.end_date)} ({leaveRequest.days} يوم)
            </p>
          </div>
        )}

        <form onSubmit={submit} className="space-y-3">
          <div>
            <Label className="text-xs font-semibold text-ink-2">سبب الرفض</Label>
            <Textarea
              value={reason}
              onChange={(e) => {
                setReason(e.target.value);
                if (error) setError(null);
              }}
              className="mt-1.5"
              rows={3}
              autoFocus
              placeholder="اكتب سبب الرفض هنا..."
            />
            {error && <p className="mt-1 text-xs text-danger">{error}</p>}
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              إلغاء
            </Button>
            <Button type="submit" disabled={mutation.isPending} className="bg-danger text-white hover:bg-danger/90">
              {mutation.isPending && <Spinner className="text-white" />}
              رفض الطلب
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
