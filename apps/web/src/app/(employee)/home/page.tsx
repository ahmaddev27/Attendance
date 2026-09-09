'use client';

import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import {
  AlertTriangle,
  ArrowUpRight,
  CalendarCheck,
  CalendarClock,
  CheckCircle2,
  ClipboardList,
  Clock,
  FileText,
  Flame,
  Inbox,
  Play,
  Plane,
  Sparkles,
} from 'lucide-react';

import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { formatDate } from '@/lib/attendance-format';
import { meDashboardApi, type EmployeeDashboardKpis } from '@/lib/api/endpoints/me-dashboard';
import { useAuthStore } from '@/lib/stores/auth-store';
import { cn } from '@/lib/utils';

const DAYS = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
const MONTHS = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];

function formatArabicDate(d: Date): string {
  return `${DAYS[d.getDay()]}، ${d.getDate()} ${MONTHS[d.getMonth()]} ${d.getFullYear()}`;
}

function formatTime(iso: string | null): string {
  if (!iso) return '—';
  const d = new Date(iso);
  return d.toLocaleTimeString('ar-EG', { hour: '2-digit', minute: '2-digit' });
}

const TODAY_STATUS_LABELS: Record<string, { label: string; tone: string }> = {
  present: { label: 'حاضر', tone: 'bg-success-soft text-success' },
  late: { label: 'متأخر', tone: 'bg-warn-soft text-warn-ink' },
  early_leave: { label: 'مغادرة مبكرة', tone: 'bg-warn-soft text-warn-ink' },
  absent: { label: 'غائب', tone: 'bg-danger-soft text-danger' },
  on_leave: { label: 'في إجازة', tone: 'bg-surface-2 text-ink-2' },
  holiday: { label: 'عطلة رسمية', tone: 'bg-brand-soft text-brand-ink' },
  weekend: { label: 'يوم عطلة', tone: 'bg-surface-2 text-ink-2' },
  remote: { label: 'عمل عن بُعد', tone: 'bg-success-soft text-success' },
  business_mission: { label: 'مأمورية', tone: 'bg-brand-soft text-brand-ink' },
};

