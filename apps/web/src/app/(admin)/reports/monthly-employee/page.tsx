'use client';

import * as React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Download, PlayCircle } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { EmployeePicker } from '@/components/employees/employee-picker';
import { AttendanceDistributionBar } from '@/components/reports/attendance-distribution-bar';
import { ReportEmptyState } from '@/components/reports/report-empty-state';
import { ReportStatCard } from '@/components/reports/report-stat-card';
import { reportsApi } from '@/lib/api/endpoints/reports';
import { formatMinutesAsHours } from '@/lib/attendance-format';
import type { EmployeeSummary } from '@/lib/api/types';

const ARABIC_MONTHS = [
  'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
  'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر',
];

const CURRENT_YEAR = new Date().getFullYear();
const YEAR_OPTIONS = Array.from({ length: 6 }, (_, i) => CURRENT_YEAR - i);

type AppliedSelection = { employee: EmployeeSummary; year: number; month: number };

export default function MonthlyEmployeeReportPage() {
  const [employee, setEmployee] = React.useState<EmployeeSummary | null>(null);
  const [year, setYear] = React.useState(CURRENT_YEAR);
  const [month, setMonth] = React.useState(new Date().getMonth() + 1);
  const [applied, setApplied] = React.useState<AppliedSelection | null>(null);

  const { data, isLoading, isFetching } = useQuery({
    queryKey: ['reports', 'monthly-employee', applied?.employee.id, applied?.year, applied?.month],
    queryFn: () => reportsApi.monthlyEmployee(applied!.employee.id, applied!.year, applied!.month),
    enabled: !!applied,
  });

  const runReport = () => {
    if (!employee) return;
    setApplied({ employee, year, month });
  };

  const exportCsv = () => {
    if (!applied) return;
    reportsApi.exportMonthlyEmployeeCsv(
      applied.employee.id,
      applied.year,
      applied.month,
      `monthly-report-${applied.employee.employee_number}-${applied.year}-${applied.month}.csv`
    );
  };

  return (
    <div>
      <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
        <Button onClick={exportCsv} disabled={!applied || !data} variant="outline" className="gap-2">
          <Download className="h-4 w-4" />
          تصدير CSV
        </Button>
        <div>
          <p className="text-xs font-medium text-muted">التقارير</p>
          <h1 className="mt-1 text-2xl font-bold text-ink">تقرير موظف شهري</h1>
        </div>
      </div>

      <div className="mb-4 grid grid-cols-1 gap-3 rounded-xl border border-hairline bg-surface p-4 sm:grid-cols-2 lg:grid-cols-4">
        <div>
          <Label className="text-xs font-semibold text-ink-2">الموظف</Label>
          <div className="mt-1.5">
            <EmployeePicker value={employee} onChange={setEmployee} placeholder="اختر موظفاً..." />
          </div>
        </div>
        <div>
          <Label className="text-xs font-semibold text-ink-2">السنة</Label>
          <Select value={String(year)} onValueChange={(v) => setYear(Number(v))}>
            <SelectTrigger className="mt-1.5">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {YEAR_OPTIONS.map((y) => (
                <SelectItem key={y} value={String(y)}>
                  <span className="num">{y}</span>
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div>
          <Label className="text-xs font-semibold text-ink-2">الشهر</Label>
          <Select value={String(month)} onValueChange={(v) => setMonth(Number(v))}>
            <SelectTrigger className="mt-1.5">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {ARABIC_MONTHS.map((label, i) => (
                <SelectItem key={label} value={String(i + 1)}>
                  {label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="flex items-end">
          <Button
            onClick={runReport}
            disabled={!employee}
            className="w-full gap-2 bg-brand text-white hover:bg-brand-hover"
          >
            <PlayCircle className="h-4 w-4" />
            تشغيل التقرير
          </Button>
        </div>
      </div>

      {!applied && <ReportEmptyState message="اختر الموظف والشهر ثم اضغط زر تشغيل التقرير لعرض النتائج" />}

      {applied && isLoading && (
        <div className="grid grid-cols-2 gap-4 lg:grid-cols-3">
          {Array.from({ length: 6 }).map((_, i) => (
            <Skeleton key={i} className="h-24 w-full rounded-xl" />
          ))}
        </div>
      )}

      {applied && !isLoading && !data && (
        <ReportEmptyState message="لا توجد بيانات لهذا الموظف في الشهر المحدد" />
      )}

      {applied && !isLoading && data && (
        <div className="space-y-6">
          <div className="flex items-center gap-3 rounded-xl border border-hairline bg-surface p-4">
            <div className="min-w-0">
              <p className="truncate font-medium text-ink">{data.employee.full_name}</p>
              <p className="num text-xs text-muted" dir="ltr">
                {data.employee.employee_number}
              </p>
            </div>
            <p className="mr-auto text-sm text-muted">
              {ARABIC_MONTHS[data.month - 1]} <span className="num">{data.year}</span>
            </p>
          </div>

          <div className="grid grid-cols-2 gap-4 lg:grid-cols-3">
            <ReportStatCard label="نسبة الحضور" value={`${data.attendance_percentage}%`} tone="success" />
            <ReportStatCard label="أيام العمل" value={data.working_days} />
            <ReportStatCard label="أيام الغياب" value={data.absent_days} tone={data.absent_days > 0 ? 'danger' : 'default'} />
            <ReportStatCard label="دقائق التأخير" value={formatMinutesAsHours(data.late_minutes)} tone={data.late_minutes > 0 ? 'warn' : 'default'} />
            <ReportStatCard label="المهام المنجزة" value={data.tasks_completed} tone="success" />
            <ReportStatCard label="الإجازات المأخوذة" value={data.leaves_taken} />
          </div>

          <div className="rounded-xl border border-hairline bg-surface p-5">
            <h2 className="mb-4 text-sm font-semibold text-ink">توزيع أيام الشهر</h2>
            <AttendanceDistributionBar distribution={data.distribution} />
          </div>

          {isFetching && <p className="text-xs text-muted">جارٍ التحديث...</p>}
        </div>
      )}
    </div>
  );
}
