'use client';

import { useMutation, useQueryClient } from '@tanstack/react-query';
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
import { LeaveTypeBadge } from '@/components/leaves/leave-type-badge';
import { Spinner } from '@/components/ui/spinner';
import { leaveRequestsApi } from '@/lib/api/endpoints/leaves';
import { formatDate } from '@/lib/attendance-format';
import type { LeaveRequest } from '@/lib/api/types';

type ApproveLeaveDialogProps = {
  leaveRequest: LeaveRequest | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
};

export function ApproveLeaveDialog({ leaveRequest, open, onOpenChange }: ApproveLeaveDialogProps) {
  const queryClient = useQueryClient();

  const mutation = useMutation({
    mutationFn: (id: number) => leaveRequestsApi.approve(id),
    onSuccess: () => {
      toast.success('تمت الموافقة على طلب الإجازة');
      queryClient.invalidateQueries({ queryKey: ['leave-requests'] });
      // Approval consumes / releases the employee's leave balance, so the
      // employee-detail balances view must refetch. The employee-side
      // "my leaves" list also needs to reflect the new status.
      queryClient.invalidateQueries({ queryKey: ['leave-balances'] });
      queryClient.invalidateQueries({ queryKey: ['my-leaves'] });
      onOpenChange(false);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'تعذر الموافقة على الطلب');
    },
  });

  return (
    <AlertDialog open={open} onOpenChange={onOpenChange}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>الموافقة على طلب الإجازة</AlertDialogTitle>
          <AlertDialogDescription asChild>
            <div className="space-y-3">
              <p>هل أنت متأكد من الموافقة على طلب الإجازة التالي؟</p>
              {leaveRequest && (
                <div className="rounded-lg border border-hairline bg-surface-2 p-3 text-sm text-ink">
                  <p className="font-medium">{leaveRequest.employee.full_name}</p>
                  <div className="mt-1">
                    <LeaveTypeBadge leaveType={leaveRequest.leave_type} className="text-xs" />
                  </div>
                  <p className="mt-1 text-xs text-muted">
                    <span className="num" dir="ltr">
                      {formatDate(leaveRequest.start_date)} — {formatDate(leaveRequest.end_date)}
                    </span>{' '}
                    (
                    <span className="num" dir="ltr">
                      {leaveRequest.days}
                    </span>{' '}
                    يوم)
                  </p>
                </div>
              )}
            </div>
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel disabled={mutation.isPending}>إلغاء</AlertDialogCancel>
          <AlertDialogAction
            disabled={mutation.isPending}
            className="bg-success text-white hover:bg-success/90"
            onClick={(e) => {
              e.preventDefault();
              if (leaveRequest) mutation.mutate(leaveRequest.id);
            }}
          >
            {mutation.isPending && <Spinner className="me-2 text-white" />}
            موافقة
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}
