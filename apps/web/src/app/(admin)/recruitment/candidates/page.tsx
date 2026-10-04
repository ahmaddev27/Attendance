'use client';

import * as React from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import {
  keepPreviousData,
  useMutation,
  useQuery,
  useQueryClient,
} from '@tanstack/react-query';
import { Eye, Pencil, Plus, Trash2 } from 'lucide-react';
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
import { CandidateFormDialog } from '@/components/recruitment/candidate-form-dialog';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { candidatesApi, CANDIDATE_STATUS_LABEL } from '@/lib/api/endpoints/candidates';
import { hasPermission, useAuthStore } from '@/lib/stores/auth-store';
import { cn } from '@/lib/utils';
import type { Candidate, CandidateStatus } from '@/lib/api/types';

const PER_PAGE = 25;

const STATUS_OPTIONS = (Object.keys(CANDIDATE_STATUS_LABEL) as CandidateStatus[]).map((v) => ({
  value: v,
  label: CANDIDATE_STATUS_LABEL[v],
}));

const SOURCE_OPTIONS = [
  { value: 'manual', label: 'يدوي' },
  { value: 'csv_import', label: 'استيراد CSV' },
  { value: 'brightgaza', label: 'BrightGaza' },
  { value: 'referral', label: 'توصية' },
];

/**
 * Candidate bank — shared pool of candidates. Searchable, filterable
 * by status / source / has-resume. Row click opens the profile.
 */
