'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import { BarChart3, Download } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
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
import { Spinner } from '@/components/ui/spinner';
import { EmployeeAvatar } from '@/components/attendance/employee-avatar';
import { EmployeeSearchSelect } from '@/components/attendance/employee-search-select';
import { AttendanceStatusBadge } from '@/components/attendance/attendance-status-badge';
import { PaginationBar } from '@/components/attendance/pagination-bar';
import { attendanceApi } from '@/lib/api/endpoints/attendance';
import { formatMinutesAsHours, formatTime } from '@/lib/attendance-format';
import { useScopedCompanyId } from '@/lib/stores/company-scope-store';
import type {
  AttendanceOrigin,
  AttendanceStatus,
  EmployeeSummary,
} from '@/lib/api/types';

const PER_PAGE = 20;

const STATUS_FILTER_OPTIONS: { value: AttendanceStatus | 'all'; label: string }[] = [
  { value: 'all', label: 'كل الحالات' },
  { value: 'present', label: 'حاضر' },
  { value: 'late', label: 'متأخر' },
  { value: 'early_leave', label: 'انصراف مبكر' },
  { value: 'absent', label: 'غائب' },
];

/** Local Y-M-D in the browser's timezone — toISOString() shifts to UTC and
 *  can roll the date backwards an hour before midnight. */
