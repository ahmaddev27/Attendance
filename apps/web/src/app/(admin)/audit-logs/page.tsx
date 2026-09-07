'use client';

import * as React from 'react';
import Link from 'next/link';
import { keepPreviousData, useQuery } from '@tanstack/react-query';

import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
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
import { EmployeeAvatar } from '@/components/employees/employee-avatar';
import { EmployeeSearchSelect } from '@/components/attendance/employee-search-select';
import { PaginationBar } from '@/components/attendance/pagination-bar';
import { AuditEventBadge } from '@/components/audit/audit-event-badge';
import { AuditLogDetailDialog } from '@/components/audit/audit-log-detail-dialog';
import { auditLogsApi } from '@/lib/api/endpoints/audit-logs';
import { AUDIT_EVENT_OPTIONS, AUDIT_SUBJECT_TYPE_OPTIONS, getSubjectTypeLabel } from '@/lib/constants/audit-options';
import type { AuditLogEntry, EmployeeSummary } from '@/lib/api/types';

const PER_PAGE = 25;

/** Only a handful of subject types have a canonical per-record page today. */
function getSubjectHref(entry: AuditLogEntry): string | null {
  if (entry.subject_type === 'App\\Models\\Employee' && entry.subject_id) {
    return `/employees/${entry.subject_id}`;
  }
  return null;
}

export default function AuditLogsPage() {
  const [page, setPage] = React.useState(1);
  const [causer, setCauser] = React.useState<EmployeeSummary | null>(null);
  const [subjectType, setSubjectType] = React.useState<string | undefined>();
  const [event, setEvent] = React.useState<string | undefined>();
  const [from, setFrom] = React.useState('');
  const [to, setTo] = React.useState('');
  const [selected, setSelected] = React.useState<AuditLogEntry | null>(null);

  const filters = {
    causer_id: causer?.id,
    subject_type: subjectType,
    event,
    from: from || undefined,
    to: to || undefined,
  };

  React.useEffect(() => {
    setPage(1);
  }, [causer?.id, subjectType, event, from, to]);

  const { data, isLoading, isFetching } = useQuery({
    queryKey: ['audit-logs', filters, page],
    queryFn: () => auditLogsApi.list({ ...filters, page, per_page: PER_PAGE }),
    placeholderData: keepPreviousData,
  });

  const rows = data?.data ?? [];

  return (
    <div>
      <div className="mb-6">
        <p className="text-xs font-medium text-muted">النظام</p>
        <h1 className="mt-1 text-2xl font-bold text-ink">سجل النظام</h1>
      </div>

      <div className="mb-4 grid grid-cols-1 gap-3 rounded-xl border border-hairline bg-surface p-4 sm:grid-cols-2 lg:grid-cols-5">
        <div>
          <Label className="text-xs font-semibold text-ink-2">المستخدم</Label>
          <div className="mt-1.5">
            <EmployeeSearchSelect value={causer} onChange={setCauser} placeholder="كل المستخدمين" />
          </div>
        </div>
        <div>
          <Label className="text-xs font-semibold text-ink-2">نوع السجل</Label>
          <Select value={subjectType ?? '__all__'} onValueChange={(v) => setSubjectType(v === '__all__' ? undefined : v)}>
            <SelectTrigger className="mt-1.5">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="__all__">كل الأنواع</SelectItem>
              {AUDIT_SUBJECT_TYPE_OPTIONS.map((opt) => (
                <SelectItem key={opt.value} value={opt.value}>
                  {opt.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div>
          <Label className="text-xs font-semibold text-ink-2">الحدث</Label>
          <Select value={event ?? '__all__'} onValueChange={(v) => setEvent(v === '__all__' ? undefined : v)}>
            <SelectTrigger className="mt-1.5">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="__all__">كل الأحداث</SelectItem>
              {AUDIT_EVENT_OPTIONS.map((opt) => (
                <SelectItem key={opt.value} value={opt.value}>
                  {opt.label}
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
      </div>

      <div className="rounded-xl border border-hairline bg-surface">
        <div className="overflow-x-auto">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="text-right">التاريخ</TableHead>
                <TableHead className="text-right">المستخدم</TableHead>
                <TableHead className="text-right">الحدث</TableHead>
                <TableHead className="text-right">الوصف</TableHead>
                <TableHead className="text-right">السجل المرتبط</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {isLoading &&
                Array.from({ length: 8 }).map((_, i) => (
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
                    لا توجد سجلات مطابقة للفلاتر المحددة
                  </TableCell>
                </TableRow>
              )}

              {!isLoading &&
                rows.map((entry) => {
                  const subjectHref = getSubjectHref(entry);
                  return (
                    <TableRow
                      key={entry.id}
                      className="cursor-pointer"
                      onClick={() => setSelected(entry)}
                    >
                      <TableCell className="num whitespace-nowrap" dir="ltr">
                        {new Date(entry.created_at).toLocaleString('ar-SA')}
                      </TableCell>
                      <TableCell>
                        {entry.causer ? (
                          <div className="flex items-center gap-2">
                            <EmployeeAvatar
                              employee={{ full_name: entry.causer.name, avatar_url: null }}
                              size={28}
                            />
                            <span className="truncate font-medium text-ink">{entry.causer.name}</span>
                          </div>
                        ) : (
                          <span className="text-muted">النظام</span>
                        )}
                      </TableCell>
                      <TableCell>
                        <AuditEventBadge event={entry.event} />
                      </TableCell>
                      <TableCell className="max-w-[260px] truncate" title={entry.description}>
                        {entry.description}
                      </TableCell>
                      <TableCell>
                        {subjectHref ? (
                          <Link
                            href={subjectHref}
                            onClick={(e) => e.stopPropagation()}
                            className="text-brand-ink underline underline-offset-2"
                          >
                            {getSubjectTypeLabel(entry.subject_type)} #{entry.subject_id}
                          </Link>
                        ) : entry.subject_type ? (
                          <span className="text-ink-2">
                            {getSubjectTypeLabel(entry.subject_type)}
                            {entry.subject_id ? ` #${entry.subject_id}` : ''}
                          </span>
                        ) : (
                          '—'
                        )}
                      </TableCell>
                    </TableRow>
                  );
                })}
            </TableBody>
          </Table>
        </div>

        {data && (
          <PaginationBar
            currentPage={data.meta.current_page}
            lastPage={data.meta.last_page}
            total={data.meta.total}
            onPageChange={setPage}
          />
        )}
      </div>
      {isFetching && !isLoading && <p className="mt-2 text-xs text-muted">جارٍ التحديث...</p>}

      <AuditLogDetailDialog
        entry={selected}
        open={!!selected}
        onOpenChange={(open) => !open && setSelected(null)}
      />
    </div>
  );
}
