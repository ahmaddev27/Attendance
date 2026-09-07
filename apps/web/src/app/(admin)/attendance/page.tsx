'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { toast } from 'sonner';
import { BarChart3, Download } from 'lucide-react';

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
import { Spinner } from '@/components/ui/spinner';
import { EmployeeAvatar } from '@/components/attendance/employee-avatar';
import { EmployeeSearchSelect } from '@/components/attendance/employee-search-select';
import { AttendanceStatusBadge } from '@/components/attendance/attendance-status-badge';
import { PaginationBar } from '@/components/attendance/pagination-bar';
import { attendanceApi } from '@/lib/api/endpoints/attendance';
import { formatMinutesAsHours, formatTime } from '@/lib/attendance-format';
import type { AttendanceStatus, EmployeeSummary } from '@/lib/api/types';

const PER_PAGE = 20;

const STATUS_FILTER_OPTIONS: { value: AttendanceStatus | 'all'; label: string }[] = [
  { value: 'all', label: 'كل الحالات' },
  { value: 'present', label: 'حاضر' },
  { value: 'late', label: 'متأخر' },
  { value: 'early_leave', label: 'انصراف مبكر' },
  { value: 'absent', label: 'غائب' },
];

export default function AttendancePage() {
  const [page, setPage] = useState(1);
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  const [employee, setEmployee] = useState<EmployeeSummary | null>(null);
  const [status, setStatus] = useState<AttendanceStatus | 'all'>('all');
  const [exporting, setExporting] = useState(false);

  const filters = {
    from: from || undefined,
    to: to || undefined,
    employee_id: employee?.id,
    status: status === 'all' ? undefined : status,
  };

  const { data, isLoading, isFetching } = useQuery({
    queryKey: ['attendance', filters, page],
    queryFn: () => attendanceApi.list({ ...filters, page, per_page: PER_PAGE }),
    placeholderData: (prev) => prev,
  });

  const handlePageChange = (nextPage: number) => setPage(nextPage);

  const resetPageAnd = <T,>(setter: (v: T) => void) => (v: T) => {
    setPage(1);
    setter(v);
  };

  const handleExport = async () => {
    setExporting(true);
    try {
      const blob = await attendanceApi.exportCsv(filters);
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `attendance-${from || 'all'}-${to || 'all'}.csv`;
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
    <div>
      <div className="mb-6">
        <p className="text-xs text-muted">الحضور</p>
        <h1 className="mt-1 text-2xl font-bold text-ink">سجل الحضور والانصراف</h1>
      </div>

      <div className="mb-4 grid grid-cols-1 gap-3 rounded-xl border border-hairline bg-surface p-4 sm:grid-cols-2 lg:grid-cols-5">
        <div>
          <Label className="text-xs font-semibold text-ink-2">من تاريخ</Label>
          <Input
            type="date"
            value={from}
            onChange={(e) => resetPageAnd(setFrom)(e.target.value)}
            className="mt-1.5"
            dir="ltr"
          />
        </div>
        <div>
          <Label className="text-xs font-semibold text-ink-2">إلى تاريخ</Label>
          <Input
            type="date"
            value={to}
            onChange={(e) => resetPageAnd(setTo)(e.target.value)}
            className="mt-1.5"
            dir="ltr"
          />
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
        <div className="flex items-end">
          <Button
            type="button"
            variant="outline"
            className="w-full"
            onClick={handleExport}
            disabled={exporting}
          >
            {exporting ? <Spinner /> : <Download className="h-4 w-4" />}
            تصدير CSV
          </Button>
        </div>
      </div>

      <div className="rounded-xl border border-hairline bg-surface">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="text-right">التاريخ</TableHead>
              <TableHead className="text-right">الموظف</TableHead>
              <TableHead className="text-right">وقت الحضور</TableHead>
              <TableHead className="text-right">وقت الانصراف</TableHead>
              <TableHead className="text-right">إجمالي الساعات</TableHead>
              <TableHead className="text-right">التأخير</TableHead>
              <TableHead className="text-right">الانصراف المبكر</TableHead>
              <TableHead className="text-right">الإضافي</TableHead>
              <TableHead className="text-right">الحالة</TableHead>
              <TableHead className="text-right" />
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading &&
              Array.from({ length: 6 }).map((_, i) => (
                <TableRow key={`skeleton-${i}`}>
                  {Array.from({ length: 10 }).map((__, j) => (
                    <TableCell key={j}>
                      <Skeleton className="h-4 w-full" />
                    </TableCell>
                  ))}
                </TableRow>
              ))}

            {!isLoading && rows.length === 0 && (
              <TableRow>
                <TableCell colSpan={10} className="py-10 text-center text-sm text-muted">
                  لا توجد سجلات حضور مطابقة للفلاتر المحددة
                </TableCell>
              </TableRow>
            )}

            {!isLoading &&
              rows.map((row) => (
                <TableRow key={row.id}>
                  <TableCell className="num whitespace-nowrap">{row.date}</TableCell>
                  <TableCell>
                    <div className="flex items-center gap-2">
                      <EmployeeAvatar employee={row.employee} size={28} />
                      <span className="truncate font-medium text-ink">{row.employee.full_name}</span>
                    </div>
                  </TableCell>
                  <TableCell className="num">{formatTime(row.check_in_at)}</TableCell>
                  <TableCell className="num">{formatTime(row.check_out_at)}</TableCell>
                  <TableCell className="num">{formatMinutesAsHours(row.total_minutes)}</TableCell>
                  <TableCell className="num">{row.late_minutes ?? 0}</TableCell>
                  <TableCell className="num">{row.early_leave_minutes ?? 0}</TableCell>
                  <TableCell className="num">{row.overtime_minutes}</TableCell>
                  <TableCell>
                    <AttendanceStatusBadge status={row.status} />
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
          <Spinner className="h-3 w-3" /> جارٍ التحديث...
        </p>
      )}
    </div>
  );
}
