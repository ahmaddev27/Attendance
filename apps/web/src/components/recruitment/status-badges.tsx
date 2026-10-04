'use client';

import { cn } from '@/lib/utils';
import {
  CASE_STATUS_META,
  CLIENT_STATUS_META,
  JOB_STATUS_META,
  LEAD_STATUS_META,
} from '@/lib/constants/recruitment-options';
import {
  APPLICATION_STATUS_LABEL,
  INTERVIEW_KIND_LABEL,
  INTERVIEW_STATUS_LABEL,
} from '@/lib/api/endpoints/candidates';
import type {
  CandidateApplicationStatus,
  ClientStatus,
  InterviewKind,
  InterviewStatus,
  JobRequirementStatus,
  LeadStatus,
  RecruitmentCaseStatus,
} from '@/lib/api/types';

/**
 * One-file bundle of the four Recruitment status pills. Each pill is a
 * `Badge` with the tone class picked from the module's central meta
 * map, so a label/color change in `recruitment-options.ts` flows to
 * every render point automatically.
 */

const BASE = 'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11px] font-semibold';

export function LeadStatusBadge({ status, className }: { status: LeadStatus; className?: string }) {
  const meta = LEAD_STATUS_META[status];
  return (
    <span className={cn(BASE, meta.className, className)}>
      <span className={cn('h-1.5 w-1.5 rounded-full', meta.dotClassName)} aria-hidden="true" />
      {meta.label}
    </span>
  );
}

export function ClientStatusBadge({ status, className }: { status: ClientStatus; className?: string }) {
  const meta = CLIENT_STATUS_META[status];
  return <span className={cn(BASE, meta.className, className)}>{meta.label}</span>;
}

export function CaseStatusBadge({ status, className }: { status: RecruitmentCaseStatus; className?: string }) {
  const meta = CASE_STATUS_META[status];
  return <span className={cn(BASE, meta.className, className)}>{meta.label}</span>;
}

export function JobStatusBadge({ status, className }: { status: JobRequirementStatus; className?: string }) {
  const meta = JOB_STATUS_META[status];
  return <span className={cn(BASE, meta.className, className)}>{meta.label}</span>;
}

const APPLICATION_TONE: Record<CandidateApplicationStatus, string> = {
  applied: 'border-hairline bg-surface-2 text-ink-2',
  in_screening: 'border-transparent bg-brand-soft text-brand-ink',
  screened_in: 'border-transparent bg-success-soft text-success',
  screened_out: 'border-transparent bg-danger-soft text-danger',
  shortlisted: 'border-transparent bg-brand-soft text-brand-ink',
  interviewing: 'border-transparent bg-brand-soft text-brand-ink',
  client_review: 'border-transparent bg-amber-50 text-amber-700',
  offered: 'border-transparent bg-amber-50 text-amber-700',
  rejected: 'border-transparent bg-danger-soft text-danger',
  withdrawn: 'border-hairline bg-surface-2 text-muted',
  hired: 'border-transparent bg-success-soft text-success',
};

export function ApplicationStatusBadge({ status, className }: { status: CandidateApplicationStatus; className?: string }) {
  return (
    <span className={cn(BASE, APPLICATION_TONE[status], className)}>
      {APPLICATION_STATUS_LABEL[status] ?? status}
    </span>
  );
}

const INTERVIEW_STATUS_TONE: Record<InterviewStatus, string> = {
  scheduled: 'border-transparent bg-brand-soft text-brand-ink',
  completed: 'border-transparent bg-success-soft text-success',
  cancelled: 'border-transparent bg-danger-soft text-danger',
  no_show: 'border-transparent bg-amber-50 text-amber-700',
  rescheduled: 'border-hairline bg-surface-2 text-ink-2',
};

export function InterviewStatusBadge({ status, className }: { status: InterviewStatus; className?: string }) {
  return (
    <span className={cn(BASE, INTERVIEW_STATUS_TONE[status], className)}>
      {INTERVIEW_STATUS_LABEL[status] ?? status}
    </span>
  );
}

export function InterviewKindBadge({ kind, className }: { kind: InterviewKind; className?: string }) {
  const tone = kind === 'client' ? 'border-transparent bg-amber-50 text-amber-700' : 'border-hairline bg-surface-2 text-ink-2';
  return <span className={cn(BASE, tone, className)}>{INTERVIEW_KIND_LABEL[kind] ?? kind}</span>;
}
