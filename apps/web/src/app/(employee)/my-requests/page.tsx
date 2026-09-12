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
import { requestTypesApi } from '@/lib/api/endpoints/request-types';
import { CANCELLABLE_REQUEST_STATUSES } from '@/lib/constants/request-options';
import { formatDateTime } from '@/lib/request-format';
import type { RequestSummary, RequestType } from '@/lib/api/types';

type ResubmitTarget = {
  requestId: number;
  requestType: RequestType;
  formData: Record<string, unknown>;
};

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
  const [resubmitTarget, setResubmitTarget] = React.useState<ResubmitTarget | null>(null);
  const [resubmitLoadingId, setResubmitLoadingId] = React.useState<number | null>(null);

  // Resubmit needs the full RequestType (for its form_schema) plus the
  // returned request's own form_data — the summary row carries neither.
  // Fetched lazily on click so the list page doesn't over-fetch for
  // every request in the table.
  const openResubmit = async (request: RequestSummary) => {
    setResubmitLoadingId(request.id);
    try {
      const [detailRes, typeRes] = await Promise.all([
        myRequestsApi.get(request.id),
        requestTypesApi.get(request.request_type.id),
      ]);
      setResubmitTarget({
        requestId: request.id,
        requestType: typeRes.data.data,
        formData: detailRes.data.data.form_data,
      });
    } catch (err) {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'تعذر تحميل بيانات الطلب');
    } finally {
      setResubmitLoadingId(null);
    }
  };

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
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <h1 className="text-2xl font-bold text-ink">طلباتي</h1>
        <Button onClick={() => setPickerOpen(true)} className="gap-2 bg-brand text-white hover:bg-brand-hover">
          <Plus className="h-4 w-4" />
          طلب جديد
        </Button>
      </div>

      <div className="rounded-xl border border-hairline bg-surface">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="text-start">رقم الطلب</TableHead>
              <TableHead className="text-start">النوع</TableHead>
              <TableHead className="text-start">تاريخ الإرسال</TableHead>
              <TableHead className="text-start">الخطوة الحالية</TableHead>
              <TableHead className="text-start">الحالة</TableHead>
              <TableHead className="text-start">إجراءات</TableHead>
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
                <TableCell>
                  <span className="num" dir="ltr">
                    #{request.request_number}
                  </span>
                </TableCell>
                <TableCell>
                  <RequestTypeBadge requestType={request.request_type} />
                </TableCell>
                <TableCell className="whitespace-nowrap">
                  <span className="num" dir="ltr">
                    {formatDateTime(request.submitted_at)}
                  </span>
                </TableCell>
                <TableCell>{request.current_step?.name ?? '—'}</TableCell>
                <TableCell>
                  <RequestStatusBadge status={request.status} />
                </TableCell>
                <TableCell>
                  <div className="flex items-center gap-2">
                    <Button type="button" variant="outline" size="sm" onClick={() => setViewingRequestId(request.id)}>
                      عرض
                    </Button>
                    {request.status === 'returned' && (
                      <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="text-brand-ink hover:bg-brand-soft"
                        disabled={resubmitLoadingId === request.id}
                        onClick={() => openResubmit(request)}
                      >
                        {resubmitLoadingId === request.id && <Spinner className="me-2" />}
                        إعادة تقديم
                      </Button>
                    )}
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

      <SubmitRequestDialog
        open={!!resubmitTarget}
        onOpenChange={(open) => !open && setResubmitTarget(null)}
        requestType={resubmitTarget?.requestType ?? null}
        resubmitRequestId={resubmitTarget?.requestId ?? null}
        initialFormData={resubmitTarget?.formData ?? null}
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
              هل أنت متأكد من إلغاء الطلب رقم{' '}
              <span className="num" dir="ltr">
                #{cancelTarget?.request_number}
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
