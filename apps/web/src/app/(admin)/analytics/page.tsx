'use client';

import * as React from 'react';
import dynamic from 'next/dynamic';
import { useQueries } from '@tanstack/react-query';
import {
  Activity,
  CalendarCheck,
  Clock,
  LogOut,
  Users,
  UserPlus,
  ClipboardList,
  TrendingUp,
  Timer,
  BarChart3,
} from 'lucide-react';

import { AnalyticsFiltersBar } from '@/components/analytics/analytics-filters';
import { AttendanceHeatmap } from '@/components/analytics/attendance-heatmap';
import { KpiCard } from '@/components/analytics/kpi-card';
import { Skeleton } from '@/components/ui/skeleton';
import { analyticsApi, type AnalyticsFilters } from '@/lib/api/endpoints/analytics';

// Recharts is ~200KB and pulls the whole d3-scale/d3-shape stack; keep it
// out of the admin shell (and the SW precache) by loading each chart lazily
// on the client only when this page renders. `ssr: false` prevents Next
// from trying to render recharts on the server, which would defeat the
// split.
const LeavesTrendChart = dynamic(
  () => import('@/components/analytics/leaves-trend-chart'),
  { ssr: false, loading: () => <Skeleton className="h-56 rounded-xl" /> },
);
const LeavesByTypeChart = dynamic(
  () => import('@/components/analytics/leaves-by-type-chart'),
  { ssr: false, loading: () => <Skeleton className="h-56 rounded-xl" /> },
);
const EmployeesByDepartmentChart = dynamic(
  () => import('@/components/analytics/employees-by-department-chart'),
  { ssr: false, loading: () => <Skeleton className="h-64 rounded-xl" /> },
);

/**
 * Recharts colour palette — pulled from our brand + status tokens so
 * every chart lines up with the KPI cards using the same hues. Kept as
 * hex here because Recharts doesn't resolve CSS vars inside <Cell fill>.
 */
const CHART_COLORS = {
  brand: '#2678c4',
  brandInk: '#0f4a7f',
  success: '#1e9e7f',
  warn: '#f5a623',
  danger: '#c74f35',
  neutral: '#5b6478',
};

const PIE_COLORS = [
  CHART_COLORS.brand,
  CHART_COLORS.success,
  CHART_COLORS.warn,
  CHART_COLORS.danger,
  CHART_COLORS.brandInk,
  CHART_COLORS.neutral,
];

