'use client';

import * as React from 'react';

import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Skeleton } from '@/components/ui/skeleton';
import { PaginationBar } from '@/components/attendance/pagination-bar';
import type { ReportRow } from '@/lib/api/types';
import { cn } from '@/lib/utils';

/** Arabic header labels for the field names report rows commonly carry. */
const KNOWN_HEADER_LABELS: Record<string, string> = {
  id: 'الرقم',
  employee_id: 'رقم الموظف',
  employee_number: 'الرقم الوظيفي',
  employee_name: 'الموظف',
  employee: 'الموظف',
  department: 'القسم',
  department_id: 'القسم',
  department_name: 'القسم',
  date: 'التاريخ',
  status: 'الحالة',
  check_in_at: 'وقت الحضور',
  check_out_at: 'وقت الانصراف',
  late_minutes: 'دقائق التأخير',
  early_leave_minutes: 'دقائق الانصراف المبكر',
  total_minutes: 'إجمالي الدقائق',
  overtime_minutes: 'دقائق إضافية',
  leave_type: 'نوع الإجازة',
  leave_type_name: 'نوع الإجازة',
  start_date: 'من تاريخ',
  end_date: 'إلى تاريخ',
  days: 'عدد الأيام',
  reason: 'السبب',
  submitted_at: 'تاريخ التقديم',
  reviewed_at: 'تاريخ المراجعة',
  reviewer: 'راجعها',
};

function humanizeKey(key: string): string {
  return KNOWN_HEADER_LABELS[key] ?? key.replace(/_/g, ' ');
}

/** Best-effort readable rendering for a report cell whose type is unknown. */
function formatCellValue(value: unknown): React.ReactNode {
  if (value === null || value === undefined || value === '') return '—';
  if (typeof value === 'boolean') return value ? 'نعم' : 'لا';
  if (typeof value === 'number') return <span className="num">{value}</span>;
  if (typeof value === 'object') {
    const record = value as Record<string, unknown>;
    if (typeof record.name === 'string') return record.name;
    if (typeof record.full_name === 'string') return record.full_name;
    return JSON.stringify(value);
  }
  return String(value);
}

type DynamicReportTableProps = {
  rows: ReportRow[];
  /** Keys to hide even though they're present on each row (e.g. raw `id`). */
  excludeKeys?: string[];
  isLoading?: boolean;
  emptyMessage?: string;
  pagination?: {
    currentPage: number;
    lastPage: number;
    total: number;
    onPageChange: (page: number) => void;
  };
};

/**
 * Renders a report's result set without knowing its columns ahead of time —
 * columns are derived from the keys of the first row. Used by the attendance
 * and leave reports, whose exact response shape is owned by the parallel M8
 * API build and may still shift.
 */
export function DynamicReportTable({
  rows,
  excludeKeys = [],
  isLoading = false,
  emptyMessage = 'لا توجد نتائج مطابقة للفلاتر المحددة',
  pagination,
}: DynamicReportTableProps) {
  const columns = React.useMemo(() => {
    if (rows.length === 0) return [];
    return Object.keys(rows[0]).filter((key) => !excludeKeys.includes(key));
  }, [rows, excludeKeys]);

  if (isLoading) {
    return (
      <div className="space-y-2 rounded-xl border border-hairline bg-surface p-4">
        {Array.from({ length: 6 }).map((_, i) => (
          <Skeleton key={i} className="h-9 w-full" />
        ))}
      </div>
    );
  }

  return (
    <div className="overflow-hidden rounded-xl border border-hairline bg-surface">
      <div className="overflow-x-auto">
        <Table>
          <TableHeader>
            <TableRow>
              {columns.map((key) => (
                <TableHead key={key} className="whitespace-nowrap text-right text-xs font-semibold text-ink-2">
                  {humanizeKey(key)}
                </TableHead>
              ))}
            </TableRow>
          </TableHeader>
          <TableBody>
            {rows.length === 0 && (
              <TableRow>
                <TableCell colSpan={Math.max(columns.length, 1)} className="py-10 text-center text-sm text-muted">
                  {emptyMessage}
                </TableCell>
              </TableRow>
            )}
            {rows.map((row, index) => (
              <TableRow key={String((row.id as React.Key | undefined) ?? index)}>
                {columns.map((key) => (
                  <TableCell key={key} className={cn(typeof row[key] === 'number' && 'num')}>
                    {formatCellValue(row[key])}
                  </TableCell>
                ))}
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>

      {pagination && rows.length > 0 && (
        <PaginationBar
          currentPage={pagination.currentPage}
          lastPage={pagination.lastPage}
          total={pagination.total}
          onPageChange={pagination.onPageChange}
        />
      )}
    </div>
  );
}
