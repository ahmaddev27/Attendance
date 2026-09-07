'use client';

import { useMemo, useState } from 'react';
import { useParams } from 'next/navigation';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { ArrowRight } from 'lucide-react';

import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { EmployeeAvatar } from '@/components/attendance/employee-avatar';
import { MonthlyCalendarGrid } from '@/components/attendance/monthly-calendar-grid';
import { ATTENDANCE_STATUS_META } from '@/components/attendance/attendance-status-badge';
import { attendanceApi } from '@/lib/api/endpoints/attendance';
import { formatMinutesAsHours, monthRange } from '@/lib/attendance-format';
import type { AttendanceStatus } from '@/lib/api/types';

function currentYearMonth() {
  const now = new Date();
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
}

type KpiCard = { label: string; value: string };

export default function EmployeeMonthlyAttendancePage() {
  const params = useParams<{ id: string }>();
  const employeeId = Number(params.id);
  const [yearMonth, setYearMonth] = useState(currentYearMonth());

  const [year, month] = yearMonth.split('-').map(Number);

  const { data: summary, isLoading: loadingSummary } = useQuery({
    queryKey: ['attendance-monthly-summary', employeeId, year, month],
    queryFn: () => attendanceApi.monthlySummary(employeeId, year, month),
    enabled: Number.isFinite(employeeId) && Boolean(year) && Boolean(month),
  });

  const range = useMemo(() => monthRange(year, month), [year, month]);

  const { data: monthAttendance } = useQuery({
    queryKey: ['attendance-monthly-days', employeeId, year, month],
    queryFn: () =>
      attendanceApi.list({ employee_id: employeeId, from: range.from, to: range.to, per_page: 100 }),
    enabled: Number.isFinite(employeeId) && Boolean(year) && Boolean(month),
  });

  const statusByDate = useMemo(() => {
    const map: Record<string, AttendanceStatus> = {};
    for (const record of monthAttendance?.data ?? []) {
      map[record.date] = record.status;
    }
    return map;
  }, [monthAttendance]);

  const kpiCards: KpiCard[] = summary
    ? [
        { label: 'أيام العمل المتوقعة', value: String(summary.total_working_days) },
        { label: 'أيام الحضور', value: String(summary.present_days) },
        { label: 'أيام الغياب', value: String(summary.absent_days) },
        { label: 'أيام الإجازة', value: String(summary.leave_days) },
        { label: 'أيام العطل', value: String(summary.holiday_days) },
        { label: 'نسبة الحضور', value: `${summary.attendance_percentage.toFixed(1)}%` },
        { label: 'إجمالي ساعات العمل', value: formatMinutesAsHours(summary.total_minutes) },
        { label: 'الساعات المتوقعة', value: formatMinutesAsHours(summary.expected_minutes) },
        { label: 'الفارق', value: formatMinutesAsHours(summary.difference_minutes) },
        { label: 'الوقت الإضافي', value: formatMinutesAsHours(summary.overtime_minutes) },
        { label: 'إجمالي التأخير', value: `${summary.late_minutes} د` },
        { label: 'إجمالي الانصراف المبكر', value: `${summary.early_leave_minutes} د` },
      ]
    : [];

  const segments = summary
    ? (['present', 'absent', 'on_leave', 'holiday'] as const).map((key) => ({
        key,
        days:
          key === 'present'
            ? summary.present_days
            : key === 'absent'
              ? summary.absent_days
              : key === 'on_leave'
                ? summary.leave_days
                : summary.holiday_days,
      }))
    : [];
  const segmentsTotal = segments.reduce((sum, s) => sum + s.days, 0) || 1;

  return (
    <div>
      <div className="mb-6">
        <p className="text-xs text-muted">
          <Link href="/attendance" className="hover:text-brand">
            الحضور
          </Link>{' '}
          / الملخص الشهري
        </p>
        <div className="mt-2 flex flex-wrap items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            {summary && <EmployeeAvatar employee={summary.employee} size={44} />}
            <div>
              <h1 className="text-2xl font-bold text-ink">
                {summary ? summary.employee.full_name : 'الملخص الشهري'}
              </h1>
              {summary && (
                <p className="num text-xs text-muted" dir="ltr">
                  {summary.employee.employee_number}
                </p>
              )}
            </div>
          </div>
          <div>
            <Label className="text-xs font-semibold text-ink-2">الشهر</Label>
            <Input
              type="month"
              value={yearMonth}
              onChange={(e) => setYearMonth(e.target.value)}
              className="mt-1.5"
              dir="ltr"
            />
          </div>
        </div>
      </div>

      {loadingSummary ? (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
          {Array.from({ length: 8 }).map((_, i) => (
            <Skeleton key={i} className="h-24 rounded-xl" />
          ))}
        </div>
      ) : summary ? (
        <>
          <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            {kpiCards.map((kpi) => (
              <Card key={kpi.label} className="border-hairline bg-surface shadow-none">
                <CardContent className="p-4">
                  <p className="text-xs text-muted">{kpi.label}</p>
                  <p className="num mt-1.5 text-xl font-bold text-ink">{kpi.value}</p>
                </CardContent>
              </Card>
            ))}
          </div>

          <Card className="mt-4 border-hairline bg-surface shadow-none">
            <CardContent className="p-4">
              <p className="mb-3 text-sm font-semibold text-ink">توزيع أيام الشهر</p>
              <div className="flex h-3 w-full overflow-hidden rounded-full bg-surface-2">
                {segments.map((segment) =>
                  segment.days > 0 ? (
                    <div
                      key={segment.key}
                      className={ATTENDANCE_STATUS_META[segment.key].dotClassName}
                      style={{ width: `${(segment.days / segmentsTotal) * 100}%` }}
                      title={`${ATTENDANCE_STATUS_META[segment.key].label}: ${segment.days}`}
                    />
                  ) : null
                )}
              </div>
              <div className="mt-3 flex flex-wrap gap-4">
                {segments.map((segment) => (
                  <div key={segment.key} className="flex items-center gap-1.5 text-xs text-ink-2">
                    <span className={`h-2.5 w-2.5 rounded-full ${ATTENDANCE_STATUS_META[segment.key].dotClassName}`} />
                    {ATTENDANCE_STATUS_META[segment.key].label}
                    <span className="num font-semibold text-ink">{segment.days}</span>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>

          <Card className="mt-4 border-hairline bg-surface shadow-none">
            <CardContent className="p-4">
              <p className="mb-3 text-sm font-semibold text-ink">التقويم الشهري</p>
              <MonthlyCalendarGrid year={year} month={month} statusByDate={statusByDate} />
            </CardContent>
          </Card>
        </>
      ) : (
        <p className="py-10 text-center text-sm text-muted">تعذر تحميل بيانات الملخص الشهري</p>
      )}

      <Link
        href="/attendance"
        className="mt-6 inline-flex items-center gap-1.5 text-sm text-ink-2 hover:text-brand"
      >
        <ArrowRight className="h-4 w-4" />
        العودة إلى سجل الحضور
      </Link>
    </div>
  );
}
