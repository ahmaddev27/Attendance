'use client';

import * as React from 'react';
import Link from 'next/link';
import { useQueries } from '@tanstack/react-query';
import { Briefcase, Building2, Flag, FolderKanban, Target, Trophy, XCircle } from 'lucide-react';

import { KpiCard } from '@/components/analytics/kpi-card';
import { Skeleton } from '@/components/ui/skeleton';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { recruitmentDashboardApi, recruitmentPipelinesApi } from '@/lib/api/endpoints/recruitment';
import { formatDate } from '@/lib/attendance-format';
import { LEAD_STATUS_META } from '@/lib/constants/recruitment-options';
import { cn } from '@/lib/utils';
import type { LeadStatus, RecruitmentPipeline } from '@/lib/api/types';

const LEAD_STATUS_ORDER = Object.keys(LEAD_STATUS_META) as LeadStatus[];

export default function RecruitmentDashboardPage() {
  // Four parallel fetches. Pipelines are only needed to turn the funnel's
  // stage ids into readable "pipeline · stage" labels.
  const [kpis, funnel, leaderboard, pipelines] = useQueries({
    queries: [
      {
        queryKey: ['recruitment-dashboard', 'kpis'],
        queryFn: async () => (await recruitmentDashboardApi.kpis()).data.data,
      },
      {
        queryKey: ['recruitment-dashboard', 'funnel'],
        queryFn: async () => (await recruitmentDashboardApi.funnel()).data.data,
      },
      {
        queryKey: ['recruitment-dashboard', 'leaderboard'],
        queryFn: async () => (await recruitmentDashboardApi.leaderboard()).data,
      },
      {
        queryKey: ['recruitment-pipelines', 'list', { per_page: 100 }],
        queryFn: async () => (await recruitmentPipelinesApi.list({ per_page: 100 })).data.data,
      },
    ],
  });

  const stageLabels = React.useMemo(() => buildStageLabels(pipelines.data ?? []), [pipelines.data]);

  return (
    <div className="space-y-6">
      <div>
        <p className="text-xs font-medium text-muted">التوظيف</p>
        <h1 className="mt-1 text-2xl font-bold text-ink">لوحة التوظيف</h1>
      </div>

      {kpis.isLoading ? (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          {Array.from({ length: 7 }).map((_, i) => <Skeleton key={i} className="h-24 rounded-xl" />)}
        </div>
      ) : kpis.data ? (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <KpiCard label="عملاء محتملون نشطون" value={kpis.data.leads_active} icon={Target} tone="brand" />
          <KpiCard label="تم تحويلهم" value={kpis.data.leads_converted} icon={Trophy} tone="success" />
          <KpiCard label="خاسرون" value={kpis.data.leads_lost} icon={XCircle} tone="danger" />
          <KpiCard label="عملاء نشطون" value={kpis.data.clients_active} icon={Building2} tone="neutral" />
          <KpiCard label="حملات مفتوحة" value={kpis.data.cases_open} icon={FolderKanban} tone="brand" />
          <KpiCard label="وظائف مفتوحة" value={kpis.data.jobs_open} icon={Briefcase} tone="warn" />
          <KpiCard label="وظائف أُغلقت هذا الشهر" value={kpis.data.jobs_filled_this_month} icon={Flag} tone="success" />
        </div>
      ) : (
        <ErrorNote>تعذر تحميل المؤشرات.</ErrorNote>
      )}

      <div className="grid gap-4 lg:grid-cols-2">
        <Panel title="العملاء المحتملون حسب الحالة" action={{ href: '/recruitment/leads/kanban', label: 'اللوحة' }}>
          {funnel.isLoading ? (
            <Skeleton className="h-64 rounded-lg" />
          ) : funnel.data ? (
            <BarList
              rows={LEAD_STATUS_ORDER.map((status) => ({
                key: status,
                label: LEAD_STATUS_META[status].label,
                value: funnel.data!.leads_by_status[status] ?? 0,
                barClassName: LEAD_STATUS_META[status].dotClassName,
              }))}
            />
          ) : (
            <ErrorNote>تعذر تحميل القمع.</ErrorNote>
          )}
        </Panel>

        <Panel title="الوظائف حسب المرحلة" action={{ href: '/recruitment/jobs', label: 'كل الوظائف' }}>
          {funnel.isLoading ? (
            <Skeleton className="h-64 rounded-lg" />
          ) : funnel.data ? (
            <BarList
              emptyText="لا وظائف قائمة على أي مرحلة."
              rows={Object.entries(funnel.data.jobs_by_stage)
                .map(([stageId, count]) => ({
                  key: stageId,
                  label: stageLabels[stageId] ?? `مرحلة #${stageId}`,
                  value: count,
                  barClassName: 'bg-brand',
                }))
                .sort((a, b) => b.value - a.value)}
            />
          ) : (
            <ErrorNote>تعذر تحميل القمع.</ErrorNote>
          )}
        </Panel>
      </div>

      <Panel
        title="الأكثر تحويلاً هذا الربع"
        subtitle={leaderboard.data ? `منذ ${formatDate(leaderboard.data.meta.quarter_started_at)}` : undefined}
      >
        {leaderboard.isLoading ? (
          <Skeleton className="h-40 rounded-lg" />
        ) : leaderboard.data ? (
          leaderboard.data.data.length === 0 ? (
            <p className="py-8 text-center text-sm text-muted">لا تحويلات هذا الربع بعد.</p>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="w-12 text-start">#</TableHead>
                  <TableHead className="text-start">المسؤول</TableHead>
                  <TableHead className="text-start">التحويلات</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {leaderboard.data.data.map((row, i) => (
                  <TableRow key={row.owner_id}>
                    <TableCell className="num" dir="ltr">{i + 1}</TableCell>
                    <TableCell>
                      <p className="text-sm font-medium text-ink">{row.owner_name ?? row.owner_email ?? '—'}</p>
                      {row.owner_name && row.owner_email && (
                        <p className="text-xs text-muted" dir="ltr">{row.owner_email}</p>
                      )}
                    </TableCell>
                    <TableCell className="num font-semibold text-brand-ink" dir="ltr">{row.conversions}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )
        ) : (
          <ErrorNote>تعذر تحميل القائمة.</ErrorNote>
        )}
      </Panel>
    </div>
  );
}

function buildStageLabels(pipelines: RecruitmentPipeline[]): Record<string, string> {
  const labels: Record<string, string> = {};
  for (const pipeline of pipelines) {
    for (const stage of pipeline.stages ?? []) {
      labels[String(stage.id)] = pipelines.length > 1 ? `${pipeline.name} · ${stage.name}` : stage.name;
    }
  }
  return labels;
}

function Panel({
  title,
  subtitle,
  action,
  children,
}: {
  title: string;
  subtitle?: string;
  action?: { href: string; label: string };
  children: React.ReactNode;
}) {
  return (
    <section className="rounded-xl border border-hairline bg-surface p-4">
      <div className="mb-3 flex items-start justify-between gap-3">
        <div>
          <h2 className="text-sm font-semibold text-ink">{title}</h2>
          {subtitle && <p className="mt-0.5 text-xs text-muted">{subtitle}</p>}
        </div>
        {action && (
          <Link href={action.href} className="text-xs text-brand-ink hover:underline">
            {action.label}
          </Link>
        )}
      </div>
      {children}
    </section>
  );
}

type BarRow = { key: string; label: string; value: number; barClassName: string };

function BarList({ rows, emptyText = 'لا بيانات.' }: { rows: BarRow[]; emptyText?: string }) {
  const max = Math.max(0, ...rows.map((r) => r.value));
  if (rows.length === 0 || max === 0) {
    return <p className="py-8 text-center text-sm text-muted">{emptyText}</p>;
  }
  return (
    <ul className="space-y-2">
      {rows.map((row) => (
        <li key={row.key} className="grid grid-cols-[minmax(0,9rem)_1fr_2.5rem] items-center gap-3 text-xs">
          <span className="truncate text-ink-2" title={row.label}>{row.label}</span>
          <div className="h-2.5 overflow-hidden rounded-full bg-surface-2">
            <div
              className={cn('h-full rounded-full transition-[width]', row.barClassName)}
              style={{ width: `${(row.value / max) * 100}%` }}
            />
          </div>
          <span className="num text-end font-semibold text-ink" dir="ltr">{row.value}</span>
        </li>
      ))}
    </ul>
  );
}

function ErrorNote({ children }: { children: React.ReactNode }) {
  return <p className="py-6 text-center text-sm text-danger">{children}</p>;
}
