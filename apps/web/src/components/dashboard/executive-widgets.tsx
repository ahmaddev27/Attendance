'use client';

import * as React from 'react';
import Link from 'next/link';
import dynamic from 'next/dynamic';
import { useQuery } from '@tanstack/react-query';
import {
  BarChart3,
  Briefcase,
  CalendarClock,
  CalendarDays,
  PieChart as PieIcon,
  Timer,
  TrendingUp,
} from 'lucide-react';

import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { analyticsApi } from '@/lib/api/endpoints/analytics';
import { attendanceApi } from '@/lib/api/endpoints/attendance';
import { interviewsApi } from '@/lib/api/endpoints/candidates';
import { leaveRequestsApi } from '@/lib/api/endpoints/leaves';
import { recruitmentDashboardApi, recruitmentPipelinesApi } from '@/lib/api/endpoints/recruitment';
import { reportsApi } from '@/lib/api/endpoints/reports';
import { formatDate } from '@/lib/attendance-format';
import { cn } from '@/lib/utils';
import type { RecruitmentPipeline } from '@/lib/api/types';

const chartSkeleton = (h: string) => ({ ssr: false, loading: () => <Skeleton className={h} /> });
const StageBarChart = dynamic(() => import('./dashboard-charts').then((m) => m.StageBarChart), chartSkeleton('h-44 rounded-xl'));
const AttendanceTrendChart = dynamic(() => import('./dashboard-charts').then((m) => m.AttendanceTrendChart), chartSkeleton('h-56 rounded-xl'));
const LeaveTypePie = dynamic(() => import('./dashboard-charts').then((m) => m.LeaveTypePie), chartSkeleton('h-56 rounded-xl'));

const WEEKDAYS = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];