export default function EmployeeHomePage() {
  const user = useAuthStore((s) => s.user);
  const firstName = user?.name?.trim()?.split(' ')[0];

  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['me', 'dashboard', 'kpis'],
    queryFn: async () => (await meDashboardApi.kpis()).data.data,
    refetchInterval: 60_000,
  });

  const today = new Date();
  const todayStatus = data?.today.status;
  const statusMeta = todayStatus ? TODAY_STATUS_LABELS[todayStatus] : null;

  return (
    <div className="space-y-6">
      {/* Hero */}
      <div className="flex flex-col gap-4 rounded-2xl border border-hairline bg-surface p-6 md:flex-row md:items-center md:justify-between md:p-8">
        <div>
          <p className="text-sm text-muted">أهلاً بعودتك</p>
          <h1 className="mt-1 text-2xl font-bold text-ink md:text-3xl">
            {firstName ? `مرحباً، ${firstName}` : 'مرحباً بك في TAQAT'}
          </h1>
          <p className="mt-1 text-xs text-muted">{formatArabicDate(today)}</p>
        </div>
        {data && (
          <div className="flex items-center gap-3 rounded-xl bg-brand-soft px-4 py-3 text-brand-ink">
            <Clock className="h-5 w-5 shrink-0" />
            <div>
              <p className="text-xs opacity-80">حالتك اليوم</p>
              {statusMeta ? (
                <div className="mt-0.5 flex items-center gap-2">
                  <span className={cn('rounded-md px-2 py-0.5 text-xs font-semibold', statusMeta.tone)}>
                    {statusMeta.label}
                  </span>
                  {data.today.checked_in_at && (
                    <span className="num text-xs text-brand-ink/70" dir="ltr">
                      دخول {formatTime(data.today.checked_in_at)}
                    </span>
                  )}
                </div>
              ) : (
                <p className="text-sm font-semibold">لم يتم تسجيل الحضور بعد</p>
              )}
            </div>
          </div>
        )}
      </div>

      {isError && (
        <Card className="border-danger-soft bg-danger-soft/40 p-4 text-sm text-danger">
          تعذّر تحميل بيانات لوحتك.{' '}
          <button onClick={() => refetch()} className="font-semibold underline">
            إعادة المحاولة
          </button>
        </Card>
      )}

      {/* KPI grid */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <KpiCard
          label="أيام حضور هذا الشهر"
          value={data?.month.present}
          hint={data ? `من ${data.month.working_days_elapsed} يوم عمل` : undefined}
          icon={<CalendarCheck className="h-5 w-5" />}
          tone="success"
          loading={isLoading}
        />
        <KpiCard
          label="مهام مفتوحة"
          value={data?.tasks.open}
          hint={data && data.tasks.overdue > 0 ? `${data.tasks.overdue} متأخرة` : undefined}
          hintTone={data?.tasks.overdue ? 'danger' : undefined}
          icon={<ClipboardList className="h-5 w-5" />}
          tone="brand"
          href="/my-tasks"
          loading={isLoading}
        />
        <KpiCard
          label="مهام أُنجزت هذا الأسبوع"
          value={data?.tasks.completed_this_week}
          icon={<CheckCircle2 className="h-5 w-5" />}
          tone="success"
          href="/my-tasks?status=done"
          loading={isLoading}
        />
        <KpiCard
          label="طلبات معلّقة"
          value={data ? data.leaves.pending + data.requests.pending : undefined}
          hint={data ? `${data.leaves.pending} إجازة · ${data.requests.pending} طلب` : undefined}
          icon={<Inbox className="h-5 w-5" />}
          tone="warning"
          loading={isLoading}
        />
      </div>

      {/* Details */}
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
        {/* Leave balances */}
        <Card className="border-hairline bg-surface p-5 lg:col-span-2">
          <div className="mb-4 flex items-center justify-between">
            <h2 className="flex items-center gap-2 text-base font-semibold text-ink">
              <Plane className="h-4 w-4 text-brand" /> أرصدة الإجازات
            </h2>
            <Link href="/my-leaves" className="text-xs font-semibold text-brand hover:text-brand-hover">
              كل الإجازات ←
            </Link>
          </div>
          {isLoading ? (
            <div className="space-y-3">
              {[1, 2, 3].map((i) => (
                <Skeleton key={i} className="h-12 w-full" />
              ))}
            </div>
          ) : (data?.leaves.balances.length ?? 0) === 0 ? (
            <p className="py-8 text-center text-sm text-muted">لا توجد أرصدة مسجّلة بعد</p>
          ) : (
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              {data?.leaves.balances.map((b, i) => {
                const pct = b.entitled > 0 ? Math.round((b.remaining / b.entitled) * 100) : 0;
                return (
                  <div key={i} className="rounded-xl border border-hairline bg-surface-2 p-4">
                    <div className="flex items-baseline justify-between">
                      <p className="text-sm font-medium text-ink">{b.type}</p>
                      <p className="num text-xs text-muted" dir="ltr">
                        {b.remaining.toFixed(0)} / {b.entitled.toFixed(0)}
                      </p>
                    </div>
                    <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-hairline">
                      <div
                        className="h-full rounded-full bg-brand transition-all"
                        style={{ width: `${Math.min(100, pct)}%` }}
                      />
                    </div>
                  </div>
                );
              })}
            </div>
          )}

          {/* Upcoming leave */}
          {data?.leaves.upcoming && (
            <div className="mt-4 flex items-center gap-3 rounded-xl border border-brand-soft bg-brand-soft/40 p-3 text-brand-ink">
              <CalendarClock className="h-5 w-5 shrink-0" />
              <div className="text-sm">
                <p className="font-semibold">إجازتك القادمة: {data.leaves.upcoming.type}</p>
                <p className="num text-xs opacity-80" dir="ltr">
                  {formatDate(data.leaves.upcoming.start_date)} → {formatDate(data.leaves.upcoming.end_date)}
                </p>
              </div>
            </div>
          )}
        </Card>

        {/* Quick actions */}
        <Card className="border-hairline bg-surface p-5">
          <div className="mb-4 flex items-center justify-between">
            <h2 className="flex items-center gap-2 text-base font-semibold text-ink">
              <Sparkles className="h-4 w-4 text-brand" /> إجراءات سريعة
            </h2>
          </div>
          <div className="space-y-2">
            <ActionRow href="/my-leaves?action=new" label="طلب إجازة جديد" icon={<Plane className="h-4 w-4" />} tone="brand" />
            <ActionRow href="/my-requests?action=new" label="طلب عام جديد" icon={<FileText className="h-4 w-4" />} tone="brand" />
            <ActionRow href="/my-tasks?status=overdue" label="مهام متأخرة" icon={<Flame className="h-4 w-4" />} tone="danger" count={data?.tasks.overdue} loading={isLoading} />
            <ActionRow href="/my-tasks?status=in_progress" label="مهام قيد التنفيذ" icon={<Play className="h-4 w-4" />} tone="brand" count={data?.tasks.in_progress} loading={isLoading} />
          </div>
        </Card>
      </div>

      {/* Month breakdown */}
      <div>
        <h2 className="mb-3 text-sm font-semibold text-muted">ملخّص الشهر</h2>
        <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
          <MetricCell label="حاضر" value={data?.month.present} icon={<CheckCircle2 className="h-4 w-4" />} tone="success" loading={isLoading} />
          <MetricCell label="متأخر" value={data?.month.late} icon={<Clock className="h-4 w-4" />} tone="warn" loading={isLoading} />
          <MetricCell label="غياب" value={data?.month.absent} icon={<AlertTriangle className="h-4 w-4" />} tone="danger" loading={isLoading} />
          <MetricCell label="إجازة" value={data?.month.leave} icon={<Plane className="h-4 w-4" />} tone="muted" loading={isLoading} />
        </div>
      </div>
    </div>
  );
}