export default function AnalyticsPage() {
  const [filters, setFilters] = React.useState<AnalyticsFilters>({});

  // Five parallel fetches — one round-trip per endpoint, cached 5min at
  // the API layer, so a filter change only re-hits whatever the user is
  // currently looking at (all five, but they arrive together in the
  // browser and render as soon as each settles).
  const results = useQueries({
    queries: [
      {
        queryKey: ['analytics', 'attendance-kpis', filters],
        queryFn: async () => (await analyticsApi.attendanceKpis(filters)).data,
      },
      {
        queryKey: ['analytics', 'attendance-heatmap', filters],
        queryFn: async () => (await analyticsApi.attendanceHeatmap(filters)).data,
      },
      {
        queryKey: ['analytics', 'leave-patterns', filters],
        queryFn: async () => (await analyticsApi.leavePatterns(filters)).data,
      },
      {
        queryKey: ['analytics', 'task-performance', filters],
        queryFn: async () => (await analyticsApi.taskPerformance(filters)).data,
      },
      {
        queryKey: ['analytics', 'employee-summary', filters],
        queryFn: async () => (await analyticsApi.employeeSummary(filters)).data,
      },
    ],
  });

  const [kpisQ, heatmapQ, leavesQ, tasksQ, employeesQ] = results;
  const kpis = kpisQ.data?.data;
  const heatmap = heatmapQ.data?.data;
  const leaves = leavesQ.data?.data;
  const tasks = tasksQ.data?.data;
  const employees = employeesQ.data?.data;
  const anyLoading = results.some((r) => r.isLoading);

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p className="text-xs font-medium text-muted">تحليلات</p>
          <h1 className="mt-1 text-2xl font-bold text-ink">لوحة التحليلات</h1>
        </div>
        {kpisQ.data?.meta.cached_at && (
          <p className="text-xs text-muted">
            آخر تحديث: <span className="num" dir="ltr">{new Date(kpisQ.data.meta.cached_at).toLocaleTimeString('ar-EG')}</span>
          </p>
        )}
      </div>

      <AnalyticsFiltersBar value={filters} onChange={setFilters} />

      {/* KPI row */}
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {anyLoading && !kpis ? (
          Array.from({ length: 4 }).map((_, i) => <Skeleton key={i} className="h-24 rounded-xl" />)
        ) : (
          <>
            <KpiCard
              label="نسبة الحضور"
              value={`${kpis?.attendance_rate ?? 0}%`}
              hint={`${kpis?.working_days ?? 0} يوم عمل`}
              icon={Activity}
              tone={
                (kpis?.attendance_rate ?? 0) >= 90 ? 'success' : (kpis?.attendance_rate ?? 0) >= 70 ? 'warn' : 'danger'
              }
            />
            <KpiCard
              label="متوسط الحضور"
              value={kpis?.avg_check_in ?? '—'}
              hint="ساعة الوصول"
              icon={Clock}
              tone="brand"
            />
            <KpiCard
              label="متوسط الانصراف"
              value={kpis?.avg_check_out ?? '—'}
              hint="ساعة المغادرة"
              icon={LogOut}
              tone="neutral"
            />
            <KpiCard
              label="إجمالي الموظفين"
              value={employees?.headcount.total ?? 0}
              hint={`${employees?.headcount.active ?? 0} نشط`}
              icon={Users}
              tone="brand"
            />
          </>
        )}
      </div>

      {/* Attendance status breakdown as chips */}
      {kpis && (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <KpiCard label="حاضر" value={kpis.totals.present} compact tone="success" icon={CalendarCheck} />
          <KpiCard label="متأخر" value={kpis.totals.late} compact tone="warn" icon={Clock} />
          <KpiCard label="غائب" value={kpis.totals.absent} compact tone="danger" icon={Activity} />
          <KpiCard label="في إجازة" value={kpis.totals.on_leave} compact tone="neutral" icon={CalendarCheck} />
        </div>
      )}

      {/* Attendance heatmap */}
      {heatmap ? (
        <AttendanceHeatmap matrix={heatmap.matrix} max={heatmap.max} />
      ) : (
        <Skeleton className="h-64 rounded-xl" />
      )}

      {/* Task performance */}
      <div className="grid gap-4 lg:grid-cols-3">
        <div className="lg:col-span-2 rounded-xl border border-hairline bg-surface p-4">
          <div className="mb-3 flex items-center justify-between">
            <h3 className="text-sm font-semibold text-ink">أداء المهام</h3>
            <p className="text-[11px] text-muted">
              {tasks?.avg_completion_hours !== null && tasks?.avg_completion_hours !== undefined
                ? `متوسط الإنجاز: ${tasks.avg_completion_hours} س`
                : 'لا توجد مهام مكتملة بعد'}
            </p>
          </div>
          {tasks ? (
            <div className="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6">
              <KpiCard label="المجموع" value={tasks.totals.total} compact tone="neutral" icon={ClipboardList} />
              <KpiCard label="مفتوحة" value={tasks.totals.open} compact tone="brand" />
              <KpiCard label="قيد التنفيذ" value={tasks.totals.in_progress} compact tone="warn" />
              <KpiCard label="منجزة" value={tasks.totals.done} compact tone="success" icon={TrendingUp} />
              <KpiCard label="ملغاة" value={tasks.totals.cancelled} compact tone="neutral" />
              <KpiCard label="متأخرة" value={tasks.totals.overdue} compact tone="danger" icon={Timer} />
            </div>
          ) : (
            <Skeleton className="h-32 rounded-xl" />
          )}
        </div>

        {/* Top assignees */}
        <div className="rounded-xl border border-hairline bg-surface p-4">
          <h3 className="mb-3 text-sm font-semibold text-ink">الأكثر إنجازاً</h3>
          {tasks?.top_assignees && tasks.top_assignees.length > 0 ? (
            <ul className="space-y-2">
              {tasks.top_assignees.map((row, i) => (
                <li key={row.name} className="flex items-center gap-3 rounded-lg bg-ground p-2">
                  <span className="num grid h-7 w-7 shrink-0 place-items-center rounded-full bg-brand-soft text-xs font-bold text-brand-ink">
                    {i + 1}
                  </span>
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium text-ink">{row.name}</p>
                    <p className="text-[11px] text-muted">
                      <span className="num" dir="ltr">
                        {row.done}
                      </span>{' '}
                      مهمة
                      {row.avg_hours !== null && (
                        <>
                          {' '}
                          · متوسط{' '}
                          <span className="num" dir="ltr">
                            {row.avg_hours}
                          </span>{' '}
                          س
                        </>
                      )}
                    </p>
                  </div>
                </li>
              ))}
            </ul>
          ) : (
            <p className="text-xs text-muted">لا توجد بيانات كافية</p>
          )}
        </div>
      </div>

      {/* Leaves — line chart of monthly totals + type/dept breakdowns */}
      <div className="grid gap-4 lg:grid-cols-3">
        <div className="lg:col-span-2 rounded-xl border border-hairline bg-surface p-4">
          <div className="mb-3 flex items-center justify-between">
            <h3 className="text-sm font-semibold text-ink">تدفق الإجازات شهرياً</h3>
            <p className="text-[11px] text-muted">
              <span className="num" dir="ltr">
                {leaves?.totals.days ?? 0}
              </span>{' '}
              يوم إجازة إجمالي
            </p>
          </div>
          {leaves && leaves.by_month.length > 0 ? (
            <LeavesTrendChart
              data={leaves.by_month}
              colors={{ brand: CHART_COLORS.brand, warn: CHART_COLORS.warn }}
            />
          ) : (
            <p className="grid h-56 place-items-center text-xs text-muted">لا توجد بيانات إجازات في هذه الفترة</p>
          )}
        </div>

        <div className="rounded-xl border border-hairline bg-surface p-4">
          <h3 className="mb-3 text-sm font-semibold text-ink">حسب النوع</h3>
          {leaves && leaves.by_type.length > 0 ? (
            <LeavesByTypeChart data={leaves.by_type} colors={PIE_COLORS} />
          ) : (
            <p className="grid h-56 place-items-center text-xs text-muted">—</p>
          )}
        </div>
      </div>

      {/* Employees — headcount by department + recent hires */}
      <div className="grid gap-4 lg:grid-cols-3">
        <div className="lg:col-span-2 rounded-xl border border-hairline bg-surface p-4">
          <h3 className="mb-3 text-sm font-semibold text-ink">التوزيع حسب القسم</h3>
          {employees && employees.by_department.length > 0 ? (
            <EmployeesByDepartmentChart
              data={employees.by_department}
              color={CHART_COLORS.brand}
            />
          ) : (
            <p className="grid h-56 place-items-center text-xs text-muted">لا توجد أقسام</p>
          )}
        </div>

        <div className="rounded-xl border border-hairline bg-surface p-4">
          <h3 className="mb-3 flex items-center gap-2 text-sm font-semibold text-ink">
            <UserPlus className="h-4 w-4 text-brand" />
            التوظيفات الأخيرة
          </h3>
          {employees?.recent_hires && employees.recent_hires.length > 0 ? (
            <ul className="space-y-2">
              {employees.recent_hires.map((emp) => (
                <li key={emp.id} className="flex items-start gap-3 rounded-lg bg-ground p-2">
                  <div className="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-brand-soft text-xs font-bold text-brand-ink">
                    {emp.name.charAt(0)}
                  </div>
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium text-ink">{emp.name}</p>
                    <p className="text-[11px] text-muted">
                      <span className="num" dir="ltr">
                        #{emp.employee_number}
                      </span>
                      {emp.department && ` · ${emp.department}`}
                    </p>
                    {emp.joining_date && (
                      <p className="num text-[10px] text-muted" dir="ltr">
                        {emp.joining_date}
                      </p>
                    )}
                  </div>
                </li>
              ))}
            </ul>
          ) : (
            <p className="text-xs text-muted">لا توظيفات جديدة في هذه الفترة</p>
          )}
        </div>
      </div>

      {/* Leaves by department table */}
      {leaves && leaves.by_department.length > 0 && (
        <div className="rounded-xl border border-hairline bg-surface p-4">
          <h3 className="mb-3 flex items-center gap-2 text-sm font-semibold text-ink">
            <BarChart3 className="h-4 w-4 text-brand" />
            الأقسام الأعلى استخداماً للإجازات
          </h3>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-hairline text-start text-xs text-muted">
                  <th className="pb-2 text-start font-medium">القسم</th>
                  <th className="pb-2 text-end font-medium">الأيام</th>
                  <th className="pb-2 text-end font-medium">عدد الطلبات</th>
                </tr>
              </thead>
              <tbody>
                {leaves.by_department.map((row) => (
                  <tr key={row.name} className="border-b border-hairline last:border-0">
                    <td className="py-2 font-medium text-ink">{row.name}</td>
                    <td className="num py-2 text-end text-ink-2" dir="ltr">
                      {row.days}
                    </td>
                    <td className="num py-2 text-end text-ink-2" dir="ltr">
                      {row.requests}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );
}
