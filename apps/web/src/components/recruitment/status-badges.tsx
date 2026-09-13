'use client';

import { cn } from '@/lib/utils';
import {
  CASE_STATUS_META,
  CLIENT_STATUS_META,
  JOB_STATUS_META,
  LEAD_STATUS_META,
} from '@/lib/constants/recruitment-options';
import type {
  ClientStatus,
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