// ---- sub-components ----

type Tone = 'brand' | 'success' | 'warning' | 'danger' | 'muted' | 'warn';

const TONE_ICON: Record<Tone, string> = {
  brand: 'bg-brand-soft text-brand-ink',
  success: 'bg-success-soft text-success',
  warning: 'bg-warn-soft text-warn-ink',
  warn: 'bg-warn-soft text-warn-ink',
  danger: 'bg-danger-soft text-danger',
  muted: 'bg-surface-2 text-ink-2',
};

function KpiCard({
  label,
  value,
  hint,
  hintTone,
  icon,
  tone = 'brand',
  loading,
  href,
}: {
  label: string;
  value: number | undefined;
  hint?: string;
  hintTone?: Tone;
  icon: React.ReactNode;
  tone?: Tone;
  loading?: boolean;
  href?: string;
}) {
  const body = (
    <div className="group flex h-full flex-col justify-between rounded-2xl border border-hairline bg-surface p-5 transition-all hover:border-brand/40 hover:shadow-sm">
      <div className="flex items-start justify-between">
        <span className={cn('grid h-10 w-10 place-items-center rounded-xl', TONE_ICON[tone])}>
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
        {hint && !loading && (
          <p
            className={cn(
              'mt-1 text-[11px]',
              hintTone === 'danger' ? 'font-semibold text-danger' : 'text-muted'
            )}
          >
            {hint}
          </p>
        )}
      </div>
    </div>
  );
  return href ? <Link href={href}>{body}</Link> : body;
}

function ActionRow({
  href,
  label,
  icon,
  tone,
  count,
  loading,
}: {
  href: string;
  label: string;
  icon: React.ReactNode;
  tone: Tone;
  count?: number;
  loading?: boolean;
}) {
  return (
    <Link
      href={href}
      className="group flex items-center gap-3 rounded-lg border border-hairline bg-surface-2 px-3 py-2.5 transition-colors hover:border-brand/40 hover:bg-brand-soft/40"
    >
      <span className={cn('grid h-8 w-8 place-items-center rounded-lg', TONE_ICON[tone])}>{icon}</span>
      <span className="flex-1 text-sm text-ink">{label}</span>
      {loading ? (
        <Skeleton className="h-6 w-10" />
      ) : count != null ? (
        <span className="num min-w-8 rounded-md bg-surface px-2 py-0.5 text-center text-sm font-semibold text-ink" dir="ltr">
          {count}
        </span>
      ) : null}
      <ArrowUpRight className="h-4 w-4 text-ink-2/40 transition-transform group-hover:-translate-x-0.5 group-hover:text-brand" />
    </Link>
  );
}

function MetricCell({
  label,
  value,
  icon,
  tone,
  loading,
}: {
  label: string;
  value: number | undefined;
  icon: React.ReactNode;
  tone: Tone;
  loading?: boolean;
}) {
  return (
    <Card className="border-hairline bg-surface p-4">
      <div className="flex items-center gap-2">
        <span className={cn('grid h-7 w-7 place-items-center rounded-lg', TONE_ICON[tone])}>{icon}</span>
        <p className="text-xs text-muted">{label}</p>
      </div>
      {loading ? (
        <Skeleton className="mt-3 h-7 w-12" />
      ) : (
        <p className="num mt-2 text-2xl font-bold text-ink" dir="ltr">
          {value ?? 0}
        </p>
      )}
    </Card>
  );
}
