'use client';

import { useQuery } from '@tanstack/react-query';
import { Building2, CalendarCheck2, ClipboardList, Inbox, Users } from 'lucide-react';

import { AlertsPanel, type AlertItem } from '@/components/executive/alerts-panel';
import { AttendanceTrendChart } from '@/components/executive/attendance-trend-chart';
import { DepartmentBreakdownList } from '@/components/executive/department-breakdown-list';
import { KpiTile, KpiTileSkeleton } from '@/components/executive/kpi-tile';
import { adminDashboardApi } from '@/lib/api/endpoints/admin-dashboard';

const STALE_TIME_MS = 5 * 60 * 1000;

function SectionCard({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="rounded-xl border border-hairline bg-surface p-5">
      <h2 className="mb-4 text-sm font-semibold text-ink">{title}</h2>
      {children}
    </div>
  );
}

export default function ExecutiveDashboardPage() {
  const { data, isLoading } = useQuery({
    queryKey: ['executive-dashboard'],
    queryFn: () => adminDashboardApi.get(),
    staleTime: STALE_TIME_MS,
  });

  const alerts: AlertItem[] = [
    {
      label: 'إجازات بانتظار الموافقة',
      count: data?.leaves.pending ?? 0,
      href: '/leaves?status=pending',
      tone: 'warn',
    },
    {
      label: 'طلبات بانتظار موافقتي',
      count: data?.requests.pending_total ?? 0,
      href: '/approvals',
      tone: 'warn',
    },
    {
      label: 'مهام متأخرة',
      count: data?.tasks.overdue ?? 0,
      href: '/tasks?status=overdue',
      tone: 'danger',
    },
  ];

  return (
    <div>
      <div className="mb-6">
        <p className="text-xs font-medium text-muted">نظرة عامة</p>
        <h1 className="mt-1 text-2xl font-bold text-ink">لوحة الإدارة التنفيذية</h1>
      </div>

      {/* Row 1 — KPI tiles */}
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {isLoading || !data ? (
          Array.from({ length: 4 }).map((_, i) => <KpiTileSkeleton key={i} />)
        ) : (
          <>
            <KpiTile
              title="الموظفون"
              icon={Users}
              mainValue={data.employees.total}
              mainLabel="إجمالي الموظفين"
              stats={[
                { label: 'نشط', value: data.employees.active, tone: 'success' },
                { label: 'في إجازة', value: data.employees.on_leave, tone: 'warn' },
                { label: 'انضم هذا الشهر', value: data.employees.joined_this_month },
              ]}
            />
            <KpiTile
              title="الحضور اليوم"
              icon={CalendarCheck2}
              mainValue={
                <>
                  {data.attendance_today.present}
                  <span className="text-lg font-medium text-muted">
                    {' '}
                    / {data.attendance_today.absent_expected}
                  </span>
                </>
              }
              mainLabel="حاضر من إجمالي المتوقعين"
              stats={[
                { label: 'متأخر', value: data.attendance_today.late, tone: 'warn' },
                { label: 'في إجازة اليوم', value: data.attendance_today.on_leave_today },
              ]}
            />
            <KpiTile
              title="الطلبات"
              icon={Inbox}
              mainValue={data.requests.pending_total}
              mainLabel="طلبات بانتظار الموافقة"
              stats={[
                { label: 'مقدمة هذا الأسبوع', value: data.requests.submitted_this_week },
              ]}
            />
            <KpiTile
              title="المهام"
              icon={ClipboardList}
              mainValue={data.tasks.total_open}
              mainLabel="مهام مفتوحة"
              stats={[
                { label: 'متأخرة', value: data.tasks.overdue, tone: 'danger' },
                { label: 'أُنجزت هذا الأسبوع', value: data.tasks.completed_this_week, tone: 'success' },
              ]}
            />
          </>
        )}
      </div>

      {/* Row 2 — trend chart + department breakdown */}
      <div className="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div className="lg:col-span-2">
          <SectionCard title="اتجاه الحضور خلال 7 أيام">
            {isLoading || !data ? (
              <div className="h-44 animate-pulse rounded-lg bg-surface-2" />
            ) : (
              <AttendanceTrendChart data={data.attendance_trend_7d} />
            )}
          </SectionCard>
        </div>
        <div>
          <SectionCard title="أعلى 5 أقسام حسب عدد الموظفين">
            {isLoading || !data ? (
              <div className="space-y-4">
                {Array.from({ length: 5 }).map((_, i) => (
                  <div key={i} className="h-6 animate-pulse rounded bg-surface-2" />
                ))}
              </div>
            ) : (
              <DepartmentBreakdownList departments={data.departments} />
            )}
          </SectionCard>
        </div>
      </div>

      {/* Row 3 — alerts */}
      <div className="mt-6">
        <div className="mb-3 flex items-center gap-2">
          <Building2 className="h-4 w-4 text-ink-2" />
          <h2 className="text-sm font-semibold text-ink">تنبيهات تستدعي المتابعة</h2>
        </div>
        {isLoading || !data ? (
          <div className="h-40 animate-pulse rounded-xl bg-surface-2" />
        ) : (
          <AlertsPanel items={alerts} />
        )}
      </div>
    </div>
  );
}
