'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { BarChart3, Download, Pencil, RotateCcw, Trash2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { DatePicker } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
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
  Attendance,
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
  // Admin manual-correction dialogs. One state slot per dialog so a user
  // closing the edit sheet never accidentally cancels a pending delete.
  const [editTarget, setEditTarget] = useState<Attendance | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<Attendance | null>(null);
  const queryClient = useQueryClient();

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ['attendance'] });
  };

  const updateMutation = useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: Parameters<typeof attendanceApi.update>[1] }) =>
      attendanceApi.update(id, payload),
    onSuccess: () => {
      toast.success('تم حفظ التعديل');
      setEditTarget(null);
      invalidate();
    },
    onError: () => toast.error('تعذّر حفظ التعديل'),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => attendanceApi.remove(id),
    onSuccess: () => {
      toast.success('تم حذف السجل');
      setDeleteTarget(null);
      invalidate();
    },
    onError: () => toast.error('تعذّر حذف السجل'),
  });

  const clearCheckOutMutation = useMutation({
    mutationFn: (id: number) => attendanceApi.clearCheckOut(id),
    onSuccess: () => {
      toast.success('تم إلغاء الانصراف');
      invalidate();
    },
    onError: () => toast.error('تعذّر إلغاء الانصراف'),
  });

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

  // Owner's rule 2026-10-04: the admin page should visibly pick up a scan
  // the moment it lands in the DB — otherwise the owner refreshes the
  // tab and wonders why his just-finished kiosk scan isn't on the list.
  // 20s is a compromise: tight enough that no admin reaches for F5
  // first, loose enough that the DB doesn't take a count query every
  // second across a 12-column aggregate.
  const LIVE_REFETCH_MS = 20_000;

  const { data, isLoading, isFetching } = useQuery({
    queryKey: ['attendance', filters, page],
    queryFn: () => attendanceApi.list({ ...filters, page, per_page: PER_PAGE }),
    placeholderData: (prev) => prev,
    refetchInterval: LIVE_REFETCH_MS,
    refetchIntervalInBackground: false,
  });

  // Stat tiles — same filter set as the list, no pagination. Keyed off the
  // raw filter object so a filter change refetches both lanes in parallel.
  const { data: stats, isLoading: statsLoading } = useQuery({
    queryKey: ['attendance', 'stats', filters],
    queryFn: () => attendanceApi.stats(filters),
    placeholderData: (prev) => prev,
    refetchInterval: LIVE_REFETCH_MS,
    refetchIntervalInBackground: false,
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
              <TableHead className="text-start">IP الحضور</TableHead>
              <TableHead className="text-start" />
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading &&
              Array.from({ length: 6 }).map((_, i) => (
                <TableRow key={`skeleton-${i}`}>
                  {Array.from({ length: 12 }).map((__, j) => (
                    <TableCell key={j}>
                      <Skeleton className="h-4 w-full" />
                    </TableCell>
                  ))}
                </TableRow>
              ))}

            {!isLoading && rows.length === 0 && (
              <TableRow>
                <TableCell colSpan={12} className="py-10 text-center text-sm text-muted">
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
                      {row.check_in_at_display ?? formatTime(row.check_in_at)}
                    </span>
                  </TableCell>
                  <TableCell>
                    <span className="num" dir="ltr">
                      {row.check_out_at_display ?? formatTime(row.check_out_at)}
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
                    {row.check_in_ip ? (
                      <span
                        className="num rounded-md bg-surface-2 px-2 py-0.5 text-xs text-ink-2"
                        dir="ltr"
                        title="عنوان IP لجهاز تسجيل الحضور"
                      >
                        {row.check_in_ip}
                      </span>
                    ) : (
                      <span className="text-xs text-muted">—</span>
                    )}
                  </TableCell>
                  <TableCell>
                    <div className="flex items-center gap-1">
                      <Link
                        href={`/attendance/employee/${row.employee_id}/monthly`}
                        title="الملخص الشهري"
                        className="grid h-8 w-8 place-items-center rounded-md text-ink-2 transition-colors hover:bg-surface-2 hover:text-brand"
                      >
                        <BarChart3 className="h-4 w-4" />
                      </Link>
                      <button
                        type="button"
                        onClick={() => setEditTarget(row)}
                        title="تعديل التوقيت أو الحالة"
                        className="grid h-8 w-8 place-items-center rounded-md text-ink-2 transition-colors hover:bg-surface-2 hover:text-brand"
                      >
                        <Pencil className="h-4 w-4" />
                      </button>
                      {row.check_out_at && (
                        <button
                          type="button"
                          onClick={() => clearCheckOutMutation.mutate(row.id)}
                          disabled={clearCheckOutMutation.isPending}
                          title="إلغاء الانصراف وإعادة فتح اليوم"
                          className="grid h-8 w-8 place-items-center rounded-md text-ink-2 transition-colors hover:bg-amber-50 hover:text-amber-700 disabled:opacity-50"
                        >
                          <RotateCcw className="h-4 w-4" />
                        </button>
                      )}
                      <button
                        type="button"
                        onClick={() => setDeleteTarget(row)}
                        title="حذف السجل"
                        className="grid h-8 w-8 place-items-center rounded-md text-ink-2 transition-colors hover:bg-danger-soft hover:text-danger"
                      >
                        <Trash2 className="h-4 w-4" />
                      </button>
                    </div>
                  </TableCell>
                </TableRow>
              ))}
          </TableBody>
        </Table>

        <EditAttendanceDialog
          attendance={editTarget}
          onClose={() => setEditTarget(null)}
          onSave={(payload) => editTarget && updateMutation.mutate({ id: editTarget.id, payload })}
          saving={updateMutation.isPending}
        />

        <AlertDialog open={!!deleteTarget} onOpenChange={(open) => !open && setDeleteTarget(null)}>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>حذف سجل الحضور</AlertDialogTitle>
              <AlertDialogDescription>
                سيتم حذف سجل {deleteTarget?.employee?.full_name ?? ''} ليوم{' '}
                <span className="num" dir="ltr">{deleteTarget?.date}</span> نهائياً. العملية غير قابلة للتراجع.
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>إلغاء</AlertDialogCancel>
              <AlertDialogAction
                className="bg-danger text-white hover:bg-danger/90"
                onClick={() => deleteTarget && deleteMutation.mutate(deleteTarget.id)}
              >
                حذف
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>

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
      <StatTile label="الموظفون" value={stats.active_headcount} tone="brand" />
      <StatTile label="الحضور" value={stats.scanned_in_count} tone="success" />
      <StatTile label="في الوقت" value={stats.present_count} tone="success" />
      <StatTile label="التأخير" value={stats.late_count} tone="warn" />
      <StatTile label="الغياب" value={stats.absent_count} tone="danger" />
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

