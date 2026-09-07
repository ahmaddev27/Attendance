'use client';

import * as React from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Inbox, Plus } from 'lucide-react';

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
import { RequestDetailDialog } from '@/components/requests/request-detail-dialog';
import { RequestStatusBadge } from '@/components/requests/request-status-badge';
import { RequestTypeBadge } from '@/components/requests/request-type-badge';
import { RequestTypePickerDialog } from '@/components/requests/request-type-picker-dialog';
import { SubmitRequestDialog } from '@/components/requests/submit-request-dialog';
import { myRequestsApi } from '@/lib/api/endpoints/requests';
import { CANCELLABLE_REQUEST_STATUSES } from '@/lib/constants/request-options';
import { formatDateTime } from '@/lib/request-format';
import type { RequestSummary, RequestType } from '@/lib/api/types';

export default function MyRequestsPage() {
  const queryClient = useQueryClient();

  const { data: requests, isLoading } = useQuery({
    queryKey: ['my-requests'],
    queryFn: async () => (await myRequestsApi.list()).data.data,
  });

  const [pickerOpen, setPickerOpen] = React.useState(false);
  const [submittingType, setSubmittingType] = React.useState<RequestType | null>(null);
  const [viewingRequestId, setViewingRequestId] = React.useState<number | null>(null);
  const [cancelTarget, setCancelTarget] = React.useState<RequestSummary | null>(null);

  const cancelMutation = useMutation({
    mutationFn: (id: number) => myRequestsApi.cancel(id),
    onSuccess: () => {
      toast.success('تم إلغاء الطلب');
      queryClient.invalidateQueries({ queryKey: ['my-requests'] });
      setCancelTarget(null);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'تعذر إلغاء الطلب');
    },
  });

  return (
    <div className="min-h-screen bg-ground p-6 md:p-10">
      <div className="mx-auto max-w-5xl space-y-6">
        <div className="flex flex-wrap items-center justify-between gap-4">
          <Button onClick={() => setPickerOpen(true)} className="gap-2 bg-brand text-white hover:bg-brand-hover">
            <Plus className="h-4 w-4" />
            طلب جديد
          </Button>
          <h1 className="text-2xl font-bold text-ink">طلباتي</h1>
        </div>

        <div className="rounded-xl border border-hairline bg-surface">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="text-right">رقم الطلب</TableHead>
                <TableHead className="text-right">النوع</TableHead>
                <TableHead className="text-right">تاريخ الإرسال</TableHead>
                <TableHead className="text-right">الخطوة الحالية</TableHead>
                <TableHead className="text-right">الحالة</TableHead>
                <TableHead className="text-right">إجراءات</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading &&
                Array.from({ length: 4 }).map((_, i) => (
                  <TableRow key={i}>
                    {Array.from({ length: 6 }).map((__, j) => (
                      <TableCell key={j}>
                        <Skeleton className="h-4 w-full" />
                      </TableCell>
                    ))}
                  </TableRow>
                ))}

              {!isLoading && (requests?.length ?? 0) === 0 && (
                <TableRow>
                  <TableCell colSpan={6} className="py-14 text-center">
                    <div className="flex flex-col items-center gap-2 text-muted">
                      <Inbox className="h-8 w-8" />
                      <p className="text-sm">لا توجد طلبات بعد</p>
                    </div>
                  </TableCell>
                </TableRow>
              )}

              {requests?.map((request) => (
                <TableRow key={request.id}>
                  <TableCell className="num" dir="ltr">
                    #{request.request_number}
                  </TableCell>
                  <TableCell>
                    <RequestTypeBadge requestType={request.request_type} />
                  </TableCell>
                  <TableCell className="num whitespace-nowrap">{formatDateTime(request.submitted_at)}</TableCell>
                  <TableCell>{request.current_step?.name ?? '—'}</TableCell>
                  <TableCell>
                    <RequestStatusBadge status={request.status} />
                  </TableCell>
                  <TableCell>
                    <div className="flex items-center gap-2">
                      <Button type="button" variant="outline" size="sm" onClick={() => setViewingRequestId(request.id)}>
                        عرض
                      </Button>
                      {CANCELLABLE_REQUEST_STATUSES.includes(request.status) && (
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          className="text-danger hover:bg-danger-soft hover:text-danger"
                          onClick={() => setCancelTarget(request)}
                        >
                          إلغاء
                        </Button>
                      )}
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      </div>

      <RequestTypePickerDialog
        open={pickerOpen}
        onOpenChange={setPickerOpen}
        onSelect={(requestType) => {
          setPickerOpen(false);
          setSubmittingType(requestType);
        }}
      />

      <SubmitRequestDialog
        open={!!submittingType}
        onOpenChange={(open) => !open && setSubmittingType(null)}
        requestType={submittingType}
      />

      <RequestDetailDialog
        requestId={viewingRequestId}
        open={viewingRequestId != null}
        onOpenChange={(open) => !open && setViewingRequestId(null)}
        mode="employee"
      />

      <AlertDialog open={!!cancelTarget} onOpenChange={(open) => !open && setCancelTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>إلغاء الطلب</AlertDialogTitle>
            <AlertDialogDescription>
              هل أنت متأكد من إلغاء الطلب رقم <span className="num">#{cancelTarget?.request_number}</span>؟
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
              {cancelMutation.isPending && <Spinner className="text-white" />}
              تأكيد الإلغاء
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