function todayIsoDate(): string {
  const now = new Date();
  const year = now.getFullYear();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  const day = String(now.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

export default function AttendancePage() {
  const [page, setPage] = useState(1);
  // Default to today → today. Users can still clear/change either field,
  // but the common case (checking today's floor) no longer requires three
  // clicks on page load.
  const [from, setFrom] = useState<string>(() => todayIsoDate());
  const [to, setTo] = useState<string>(() => todayIsoDate());
  const [employee, setEmployee] = useState<EmployeeSummary | null>(null);
  const [status, setStatus] = useState<AttendanceStatus | 'all'>('all');
  const [exporting, setExporting] = useState(false);

  // Soft Company Scoping — the switcher writes to this store; the query
  // refetches automatically because the id is part of the key.
  const scopedCompanyId = useScopedCompanyId();

  const filters = {
    date_from: from || undefined,
    date_to: to || undefined,
    employee_id: employee?.id,
    status: status === 'all' ? undefined : status,
    company_id: scopedCompanyId ?? undefined,
  };

  const { data, isLoading, isFetching } = useQuery({
    queryKey: ['attendance', filters, page],
    queryFn: () => attendanceApi.list({ ...filters, page, per_page: PER_PAGE }),
    placeholderData: (prev) => prev,
  });

  // Stat tiles — same filter set as the list, no pagination. Keyed off the
  // raw filter object so a filter change refetches both lanes in parallel.
  const { data: stats, isLoading: statsLoading } = useQuery({
    queryKey: ['attendance', 'stats', filters],
    queryFn: () => attendanceApi.stats(filters),
    placeholderData: (prev) => prev,
  });

  const handlePageChange = (nextPage: number) => setPage(nextPage);

  const resetPageAnd = <T,>(setter: (v: T) => void) => (v: T) => {
    setPage(1);
    setter(v);
  };

  // The report endpoint aggregates by (year, month), so a filter range that
  // straddles two calendar months cannot be represented as a single export.
  // We surface that upstream by disabling the button instead of silently
  // truncating the request to the "from" month.
  const rangeSpansMultipleMonths = (() => {
    if (!from || !to) return false;
    const start = new Date(`${from}T00:00:00`);
    const end = new Date(`${to}T00:00:00`);
    return (
      start.getFullYear() !== end.getFullYear() ||
      start.getMonth() !== end.getMonth()
    );
  })();

  const exportDisabledReason = rangeSpansMultipleMonths
    ? 'التصدير يعمل على شهر واحد فقط — قلّص نطاق التاريخ إلى شهر واحد'
    : undefined;

  const handleExport = async () => {
    if (rangeSpansMultipleMonths) return;
    setExporting(true);
    try {
      // Derive year/month from the "from" filter (or "to" if only "to" is
      // set), falling back to the current month. Pass employee_id through
      // so the CSV honors the same employee filter as the table.
      const anchorSource = from || to || '';
      const anchor = anchorSource ? new Date(`${anchorSource}T00:00:00`) : new Date();
      const year = anchor.getFullYear();
      const month = anchor.getMonth() + 1;
      const blob = await attendanceApi.exportCsv({
        year,
        month,
        employee_id: filters.employee_id,
        company_id: scopedCompanyId ?? undefined,
      });
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `attendance-${year}-${String(month).padStart(2, '0')}.csv`;
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
    } catch {
      toast.error('تعذر تصدير الملف، حاول مرة أخرى');
    } finally {
      setExporting(false);
    }
  };

  const rows = data?.data ?? [];

  return (
    <div className="space-y-4">
      {/*
        RTL header row: title cluster first (right in RTL), Export button
        last (left). Mirrors /reports/attendance after commit 1c35f7f.
        Do not reorder — the visual order flips with the writing direction.
      */}
      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <p className="text-xs text-muted">الحضور</p>
          <h1 className="mt-1 text-2xl font-bold text-ink">سجل الحضور والانصراف</h1>
        </div>
        <Button
          type="button"
          className="gap-2 bg-brand text-white hover:bg-brand-hover"
          onClick={handleExport}
          disabled={exporting || rangeSpansMultipleMonths}
          title={exportDisabledReason}
        >
          {exporting ? <Spinner className="h-4 w-4" /> : <Download className="h-4 w-4" />}
          تصدير CSV
        </Button>
      </div>

      <StatCards stats={stats} loading={statsLoading} />

      <div className="grid grid-cols-1 gap-3 rounded-xl border border-hairline bg-surface p-4 sm:grid-cols-2 lg:grid-cols-4">
        <div>
          <Label className="text-xs font-semibold text-ink-2">من تاريخ</Label>
          <div className="mt-1.5">
            <DatePicker
              value={from}
              onChange={resetPageAnd(setFrom)}
              placeholder="اختر تاريخاً"
              max={to || undefined}
            />
          </div>
        </div>
        <div>
          <Label className="text-xs font-semibold text-ink-2">إلى تاريخ</Label>
          <div className="mt-1.5">
            <DatePicker
              value={to}
              onChange={resetPageAnd(setTo)}
              placeholder="اختر تاريخاً"
              min={from || undefined}
            />
          </div>
        </div>
        <div>
          <Label className="text-xs font-semibold text-ink-2">الموظف</Label>
          <div className="mt-1.5">
            <EmployeeSearchSelect value={employee} onChange={resetPageAnd(setEmployee)} />
          </div>
        </div>
        <div>
          <Label className="text-xs font-semibold text-ink-2">الحالة</Label>
          <Select value={status} onValueChange={resetPageAnd(setStatus) as (v: string) => void}>
            <SelectTrigger className="mt-1.5">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {STATUS_FILTER_OPTIONS.map((opt) => (
                <SelectItem key={opt.value} value={opt.value}>
                  {opt.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>

      <div className="rounded-xl border border-hairline bg-surface">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="text-start">التاريخ</TableHead>
              <TableHead className="text-start">الموظف</TableHead>
              <TableHead className="text-start">وقت الحضور</TableHead>
              <TableHead className="text-start">وقت الانصراف</TableHead>
              <TableHead className="text-start">إجمالي الساعات</TableHead>
              <TableHead className="text-start">التأخير</TableHead>
              <TableHead className="text-start">الانصراف المبكر</TableHead>
              <TableHead className="text-start">الإضافي</TableHead>
              <TableHead className="text-start">الحالة</TableHead>
              <TableHead className="text-start">المصدر</TableHead>
              <TableHead className="text-start" />
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading &&
              Array.from({ length: 6 }).map((_, i) => (
                <TableRow key={`skeleton-${i}`}>
                  {Array.from({ length: 11 }).map((__, j) => (
                    <TableCell key={j}>
                      <Skeleton className="h-4 w-full" />
                    </TableCell>
                  ))}
                </TableRow>
              ))}

            {!isLoading && rows.length === 0 && (
              <TableRow>
                <TableCell colSpan={11} className="py-10 text-center text-sm text-muted">
                  لا توجد سجلات حضور مطابقة للفلاتر المحددة
                </TableCell>
              </TableRow>
            )}

            {!isLoading &&
              rows.map((row) => (
                <TableRow key={row.id}>
                  <TableCell className="whitespace-nowrap">
                    <span className="num" dir="ltr">
                      {row.date}
                    </span>
                  </TableCell>
                  <TableCell>
                    <div className="flex min-w-0 items-center gap-2">
                      <EmployeeAvatar employee={row.employee} size={28} />
                      <span className="min-w-0 truncate font-medium text-ink">
                        {row.employee?.full_name || '—'}
                      </span>
                    </div>
                  </TableCell>
                  <TableCell>
                    <span className="num" dir="ltr">
                      {formatTime(row.check_in_at)}
                    </span>
                  </TableCell>
                  <TableCell>
                    <span className="num" dir="ltr">
                      {formatTime(row.check_out_at)}
                    </span>
                  </TableCell>
                  <TableCell>
                    <span className="num whitespace-nowrap" dir="ltr">
                      {formatMinutesAsHours(row.total_minutes)}
                    </span>
                  </TableCell>
                  <TableCell>
                    <span className="num whitespace-nowrap" dir="ltr">
                      {formatMinutesAsHours(row.late_minutes ?? 0)}
                    </span>
                  </TableCell>
                  <TableCell>
                    <span className="num whitespace-nowrap" dir="ltr">
                      {formatMinutesAsHours(row.early_leave_minutes ?? 0)}
                    </span>
                  </TableCell>
                  <TableCell>
                    <span className="num whitespace-nowrap" dir="ltr">
                      {formatMinutesAsHours(row.overtime_minutes)}
                    </span>
                  </TableCell>
                  <TableCell>
                    <AttendanceStatusBadge status={row.status} />
                  </TableCell>
                  <TableCell>
                    <OriginBadge origin={row.origin} />
                  </TableCell>
                  <TableCell>
                    <Link
                      href={`/attendance/employee/${row.employee_id}/monthly`}
                      title="الملخص الشهري"
                      className="grid h-8 w-8 place-items-center rounded-md text-ink-2 transition-colors hover:bg-surface-2 hover:text-brand"
                    >
                      <BarChart3 className="h-4 w-4" />
                    </Link>
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
            onPageChange={handlePageChange}
          />
        )}
      </div>
      {isFetching && !isLoading && (
        <p className="mt-2 flex items-center gap-2 text-xs text-muted">
          <Spinner className="h-3 w-3" />
          جارٍ التحديث...
        </p>
      )}
    </div>
  );
}

/* -------------------------------------------------------------------------- */
/* Stat tiles                                                                 */
/* -------------------------------------------------------------------------- */

type StatTone = 'ink' | 'success' | 'warn' | 'danger' | 'brand' | 'muted';

type StatTileProps = {
  label: string;
  value: number | string;
  tone?: StatTone;
};

function StatTile({ label, value, tone = 'ink' }: StatTileProps) {
  const toneClass: Record<StatTone, string> = {
    ink: 'text-ink',
    success: 'text-success',
    warn: 'text-warn-ink',
    danger: 'text-danger',
    brand: 'text-brand',
    muted: 'text-muted',
  };
  return (
    <Card className="border-hairline bg-surface p-4">
      <p className="text-xs text-muted">{label}</p>
      <p className={`num mt-1 text-2xl font-bold ${toneClass[tone]}`} dir="ltr">
        {value}
      </p>
    </Card>
  );
}

type AttendanceStats = Awaited<ReturnType<typeof attendanceApi.stats>>;

function StatCards({ stats, loading }: { stats: AttendanceStats | undefined; loading: boolean }) {
  if (loading && !stats) {
    return (
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        {Array.from({ length: 10 }).map((_, i) => (
          <Card key={i} className="border-hairline bg-surface p-4">
            <Skeleton className="h-3 w-20" />
            <Skeleton className="mt-2 h-6 w-16" />
          </Card>
        ))}
      </div>
    );
  }

  if (!stats) return null;

  // Hours tiles get a one-decimal cap so a 7.333... reading doesn't blow
  // the tile's visual width; the backend already rounds to two decimals.
  const asHours = (value: number) => value.toFixed(1);

  return (
    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
      <StatTile label="الموظفون" value={stats.unique_employees} tone="brand" />
      <StatTile label="الحضور" value={stats.present_count} tone="success" />
      <StatTile label="الغياب" value={stats.absent_count} tone="danger" />
      <StatTile label="التأخير" value={stats.late_count} tone="warn" />
      <StatTile label="إجازات" value={stats.on_leave_count} />
      <StatTile label="ساعات العمل" value={asHours(stats.total_hours)} />
      <StatTile label="وقت إضافي" value={asHours(stats.total_overtime_hours)} tone="success" />
      <StatTile label="متوسط الساعات/يوم" value={asHours(stats.avg_hours_per_day)} />
      <StatTile label="داخل الموقع" value={stats.onsite_count} tone="success" />
      <StatTile label="خارج الموقع" value={stats.remote_count} tone="warn" />
    </div>
  );
}

/* -------------------------------------------------------------------------- */
/* Origin badge                                                               */
/* -------------------------------------------------------------------------- */

// Reuses the same -soft/text token pairs that AttendanceStatusBadge
// already consumes, so the two pills visually belong in the same row
// instead of inventing a parallel palette.
const ORIGIN_PRESENTATION: Record<AttendanceOrigin, { label: string; className: string }> = {
  onsite: {
    label: 'داخل الموقع',
    className: 'bg-success-soft text-success',
  },
  remote: {
    label: 'عن بُعد',
    className: 'bg-warn-soft text-warn-ink',
  },
  unknown: {
    label: '—',
    className: 'bg-surface-2 text-muted',
  },
};

function OriginBadge({ origin }: { origin: AttendanceOrigin }) {
  const { label, className } = ORIGIN_PRESENTATION[origin] ?? ORIGIN_PRESENTATION.unknown;
  return (
    <span
      className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${className}`}
    >
      {label}
    </span>
  );
}
