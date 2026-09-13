'use client';

import * as React from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { ChevronRight, Pencil } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { CaseStatusBadge, JobStatusBadge } from '@/components/recruitment/status-badges';
import { CaseFormDialog } from '@/app/(admin)/recruitment/cases/_components/case-form-dialog';
import { jobsApi, recruitmentCasesApi } from '@/lib/api/endpoints/recruitment';
import { formatDate } from '@/lib/attendance-format';
import { CASE_PRIORITY_LABELS } from '@/lib/constants/recruitment-options';
import { hasPermission, useAuthStore } from '@/lib/stores/auth-store';

export default function CaseDetailPage() {
  const params = useParams<{ id: string }>();
  const caseId = Number(params.id);
  const user = useAuthStore((s) => s.user);
  const canManage = hasPermission(user, 'manage-recruitment-cases');
  const canViewJobs = hasPermission(user, 'view-jobs');
  const [editOpen, setEditOpen] = React.useState(false);

  const { data: caseRes, isLoading } = useQuery({
    queryKey: ['recruitment-cases', caseId],
    queryFn: async () => (await recruitmentCasesApi.get(caseId)).data.data,
    enabled: Number.isFinite(caseId),
  });

  const { data: jobsRes } = useQuery({
    queryKey: ['case-jobs', caseId],
    queryFn: async () => (await jobsApi.listForCase(caseId, { per_page: 50 })).data,
    enabled: Number.isFinite(caseId) && canViewJobs,
  });

  if (isLoading) return <Skeleton className="h-72 rounded-xl" />;
  if (!caseRes) return <p className="p-8 text-center text-sm text-muted">لم يتم العثور على الحملة.</p>;
  const c = caseRes;
  const jobs = jobsRes?.data ?? [];

  return (
    <div className="space-y-6">
      <nav className="flex items-center gap-1 text-xs text-muted">
        <Link href="/recruitment/cases" className="hover:text-brand-ink">
          الحملات
        </Link>
        <ChevronRight className="h-3 w-3" />
        <span className="num" dir="ltr">
          {c.case_number}
        </span>
      </nav>

      <div className="rounded-xl border border-hairline bg-surface p-6">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <div className="flex items-center gap-3">
              <h1 className="text-2xl font-bold text-ink">{c.title}</h1>
              <CaseStatusBadge status={c.status} />
            </div>
            <p className="mt-1 num text-xs text-muted" dir="ltr">{c.case_number}</p>
            <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-ink-2">
              {c.client && (
                <Link href={`/recruitment/clients/${c.client.id}`} className="text-brand-ink hover:underline">
                  {c.client.company_name}
                </Link>
              )}
              <span>الأولوية: {CASE_PRIORITY_LABELS[c.priority] ?? c.priority}</span>
              {c.target_hires ? (
                <span>المستهدف: <span className="num" dir="ltr">{c.target_hires}</span></span>
              ) : null}
              {c.deadline && <span>الإغلاق: <span className="num" dir="ltr">{formatDate(c.deadline)}</span></span>}
              {c.owner && <span>المالك: {c.owner.name}</span>}
            </div>
          </div>
          {canManage && (
            <Button type="button" variant="outline" className="gap-2" onClick={() => setEditOpen(true)}>
              <Pencil className="h-4 w-4" /> تعديل
            </Button>
          )}
        </div>
        {c.description && (
          <p className="mt-4 whitespace-pre-wrap text-sm text-ink">{c.description}</p>
        )}
      </div>

      <div className="rounded-xl border border-hairline bg-surface">
        <div className="border-b border-hairline p-4">
          <h2 className="text-sm font-semibold text-ink">الوظائف ({jobs.length})</h2>
        </div>
        {jobs.length === 0 ? (
          <p className="py-8 text-center text-sm text-muted">لا وظائف مسجلة في هذه الحملة.</p>
        ) : (
          <ul className="divide-y divide-hairline">
            {jobs.map((j) => (
              <li key={j.id}>
                <Link
                  href={`/recruitment/jobs/${j.id}`}
                  className="flex items-center justify-between gap-3 p-4 hover:bg-surface-2"
                >
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-semibold text-ink" title={j.title}>{j.title}</p>
                    <p className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-ink-2">
                      <span className="num" dir="ltr">{j.job_number}</span>
                      {j.current_stage && <span>· {j.current_stage.name}</span>}
                      <span>· الشواغر: <span className="num" dir="ltr">{j.openings}</span></span>
                    </p>
                  </div>
                  <JobStatusBadge status={j.status} />
                </Link>
              </li>
            ))}
          </ul>
        )}
      </div>

      <CaseFormDialog open={editOpen} onOpenChange={setEditOpen} caseData={c} />
    </div>
  );
}
