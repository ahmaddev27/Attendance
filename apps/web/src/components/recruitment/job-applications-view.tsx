'use client';

import * as React from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ChevronLeft, Eye, FileUp, Plus, Undo2, XCircle } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Skeleton } from '@/components/ui/skeleton';
import { DataTable, type DataTableColumn } from '@/components/data-table/data-table';
import { FilterBar } from '@/components/data-table/filter-bar';
import { FilterSelect } from '@/components/data-table/filter-select';
import { AttachCandidateDialog } from '@/components/recruitment/attach-candidate-dialog';
import { RejectApplicationDialog } from '@/components/recruitment/reject-application-dialog';
import { ApplicationStatusBadge } from '@/components/recruitment/status-badges';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import {
  applicationsApi,
  APPLICATION_STATUS_LABEL,
  shortlistApi,
} from '@/lib/api/endpoints/candidates';
import { jobsApi } from '@/lib/api/endpoints/recruitment';
import { formatDate } from '@/lib/attendance-format';
import { hasPermission, useAuthStore } from '@/lib/stores/auth-store';
import type { CandidateApplication, CandidateApplicationStatus } from '@/lib/api/types';

const PER_PAGE = 25;
const CLOSED: CandidateApplicationStatus[] = ['rejected', 'withdrawn', 'hired'];

const STATUS_OPTIONS = (Object.keys(APPLICATION_STATUS_LABEL) as CandidateApplicationStatus[]).map((v) => ({
  value: v,
  label: APPLICATION_STATUS_LABEL[v],
}));

type Props = {
  jobId: number;
  /** Shortlist page: same table, restricted to flagged applications. */
  shortlistOnly?: boolean;
};

/**
 * Shared table behind `/jobs/{id}/candidates` and `/jobs/{id}/shortlist`.
 * Both screens differ only by the endpoint they read and a few header
 * actions, so keeping one component stops the two from drifting.
 */
