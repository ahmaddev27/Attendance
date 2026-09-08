'use client';

import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import {
  AlertTriangle,
  ArrowUpRight,
  CheckCircle2,
  Clock,
  Coffee,
  Flame,
  Inbox,
  ListChecks,
  Play,
  UserCheck,
  UserX,
  Users,
} from 'lucide-react';

import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { adminDashboardApi, type AdminDashboardKpis } from '@/lib/api/endpoints/admin-dashboard';
import { useAuthStore } from '@/lib/stores/auth-store';
import { cn } from '@/lib/utils';

// Arabic day/month labels — no Intl dep, no browser locale drift.
const DAYS = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
const MONTHS = [
  'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
  'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر',
];

function formatArabicDate(iso: string): string {
  const d = new Date(iso);
  return `${DAYS[d.getDay()]}، ${d.getDate()} ${MONTHS[d.getMonth()]} ${d.getFullYear()}`;
}

export default function AdminDashboardPage() {
  const user = useAuthStore((s) => s.user);
  const firstName = user?.name?.trim()?.split(' ')[0];

  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['admin', 'dashboard', 'kpis'],
    queryFn: async () => (await adminDashboardApi.kpis()).data.data,
    // Refresh every minute so counters like "present today" stay live
    // without hammering the endpoint.
    refetchInterval: 60_000,
  });

  const today = new Date().toISOString().slice(0, 10);

  return (
    <div className="space-y-6">
      {/* Hero — welcome + today's date */}
      <div className="flex flex-col gap-4 rounded-2xl border border-hairline bg-surface p-6 md:flex-row md:items-center md:justify-between md:p-8">
        <div>
          <p className="text-sm text-muted">أهلاً بعودتك</p>
          <h1 className="mt-1 text-2xl font-bold text-ink md:text-3xl">
            {firstName ? `مرحباً، ${firstName}` : 'لوحة التحكم'}
          </h1>
          <p className="mt-1 text-xs text-muted">{formatArabicDate(today)}</p>
        </div>
        {data && (
          <div className="flex items-center gap-3 rounded-xl bg-brand-soft px-4 py-3 text-brand-ink">
            <UserCheck className="h-5 w-5 shrink-0" />
            <div>
              <p className="text-xs opacity-80">حاضرون اليوم</p>
              <p className="num text-lg font-bold" dir="ltr">
                {data.today.present}
                <span className="text-sm opacity-60"> / {data.employees.active}</span>
              </p>
            </div>
          </div>
        )}
      </div>

      {isError && (
        <Card className="border-danger-soft bg-danger-soft/40 p-4 text-sm text-danger">
          تعذّر تحميل بيانات لوحة التحكم.{' '}
          <button onClick={() => refetch()} className="font-semibold underline">
            إعادة المحاولة
          </button>
        </Card>
      )}

      {/* KPI grid — 4 big cards, collapse to 2 on tablet, 1 on mobile */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <KpiCard
          label="إجمالي الموظفين"
          value={data?.employees.total}
          icon={<Users className="h-5 w-5" />}
          tone="brand"
          loading={isLoading}
          href="/employees"
          breakdown={
            data && (
              <>
                <BreakdownChip label="نشط" value={data.employees.active} tone="success" />
                <BreakdownChip label="غير نشط" value={data.employees.inactive} tone="muted" />
              </>
            )
          }
        />
        <KpiCard
          label="حضور اليوم"
          value={data?.today.present}
          icon={<Clock className="h-5 w-5" />}
          tone="success"
          loading={isLoading}
          href="/attendance"
          breakdown={
            data && (
              <>
                <BreakdownChip label="متأخر" value={data.today.late} tone="warning" />
                <BreakdownChip label="غائب" value={data.today.absent} tone="danger" />
                <BreakdownChip label="إجازة" value={data.today.on_leave} tone="muted" />
              </>
            )
          }
        />
        <KpiCard
          label="بانتظار الموافقة"
          value={data?.pending.total}
          icon={<Inbox className="h-5 w-5" />}
          tone="warning"
          loading={isLoading}
          href="/approvals"
          breakdown={
            data && (
              <>
                <BreakdownChip label="إجازات" value={data.pending.leaves} tone="muted" />
                <BreakdownChip label="طلبات" value={data.pending.requests} tone="muted" />
              </>
            )
          }
        />
        <KpiCard
          label="مهام مفتوحة"
          value={data?.tasks.open}
          icon={<ListChecks className="h-5 w-5" />}
          tone="brand"
          loading={isLoading}
          href="/tasks"
          breakdown={
            data && (
              <>
                <BreakdownChip label="قيد التنفيذ" value={data.tasks.in_progress} tone="brand" />
                <BreakdownChip label="متأخرة" value={data.tasks.overdue} tone="danger" />
              </>
            )
          }
        />
      </div>

      {/* Details row */}
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <TodayOverviewCard data={data} loading={isLoading} />
        <PendingActionsCard data={data} loading={isLoading} />
      </div>
    </div>
  );
}

