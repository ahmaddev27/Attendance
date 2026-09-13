'use client';

import * as React from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowRight, Ban, Check, ChevronRight, Circle, Pencil } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { JobStatusBadge } from '@/components/recruitment/status-badges';
import { JobFormDialog } from '@/app/(admin)/recruitment/jobs/_components/job-form-dialog';
import { AdvanceStageDialog } from '@/app/(admin)/recruitment/jobs/[id]/_components/advance-stage-dialog';
import { useOptionLists } from '@/hooks/use-option-lists';
import { jobsApi, recruitmentPipelinesApi } from '@/lib/api/endpoints/recruitment';
import { formatDate } from '@/lib/attendance-format';
import {
  EMPLOYMENT_TYPE_LABELS,
  WORK_MODE_LABELS,
} from '@/lib/constants/recruitment-options';
import { hasPermission, useAuthStore } from '@/lib/stores/auth-store';
import { cn } from '@/lib/utils';
import type { RecruitmentPipelineStage } from '@/lib/api/types';

export default function JobDetailPage() {
  const params = useParams<{ id: string }>();
  const jobId = Number(params.id);
  const qc = useQueryClient();
  const user = useAuthStore((s) => s.user);
  const canManage = hasPermission(user, 'manage-jobs');
  const canAdvance = hasPermission(user, 'advance-job-stage');
  const { labelOf } = useOptionLists();
  const [editOpen, setEditOpen] = React.useState(false);
  const [advanceOpen, setAdvanceOpen] = React.useState(false);

  const { data: jobRes, isLoading } = useQuery({
    queryKey: ['jobs', jobId],
    queryFn: async () => (await jobsApi.get(jobId)).data.data,
    enabled: Number.isFinite(jobId),
  });

  const { data: pipelineRes } = useQuery({
    queryKey: ['recruitment-pipelines', jobRes?.pipeline_id],
    queryFn: async () => (await recruitmentPipelinesApi.get(jobRes!.pipeline_id)).data.data,
    enabled: !!jobRes?.pipeline_id,
  });

  const cancelMutation = useMutation({
    mutationFn: () => jobsApi.cancel(jobId),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['jobs'] });
      toast.success('تم إلغاء الوظيفة');
    },
    onError: () => toast.error('تعذر الإلغاء'),
  });

  if (isLoading) return <Skeleton className="h-96 rounded-xl" />;
  if (!jobRes) return <p className="p-8 text-center text-sm text-muted">لم يتم العثور على الوظيفة.</p>;
  const job = jobRes;
  const stages: RecruitmentPipelineStage[] = pipelineRes?.stages
    ? [...pipelineRes.stages].sort((a, b) => a.display_order - b.display_order)
    : [];
  const currentIndex = stages.findIndex((s) => s.id === job.current_stage_id);
  const isTerminal = job.current_stage?.is_terminal || job.status === 'filled' || job.status === 'cancelled';

  return (
    <div className="space-y-6">
      <nav className="flex items-center gap-1 text-xs text-muted">
        <Link href="/recruitment/jobs" className="hover:text-brand-ink">الوظائف</Link>
        <ChevronRight className="h-3 w-3" />
        <span className="num" dir="ltr">{job.job_number}</span>
      </nav>

      <div className="rounded-xl border border-hairline bg-surface p-6">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <div className="flex items-center gap-3">
              <h1 className="text-2xl font-bold text-ink">{job.title}</h1>
              <JobStatusBadge status={job.status} />
            </div>
            <p className="mt-1 num text-xs text-muted" dir="ltr">{job.job_number}</p>
            <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-ink-2">
              {job.recruitment_case?.client && (
                <Link href={`/recruitment/clients/${job.recruitment_case.client.id}`} className="text-brand-ink hover:underline">
                  {job.recruitment_case.client.company_name}
                </Link>
              )}
              {job.recruitment_case && (
                <Link href={`/recruitment/cases/${job.recruitment_case.id}`} className="hover:text-brand-ink">
                  {job.recruitment_case.title}
                </Link>
              )}
              <span>الشواغر: <span className="num" dir="ltr">{job.openings}</span></span>
              {job.owner && <span>المالك: {job.owner.name}</span>}
            </div>
          </div>
          <div className="flex flex-wrap gap-2">
            {canManage && (
              <Button type="button" variant="outline" className="gap-2" onClick={() => setEditOpen(true)}>
                <Pencil className="h-4 w-4" /> تعديل
              </Button>
            )}
            {canAdvance && !isTerminal && (
              <Button
                type="button"
                className="gap-2 bg-brand text-white hover:bg-brand-hover"
                onClick={() => setAdvanceOpen(true)}
              >
                <ArrowRight className="h-4 w-4" /> نقل للمرحلة التالية
              </Button>
            )}
            {canManage && !isTerminal && (
              <Button
                type="button"
                variant="outline"
                className="gap-2 text-danger hover:bg-danger-soft"
                onClick={() => cancelMutation.mutate()}
                disabled={cancelMutation.isPending}
              >
                <Ban className="h-4 w-4" /> إلغاء الوظيفة
              </Button>
            )}
          </div>
        </div>
      </div>

      {stages.length > 0 && (
        <div className="rounded-xl border border-hairline bg-surface p-5">
          <h2 className="mb-4 text-sm font-semibold text-ink">مسار المراحل</h2>
          <ol className="flex flex-wrap items-center gap-y-3 overflow-x-auto">
            {stages.map((stage, index) => {
              const isCurrent = stage.id === job.current_stage_id;
              const isDone = currentIndex >= 0 && index < currentIndex;
              return (
                <li key={stage.id} className="flex items-center">
                  <div
                    className={cn(
                      'flex items-center gap-2 rounded-lg border px-3 py-1.5 text-xs font-medium',
                      isCurrent && 'border-brand bg-brand-soft text-brand-ink shadow-sm',
                      isDone && 'border-success-soft bg-success-soft text-success',
                      !isCurrent && !isDone && 'border-hairline bg-surface text-ink-2',
                    )}
                  >
                    {isDone ? (
                      <Check className="h-3.5 w-3.5" />
                    ) : (
                      <Circle className={cn('h-3.5 w-3.5', isCurrent && 'fill-brand text-brand')} />
                    )}
                    <span>{stage.name}</span>
                    {stage.is_terminal && <span className="text-[10px] opacity-60">(نهائية)</span>}
                  </div>
                  {index < stages.length - 1 && <div className="mx-2 h-px w-6 bg-hairline" />}
                </li>
              );
            })}
          </ol>
          {job.stage_entered_at && (
            <p className="mt-3 num text-xs text-muted" dir="ltr">
              دخلت المرحلة الحالية في: {formatDate(job.stage_entered_at)}
            </p>
          )}
        </div>
      )}

      <div className="grid gap-4 lg:grid-cols-3">
        <div className="space-y-4 lg:col-span-2">
          <SectionCard title="التفاصيل">
            <dl className="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
              <Row label="الإدارة" value={job.department} />
              <Row label="نوع التوظيف" value={EMPLOYMENT_TYPE_LABELS[job.employment_type]} />
              <Row label="نمط العمل" value={WORK_MODE_LABELS[job.work_mode]} />
              <Row label="الموقع" value={job.location} />
              <Row
                label="نطاق الراتب"
                value={
                  job.salary_min || job.salary_max ? (
                    <span className="num" dir="ltr">
                      {job.salary_min ?? '?'} – {job.salary_max ?? '?'} {job.salary_currency ?? ''}
                    </span>
                  ) : null
                }
              />
              <Row label="سنوات الخبرة" value={job.required_experience_years} isNumber />
              <Row label="المستوى التعليمي" value={labelOf('education_levels', job.education_level)} />
              <Row label="آخر موعد للتقديم" value={job.application_deadline ? formatDate(job.application_deadline) : null} isNumber />
              <Row label="تاريخ البدء المستهدف" value={job.target_start_date ? formatDate(job.target_start_date) : null} isNumber />
              <Row
                label="رابط النشر"
                value={
                  job.publication_url ? (
                    <a href={job.publication_url} target="_blank" rel="noreferrer" className="text-brand-ink hover:underline" dir="ltr">
                      فتح
                    </a>
                  ) : null
                }
              />
              <Row label="نُشر في" value={job.published_at ? formatDate(job.published_at) : null} isNumber />
            </dl>
            {job.required_skills && job.required_skills.length > 0 && (
              <SkillsRow label="المهارات المطلوبة" items={job.required_skills} />
            )}
            {job.nice_to_have_skills && job.nice_to_have_skills.length > 0 && (
              <SkillsRow label="مهارات مفضّلة" items={job.nice_to_have_skills} />
            )}
            {job.required_languages && job.required_languages.length > 0 && (
              <SkillsRow label="اللغات" items={job.required_languages} />
            )}
          </SectionCard>

          {(job.description || job.responsibilities) && (
            <SectionCard title="الوصف والمسؤوليات">
              {job.description && (
                <div>
                  <p className="text-xs font-semibold text-ink-2">الوصف</p>
                  <p className="mt-1 whitespace-pre-wrap text-sm text-ink">{job.description}</p>
                </div>
              )}
              {job.responsibilities && (
                <div className="mt-4 border-t border-hairline pt-4">
                  <p className="text-xs font-semibold text-ink-2">المسؤوليات</p>
                  <p className="mt-1 whitespace-pre-wrap text-sm text-ink">{job.responsibilities}</p>
                </div>
              )}
            </SectionCard>
          )}
        </div>

        <aside className="space-y-4">
          <SectionCard title="حالة المرحلة">
            <dl className="space-y-3">
              <Row label="المسار" value={job.pipeline?.name} />
              <Row label="المرحلة الحالية" value={job.current_stage?.name} />
              <Row label="نوع مالك المرحلة" value={job.current_stage?.owner_rule_type} />
              {job.current_stage?.sla_hours ? (
                <Row label="SLA (ساعة)" value={job.current_stage.sla_hours} isNumber />
              ) : null}
              <Row label="أنشئت في" value={formatDate(job.created_at)} isNumber />
              {job.completed_at && <Row label="اكتملت في" value={formatDate(job.completed_at)} isNumber />}
            </dl>
          </SectionCard>
        </aside>
      </div>

      <JobFormDialog open={editOpen} onOpenChange={setEditOpen} job={job} />
      {advanceOpen && <AdvanceStageDialog open={advanceOpen} onOpenChange={setAdvanceOpen} job={job} />}
    </div>
  );
}

