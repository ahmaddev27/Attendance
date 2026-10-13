'use client';

import * as React from 'react';
import Link from 'next/link';
import dynamic from 'next/dynamic';
import { useParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { CalendarDays, Download, Mail, Phone } from 'lucide-react';
import { toast } from 'sonner';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { EmployeeAvatar } from '@/components/employees/employee-avatar';
import { EmployeeStatusBadge } from '@/components/employees/employee-status-badge';
import { employeesApi } from '@/lib/api/endpoints/employees';
import { employeeTasksReportApi, type EmployeeTasksReportTask } from '@/lib/api/endpoints/employee-tasks-report';
import { formatDate } from '@/lib/attendance-format';
import { hasPermission, useAuthStore } from '@/lib/stores/auth-store';
import { cn } from '@/lib/utils';

const EmployeeTasksChart = dynamic(() => import('@/components/employees/employee-tasks-chart'), {
  ssr: false,
  loading: () => <Skeleton className="h-64 rounded-xl" />,
});

const currentYear = new Date().getFullYear();
const YEAR_OPTIONS = [currentYear, currentYear - 1, currentYear - 2];
const ALL_MONTHS = 'all';
const MONTHS = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];

const ROLE_LABELS: Record<EmployeeTasksReportTask['assigned_role'], string> = {
  assignee: 'مسندة لي',
  creator: 'أنشأتها',
};

const KPI_TILES = [
  { key: 'total', label: 'الإجمالي', tone: 'text-ink' },
  { key: 'pending', label: 'قيد الانتظار', tone: 'text-ink-2' },
  { key: 'in_progress', label: 'قيد التنفيذ', tone: 'text-brand' },
  { key: 'completed', label: 'مكتملة', tone: 'text-success' },
  { key: 'cancelled', label: 'ملغاة', tone: 'text-muted' },
  { key: 'overdue', label: 'متأخرة', tone: 'text-danger' },
] as const;