// ---------- Sub-components -----------------------------------------------

type Tone = 'brand' | 'success' | 'warning' | 'danger' | 'muted';

const TONE_CHIP: Record<Tone, string> = {
  brand: 'bg-brand-soft text-brand-ink',
  success: 'bg-success-soft text-success',
  warning: 'bg-warn-soft text-warn-ink',
  danger: 'bg-danger-soft text-danger',
  muted: 'bg-surface-2 text-ink-2',
};

const TONE_ICON_WRAP: Record<Tone, string> = {
  brand: 'bg-brand-soft text-brand-ink',
  success: 'bg-success-soft text-success',
  warning: 'bg-warn-soft text-warn-ink',
  danger: 'bg-danger-soft text-danger',
  muted: 'bg-surface-2 text-ink-2',
};

function KpiCard({
  label,
  value,
  icon,
  tone = 'brand',
  loading,
  href,
  breakdown,
}: {
  label: string;
  value: number | undefined;
  icon: React.ReactNode;
  tone?: Tone;
  loading?: boolean;
  href?: string;
  breakdown?: React.ReactNode;
}) {
  const body = (
    <div className="group flex h-full flex-col justify-between rounded-2xl border border-hairline bg-surface p-5 transition-all hover:border-brand/40 hover:shadow-sm">
      <div className="flex items-start justify-between">
        <span className={cn('grid h-10 w-10 place-items-center rounded-xl', TONE_ICON_WRAP[tone])}>
          {icon}
        </span>
        {href && (
          <ArrowUpRight className="h-4 w-4 text-ink-2/50 opacity-0 transition-opacity group-hover:opacity-100" />
        )}
      </div>
      <div className="mt-4">
        <p className="text-xs font-medium text-muted">{label}</p>
        {loading ? (
          <Skeleton className="mt-2 h-9 w-20" />
        ) : (
          <p className="num mt-1 text-3xl font-bold text-ink" dir="ltr">
            {value ?? '—'}
          </p>
        )}
        {breakdown && <div className="mt-3 flex flex-wrap gap-1.5">{breakdown}</div>}
      </div>
    </div>
  );

  return href ? <Link href={href}>{body}</Link> : body;
}

function BreakdownChip({ label, value, tone = 'muted' }: { label: string; value: number; tone?: Tone }) {
  return (
    <span
      className={cn(
        'inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-medium',
        TONE_CHIP[tone]
      )}
    >
      <span>{label}</span>
      <span className="num" dir="ltr">
        {value}
      </span>
    </span>
  );
}

