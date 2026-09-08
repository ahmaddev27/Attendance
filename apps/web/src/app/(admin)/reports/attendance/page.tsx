'use client';

import * as React from 'react';
import { useQuery } from '@tanstack/react-query';
import { BarChart3, Download, Loader2 } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Skeleton } from '@/components/ui/skeleton';
import { departmentsApi } from '@/lib/api/endpoints/departments';
import { reportsApi, type AttendanceReportRow } from '@/lib/api/endpoints/reports';

type ExportFormat = 'csv' | 'xlsx' | 'pdf';

const EXPORT_MIME: Record<ExportFormat, string> = {
  csv: 'text/csv;charset=utf-8;',
  xlsx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  pdf: 'application/pdf',
};

const EXPORT_LABEL: Record<ExportFormat, string> = {
  csv: 'CSV',
  xlsx: 'Excel',
  pdf: 'PDF',
};

const MONTHS_AR = [
  'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
  'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر',
];

export default function AttendanceReportPage() {
  const now = new Date();
  const [year, setYear] = React.useState(now.getFullYear());
  const [month, setMonth] = React.useState(now.getMonth() + 1);
  const [departmentId, setDepartmentId] = React.useState<number | undefined>();
  const [downloading, setDownloading] = React.useState<ExportFormat | null>(null);
  const [menuOpen, setMenuOpen] = React.useState(false);

  const { data: departments } = useQuery({
    queryKey: ['departments', 'filter-options'],
    queryFn: async () => (await departmentsApi.list({ per_page: 100, is_active: true })).data.data,
    staleTime: 60_000,
  });

  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['reports', 'attendance-monthly', { year, month, departmentId }],
    queryFn: async () =>
      (await reportsApi.attendanceMonthly({ year, month, department_id: departmentId })).data.data,
  });

  const rows: AttendanceReportRow[] = data ?? [];

  const handleDownload = async (format: ExportFormat) => {
    // Guard: axios' Blob transport returns application/json when Laravel
    // sends a 4xx, so the caller re-types the blob per requested format.
    const fetchers: Record<ExportFormat, () => Promise<{ data: Blob }>> = {
      csv: () => reportsApi.attendanceMonthlyCsv({ year, month, department_id: departmentId }),
      xlsx: () => reportsApi.attendanceMonthlyXlsx({ year, month, department_id: departmentId }),
      pdf: () => reportsApi.attendanceMonthlyPdf({ year, month, department_id: departmentId }),
    };

    try {
      setDownloading(format);
      setMenuOpen(false);
      const res = await fetchers[format]();
      const blob = new Blob([res.data], { type: EXPORT_MIME[format] });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `attendance-${year}-${String(month).padStart(2, '0')}.${format}`;
      document.body.appendChild(a);
      a.click();
      a.remove();
      window.URL.revokeObjectURL(url);
    } catch {
      toast.error('تعذّر تحميل الملف');
    } finally {
      setDownloading(null);
    }
  };

  const totals = React.useMemo(() => {
    return rows.reduce(
      (acc, r) => ({
        present: acc.present + r.present_days,
        late: acc.late + r.late_days,
        absent: acc.absent + r.absent_days,
        leave: acc.leave + r.leave_days,
        totalMinutes: acc.totalMinutes + r.total_minutes,
      }),
      { present: 0, late: 0, absent: 0, leave: 0, totalMinutes: 0 }
    );
  }, [rows]);

  return (
    <div className="space-y-4">
      <div>
        <h1 className="flex items-center gap-2 text-2xl font-bold text-ink">
          <BarChart3 className="h-6 w-6 text-brand" /> تقرير الحضور الشهري
        </h1>
        <p className="mt-1 text-sm text-muted">ملخّص أيام الحضور والتأخير والغياب لكل موظف — قابل للتصدير CSV / Excel / PDF.</p>
      </div>

      {/* Filters */}
      <Card className="border-hairline bg-surface p-4">
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <label className="text-xs">
            <span className="mb-1 block font-semibold text-ink-2">السنة</span>
            <select
              value={year}
              onChange={(e) => setYear(Number(e.target.value))}
              className="w-full rounded-lg border border-hairline bg-surface px-3 py-2 text-sm"
              dir="ltr"
            >
              {[year - 1, year, year + 1].map((y) => (
                <option key={y} value={y}>{y}</option>
              ))}
            </select>
          </label>
          <label className="text-xs">
            <span className="mb-1 block font-semibold text-ink-2">الشهر</span>
            <select
              value={month}
              onChange={(e) => setMonth(Number(e.target.value))}
              className="w-full rounded-lg border border-hairline bg-surface px-3 py-2 text-sm"
            >
              {MONTHS_AR.map((name, i) => (
                <option key={i} value={i + 1}>{name}</option>
              ))}
            </select>
          </label>
          <label className="text-xs">
            <span className="mb-1 block font-semibold text-ink-2">القسم</span>
            <select
              value={departmentId ?? ''}
              onChange={(e) => setDepartmentId(e.target.value ? Number(e.target.value) : undefined)}
              className="w-full rounded-lg border border-hairline bg-surface px-3 py-2 text-sm"
            >
              <option value="">كل الأقسام</option>
              {departments?.map((d) => (
                <option key={d.id} value={d.id}>{d.name}</option>
              ))}
            </select>
          </label>
          <div className="flex items-end">
            <Popover open={menuOpen} onOpenChange={setMenuOpen}>
              <PopoverTrigger asChild>
                <Button
                  type="button"
                  className="w-full bg-brand hover:bg-brand-hover text-white"
                  disabled={downloading !== null || rows.length === 0}
                >
                  {downloading !== null ? (
                    <Loader2 className="me-1 h-4 w-4 animate-spin" />
                  ) : (
                    <Download className="me-1 h-4 w-4" />
                  )}
                  تصدير
                </Button>
              </PopoverTrigger>
              <PopoverContent align="end" className="w-40 p-1">
                {(['csv', 'xlsx', 'pdf'] as const).map((fmt) => (
                  <button
                    key={fmt}
                    type="button"
                    onClick={() => handleDownload(fmt)}
                    disabled={downloading !== null}
                    className="flex w-full items-center gap-2 rounded-md px-3 py-2 text-sm text-ink hover:bg-surface-2 disabled:opacity-60"
                  >
                    {downloading === fmt ? (
                      <Loader2 className="h-4 w-4 animate-spin" />
                    ) : (
                      <Download className="h-4 w-4" />
                    )}
                    {EXPORT_LABEL[fmt]}
                  </button>
                ))}
              </PopoverContent>
            </Popover>
          </div>
        </div>
      </Card>

      {/* Totals strip */}
      {rows.length > 0 && (
        <div className="grid grid-cols-2 gap-3 md:grid-cols-4 lg:grid-cols-5">
          <TotalCell label="الموظفون" value={rows.length} />
          <TotalCell label="أيام حضور" value={totals.present} />
          <TotalCell label="أيام تأخير" value={totals.late} tone="warn" />
          <TotalCell label="أيام غياب" value={totals.absent} tone="danger" />
          <TotalCell label="ساعات" value={Math.round(totals.totalMinutes / 60)} />
        </div>
      )}

      {/* Table */}
      <Card className="border-hairline bg-surface p-0">
        <div className="overflow-x-auto">
          <table className="w-full min-w-[720px] text-start text-sm">
            <thead className="border-b border-hairline bg-surface-2">
              <tr>
                <Th>#</Th>
                <Th>الاسم</Th>
                <Th>القسم</Th>
                <Th className="text-center">حضور</Th>
                <Th className="text-center">تأخير</Th>
                <Th className="text-center">غياب</Th>
                <Th className="text-center">إجازة</Th>
                <Th className="text-center">ساعات</Th>
                <Th className="text-center">وقت إضافي</Th>
              </tr>
            </thead>
            <tbody>
              {isError ? (
                <tr>
                  <td colSpan={9} className="p-8 text-center text-sm text-danger">
                    تعذّر تحميل التقرير.{' '}
                    <button onClick={() => refetch()} className="font-semibold underline">
                      إعادة المحاولة
                    </button>
                  </td>
                </tr>
              ) : isLoading ? (
                Array.from({ length: 6 }).map((_, i) => (
                  <tr key={i} className="border-b border-hairline">
                    <td colSpan={9} className="p-3">
                      <Skeleton className="h-6 w-full" />
                    </td>
                  </tr>
                ))
              ) : rows.length === 0 ? (
                <tr>
                  <td colSpan={9} className="p-8 text-center text-sm text-muted">
                    لا توجد بيانات لهذا الفلتر.
                  </td>
                </tr>
              ) : (
                rows.map((r) => (
                  <tr key={r.employee_id} className="border-b border-hairline last:border-b-0">
                    <Td className="num" dir="ltr">{r.employee_number}</Td>
                    <Td className="font-medium text-ink">{r.full_name}</Td>
                    <Td className="text-ink-2">{r.department ?? '—'}</Td>
                    <Td className="num text-center" dir="ltr">{r.present_days}</Td>
                    <Td className="num text-center text-warn-ink" dir="ltr">{r.late_days || '—'}</Td>
                    <Td className="num text-center text-danger" dir="ltr">{r.absent_days || '—'}</Td>
                    <Td className="num text-center" dir="ltr">{r.leave_days || '—'}</Td>
                    <Td className="num text-center" dir="ltr">
                      {Math.round(r.total_minutes / 60)}
                    </Td>
                    <Td className="num text-center text-success" dir="ltr">
                      {r.overtime_minutes ? Math.round(r.overtime_minutes / 60) : '—'}
                    </Td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  );
}

function Th({ children, className = '' }: { children: React.ReactNode; className?: string }) {
  return <th className={`px-4 py-3 text-xs font-semibold text-ink-2 ${className}`}>{children}</th>;
}

function Td({ children, className = '', ...rest }: React.HTMLAttributes<HTMLTableCellElement>) {
  return (
    <td className={`px-4 py-3 text-sm ${className}`} {...rest}>
      {children}
    </td>
  );
}

function TotalCell({ label, value, tone }: { label: string; value: number; tone?: 'warn' | 'danger' }) {
  const toneClass =
    tone === 'warn' ? 'text-warn-ink' : tone === 'danger' ? 'text-danger' : 'text-ink';
  return (
    <Card className="border-hairline bg-surface p-4">
      <p className="text-xs text-muted">{label}</p>
      <p className={`num mt-1 text-2xl font-bold ${toneClass}`} dir="ltr">{value}</p>
    </Card>
  );
}
