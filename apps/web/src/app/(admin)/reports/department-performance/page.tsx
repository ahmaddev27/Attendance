'use client';

import * as React from 'react';
import { useQuery } from '@tanstack/react-query';
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
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Skeleton } from '@/components/ui/skeleton';
import { ReportEmptyState } from '@/components/reports/report-empty-state';
import { departmentsApi } from '@/lib/api/endpoints/departments';
import { reportsApi } from '@/lib/api/endpoints/reports';
import type { DepartmentPerformanceParams } from '@/lib/api/types';
import { cn } from '@/lib/utils';

export default function DepartmentPerformanceReportPage() {
  const [departmentId, setDepartmentId] = React.useState<string | undefined>();
  const [from, setFrom] = React.useState('');
  const [to, setTo] = React.useState('');
  const [appliedParams, setAppliedParams] = React.useState<DepartmentPerformanceParams | null>(null);

  const { data: departments } = useQuery({
    queryKey: ['departments', 'filter-options'],
    queryFn: async () => (await departmentsApi.list({ per_page: 100, is_active: true })).data.data,
    staleTime: 60_000,
  });

  const { data, isLoading, isFetching } = useQuery({
    queryKey: ['reports', 'department-performance', appliedParams],
    queryFn: () => reportsApi.departmentPerformance(appliedParams ?? {}),
    enabled: !!appliedParams,
  });

  const runReport = () => {
    setAppliedParams({
      department_id: departmentId ? Number(departmentId) : undefined,
      from: from || undefined,
      to: to || undefined,
    });
  };

  const exportCsv = () => {
    if (!appliedParams) return;
    reportsApi.exportDepartmentPerformanceCsv(appliedParams, 'department-performance.csv');
  };

  const rows = data ?? [];

  return (
    <div>
      <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
        <Button
          onClick={exportCsv}
          disabled={!appliedParams || rows.length === 0}
          variant="outline"
          className="gap-2"
        >
          <Download className="h-4 w-4" />
          تصدير CSV
        </Button>
        <div>
          <p className="text-xs font-medium text-muted">التقارير</p>
          <h1 className="mt-1 text-2xl font-bold text-ink">تقرير أداء الأقسام</h1>
        </div>
      </div>

      <div className="mb-4 grid grid-cols-1 gap-3 rounded-xl border border-hairline bg-surface p-4 sm:grid-cols-2 lg:grid-cols-4">
        <div>
          <Label className="text-xs font-semibold text-ink-2">القسم</Label>
          <Select
            value={departmentId ?? '__all__'}
            onValueChange={(v) => setDepartmentId(v === '__all__' ? undefined : v)}
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
          <Label className="text-xs font-semibold text-ink-2">من تاريخ</Label>
          <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="mt-1.5" dir="ltr" />
        </div>
        <div>
          <Label className="text-xs font-semibold text-ink-2">إلى تاريخ</Label>
          <Input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="mt-1.5" dir="ltr" />
        </div>
        <div className="flex items-end">
          <Button onClick={runReport} className="w-full gap-2 bg-brand text-white hover:bg-brand-hover">
            <PlayCircle className="h-4 w-4" />
            تشغيل التقرير
          </Button>
        </div>
      </div>

      {!appliedParams && <ReportEmptyState />}

      {appliedParams && (
        <div className="overflow-hidden rounded-xl border border-hairline bg-surface">
          <div className="overflow-x-auto">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="text-right">القسم</TableHead>
                  <TableHead className="text-right">عدد الموظفين</TableHead>
                  <TableHead className="text-right">متوسط نسبة الحضور</TableHead>
                  <TableHead className="text-right">معدل إنجاز المهام</TableHead>
                  <TableHead className="text-right">المهام المتأخرة</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {isLoading &&
                  Array.from({ length: 5 }).map((_, i) => (
                    <TableRow key={`skeleton-${i}`}>
                      {Array.from({ length: 5 }).map((__, j) => (
                        <TableCell key={j}>
                          <Skeleton className="h-4 w-full" />
                        </TableCell>
                      ))}
                    </TableRow>
                  ))}

                {!isLoading && rows.length === 0 && (
                  <TableRow>
                    <TableCell colSpan={5} className="py-10 text-center text-sm text-muted">
                      لا توجد بيانات مطابقة للفلاتر المحددة
                    </TableCell>
                  </TableRow>
                )}

                {!isLoading &&
                  rows.map((row) => (
                    <TableRow key={row.department_id}>
                      <TableCell className="font-medium text-ink">{row.department_name}</TableCell>
                      <TableCell className="num">{row.employees_count}</TableCell>
                      <TableCell className="num">{row.avg_attendance_percentage}%</TableCell>
                      <TableCell className="num">{row.task_completion_rate}%</TableCell>
                      <TableCell className={cn('num', row.overdue_tasks > 0 && 'font-semibold text-danger')}>
                        {row.overdue_tasks}
                      </TableCell>
                    </TableRow>
                  ))}
              </TableBody>
            </Table>
          </div>
          {isFetching && !isLoading && (
            <p className="border-t border-hairline px-4 py-2 text-xs text-muted">جارٍ التحديث...</p>
          )}
        </div>
      )}
    </div>
  );
}
