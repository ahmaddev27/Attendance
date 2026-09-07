'use client';

import * as React from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { Download, PlayCircle } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { EmployeePicker } from '@/components/employees/employee-picker';
import { DynamicReportTable } from '@/components/reports/dynamic-report-table';
import { ReportEmptyState } from '@/components/reports/report-empty-state';
import { ATTENDANCE_STATUS_META } from '@/components/attendance/attendance-status-badge';
import { departmentsApi } from '@/lib/api/endpoints/departments';
import { reportsApi } from '@/lib/api/endpoints/reports';
import type { AttendanceReportParams, AttendanceStatus, EmployeeSummary } from '@/lib/api/types';

const PER_PAGE = 25;

type FormFilters = {
  from: string;
  to: string;
  employee: EmployeeSummary | null;
  departmentId: string | undefined;
  status: AttendanceStatus | 'all';
};

function buildParams(filters: FormFilters): AttendanceReportParams {
  return {
    from: filters.from || undefined,
    to: filters.to || undefined,
    employee_id: filters.employee?.id,
    department_id: filters.departmentId ? Number(filters.departmentId) : undefined,
    status: filters.status !== 'all' ? filters.status : undefined,
  };
}

export default function AttendanceReportPage() {
  const [form, setForm] = React.useState<FormFilters>({
    from: '',
    to: '',
    employee: null,
    departmentId: undefined,
    status: 'all',
  });
  const [appliedParams, setAppliedParams] = React.useState<AttendanceReportParams | null>(null);
  const [page, setPage] = React.useState(1);

  const { data: departments } = useQuery({
    queryKey: ['departments', 'filter-options'],
    queryFn: async () => (await departmentsApi.list({ per_page: 100, is_active: true })).data.data,
    staleTime: 60_000,
  });

  const { data, isLoading, isFetching } = useQuery({
    queryKey: ['reports', 'attendance', appliedParams, page],
    queryFn: () => reportsApi.attendance({ ...appliedParams, page, per_page: PER_PAGE }),
    enabled: !!appliedParams,
    placeholderData: keepPreviousData,
  });

  const runReport = () => {
    setAppliedParams(buildParams(form));
    setPage(1);
  };

  const exportCsv = () => {
    if (!appliedParams) return;
    reportsApi.exportAttendanceCsv(appliedParams, 'attendance-report.csv');
  };

  return (
    <div>
      <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
        <Button
          onClick={exportCsv}
          disabled={!appliedParams || !data || data.data.length === 0}
          variant="outline"
          className="gap-2"
        >
          <Download className="h-4 w-4" />
          تصدير CSV
        </Button>
        <div>
          <p className="text-xs font-medium text-muted">التقارير</p>
          <h1 className="mt-1 text-2xl font-bold text-ink">تقرير الحضور</h1>
        </div>
      </div>

      <div className="mb-4 grid grid-cols-1 gap-3 rounded-xl border border-hairline bg-surface p-4 sm:grid-cols-2 lg:grid-cols-5">
        <div>
          <Label className="text-xs font-semibold text-ink-2">من تاريخ</Label>
          <Input
            type="date"
            value={form.from}
            onChange={(e) => setForm((f) => ({ ...f, from: e.target.value }))}
            className="mt-1.5"
            dir="ltr"
          />
        </div>
        <div>
          <Label className="text-xs font-semibold text-ink-2">إلى تاريخ</Label>
          <Input
            type="date"
            value={form.to}
            onChange={(e) => setForm((f) => ({ ...f, to: e.target.value }))}
            className="mt-1.5"
            dir="ltr"
          />
        </div>
        <div>
          <Label className="text-xs font-semibold text-ink-2">الموظف</Label>
          <div className="mt-1.5">
            <EmployeePicker
              value={form.employee}
              onChange={(employee) => setForm((f) => ({ ...f, employee }))}
              placeholder="كل الموظفين"
            />
          </div>
        </div>
        <div>
          <Label className="text-xs font-semibold text-ink-2">القسم</Label>
          <Select
            value={form.departmentId ?? '__all__'}
            onValueChange={(v) => setForm((f) => ({ ...f, departmentId: v === '__all__' ? undefined : v }))}
          >
            <SelectTrigger className="mt-1.5">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="__all__">كل الأقسام</SelectItem>
              {(departments ?? []).map((dept) => (
                <SelectItem key={dept.id} value={String(dept.id)}>
                  {dept.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div>
          <Label className="text-xs font-semibold text-ink-2">الحالة</Label>
          <Select
            value={form.status}
            onValueChange={(v) => setForm((f) => ({ ...f, status: v as AttendanceStatus | 'all' }))}
          >
            <SelectTrigger className="mt-1.5">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">كل الحالات</SelectItem>
              {Object.entries(ATTENDANCE_STATUS_META).map(([value, meta]) => (
                <SelectItem key={value} value={value}>
                  {meta.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="flex items-end sm:col-span-2 lg:col-span-5">
          <Button onClick={runReport} className="gap-2 bg-brand text-white hover:bg-brand-hover">
            <PlayCircle className="h-4 w-4" />
            تشغيل التقرير
          </Button>
        </div>
      </div>

      {!appliedParams && <ReportEmptyState />}

      {appliedParams && (
        <>
          <DynamicReportTable
            rows={data?.data ?? []}
            isLoading={isLoading}
            pagination={
              data
                ? {
                    currentPage: data.meta.current_page,
                    lastPage: data.meta.last_page,
                    total: data.meta.total,
                    onPageChange: setPage,
                  }
                : undefined
            }
          />
          {isFetching && !isLoading && (
            <p className="mt-2 text-xs text-muted">جارٍ التحديث...</p>
          )}
        </>
      )}
    </div>
  );
}
