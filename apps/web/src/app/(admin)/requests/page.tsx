'use client';

import * as React from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { Eye } from 'lucide-react';

import { DatePicker } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { DataTable, type DataTableColumn } from '@/components/data-table/data-table';
import { FilterBar } from '@/components/data-table/filter-bar';
import { FilterSelect } from '@/components/data-table/filter-select';
import { EmployeeAvatar } from '@/components/employees/employee-avatar';
import { EmployeeSearchSelect } from '@/components/attendance/employee-search-select';
import { RequestDetailDialog } from '@/components/requests/request-detail-dialog';
import { RequestStatusBadge } from '@/components/requests/request-status-badge';
import { RequestTypeBadge } from '@/components/requests/request-type-badge';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { requestTypesApi } from '@/lib/api/endpoints/request-types';
import { requestsApi } from '@/lib/api/endpoints/requests';
import { REQUEST_STATUS_OPTIONS } from '@/lib/constants/request-options';
import { formatDateTime } from '@/lib/request-format';
import type { EmployeeSummary, RequestStatus, RequestSummary } from '@/lib/api/types';

const PER_PAGE = 20;

export default function RequestsPage() {
  const [page, setPage] = React.useState(1);
  const [search, setSearch] = React.useState('');
  const [status, setStatus] = React.useState<string | undefined>();
  const [requestTypeId, setRequestTypeId] = React.useState<string | undefined>();
  const [employee, setEmployee] = React.useState<EmployeeSummary | null>(null);
  const [from, setFrom] = React.useState('');
  const [to, setTo] = React.useState('');

  const [viewingRequestId, setViewingRequestId] = React.useState<number | null>(null);

  const debouncedSearch = useDebouncedValue(search);

  React.useEffect(() => {
    setPage(1);
  }, [debouncedSearch, status, requestTypeId, employee?.id, from, to]);

  const { data: requestTypes } = useQuery({
    queryKey: ['request-types', 'filter-options'],
    queryFn: async () => (await requestTypesApi.list()).data.data,
  });

  const { data, isLoading } = useQuery({
    queryKey: ['requests', 'list', { page, search: debouncedSearch, status, requestTypeId, employeeId: employee?.id, from, to }],
    queryFn: async () => {
      const { data } = await requestsApi.list({
        page,
        per_page: PER_PAGE,
        search: debouncedSearch || undefined,
        // 'all' is a UI-only sentinel — the backend repository filters
        // by status literally, so passing 'all' would match nothing.
        status: status && status !== 'all' ? (status as RequestStatus) : undefined,
        request_type_id: requestTypeId ? Number(requestTypeId) : undefined,
        employee_id: employee?.id,
        from: from || undefined,
        to: to || undefined,
      });
      return data;
    },
    placeholderData: keepPreviousData,
  });

  const columns: DataTableColumn<RequestSummary>[] = [
    {
      key: 'request_number',
      header: 'رقم الطلب',
      cell: (request) => (
        <button
          type="button"
          onClick={() => setViewingRequestId(request.id)}
          className="num text-brand-ink underline-offset-2 hover:underline"
          dir="ltr"
        >
          #{request.request_number}
        </button>
      ),
    },
    {
      key: 'request_type',
      header: 'نوع الطلب',
      cell: (request) => <RequestTypeBadge requestType={request.request_type} />,
    },
    {
      key: 'employee',
      header: 'الموظف',
      cell: (request) => (
        <button type="button" onClick={() => setViewingRequestId(request.id)} className="flex items-center gap-2">
          <EmployeeAvatar employee={request.employee} size={28} />
          <div className="min-w-0 text-start">
            <p className="truncate font-medium text-ink">{request.employee.full_name}</p>
            <p className="num text-xs text-muted" dir="ltr">
              {request.employee.employee_number}
            </p>
          </div>
        </button>
      ),
    },
    {
      key: 'submitted_at',
      header: 'تاريخ الإرسال',
      cell: (request) => (
        <span className="num whitespace-nowrap" dir="ltr">
          {formatDateTime(request.submitted_at)}
        </span>
      ),
    },
    {
      key: 'current_step',
      header: 'الخطوة الحالية',
      cell: (request) => request.current_step?.name ?? '—',
    },
    {
      key: 'status',
      header: 'الحالة',
      cell: (request) => <RequestStatusBadge status={request.status} />,
    },
  ];

  return (
    <div className="space-y-6">
      <div>
        <p className="text-xs font-medium text-muted">مسارات العمل</p>
        <h1 className="mt-1 text-2xl font-bold text-ink">جميع الطلبات</h1>
      </div>

      <FilterBar searchValue={search} onSearchChange={setSearch} searchPlaceholder="بحث برقم الطلب...">
        <FilterSelect
          value={status}
          onChange={setStatus}
          options={REQUEST_STATUS_OPTIONS}
          placeholder="الحالة"
          allLabel="كل الحالات"
        />
        <FilterSelect
          value={requestTypeId}
          onChange={setRequestTypeId}
          options={(requestTypes ?? []).map((rt) => ({ value: String(rt.id), label: rt.name }))}
          placeholder="نوع الطلب"
          allLabel="كل الأنواع"
        />
        <div className="w-full sm:w-56">
          <EmployeeSearchSelect value={employee} onChange={setEmployee} placeholder="كل الموظفين" />
        </div>
        <div className="flex items-center gap-2">
          <div className="w-44">
            <Label className="sr-only">من تاريخ</Label>
            <DatePicker value={from} onChange={setFrom} placeholder="من تاريخ" max={to || undefined} />
          </div>
          <span className="text-xs text-muted">إلى</span>
          <div className="w-44">
            <Label className="sr-only">إلى تاريخ</Label>
            <DatePicker value={to} onChange={setTo} placeholder="إلى تاريخ" min={from || undefined} />
          </div>
        </div>
      </FilterBar>

      <DataTable
        columns={columns}
        data={data?.data ?? []}
        rowKey={(request) => request.id}
        isLoading={isLoading}
        emptyMessage="لا توجد طلبات مطابقة للفلاتر المحددة"
        actions={[{ label: 'عرض التفاصيل', icon: Eye, onClick: (request) => setViewingRequestId(request.id) }]}
        pagination={data ? { meta: data.meta, onPageChange: setPage } : undefined}
      />

      <RequestDetailDialog
        requestId={viewingRequestId}
        open={viewingRequestId != null}
        onOpenChange={(open) => !open && setViewingRequestId(null)}
        mode="admin"
      />
    </div>
  );
}