/* -------------------------------------------------------------------------- */
/* Edit dialog                                                                */
/* -------------------------------------------------------------------------- */

/** Converts a UTC ISO datetime into the `datetime-local` input's expected
 *  shape in the viewer's local timezone. The native input strips the TZ
 *  suffix so we must do the clock-offset ourselves. */
function toLocalInputValue(iso: string | null | undefined): string {
  if (!iso) return '';
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return '';
  const pad = (n: number) => String(n).padStart(2, '0');
  return (
    `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}` +
    `T${pad(date.getHours())}:${pad(date.getMinutes())}`
  );
}

/** Converts the `datetime-local` string back into an ISO UTC string so the
 *  backend never has to guess what offset the admin typed in. */
function fromLocalInputValue(local: string): string | null {
  if (!local) return null;
  const parsed = new Date(local);
  if (Number.isNaN(parsed.getTime())) return null;
  return parsed.toISOString();
}

const EDIT_STATUS_OPTIONS: { value: AttendanceStatus; label: string }[] = [
  { value: 'present', label: 'حاضر' },
  { value: 'late', label: 'متأخر' },
  { value: 'early_leave', label: 'انصراف مبكر' },
  { value: 'absent', label: 'غائب' },
  { value: 'on_leave', label: 'في إجازة' },
  { value: 'remote', label: 'عن بُعد' },
  { value: 'business_mission', label: 'في مهمة عمل' },
];