// Local-date YMD: toISOString() would shift the day for UTC+ offsets late at night.
function ymd(d: Date): string {
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${d.getFullYear()}-${m}-${day}`;
}

function addDays(d: Date, n: number): Date {
  const copy = new Date(d);
  copy.setDate(copy.getDate() + n);
  return copy;
}

function Widget({
  title,
  icon,
  href,
  className,
  children,
}: {
  title: string;
  icon: React.ReactNode;
  href?: string;
  className?: string;
  children: React.ReactNode;
}) {
  return (
    <Card className={cn('border-hairline bg-surface shadow-none', className)}>
      <CardContent className="p-5">
        <div className="mb-4 flex items-center justify-between gap-2">
          <h2 className="flex items-center gap-2 text-base font-bold text-ink">
            <span className="text-brand">{icon}</span>
            {title}
          </h2>
          {href && (
            <Link href={href} className="text-xs font-semibold text-brand hover:text-brand-hover">
              عرض الكل ←
            </Link>
          )}
        </div>
        {children}
      </CardContent>
    </Card>
  );
}

function Empty({ text }: { text: string }) {
  return (
    <div className="grid h-32 place-items-center text-center text-sm text-muted">
      <div>
        <p className="text-2xl text-ink-2/40">—</p>
        <p>{text}</p>
      </div>
    </div>
  );
}

function ListSkeleton({ rows = 4 }: { rows?: number }) {
  return (
    <div className="space-y-2">
      {Array.from({ length: rows }).map((_, i) => (
        <Skeleton key={i} className="h-10 w-full rounded-lg" />
      ))}
    </div>
  );
}

function StatTile({ label, value, loading, tone }: { label: string; value?: number; loading?: boolean; tone: string }) {
  return (
    <div className="rounded-xl border border-hairline bg-surface-2 p-4">
      <p className="text-xs text-muted">{label}</p>
      {loading ? (
        <Skeleton className="mt-2 h-8 w-14" />
      ) : (
        <p className={cn('num mt-1 text-3xl font-bold', tone)} dir="ltr">
          {value ?? '—'}
        </p>
      )}
    </div>
  );
}

function stageLabels(pipelines: RecruitmentPipeline[]): Record<string, string> {
  const labels: Record<string, string> = {};
  for (const p of pipelines) {
    for (const s of p.stages ?? []) labels[String(s.id)] = pipelines.length > 1 ? `${p.name} · ${s.name}` : s.name;
  }
  return labels;
}

// ---------- Recruitment ----------------------------------------------------

export function RecruitmentWidgets() {
  const kpis = useQuery({
    queryKey: ['recruitment-dashboard', 'kpis'],
    queryFn: async () => (await recruitmentDashboardApi.kpis()).data.data,
  });
  const funnel = useQuery({
    queryKey: ['recruitment-dashboard', 'funnel'],
    queryFn: async () => (await recruitmentDashboardApi.funnel()).data.data,
  });
  const pipelines = useQuery({
    queryKey: ['recruitment-pipelines', 'list', { per_page: 100 }],
    queryFn: async () => (await recruitmentPipelinesApi.list({ per_page: 100 })).data.data,
  });
  // "This week" = the next 7 days; a rolling window is more useful than Sun-Sat.
  const from = ymd(new Date());
  const to = ymd(addDays(new Date(), 7));
  const interviews = useQuery({
    queryKey: ['admin', 'dashboard', 'interviews', from],
    queryFn: async () => (await interviewsApi.list({ status: 'scheduled', from, to, per_page: 50 })).data,
  });

  const stageData = React.useMemo(() => {
    const labels = stageLabels(pipelines.data ?? []);
    return Object.entries(funnel.data?.jobs_by_stage ?? {})
      .map(([id, count]) => ({ name: labels[id] ?? `مرحلة ${id}`, count }))
      .filter((s) => s.count > 0);
  }, [funnel.data, pipelines.data]);

  const upcoming = [...(interviews.data?.data ?? [])]
    .sort((a, b) => a.scheduled_at.localeCompare(b.scheduled_at))
    .slice(0, 5);

  return (
    <>
      <Widget title="الوظائف حسب المرحلة" icon={<BarChart3 className="h-4 w-4" />} href="/recruitment/dashboard" className="md:col-span-7">
        {funnel.isLoading ? (
          <Skeleton className="h-44 rounded-xl" />
        ) : stageData.length === 0 ? (
          <Empty text="لا توجد وظائف مفتوحة في خط التوظيف" />
        ) : (
          <StageBarChart data={stageData} />
        )}
      </Widget>

      <div className="grid gap-4 md:col-span-5">
        <Widget title="التوظيف في سطور" icon={<Briefcase className="h-4 w-4" />} href="/recruitment/dashboard">
          <div className="grid grid-cols-2 gap-3">
            <StatTile label="وظائف مفتوحة" value={kpis.data?.jobs_open} loading={kpis.isLoading} tone="text-brand" />
            <StatTile label="شُغلت هذا الشهر" value={kpis.data?.jobs_filled_this_month} loading={kpis.isLoading} tone="text-success" />
          </div>
        </Widget>
        <Widget title="مقابلات هذا الأسبوع" icon={<CalendarClock className="h-4 w-4" />}>
          <p className="num mb-3 text-3xl font-bold text-brand" dir="ltr">
            {interviews.isLoading ? '…' : (interviews.data?.meta?.total ?? upcoming.length)}
          </p>
          {interviews.isLoading ? (
            <ListSkeleton rows={3} />
          ) : upcoming.length === 0 ? (
            <Empty text="لا يوجد مقابلات مجدولة" />
          ) : (
            <ul className="space-y-1.5">
              {upcoming.map((iv) => (
                <li key={iv.id} className="flex items-center justify-between gap-2 rounded-lg bg-surface-2 px-3 py-2 text-sm">
                  <span className="truncate text-ink">{iv.application?.candidate?.full_name ?? iv.interview_number}</span>
                  <span className="num shrink-0 text-xs text-muted" dir="ltr">
                    {formatDate(iv.scheduled_at)} · {iv.scheduled_at.slice(11, 16)}
                  </span>
                </li>
              ))}
            </ul>
          )}
        </Widget>
      </div>
    </>
  );
}

// ---------- Attendance -----------------------------------------------------

export function AttendanceWidgets({ companyId }: { companyId?: number }) {
  const trend = useQuery({
    queryKey: ['admin', 'dashboard', 'attendance-trend', { companyId }],
    // The stats endpoint is a range aggregate, so a per-day series needs one
    // call per day; 7 small parallel calls beat adding a backend endpoint here.
    queryFn: async () => {
      const days = Array.from({ length: 7 }, (_, i) => addDays(new Date(), i - 6));
      return Promise.all(
        days.map(async (d) => {
          const date = ymd(d);
          const s = (await attendanceApi.stats({ date_from: date, date_to: date, company_id: companyId })).data.data;
          return { label: WEEKDAYS[d.getDay()], present: s.present_count, absent: s.absent_count };
        }),
      );
    },
    staleTime: 60_000,
  });

  const now = new Date();
  const late = useQuery({
    queryKey: ['admin', 'dashboard', 'late-top', now.getFullYear(), now.getMonth(), { companyId }],
    queryFn: async () =>
      (await reportsApi.attendanceMonthly({ year: now.getFullYear(), month: now.getMonth() + 1, company_id: companyId })).data
        .data,
    staleTime: 60_000,
  });

  const top = [...(late.data ?? [])]
    .filter((r) => r.late_minutes > 0)
    .sort((a, b) => b.late_minutes - a.late_minutes)
    .slice(0, 5);
  const max = top[0]?.late_minutes ?? 1;

  return (
    <>
      <Widget title="الحضور آخر 7 أيام" icon={<TrendingUp className="h-4 w-4" />} href="/attendance" className="md:col-span-7">
        {trend.isLoading ? <Skeleton className="h-56 rounded-xl" /> : <AttendanceTrendChart data={trend.data ?? []} />}
      </Widget>
      <Widget title="الأكثر تأخراً هذا الشهر" icon={<Timer className="h-4 w-4" />} href="/reports" className="md:col-span-5">
        {late.isLoading ? (
          <ListSkeleton rows={5} />
        ) : top.length === 0 ? (
          <Empty text="لا توجد دقائق تأخير هذا الشهر" />
        ) : (
          <ol className="space-y-3">
            {top.map((r, i) => (
              <li key={r.employee_id}>
                <div className="flex items-center justify-between gap-2 text-sm">
                  <span className="truncate text-ink">
                    <span className="num me-2 text-muted" dir="ltr">{i + 1}.</span>
                    {r.full_name}
                  </span>
                  <span className="num shrink-0 font-semibold text-warn-ink" dir="ltr">{r.late_minutes} د</span>
                </div>
                <div className="mt-1 h-1.5 rounded-full bg-surface-2">
                  {/* In RTL the bar fills from the right edge by default. */}
                  <div className="h-full rounded-full bg-warn" style={{ width: `${(r.late_minutes / max) * 100}%` }} />
                </div>
              </li>
            ))}
          </ol>
        )}
      </Widget>
    </>
  );
}

// ---------- Leaves ---------------------------------------------------------

export function LeaveWidgets({ companyId }: { companyId?: number }) {
  const today = new Date();
  const from = ymd(today);
  const to = ymd(addDays(today, 14));
  const upcoming = useQuery({
    queryKey: ['admin', 'dashboard', 'leaves-upcoming', from, { companyId }],
    queryFn: async () =>
      (
        await leaveRequestsApi.list({ status: 'approved', start_date: from, end_date: to, per_page: 100, company_id: companyId })
      ).data.data,
  });
  const patterns = useQuery({
    queryKey: ['admin', 'dashboard', 'leave-types', today.getFullYear(), today.getMonth()],
    queryFn: async () =>
      (
        await analyticsApi.leavePatterns({
          from: ymd(new Date(today.getFullYear(), today.getMonth(), 1)),
          to: ymd(new Date(today.getFullYear(), today.getMonth() + 1, 0)),
        })
      ).data.data.by_type,
  });

  // The API filter's overlap semantics are not guaranteed, so re-check here.
  const rows = (upcoming.data ?? [])
    .filter((l) => l.end_date.slice(0, 10) >= from && l.start_date.slice(0, 10) <= to)
    .sort((a, b) => a.start_date.localeCompare(b.start_date))
    .slice(0, 8);
  const pie = (patterns.data ?? []).filter((t) => t.days > 0);

  return (
    <>
      <Widget title="إجازات معتمدة قادمة (14 يوماً)" icon={<CalendarDays className="h-4 w-4" />} href="/leaves" className="md:col-span-7">
        {upcoming.isLoading ? (
          <ListSkeleton />
        ) : rows.length === 0 ? (
          <Empty text="لا توجد إجازات معتمدة خلال 14 يوماً القادمة" />
        ) : (
          <ul className="space-y-1.5">
            {rows.map((l) => (
              <li key={l.id} className="flex items-center justify-between gap-3 rounded-lg bg-surface-2 px-3 py-2 text-sm">
                <div className="min-w-0">
                  <p className="truncate font-medium text-ink">{l.employee.full_name}</p>
                  <p className="text-xs text-muted">{l.leave_type.name}</p>
                </div>
                <span className="num shrink-0 text-xs text-ink-2" dir="ltr">
                  {formatDate(l.start_date)} → {formatDate(l.end_date)}
                </span>
              </li>
            ))}
          </ul>
        )}
      </Widget>
      <Widget title="أنواع الإجازات هذا الشهر" icon={<PieIcon className="h-4 w-4" />} href="/analytics" className="md:col-span-5">
        {patterns.isLoading ? (
          <Skeleton className="h-56 rounded-xl" />
        ) : pie.length === 0 ? (
          <Empty text="لا توجد إجازات مسجلة هذا الشهر" />
        ) : (
          <LeaveTypePie data={pie} />
        )}
      </Widget>
    </>
  );
}