function SectionCard({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="rounded-xl border border-hairline bg-surface p-5">
      <h2 className="mb-3 text-sm font-semibold text-ink">{title}</h2>
      {children}
    </div>
  );
}

function Row({
  label,
  value,
  isNumber = false,
}: {
  label: string;
  value: React.ReactNode | string | number | null;
  isNumber?: boolean;
}) {
  const display: React.ReactNode = value === null || value === undefined || value === '' ? (
    <span className="text-xs text-muted">—</span>
  ) : (
    value
  );
  return (
    <div className="grid grid-cols-[130px_1fr] items-baseline gap-3">
      <dt className="text-xs text-muted">{label}</dt>
      <dd className={cn('text-sm text-ink', isNumber && 'num')} dir={isNumber ? 'ltr' : undefined}>
        {display}
      </dd>
    </div>
  );
}

function SkillsRow({ label, items }: { label: string; items: string[] }) {
  return (
    <div className="mt-4 border-t border-hairline pt-4">
      <p className="mb-2 text-xs font-semibold text-ink-2">{label}</p>
      <div className="flex flex-wrap gap-1.5">
        {items.map((s) => (
          <span key={s} className="rounded-full border border-hairline bg-surface-2 px-2.5 py-0.5 text-[11px] text-ink">
            {s}
          </span>
        ))}
      </div>
    </div>
  );
}