export default function CandidatesPage() {
  const router = useRouter();
  const qc = useQueryClient();
  const user = useAuthStore((s) => s.user);
  const canManage = hasPermission(user, 'manage-candidates');

  const [page, setPage] = React.useState(1);
  const [search, setSearch] = React.useState('');
  const [status, setStatus] = React.useState<CandidateStatus | undefined>();
  const [source, setSource] = React.useState<string | undefined>();
  const [hasResume, setHasResume] = React.useState(false);

  const [formOpen, setFormOpen] = React.useState(false);
  const [editing, setEditing] = React.useState<Candidate | null>(null);
  const [deleteTarget, setDeleteTarget] = React.useState<Candidate | null>(null);

  const debouncedSearch = useDebouncedValue(search);

  React.useEffect(() => {
    setPage(1);
  }, [debouncedSearch, status, source, hasResume]);

  const filters = {
    page,
    per_page: PER_PAGE,
    search: debouncedSearch || undefined,
    status,
    source,
    has_resume: hasResume || undefined,
  };

  const { data, isLoading } = useQuery({
    queryKey: ['candidates', 'list', filters],
    queryFn: async () => (await candidatesApi.list(filters)).data,
    placeholderData: keepPreviousData,
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => candidatesApi.remove(id),
    onSuccess: () => {
      toast.success('تم حذف المرشّح');
      qc.invalidateQueries({ queryKey: ['candidates'] });
      setDeleteTarget(null);
    },
    onError: () => toast.error('تعذر الحذف'),
  });

  const columns: DataTableColumn<Candidate>[] = [
    {
      key: 'identity',
      header: 'المرشّح',
      cell: (c) => (
        <Link
          href={`/recruitment/candidates/${c.id}`}
          className="flex items-center gap-3 hover:underline"
        >
          <Avatar fullName={c.full_name} />
          <div className="min-w-0">
            <p className="truncate font-medium text-ink">{c.full_name}</p>
            <p className="num truncate text-xs text-muted" dir="ltr">
              {c.email ?? '—'}
            </p>
          </div>
        </Link>
      ),
    },
    {
      key: 'phone',
      header: 'الهاتف',
      cell: (c) =>
        c.phone ? (
          <span className="num" dir="ltr">
            {c.phone}
          </span>
        ) : (
          '—'
        ),
    },
    { key: 'headline', header: 'العنوان', cell: (c) => c.headline ?? '—' },
    { key: 'country', header: 'الدولة', cell: (c) => c.country ?? '—' },
    {
      key: 'years',
      header: 'سنوات الخبرة',
      cell: (c) =>
        c.years_of_experience !== null ? (
          <span className="num" dir="ltr">
            {c.years_of_experience}
          </span>
        ) : (
          '—'
        ),
    },
    {
      key: 'status',
      header: 'الحالة',
      cell: (c) => <CandidateStatusBadge status={c.status} />,
    },
  ];

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p className="text-xs font-medium text-muted">التوظيف</p>
          <h1 className="mt-1 text-2xl font-bold text-ink">بنك المرشّحين</h1>
        </div>
        {canManage && (
          <Button
            onClick={() => {
              setEditing(null);
              setFormOpen(true);
            }}
            className="gap-2 bg-brand text-white hover:bg-brand-hover"
          >
            <Plus className="h-4 w-4" /> إضافة مرشّح
          </Button>
        )}
      </div>

      <FilterBar
        searchValue={search}
        onSearchChange={setSearch}
        searchPlaceholder="ابحث بالاسم أو البريد أو الهاتف..."
      >
        <FilterSelect
          value={status}
          onChange={(value) => setStatus(value as CandidateStatus | undefined)}
          options={STATUS_OPTIONS}
          placeholder="الحالة"
          allLabel="كل الحالات"
        />
        <FilterSelect
          value={source}
          onChange={setSource}
          options={SOURCE_OPTIONS}
          placeholder="المصدر"
          allLabel="كل المصادر"
        />
        <label className="flex cursor-pointer items-center gap-2 whitespace-nowrap text-sm text-ink">
          <Checkbox
            checked={hasResume}
            onCheckedChange={(checked) => setHasResume(checked === true)}
          />
          لديهم سيرة ذاتية فقط
        </label>
      </FilterBar>

      <DataTable
        columns={columns}
        data={data?.data ?? []}
        rowKey={(row) => row.id}
        isLoading={isLoading}
        emptyMessage="لا مرشّحين مطابقين"
        actions={[
          { label: 'عرض', icon: Eye, onClick: (c) => router.push(`/recruitment/candidates/${c.id}`) },
          ...(canManage
            ? [
                {
                  label: 'تعديل',
                  icon: Pencil,
                  onClick: (c: Candidate) => {
                    setEditing(c);
                    setFormOpen(true);
                  },
                },
                {
                  label: 'حذف',
                  icon: Trash2,
                  variant: 'destructive' as const,
                  onClick: setDeleteTarget,
                },
              ]
            : []),
        ]}
        pagination={data ? { meta: data.meta, onPageChange: setPage } : undefined}
      />

      <CandidateFormDialog open={formOpen} onOpenChange={setFormOpen} candidate={editing} />

      <AlertDialog open={!!deleteTarget} onOpenChange={(open) => !open && setDeleteTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>حذف المرشّح</AlertDialogTitle>
            <AlertDialogDescription>
              سيتم أرشفة {deleteTarget?.full_name}. يمكن استعادته لاحقاً من قِبَل المسؤول.
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

function Avatar({ fullName }: { fullName: string }) {
  const initials = fullName
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((part) => part.charAt(0))
    .join('')
    .toUpperCase();
  return (
    <span
      className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-soft text-xs font-semibold text-brand-ink"
      aria-hidden
    >
      {initials || '?'}
    </span>
  );
}

function CandidateStatusBadge({ status }: { status: CandidateStatus }) {
  const map: Record<CandidateStatus, string> = {
    active: 'bg-success-soft text-success',
    blacklisted: 'bg-danger-soft text-danger',
    placed: 'bg-brand-soft text-brand-ink',
    inactive: 'bg-surface-2 text-ink-2',
  };
  return (
    <span
      className={cn(
        'inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold',
        map[status],
      )}
    >
      {CANDIDATE_STATUS_LABEL[status]}
    </span>
  );
}
