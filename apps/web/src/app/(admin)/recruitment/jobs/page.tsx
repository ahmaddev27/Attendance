'use client';

import * as React from 'react';
import { useRouter } from 'next/navigation';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Download, Pencil, Plus, Trash2 } from 'lucide-react';
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
import { Checkbox } from '@/components/ui/checkbox';
import { DataTable, type DataTableColumn } from '@/components/data-table/data-table';
import { FilterBar } from '@/components/data-table/filter-bar';
import { FilterSelect } from '@/components/data-table/filter-select';
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
  const [status, setStatus] = React.useState<JobRequirementStatus | undefined>();
  const [workMode, setWorkMode] = React.useState<WorkMode | undefined>();
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
    status,
    work_mode: workMode,
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

  const importMutation = useMutation({
    mutationFn: async () => (await jobsApi.importFromBrightGaza()).data,
    onSuccess: (result) => {
      if (result.data.failed > 0) {
        toast.warning(result.message);
      } else {
        toast.success(result.message);
      }
      qc.invalidateQueries({ queryKey: ['jobs'] });
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'تعذر السحب من BrightGaza');
    },
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
        <div className="flex max-w-[300px] items-center gap-2">
          <button
            type="button"
            onClick={() => router.push(`/recruitment/jobs/${j.id}`)}
            className="truncate text-start font-medium text-ink hover:underline"
            title={j.title}
          >
            {j.title}
          </button>
          {j.external && (
            <span className="shrink-0 rounded bg-brand-soft px-1.5 py-0.5 text-[10px] font-medium text-brand-ink">
              BrightGaza
            </span>
          )}
        </div>
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
          <div className="flex flex-wrap items-center gap-2">
            <Button
              type="button"
              variant="outline"
              className="gap-2"
              onClick={() => importMutation.mutate()}
              disabled={importMutation.isPending}
            >
              <Download className="h-4 w-4" />
              {importMutation.isPending ? 'جارٍ السحب…' : 'سحب من BrightGaza'}
            </Button>
            <Button
              onClick={() => {
                setEditing(null);
                setFormOpen(true);
              }}
              className="gap-2 bg-brand text-white hover:bg-brand-hover"
            >
              <Plus className="h-4 w-4" /> وظيفة جديدة
            </Button>
          </div>
        )}
      </div>

      <FilterBar searchValue={search} onSearchChange={setSearch} searchPlaceholder="ابحث بمسمى الوظيفة...">
        <FilterSelect
          value={status}
          onChange={(value) => setStatus(value as JobRequirementStatus | undefined)}
          options={JOB_STATUS_OPTIONS}
          placeholder="الحالة"
          allLabel="كل الحالات"
        />
        <FilterSelect
          value={workMode}
          onChange={(value) => setWorkMode(value as WorkMode | undefined)}
          options={WORK_MODE_OPTIONS}
          placeholder="نمط العمل"
          allLabel="كل أنماط العمل"
        />
        <label className="flex cursor-pointer items-center gap-2 whitespace-nowrap text-sm text-ink">
          <Checkbox checked={openOnly} onCheckedChange={(checked) => setOpenOnly(checked === true)} />
          الوظائف المفتوحة فقط
        </label>
      </FilterBar>

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
