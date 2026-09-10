'use client';

import * as React from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { CheckCircle2, Eye, Plus, XCircle } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { DatePicker } from '@/components/ui/date-picker';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Skeleton } from '@/components/ui/skeleton';
import { EmployeeAvatar } from '@/components/employees/employee-avatar';
import { EmployeeSearchSelect } from '@/components/attendance/employee-search-select';
import { PaginationBar } from '@/components/attendance/pagination-bar';
import { LeaveStatusBadge } from '@/components/leaves/leave-status-badge';
import { LeaveTypeBadge } from '@/components/leaves/leave-type-badge';
import { ApproveLeaveDialog } from '@/components/leaves/approve-leave-dialog';
import { RejectLeaveDialog } from '@/components/leaves/reject-leave-dialog';
import { LeaveDetailsDialog } from '@/components/leaves/leave-details-dialog';
import { CreateLeaveDialog } from '@/components/leaves/create-leave-dialog';
import { leaveRequestsApi } from '@/lib/api/endpoints/leaves';
import { leaveTypesApi } from '@/lib/api/endpoints/leave-types';
import { formatDate } from '@/lib/attendance-format';
import { LEAVE_STATUS_OPTIONS } from '@/lib/constants/leave-options';
import type { EmployeeSummary, LeaveRequest, LeaveStatus } from '@/lib/api/types';

const PER_PAGE = 20;

