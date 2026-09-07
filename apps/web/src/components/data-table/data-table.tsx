'use client';

import * as React from 'react';
import { Inbox } from 'lucide-react';

import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { Skeleton } from '@/components/ui/skeleton';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { PaginatedResponse } from '@/lib/api/types';

export type DataTableColumn<T> = {
  key: string;
  header: string;
  align?: 'start' | 'center' | 'end';
  className?: string;
  cell: (row: T) => React.ReactNode;
};

export type DataTableRowAction<T> = {
  label: string;
  icon: React.ComponentType<{ className?: string }>;
  onClick: (row: T) => void;
  variant?: 'default' | 'destructive';
  hidden?: (row: T) => boolean;
};

type DataTableProps<T> = {
  columns: DataTableColumn<T>[];
  data: T[];
  rowKey: (row: T) => React.Key;
  isLoading?: boolean;
  emptyMessage?: string;
  actions?: DataTableRowAction<T>[];
  pagination?: {
    meta: PaginatedResponse<unknown>['meta'];
    onPageChange: (page: number) => void;
  };
  skeletonRows?: number;
};

/**
 * Generic list table shared by every M2 admin page (employees, departments,
 * teams, positions). Consumers only describe columns + optional row actions;
 * loading/empty states and the pagination footer are handled once, here.
 */
export function DataTable<T>({
  columns,
  data,
  rowKey,
  isLoading = false,
  emptyMessage = 'لا توجد بيانات لعرضها',
  actions,
  pagination,
  skeletonRows = 5,
}: DataTableProps<T>) {
  const hasActions = !!actions?.length;
  const colSpan = columns.length + (hasActions ? 1 : 0);

  return (
    <div className="overflow-hidden rounded-xl border border-hairline bg-surface">
      <Table>
        <TableHeader>
          <TableRow className="border-hairline hover:bg-transparent">
            {columns.map((col) => (
              <TableHead
                key={col.key}
                className={cn(
                  'whitespace-nowrap text-xs font-semibold text-ink-2',
                  col.align === 'center' && 'text-center',
                  col.align === 'end' && 'text-left',
                  col.className
                )}
              >
                {col.header}
              </TableHead>
            ))}
            {hasActions && (
              <TableHead className="w-1 whitespace-nowrap text-left text-xs font-semibold text-ink-2">
                إجراءات
              </TableHead>
            )}
          </TableRow>
        </TableHeader>
        <TableBody>
          {isLoading &&
            Array.from({ length: skeletonRows }).map((_, i) => (
              <TableRow key={`skeleton-${i}`} className="border-hairline hover:bg-transparent">
                {columns.map((col) => (
                  <TableCell key={col.key}>
                    <Skeleton className="h-4 w-full max-w-[140px]" />
                  </TableCell>
                ))}
                {hasActions && (
                  <TableCell>
                    <Skeleton className="h-4 w-16" />
                  </TableCell>
                )}
              </TableRow>
            ))}

          {!isLoading && data.length === 0 && (
            <TableRow className="hover:bg-transparent">
              <TableCell colSpan={colSpan} className="h-40 text-center">
                <div className="flex flex-col items-center justify-center gap-2 text-muted">
                  <Inbox className="h-8 w-8" />
                  <p className="text-sm">{emptyMessage}</p>
                </div>
              </TableCell>
            </TableRow>
          )}

          {!isLoading &&
            data.map((row) => (
              <TableRow key={rowKey(row)} className="border-hairline">
                {columns.map((col) => (
                  <TableCell
                    key={col.key}
                    className={cn(
                      col.align === 'center' && 'text-center',
                      col.align === 'end' && 'text-left',
                      col.className
                    )}
                  >
                    {col.cell(row)}
                  </TableCell>
                ))}
                {hasActions && (
                  <TableCell>
                    <div className="flex items-center justify-end gap-1">
                      {actions
                        .filter((action) => !action.hidden?.(row))
                        .map((action) => (
                          <Button
                            key={action.label}
                            type="button"
                            variant="ghost"
                            size="icon"
                            className={cn(
                              'h-8 w-8',
                              action.variant === 'destructive'
                                ? 'text-danger hover:bg-danger-soft hover:text-danger'
                                : 'text-ink-2 hover:bg-brand-soft hover:text-brand-ink'
                            )}
                            title={action.label}
                            aria-label={action.label}
                            onClick={() => action.onClick(row)}
                          >
                            <action.icon className="h-4 w-4" />
                          </Button>
                        ))}
                    </div>
                  </TableCell>
                )}
              </TableRow>
            ))}
        </TableBody>
      </Table>

      {pagination && !isLoading && data.length > 0 && (
        <DataTablePagination meta={pagination.meta} onPageChange={pagination.onPageChange} />
      )}
    </div>
  );
}

function DataTablePagination({
  meta,
  onPageChange,
}: {
  meta: PaginatedResponse<unknown>['meta'];
  onPageChange: (page: number) => void;
}) {
  const { current_page, last_page, per_page, total } = meta;
  const from = total === 0 ? 0 : (current_page - 1) * per_page + 1;
  const to = Math.min(current_page * per_page, total);

  return (
    <div className="flex flex-col gap-3 border-t border-hairline px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
      <p className="text-xs text-muted">
        عرض <span className="num">{from}</span>-<span className="num">{to}</span> من أصل{' '}
        <span className="num">{total}</span>
      </p>
      <div className="flex items-center gap-2">
        <Button
          type="button"
          variant="outline"
          size="sm"
          disabled={current_page <= 1}
          onClick={() => onPageChange(current_page - 1)}
        >
          السابق
        </Button>
        <span className="num text-xs text-ink-2">
          {current_page} / {last_page}
        </span>
        <Button
          type="button"
          variant="outline"
          size="sm"
          disabled={current_page >= last_page}
          onClick={() => onPageChange(current_page + 1)}
        >
          التالي
        </Button>
      </div>
    </div>
  );
}
