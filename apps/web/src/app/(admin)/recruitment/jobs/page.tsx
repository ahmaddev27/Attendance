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
import { JobStatusBadge } from '@/components/recruitment/status-badges';
import { JobFormDialog } from '@/app/(admin)/recruitment/jobs/_components/job-form-dialog';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { jobsApi } from '@/lib/api/endpoints/recruitment';
import {
  EMPLOYMENT_TYPE_LABELS,
  JOB_STATUS_OPTIONS,
  WORK_MODE_LABELS,
  WORK_MODE_OPTIONS,
} from '@/lib/constants/recruitment-options';
import { hasPermission, useAuthStore } from '@/lib/stores/auth-store';
import type { JobRequirement, JobRequirementStatus, WorkMode } from '@/lib/api/types';

const PER_PAGE = 25;

export default function JobsPage() {
  const router = useRouter();
  const qc = useQueryClient();
  const user = useAuthStore((s) => s.user);
  const canManage = hasPermission(user, 'manage-jobs');

  const [page, setPage] = React.useState(1);
  const [search, setSearch] = React.useState('');
  const [status, setStatus] = React.useState<JobRequirementStatus | 'all'>('all');
  const [workMode, setWorkMode] = React.useState<WorkMode | 'all'>('all');
  const [openOnly, setOpenOnly] = React.useState(true);

  const [formOpen, setFormOpen] = React.useState(false);
  const [editing, setEditing] = React.useState<JobRequirement | null>(null);
  const [deleteTarget, setDeleteTarget] = React.useState<JobRequirement | null>(null);

  const debouncedSearch = useDebouncedValue(search);

  React.useEffect(() => {
    setPage(1);
  }, [debouncedSearch, status, workMode, openOnly]);

  const filters = {
    page,
    per_page: PER_PAGE,
    search: debouncedSearch || undefined,
    status: status === 'all' ? undefined : status,
    work_mode: workMode === 'all' ? undefined : workMode,
    open_only: openOnly || undefined,
  };

  const { data, isLoading } = useQuery({
    queryKey: ['jobs', 'list', filters],
    queryFn: async () => (await jobsApi.list(filters)).data,
    placeholderData: keepPreviousData,
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => jobsApi.delete(id),
    onSuccess: () => {
      toast.success('تم الحذف');
      qc.invalidateQueries({ queryKey: ['jobs'] });
      setDeleteTarget(null);
    },
    onError: () => toast.error('تعذر الحذف'),
  });

  const columns: DataTableColumn<JobRequirement>[] = [
    {
      key: 'job_number',
      header: 'الرقم',
      cell: (j) => (
        <button
          type="button"
          onClick={() => router.push(`/recruitment/jobs/${j.id}`)}
          className="num text-start font-medium text-brand-ink hover:underline"
          dir="ltr"
        >
          {j.job_number}
        </button>
      ),
    },
    {
      key: 'title',
      header: 'المسمى',
      cell: (j) => (
        <button
          type="button"
          onClick={() => router.push(`/recruitment/jobs/${j.id}`)}
          className="max-w-[240px] truncate text-start font-medium text-ink hover:underline"
          title={j.title}
        >
          {j.title}
        </button>
      ),
    },
    { key: 'client', header: 'العميل', cell: (j) => j.recruitment_case?.client?.company_name ?? '—' },
    {
      key: 'openings',
      header: 'الشواغر',
      cell: (j) => <span className="num" dir="ltr">{j.openings}</span>,
    },
    { key: 'work_mode', header: 'نمط العمل', cell: (j) => WORK_MODE_LABELS[j.work_mode] ?? j.work_mode },
    { key: 'employment', header: 'نوع التوظيف', cell: (j) => EMPLOYMENT_TYPE_LABELS[j.employment_type] ?? j.employment_type },
    { key: 'stage', header: 'المرحلة الحالية', cell: (j) => j.current_stage?.name ?? '—' },
    { key: 'status', header: 'الحالة', cell: (j) => <JobStatusBadge status={j.status} /> },
  ];

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p className="text-xs font-medium text-muted">التوظيف</p>
          <h1 className="mt-1 text-2xl font-bold text-ink">الوظائف</h1>
        </div>
        {canManage && (
          <Button
            onClick={() => {
              setEditing(null);
              setFormOpen(true);
            }}
            className="gap-2 bg-brand text-white hover:bg-brand-hover"
          >
            <Plus className="h-4 w-4" /> وظيفة جديدة
          </Button>
        )}
      </div>

      <div className="grid grid-cols-1 gap-3 rounded-xl border border-hairline bg-surface p-4 sm:grid-cols-4">
        <div className="sm:col-span-2">
          <Label className="text-xs font-semibold text-ink-2">بحث</Label>
          <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="ابحث بمسمى الوظيفة..." className="mt-1.5" />
        </div>
        <div>
          <Label className="text-xs font-semibold text-ink-2">الحالة</Label>
          <Select value={status} onValueChange={(v) => setStatus(v as JobRequirementStatus | 'all')}>
            <SelectTrigger className="mt-1.5"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="all">كل الحالات</SelectItem>
              {JOB_STATUS_OPTIONS.map((o) => (<SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>))}
            </SelectContent>
          </Select>
        </div>
        <div>
          <Label className="text-xs font-semibold text-ink-2">نمط العمل</Label>
          <Select value={workMode} onValueChange={(v) => setWorkMode(v as WorkMode | 'all')}>
            <SelectTrigger className="mt-1.5"><SelectValue /></SelectTrigger>
            <SelectContent>
              <SelectItem value="all">الكل</SelectItem>
              {WORK_MODE_OPTIONS.map((o) => (<SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>))}
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
            الوظائف المفتوحة فقط
          </label>
        </div>
      </div>

      <DataTable
        columns={columns}
        data={data?.data ?? []}
        rowKey={(row) => row.id}
        isLoading={isLoading}
        emptyMessage="لا وظائف مطابقة"
        actions={
          canManage
            ? [
                { label: 'تعديل', icon: Pencil, onClick: (j) => { setEditing(j); setFormOpen(true); } },
                { label: 'حذف', icon: Trash2, variant: 'destructive', onClick: setDeleteTarget },
              ]
            : undefined
        }
        pagination={data ? { meta: data.meta, onPageChange: setPage } : undefined}
      />

      <JobFormDialog open={formOpen} onOpenChange={setFormOpen} job={editing} />

      <AlertDialog open={!!deleteTarget} onOpenChange={(open) => !open && setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>حذف الوظيفة</AlertDialogTitle>
            <AlertDialogDescription>
              سيتم حذف {deleteTarget?.title}.
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