export default function LeavesPage() {
  const [page, setPage] = React.useState(1);
  const [status, setStatus] = React.useState<LeaveStatus | 'all'>('all');
  const [leaveTypeId, setLeaveTypeId] = React.useState<string | undefined>();
  const [employee, setEmployee] = React.useState<EmployeeSummary | null>(null);
  const [from, setFrom] = React.useState('');
  const [to, setTo] = React.useState('');

  const [createOpen, setCreateOpen] = React.useState(false);
  const [approveTarget, setApproveTarget] = React.useState<LeaveRequest | null>(null);
  const [rejectTarget, setRejectTarget] = React.useState<LeaveRequest | null>(null);
  const [viewTarget, setViewTarget] = React.useState<LeaveRequest | null>(null);

  const filters = {
    // The backend repository treats an unknown status as "match nothing";
    // 'all' is a UI-only sentinel and must be dropped before the request.
    status: status === 'all' ? undefined : status,
    leave_type_id: leaveTypeId ? Number(leaveTypeId) : undefined,
    employee_id: employee?.id,
    start_date: from || undefined,
    end_date: to || undefined,
  };

  React.useEffect(() => {
    setPage(1);
  }, [status, leaveTypeId, employee?.id, from, to]);

  const { data: leaveTypes } = useQuery({
    queryKey: ['leave-types', 'filter-options'],
    queryFn: async () => (await leaveTypesApi.list()).data.data,
  });

  const { data, isLoading, isFetching } = useQuery({
    queryKey: ['leave-requests', filters, page],
    queryFn: async () => (await leaveRequestsApi.list({ ...filters, page, per_page: PER_PAGE })).data,
    placeholderData: keepPreviousData,
  });

  const rows = data?.data ?? [];

  return (
    <div>
      <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
          <p className="text-xs font-medium text-muted">الإجازات</p>
          <h1 className="mt-1 text-2xl font-bold text-ink">طلبات الإجازة</h1>
        </div>
        <Button onClick={() => setCreateOpen(true)} className="gap-2 bg-brand text-white hover:bg-brand-hover">
          <Plus className="h-4 w-4" />
          طلب جديد
        </Button>
      </div>

      <div className="mb-4 grid grid-cols-1 gap-3 rounded-xl border border-hairline bg-surface p-4 sm:grid-cols-2 lg:grid-cols-5">
        <div>
          <Label className="text-xs font-semibold text-ink-2">الحالة</Label>
          <Select value={status} onValueChange={(v) => setStatus(v as LeaveStatus | 'all')}>
            <SelectTrigger className="mt-1.5">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">كل الحالات</SelectItem>
              {LEAVE_STATUS_OPTIONS.map((opt) => (
                <SelectItem key={opt.value} value={opt.value}>
                  {opt.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div>
          <Label className="text-xs font-semibold text-ink-2">نوع الإجازة</Label>
          <Select value={leaveTypeId ?? '__all__'} onValueChange={(v) => setLeaveTypeId(v === '__all__' ? undefined : v)}>
            <SelectTrigger className="mt-1.5">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="__all__">كل الأنواع</SelectItem>
              {(leaveTypes ?? []).map((type) => (
                <SelectItem key={type.id} value={String(type.id)}>
                  {type.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div>
          <Label className="text-xs font-semibold text-ink-2">الموظف</Label>
          <div className="mt-1.5">
            <EmployeeSearchSelect value={employee} onChange={setEmployee} />
          </div>
        </div>
        <div>
          <Label className="text-xs font-semibold text-ink-2">من تاريخ</Label>
          <div className="mt-1.5">
            <DatePicker value={from} onChange={setFrom} placeholder="اختر تاريخاً" max={to || undefined} />
          </div>
        </div>
        <div>
          <Label className="text-xs font-semibold text-ink-2">إلى تاريخ</Label>
          <div className="mt-1.5">
            <DatePicker value={to} onChange={setTo} placeholder="اختر تاريخاً" min={from || undefined} />
          </div>
        </div>
      </div>

      <div className="rounded-xl border border-hairline bg-surface">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="text-start">الموظف</TableHead>
              <TableHead className="text-start">نوع الإجازة</TableHead>
              <TableHead className="text-start">التواريخ</TableHead>
              <TableHead className="text-start">الأيام</TableHead>
              <TableHead className="text-start">السبب</TableHead>
              <TableHead className="text-start">الحالة</TableHead>
              <TableHead className="text-start">إجراءات</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading &&
              Array.from({ length: 6 }).map((_, i) => (
                <TableRow key={`skeleton-${i}`}>
                  {Array.from({ length: 7 }).map((__, j) => (
                    <TableCell key={j}>
                      <Skeleton className="h-4 w-full" />
                    </TableCell>
                  ))}
                </TableRow>
              ))}

            {!isLoading && rows.length === 0 && (
              <TableRow>
                <TableCell colSpan={7} className="py-10 text-center text-sm text-muted">
                  لا توجد طلبات إجازة مطابقة للفلاتر المحددة
                </TableCell>
              </TableRow>
            )}

            {!isLoading &&
              rows.map((row) => (
                <TableRow key={row.id}>
                  <TableCell>
                    <div className="flex items-center gap-2">
                      <EmployeeAvatar employee={row.employee} size={28} />
                      <div className="min-w-0">
                        <p className="truncate font-medium text-ink">{row.employee.full_name}</p>
                        <p className="num text-xs text-muted" dir="ltr">
                          {row.employee.employee_number}
                        </p>
                      </div>
                    </div>
                  </TableCell>
                  <TableCell>
                    <LeaveTypeBadge leaveType={row.leave_type} />
                  </TableCell>
                  <TableCell className="whitespace-nowrap">
                    <span className="num" dir="ltr">
                      {formatDate(row.start_date)} — {formatDate(row.end_date)}
                    </span>
                  </TableCell>
                  <TableCell>
                    <span className="num" dir="ltr">
                      {row.days}
                    </span>
                  </TableCell>
                  <TableCell className="max-w-[180px] truncate" title={row.reason ?? undefined}>
                    {row.reason || '—'}
                  </TableCell>
                  <TableCell>
                    <LeaveStatusBadge status={row.status} />
                  </TableCell>
                  <TableCell>
                    <div className="flex items-center gap-1">
                      {row.status === 'pending' && (
                        <>
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            title="موافقة"
                            className="text-success hover:bg-success-soft hover:text-success"
                            onClick={() => setApproveTarget(row)}
                          >
                            <CheckCircle2 className="h-4 w-4" />
                          </Button>
                          <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            title="رفض"
                            className="text-danger hover:bg-danger-soft hover:text-danger"
                            onClick={() => setRejectTarget(row)}
                          >
                            <XCircle className="h-4 w-4" />
                          </Button>
                        </>
                      )}
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        title="عرض التفاصيل"
                        onClick={() => setViewTarget(row)}
                      >
                        <Eye className="h-4 w-4" />
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))}
          </TableBody>
        </Table>

        {data && (
          <PaginationBar
            currentPage={data.meta.current_page}
            lastPage={data.meta.last_page}
            total={data.meta.total}
            onPageChange={setPage}
          />
        )}
      </div>
      {isFetching && !isLoading && (
        <p className="mt-2 flex items-center gap-2 text-xs text-muted">جارٍ التحديث...</p>
      )}

      <CreateLeaveDialog open={createOpen} onOpenChange={setCreateOpen} />
      <ApproveLeaveDialog
        leaveRequest={approveTarget}
        open={!!approveTarget}
        onOpenChange={(open) => !open && setApproveTarget(null)}
      />
      <RejectLeaveDialog
        leaveRequest={rejectTarget}
        open={!!rejectTarget}
        onOpenChange={(open) => !open && setRejectTarget(null)}
      />
      <LeaveDetailsDialog
        leaveRequest={viewTarget}
        open={!!viewTarget}
        onOpenChange={(open) => !open && setViewTarget(null)}
      />
    </div>
  );
}