export function JobApplicationsView({ jobId, shortlistOnly = false }: Props) {
  const router = useRouter();
  const qc = useQueryClient();
  const user = useAuthStore((s) => s.user);
  const canManage = hasPermission(user, 'manage-candidates');
  const canShortlist = hasPermission(user, 'shortlist-candidates');

  const [page, setPage] = React.useState(1);
  const [search, setSearch] = React.useState('');
  const [status, setStatus] = React.useState<CandidateApplicationStatus | undefined>();
  const [attachOpen, setAttachOpen] = React.useState(false);
  const [rejectId, setRejectId] = React.useState<number | null>(null);
  const debouncedSearch = useDebouncedValue(search);

  React.useEffect(() => {
    setPage(1);
  }, [debouncedSearch, status]);

  const { data: job } = useQuery({
    queryKey: ['jobs', jobId],
    queryFn: async () => (await jobsApi.get(jobId)).data.data,
    enabled: Number.isFinite(jobId),
  });

  const filters = { page, per_page: PER_PAGE, search: debouncedSearch || undefined, status };
  const listKey = shortlistOnly ? 'job-shortlist' : 'job-applications';
  const { data, isLoading } = useQuery({
    queryKey: [listKey, jobId, filters],
    queryFn: async () =>
      (shortlistOnly
        ? await shortlistApi.listForJob(jobId, { page, per_page: PER_PAGE })
        : await applicationsApi.listForJob(jobId, filters)
      ).data,
    placeholderData: keepPreviousData,
    enabled: Number.isFinite(jobId),
  });

  const invalidate = () => {
    qc.invalidateQueries({ queryKey: ['job-applications', jobId] });
    qc.invalidateQueries({ queryKey: ['job-shortlist', jobId] });
  };

  const toggleShortlist = useMutation({
    mutationFn: (app: CandidateApplication) =>
      app.is_shortlisted ? shortlistApi.remove(app.id) : shortlistApi.add(app.id),
    onSuccess: invalidate,
    onError: () => toast.error('تعذر تحديث القائمة المختصرة'),
  });

  const withdraw = useMutation({
    mutationFn: (id: number) => applicationsApi.withdraw(id),
    onSuccess: () => {
      toast.success('تم سحب الطلب');
      invalidate();
    },
    onError: () => toast.error('تعذر سحب الطلب'),
  });

  const columns: DataTableColumn<CandidateApplication>[] = [
    {
      key: 'candidate',
      header: 'المرشّح',
      cell: (a) => (
        <Link href={`/recruitment/applications/${a.id}`} className="block min-w-0 hover:underline">
          <p className="truncate font-medium text-ink">{a.candidate?.full_name ?? '—'}</p>
          <p className="num truncate text-xs text-muted" dir="ltr">
            {a.candidate?.email ?? '—'}
          </p>
        </Link>
      ),
    },
    { key: 'stage', header: 'المرحلة', cell: (a) => a.current_stage?.name ?? '—' },
    { key: 'status', header: 'الحالة', cell: (a) => <ApplicationStatusBadge status={a.status} /> },
    {
      key: 'shortlist',
      header: 'قائمة مختصرة',
      align: 'center',
      cell: (a) => (
        <Checkbox
          checked={a.is_shortlisted}
          disabled={!canShortlist || CLOSED.includes(a.status) || toggleShortlist.isPending}
          onCheckedChange={() => toggleShortlist.mutate(a)}
          aria-label="إضافة إلى القائمة المختصرة"
        />
      ),
    },
    {
      key: 'applied',
      header: 'تاريخ التقديم',
      cell: (a) => (
        <span className="num" dir="ltr">
          {formatDate(a.applied_at)}
        </span>
      ),
    },
  ];

  return (
    <div className="space-y-6">
      <nav className="flex items-center gap-1 text-xs text-muted">
        <Link href="/recruitment/jobs" className="hover:text-brand-ink">الوظائف</Link>
        <ChevronLeft className="h-3 w-3" />
        <Link href={`/recruitment/jobs/${jobId}`} className="hover:text-brand-ink">
          {job ? job.title : <Skeleton className="inline-block h-3 w-20 align-middle" />}
        </Link>
        <ChevronLeft className="h-3 w-3" />
        <span>{shortlistOnly ? 'القائمة المختصرة' : 'المرشّحون'}</span>
      </nav>

      <div className="flex flex-wrap items-center justify-between gap-4">
        <h1 className="text-2xl font-bold text-ink">
          {shortlistOnly ? 'القائمة المختصرة' : 'مرشّحو الوظيفة'}
        </h1>
        <div className="flex flex-wrap gap-2">
          <Button asChild variant="outline" className="gap-2">
            <Link href={`/recruitment/jobs/${jobId}/${shortlistOnly ? 'candidates' : 'shortlist'}`}>
              {shortlistOnly ? 'كل المرشّحين' : 'القائمة المختصرة'}
            </Link>
          </Button>
          {canManage && (
            <>
              <Button asChild variant="outline" className="gap-2">
                <Link href={`/recruitment/jobs/${jobId}/applications/import`}>
                  <FileUp className="h-4 w-4" /> استيراد CSV
                </Link>
              </Button>
              <Button
                onClick={() => setAttachOpen(true)}
                className="gap-2 bg-brand text-white hover:bg-brand-hover"
              >
                <Plus className="h-4 w-4" /> إضافة مرشّح
              </Button>
            </>
          )}
        </div>
      </div>

      {!shortlistOnly && (
        <FilterBar
          searchValue={search}
          onSearchChange={setSearch}
          searchPlaceholder="ابحث باسم المرشّح..."
        >
          <FilterSelect
            value={status}
            onChange={(value) => setStatus(value as CandidateApplicationStatus | undefined)}
            options={STATUS_OPTIONS}
            placeholder="الحالة"
            allLabel="كل الحالات"
          />
        </FilterBar>
      )}

      <DataTable
        columns={columns}
        data={data?.data ?? []}
        rowKey={(row) => row.id}
        isLoading={isLoading}
        emptyMessage={shortlistOnly ? 'لا مرشّحين في القائمة المختصرة' : 'لا توجد طلبات لهذه الوظيفة'}
        actions={[
          { label: 'عرض الطلب', icon: Eye, onClick: (a) => router.push(`/recruitment/applications/${a.id}`) },
          ...(canManage
            ? [
                {
                  label: 'رفض',
                  icon: XCircle,
                  variant: 'destructive' as const,
                  hidden: (a: CandidateApplication) => CLOSED.includes(a.status),
                  onClick: (a: CandidateApplication) => setRejectId(a.id),
                },
                {
                  label: 'سحب',
                  icon: Undo2,
                  hidden: (a: CandidateApplication) => CLOSED.includes(a.status),
                  onClick: (a: CandidateApplication) => withdraw.mutate(a.id),
                },
              ]
            : []),
        ]}
        pagination={data ? { meta: data.meta, onPageChange: setPage } : undefined}
      />

      <AttachCandidateDialog
        open={attachOpen}
        onOpenChange={setAttachOpen}
        jobId={jobId}
        excludeCandidateIds={(data?.data ?? [])
          .map((a) => a.candidate?.id)
          .filter((id): id is number => typeof id === 'number')}
      />
      {rejectId !== null && (
        <RejectApplicationDialog
          open
          onOpenChange={(open) => !open && setRejectId(null)}
          applicationId={rejectId}
        />
      )}
    </div>
  );
}