type EditDialogProps = {
  attendance: Attendance | null;
  onClose: () => void;
  onSave: (payload: { check_in_at?: string | null; check_out_at?: string | null; status?: AttendanceStatus; notes?: string | null }) => void;
  saving: boolean;
};

function EditAttendanceDialog({ attendance, onClose, onSave, saving }: EditDialogProps) {
  const [checkIn, setCheckIn] = useState<string>(() => toLocalInputValue(attendance?.check_in_at));
  const [checkOut, setCheckOut] = useState<string>(() => toLocalInputValue(attendance?.check_out_at));
  const [status, setStatus] = useState<AttendanceStatus>(attendance?.status ?? 'present');
  const [notes, setNotes] = useState<string>('');

  const open = !!attendance;

  // When a new row is selected, re-hydrate the fields from it. Keyed on
  // the row id so re-renders during saving don't blow away the admin's
  // in-progress edits. Switched from useMemo (side-effect anti-pattern
  // that silently dropped writes under React 18 strict-mode double
  // rendering — the root cause of the owner's 2026-10-04 "edit doesn't
  // save the hours" report) to useEffect, which is the right tool for
  // "run this once when the row id changes".
  const rowKey = attendance ? `${attendance.id}` : '';
  useEffect(() => {
    if (attendance) {
      setCheckIn(toLocalInputValue(attendance.check_in_at));
      setCheckOut(toLocalInputValue(attendance.check_out_at));
      setStatus(attendance.status);
      setNotes('');
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [rowKey]);

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    const payload: Parameters<typeof onSave>[0] = {
      check_in_at: fromLocalInputValue(checkIn),
      check_out_at: checkOut ? fromLocalInputValue(checkOut) : null,
      status,
    };
    if (notes.trim()) payload.notes = notes.trim();
    onSave(payload);
  };

  return (
    <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>تعديل سجل الحضور</DialogTitle>
          <DialogDescription>
            {attendance?.employee?.full_name} ·{' '}
            <span className="num" dir="ltr">{attendance?.date}</span>
          </DialogDescription>
        </DialogHeader>
        <form onSubmit={submit} className="space-y-4">
          <div>
            <Label className="text-xs font-semibold text-ink-2">وقت الحضور</Label>
            <Input
              type="datetime-local"
              value={checkIn}
              onChange={(e) => setCheckIn(e.target.value)}
              className="num mt-1.5"
              dir="ltr"
              required
            />
          </div>
          <div>
            <Label className="text-xs font-semibold text-ink-2">وقت الانصراف</Label>
            <Input
              type="datetime-local"
              value={checkOut}
              onChange={(e) => setCheckOut(e.target.value)}
              className="num mt-1.5"
              dir="ltr"
            />
            <p className="mt-1 text-[11px] text-muted">اتركه فارغاً لإعادة فتح اليوم.</p>
          </div>
          <div>
            <Label className="text-xs font-semibold text-ink-2">الحالة</Label>
            <Select value={status} onValueChange={(v) => setStatus(v as AttendanceStatus)}>
              <SelectTrigger className="mt-1.5">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {EDIT_STATUS_OPTIONS.map((opt) => (
                  <SelectItem key={opt.value} value={opt.value}>
                    {opt.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div>
            <Label className="text-xs font-semibold text-ink-2">ملاحظات (اختياري)</Label>
            <Input
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              placeholder="سبب التعديل مثلاً"
              className="mt-1.5"
            />
          </div>
          <DialogFooter>
            <Button type="button" variant="ghost" onClick={onClose} disabled={saving}>
              إلغاء
            </Button>
            <Button type="submit" disabled={saving} className="gap-2 bg-brand text-white hover:bg-brand-hover">
              {saving && <Spinner />}
              حفظ
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
