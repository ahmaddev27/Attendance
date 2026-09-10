'use client';

import * as React from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { CalendarOff, Plus } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
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
import { Spinner } from '@/components/ui/spinner';
import { BalanceCard } from '@/components/leaves/balance-card';
import { LeaveStatusBadge } from '@/components/leaves/leave-status-badge';
import { LeaveTypeBadge } from '@/components/leaves/leave-type-badge';
import { SubmitLeaveDialog } from '@/components/leaves/submit-leave-dialog';
import { myLeavesApi } from '@/lib/api/endpoints/leaves';
import { formatDate } from '@/lib/attendance-format';
import type { LeaveRequest } from '@/lib/api/types';

// `approved` is included because the backend `LeaveRequestService::cancel`
// permits self-cancellation of an already-approved leave as long as
// `assertMinNotice` still passes (the leave type's `min_notice_days`
// window hasn't closed). If the notice window has elapsed the backend
// returns 422 and the toast on the mutation surfaces the reason.
const CANCELLABLE_STATUSES: LeaveRequest['status'][] = ['draft', 'pending', 'approved'];

export default function MyLeavesPage() {
  const queryClient = useQueryClient();
  const [submitOpen, setSubmitOpen] = React.useState(false);
  const [cancelTarget, setCancelTarget] = React.useState<LeaveRequest | null>(null);

  const { data: balances, isLoading: balancesLoading } = useQuery({
    queryKey: ['my-leave-balances'],
    queryFn: async () => (await myLeavesApi.balances()).data.data,
  });

  const { data: leaves, isLoading: leavesLoading } = useQuery({
    queryKey: ['my-leaves'],
    queryFn: async () => (await myLeavesApi.list()).data.data,
  });

  const cancelMutation = useMutation({
    mutationFn: (id: number) => myLeavesApi.cancel(id),
    onSuccess: () => {
      toast.success('تم إلغاء طلب الإجازة');
      queryClient.invalidateQueries({ queryKey: ['my-leaves'] });
      queryClient.invalidateQueries({ queryKey: ['my-leave-balances'] });
      setCancelTarget(null);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'تعذر إلغاء طلب الإجازة');
    },
  });

  const balanceBasedBalances = (balances ?? []).filter((balance) => balance.leave_type?.is_balance_based);

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <h1 className="text-2xl font-bold text-ink">إجازاتي</h1>
        <Button onClick={() => setSubmitOpen(true)} className="gap-2 bg-brand text-white hover:bg-brand-hover">
          <Plus className="h-4 w-4" />
          طلب إجازة جديد
        </Button>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {balancesLoading &&
          Array.from({ length: 3 }).map((_, i) => <Skeleton key={i} className="h-40 rounded-xl" />)}
        {!balancesLoading &&
          balanceBasedBalances.map((balance) => <BalanceCard key={balance.id} balance={balance} />)}
      </div>

      <div className="rounded-xl border border-hairline bg-surface">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="text-start">التواريخ</TableHead>
              <TableHead className="text-start">النوع</TableHead>
              <TableHead className="text-start">الأيام</TableHead>
              <TableHead className="text-start">الحالة</TableHead>
              <TableHead className="text-start">السبب</TableHead>
              <TableHead className="text-start">المراجعة</TableHead>
              <TableHead className="text-start">إجراءات</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {leavesLoading &&
              Array.from({ length: 4 }).map((_, i) => (
                <TableRow key={i}>
                  {Array.from({ length: 7 }).map((__, j) => (
                    <TableCell key={j}>
                      <Skeleton className="h-4 w-full" />
                    </TableCell>
                  ))}
                </TableRow>
              ))}

            {!leavesLoading && (leaves?.length ?? 0) === 0 && (
              <TableRow>
                <TableCell colSpan={7} className="py-14 text-center">
                  <div className="flex flex-col items-center gap-2 text-muted">
                    <CalendarOff className="h-8 w-8" />
                    <p className="text-sm">لا توجد طلبات إجازة بعد</p>
                  </div>
                </TableCell>
              </TableRow>
            )}

            {leaves?.map((leave) => (
              <TableRow key={leave.id}>
                <TableCell className="whitespace-nowrap">
                  <span className="num" dir="ltr">
                    {formatDate(leave.start_date)} — {formatDate(leave.end_date)}
                  </span>
                </TableCell>
                <TableCell>
                  <LeaveTypeBadge leaveType={leave.leave_type} />
                </TableCell>
                <TableCell>
                  <span className="num" dir="ltr">
                    {leave.days}
                  </span>
                </TableCell>
                <TableCell>
                  <LeaveStatusBadge status={leave.status} />
                </TableCell>
                <TableCell className="max-w-[160px] truncate" title={leave.reason ?? undefined}>
                  {leave.reason || '—'}
                </TableCell>
                <TableCell className="text-xs text-muted">
                  {leave.reviewer ? (
                    <>
                      <p>{leave.reviewer.name}</p>
                      <p className="num" dir="ltr">
                        {formatDate(leave.reviewed_at)}
                      </p>
                    </>
                  ) : (
                    '—'
                  )}
                </TableCell>
                <TableCell>
                  {CANCELLABLE_STATUSES.includes(leave.status) ? (
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      className="text-danger hover:bg-danger-soft hover:text-danger"
                      onClick={() => setCancelTarget(leave)}
                    >
                      إلغاء
                    </Button>
                  ) : (
                    <span className="text-xs text-muted">—</span>
                  )}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>

      <SubmitLeaveDialog open={submitOpen} onOpenChange={setSubmitOpen} />

      <AlertDialog open={!!cancelTarget} onOpenChange={(open) => !open && setCancelTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>إلغاء طلب الإجازة</AlertDialogTitle>
            <AlertDialogDescription>
              هل أنت متأكد من إلغاء طلب الإجازة من{' '}
              <span className="num" dir="ltr">
                {cancelTarget && formatDate(cancelTarget.start_date)}
              </span>{' '}
              إلى{' '}
              <span className="num" dir="ltr">
                {cancelTarget && formatDate(cancelTarget.end_date)}
              </span>
              ؟
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={cancelMutation.isPending}>تراجع</AlertDialogCancel>
            <AlertDialogAction
              disabled={cancelMutation.isPending}
              className="bg-danger text-white hover:bg-danger/90"
              onClick={(e) => {
                e.preventDefault();
                if (cancelTarget) cancelMutation.mutate(cancelTarget.id);
              }}
            >
              {cancelMutation.isPending && <Spinner className="me-2 text-white" />}
              تأكيد الإلغاء
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