export default function EmployeeDetailsPage() {
  const params = useParams<{ id: string }>();
  const employeeId = Number(params.id);
  const user = useAuthStore((s) => s.user);
  const canApproveLeaves = hasPermission(user, 'approve-leaves');
  const [year, setYear] = React.useState(currentYear);
  const [month, setMonth] = React.useState<string>(ALL_MONTHS);
  const [downloading, setDownloading] = React.useState(false);

  const reportParams = { year, month: month === ALL_MONTHS ? undefined : Number(month) };

  const { data: employee, isLoading: employeeLoading } = useQuery({
    queryKey: ['employees', employeeId],
    queryFn: async () => (await employeesApi.get(employeeId)).data.data,
    enabled: Number.isFinite(employeeId),
  });

  const { data: report, isLoading: reportLoading } = useQuery({
    queryKey: ['employees', employeeId, 'tasks-report', reportParams],
    queryFn: async () => (await employeeTasksReportApi.get(employeeId, reportParams)).data,
    enabled: Number.isFinite(employeeId),
  });

  const downloadPdf = async () => {
    setDownloading(true);
    try {
      const res = await employeeTasksReportApi.pdf(employeeId, reportParams);
      const url = window.URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }));
      const link = document.createElement('a');
      link.href = url;
      link.download = `employee-${employee?.employee_number ?? employeeId}-tasks-${year}.pdf`;
      link.click();
      window.URL.revokeObjectURL(url);
    } catch {
      toast.error('تعذّر تحميل ملف PDF');
    } finally {
      setDownloading(false);
    }
  };

  return (
    <div className="space-y-6">
      <p className="text-xs text-muted">
        <Link href="/employees" className="hover:text-brand">
          الموظفون
        </Link>{' '}
        / تفاصيل الموظف
      </p>

      <section className="flex flex-wrap items-center gap-4 rounded-xl border border-line bg-surface p-5">
        {employeeLoading || !employee ? (
          <Skeleton className="h-16 w-full" />
        ) : (
          <>
            <EmployeeAvatar employee={employee} size={64} />
            <div className="min-w-0 flex-1 space-y-1">
              <div className="flex flex-wrap items-center gap-2">
                <h1 className="text-2xl font-bold text-ink">{employee.full_name}</h1>
                <EmployeeStatusBadge status={employee.status} />
              </div>
              <p className="text-sm text-ink-2">
                <span className="num" dir="ltr">{employee.employee_number}</span>
                {' · '}
                {employee.department?.name ?? '—'}
                {' · '}
                {employee.position?.title ?? '—'}
              </p>
              <div className="flex flex-wrap gap-4 text-xs text-muted">
                {employee.phone && (
                  <span className="inline-flex items-center gap-1" dir="ltr">
                    <Phone className="h-3.5 w-3.5" />
                    {employee.phone}
                  </span>
                )}
                {employee.email && (
                  <span className="inline-flex items-center gap-1" dir="ltr">
                    <Mail className="h-3.5 w-3.5" />
                    {employee.email}
                  </span>
                )}
              </div>
            </div>
          </>
        )}
      </section>

      <Tabs defaultValue="tasks">
        <TabsList>
          <TabsTrigger value="tasks">تقرير المهام</TabsTrigger>
        </TabsList>

        <TabsContent value="tasks" className="space-y-6">
          <div className="flex flex-wrap items-end justify-between gap-4">
            <div className="flex flex-wrap items-end gap-3">
              <div>
                <Label className="text-xs font-semibold text-ink-2">السنة</Label>
                <Select value={String(year)} onValueChange={(v) => setYear(Number(v))}>
                  <SelectTrigger className="mt-1.5 w-32">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {YEAR_OPTIONS.map((y) => (
                      <SelectItem key={y} value={String(y)}>
                        <span className="num" dir="ltr">{y}</span>
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div>
                <Label className="text-xs font-semibold text-ink-2">الشهر</Label>
                <Select value={month} onValueChange={setMonth}>
                  <SelectTrigger className="mt-1.5 w-36">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value={ALL_MONTHS}>كل الأشهر</SelectItem>
                    {MONTHS.map((label, i) => (
                      <SelectItem key={label} value={String(i + 1)}>
                        {label}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </div>
            <Button onClick={downloadPdf} disabled={downloading} className="gap-2 bg-brand text-white hover:bg-brand-hover">
              <Download className="h-4 w-4" />
              {downloading ? 'جارٍ التحميل...' : 'تحميل PDF'}
            </Button>
          </div>

          <div className="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-6">
            {KPI_TILES.map((tile) => (
              <div key={tile.key} className="rounded-xl border border-line bg-surface p-4 text-center">
                {reportLoading || !report ? (
                  <Skeleton className="mx-auto h-8 w-12" />
                ) : (
                  <p className={cn('num text-2xl font-bold', tile.tone)}>{report.totals[tile.key]}</p>
                )}
                <p className="mt-1 text-xs text-muted">{tile.label}</p>
              </div>
            ))}
          </div>

          <div className="rounded-xl border border-line bg-surface p-4" dir="ltr">
            {report ? <EmployeeTasksChart data={report.by_month} /> : <Skeleton className="h-64 rounded-xl" />}
          </div>

          <div className="rounded-xl border border-line bg-surface">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>العنوان</TableHead>
                  <TableHead>الحالة</TableHead>
                  <TableHead>الأولوية</TableHead>
                  <TableHead>الدور</TableHead>
                  <TableHead>تاريخ الإنشاء</TableHead>
                  <TableHead>تاريخ الاستحقاق</TableHead>
                  <TableHead>تاريخ الإكمال</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {report?.tasks.map((task) => (
                  <TableRow key={task.id}>
                    <TableCell className="font-medium text-ink">{task.title}</TableCell>
                    <TableCell>{task.status ?? '—'}</TableCell>
                    <TableCell>{task.priority ?? '—'}</TableCell>
                    <TableCell>
                      <Badge variant="outline">{ROLE_LABELS[task.assigned_role]}</Badge>
                    </TableCell>
                    <TableCell>{formatDate(task.created_at)}</TableCell>
                    <TableCell className={cn(task.is_overdue && 'font-semibold text-danger')}>
                      {formatDate(task.due_date)}
                    </TableCell>
                    <TableCell>{formatDate(task.completed_at)}</TableCell>
                  </TableRow>
                ))}
                {report && report.tasks.length === 0 && (
                  <TableRow>
                    <TableCell colSpan={7} className="py-8 text-center text-sm text-muted">
                      لا توجد مهام في هذه الفترة
                    </TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </div>

          {canApproveLeaves && (
            <Link
              href={`/employees/${employeeId}/leaves`}
              className="inline-flex items-center gap-2 text-sm font-medium text-brand hover:underline"
            >
              <CalendarDays className="h-4 w-4" />
              عرض إجازات الموظف
            </Link>
          )}
        </TabsContent>
      </Tabs>
    </div>
  );
}
