'use client';

import * as React from 'react';
import { useRouter } from 'next/navigation';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { toast } from 'sonner';

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
import { DataTable, type DataTableColumn } from '@/components/data-table/data-table';
import { CaseStatusBadge } from '@/components/recruitment/status-badges';
import { CaseFormDialog } from '@/app/(admin)/recruitment/cases/_components/case-form-dialog';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { recruitmentCasesApi } from '@/lib/api/endpoints/recruitment';
import { formatDate } from '@/lib/attendance-format';
import {
  CASE_PRIORITY_LABELS,
  CASE_STATUS_OPTIONS,
} from '@/lib/constants/recruitment-options';
import { hasPermission, useAuthStore } from '@/lib/stores/auth-store';
import type { RecruitmentCase, RecruitmentCaseStatus } from '@/lib/api/types';

const PER_PAGE = 25;

export default function CasesPage() {
  const router = useRouter();
  const qc = useQueryClient();
  const user = useAuthStore((s) => s.user);
  const canManage = hasPermission(user, 'manage-recruitment-cases');

  const [page, setPage] = React.useState(1);
  const [search, setSearch] = React.useState('');
  const [status, setStatus] = React.useState<RecruitmentCaseStatus | 'all'>('all');
  const [openOnly, setOpenOnly] = React.useState(true);

  const [formOpen, setFormOpen] = React.useState(false);
  const [editing, setEditing] = React.useState<RecruitmentCase | null>(null);
  const [deleteTarget, setDeleteTarget] = React.useState<RecruitmentCase | null>(null);

  const debouncedSearch = useDebouncedValue(search);

  React.useEffect(() => {
    setPage(1);
  }, [debouncedSearch, status, openOnly]);

  const filters = {
    page,
    per_page: PER_PAGE,
    search: debouncedSearch || undefined,
    status: status === 'all' ? undefined : status,
    open_only: openOnly || undefined,
  };

  const { data, isLoading } = useQuery({
    queryKey: ['recruitment-cases', 'list', filters],
    queryFn: async () => (await recruitmentCasesApi.list(filters)).data,
    placeholderData: keepPreviousData,
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => recruitmentCasesApi.delete(id),
    onSuccess: () => {
      toast.success('تم حذف الحملة');
      qc.invalidateQueries({ queryKey: ['recruitment-cases'] });
      setDeleteTarget(null);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'تعذر الحذف');
    },
  });

  const columns: DataTableColumn<RecruitmentCase>[] = [
    {
      key: 'case_number',
      header: 'الرقم',
      cell: (row) => (
        <button
          type="button"
          onClick={() => router.push(`/recruitment/cases/${row.id}`)}
          className="num text-start font-medium text-brand-ink hover:underline"
          dir="ltr"
        >
          {row.case_number}
        </button>
      ),
    },
    {
      key: 'title',
      header: 'الحملة',
      cell: (row) => (
        <button
          type="button"
          onClick={() => router.push(`/recruitment/cases/${row.id}`)}
          className="max-w-[280px] truncate text-start font-medium text-ink hover:underline"
          title={row.title}
        >
          {row.title}
        </button>
      ),
    },
    { key: 'client', header: 'العميل', cell: (row) => row.client?.company_name ?? <span className="text-xs text-muted">—</span> },
    { key: 'status', header: 'الحالة', cell: (row) => <CaseStatusBadge status={row.status} /> },
    { key: 'priority', header: 'الأولوية', cell: (row) => <span className="text-sm text-ink-2">{CASE_PRIORITY_LABELS[row.priority] ?? row.priority}</span> },
    { key: 'target_hires', header: 'المستهدَف', cell: (row) => row.target_hires ? <span className="num" dir="ltr">{row.target_hires}</span> : <span className="text-xs text-muted">—</span> },
    { key: 'deadline', header: 'الإغلاق', cell: (row) => row.deadline ? <span className="num" dir="ltr">{formatDate(row.deadline)}</span> : <span className="text-xs text-muted">—</span> },
  ];

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p className="text-xs font-medium text-muted">التوظيف</p>
          <h1 className="mt-1 text-2xl font-bold text-ink">الحملات</h1>
        </div>
        {canManage && (
          <Button
            onClick={() => {
              setEditing(null);
              setFormOpen(true);
            }}
            className="gap-2 bg-brand text-white hover:bg-brand-hover"
          >
            <Plus className="h-4 w-4" />
            حملة جديدة
          </Button>
        )}
      </div>

      <div className="grid grid-cols-1 gap-3 rounded-xl border border-hairline bg-surface p-4 sm:grid-cols-4">
        <div className="sm:col-span-2">
          <Label className="text-xs font-semibold text-ink-2">بحث</Label>
          <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="ابحث بعنوان الحملة..." className="mt-1.5" />
        </div>
        <div>
          <Label className="text-xs font-semibold text-ink-2">الحالة</Label>
          <Select value={status} onValueChange={(v) => setStatus(v as RecruitmentCaseStatus | 'all')}>
            <SelectTrigger className="mt-1.5"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="all">كل الحالات</SelectItem>
              {CASE_STATUS_OPTIONS.map((o) => (
                <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="flex items-end">
          <label className="flex items-center gap-2 text-sm text-ink">
            <input
              type="checkbox"
              checked={openOnly}
              onChange={(e) => setOpenOnly(e.target.checked)}
              className="h-4 w-4 rounded border-hairline"
            />
            الحملات المفتوحة فقط
          </label>
        </div>
      </div>

      <DataTable
        columns={columns}
        data={data?.data ?? []}
        rowKey={(row) => row.id}
        isLoading={isLoading}
        emptyMessage="لا حملات مطابقة"
        actions={
          canManage
            ? [
                { label: 'تعديل', icon: Pencil, onClick: (row) => { setEditing(row); setFormOpen(true); } },
                { label: 'حذف', icon: Trash2, variant: 'destructive', onClick: setDeleteTarget },
              ]
            : undefined
        }
        pagination={data ? { meta: data.meta, onPageChange: setPage } : undefined}
      />

      <CaseFormDialog open={formOpen} onOpenChange={setFormOpen} caseData={editing} />

      <AlertDialog open={!!deleteTarget} onOpenChange={(open) => !open && setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>حذف الحملة</AlertDialogTitle>
            <AlertDialogDescription>
              سيتم حذف الحملة {deleteTarget?.title}.
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
    </div>
  );
}