function TodayOverviewCard({ data, loading }: { data?: AdminDashboardKpis; loading?: boolean }) {
  const rows: Array<{ label: string; value?: number; icon: React.ReactNode; tone: Tone }> = [
    { label: 'حاضرون', value: data?.today.present, icon: <CheckCircle2 className="h-4 w-4" />, tone: 'success' },
    { label: 'متأخرون', value: data?.today.late, icon: <Clock className="h-4 w-4" />, tone: 'warning' },
    { label: 'غائبون', value: data?.today.absent, icon: <UserX className="h-4 w-4" />, tone: 'danger' },
    { label: 'في إجازة', value: data?.today.on_leave, icon: <Coffee className="h-4 w-4" />, tone: 'muted' },
  ];

  return (
    <Card className="border-hairline bg-surface p-5 lg:col-span-2">
      <div className="mb-4 flex items-center justify-between">
        <h2 className="text-base font-semibold text-ink">حضور اليوم</h2>
        <Link href="/attendance" className="text-xs font-semibold text-brand hover:text-brand-hover">
          عرض كامل السجل ←
        </Link>
      </div>
      <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
        {rows.map((row) => (
          <div key={row.label} className="rounded-xl border border-hairline bg-surface-2 p-4">
            <div className="flex items-center gap-2">
              <span className={cn('grid h-7 w-7 place-items-center rounded-lg', TONE_ICON_WRAP[row.tone])}>
                {row.icon}
              </span>
              <p className="text-xs text-muted">{row.label}</p>
            </div>
            {loading ? (
              <Skeleton className="mt-3 h-7 w-12" />
            ) : (
              <p className="num mt-2 text-2xl font-bold text-ink" dir="ltr">
                {row.value ?? 0}
              </p>
            )}
          </div>
        ))}
      </div>
    </Card>
  );
}

function PendingActionsCard({ data, loading }: { data?: AdminDashboardKpis; loading?: boolean }) {
  return (
    <Card className="border-hairline bg-surface p-5">
      <div className="mb-4 flex items-center justify-between">
        <h2 className="text-base font-semibold text-ink">بحاجة لإجراء</h2>
      </div>
      <div className="space-y-2">
        <ActionRow
          href="/approvals"
          label="طلبات موافقة"
          count={data?.pending.total}
          icon={<Inbox className="h-4 w-4" />}
          tone="warning"
          loading={loading}
        />
        <ActionRow
          href="/tasks?status=overdue"
          label="مهام متأخرة"
          count={data?.tasks.overdue}
          icon={<Flame className="h-4 w-4" />}
          tone="danger"
          loading={loading}
        />
        <ActionRow
          href="/tasks?status=in_progress"
          label="مهام قيد التنفيذ"
          count={data?.tasks.in_progress}
          icon={<Play className="h-4 w-4" />}
          tone="brand"
          loading={loading}
        />
        <ActionRow
          href="/attendance"
          label="غياب اليوم"
          count={data?.today.absent}
          icon={<AlertTriangle className="h-4 w-4" />}
          tone="muted"
          loading={loading}
        />
      </div>
    </Card>
  );
}

function ActionRow({
  href,
  label,
  count,
  icon,
  tone,
  loading,
}: {
  href: string;
  label: string;
  count?: number;
  icon: React.ReactNode;
  tone: Tone;
  loading?: boolean;
}) {
  return (
    <Link
      href={href}
      className="group flex items-center gap-3 rounded-lg border border-hairline bg-surface-2 px-3 py-2.5 transition-colors hover:border-brand/40 hover:bg-brand-soft/40"
    >
      <span className={cn('grid h-8 w-8 place-items-center rounded-lg', TONE_ICON_WRAP[tone])}>{icon}</span>
      <span className="flex-1 text-sm text-ink">{label}</span>
      {loading ? (
        <Skeleton className="h-6 w-10" />
      ) : (
        <span className="num min-w-8 rounded-md bg-surface px-2 py-0.5 text-center text-sm font-semibold text-ink" dir="ltr">
          {count ?? 0}
        </span>
      )}
      <ArrowUpRight className="h-4 w-4 text-ink-2/40 transition-transform group-hover:-translate-x-0.5 group-hover:text-brand" />
    </Link>
  );
}
