'use client';

import * as React from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { ArrowRight, SlidersHorizontal } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { EmployeeAvatar } from '@/components/employees/employee-avatar';
import { BalanceCard } from '@/components/leaves/balance-card';
import { LeaveStatusBadge } from '@/components/leaves/leave-status-badge';
import { LeaveTypeBadge } from '@/components/leaves/leave-type-badge';
import { AdjustBalanceDialog } from '@/components/leaves/adjust-balance-dialog';
import { employeesApi } from '@/lib/api/endpoints/employees';
import { leaveBalancesApi } from '@/lib/api/endpoints/leave-balances';
import { leaveRequestsApi } from '@/lib/api/endpoints/leaves';
import { formatDate } from '@/lib/attendance-format';
import type { LeaveBalance } from '@/lib/api/types';

const currentYear = new Date().getFullYear();
const YEAR_OPTIONS = [currentYear - 1, currentYear, currentYear + 1];

export default function EmployeeLeavesPage() {
  const params = useParams<{ id: string }>();
  const employeeId = Number(params.id);
  const [year, setYear] = React.useState(currentYear);
  const [adjustingBalance, setAdjustingBalance] = React.useState<LeaveBalance | null>(null);

  const { data: employee, isLoading: employeeLoading } = useQuery({
    queryKey: ['employees', employeeId],
    queryFn: async () => (await employeesApi.get(employeeId)).data.data,
    enabled: Number.isFinite(employeeId),
  });

  const { data: balances, isLoading: balancesLoading } = useQuery({
    queryKey: ['leave-balances', employeeId, year],
    queryFn: async () => (await leaveBalancesApi.list({ employee_id: employeeId, year })).data.data,
    enabled: Number.isFinite(employeeId),
  });

  const { data: leaves, isLoading: leavesLoading } = useQuery({
    queryKey: ['leave-requests', 'by-employee', employeeId, year],
    queryFn: async () =>
      (
        await leaveRequestsApi.list({
          employee_id: employeeId,
          start_date: `${year}-01-01`,
          end_date: `${year}-12-31`,
          per_page: 100,
        })
      ).data.data,
    enabled: Number.isFinite(employeeId),
  });

  return (
    <div>
      <div className="mb-6">
        <p className="text-xs text-muted">
          <Link href="/employees" className="hover:text-brand">
            الموظفون
          </Link>{' '}
          / إجازات الموظف
        </p>
        <div className="mt-2 flex flex-wrap items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            {employeeLoading ? (
              <Skeleton className="h-11 w-11 rounded-full" />
            ) : employee ? (
              <EmployeeAvatar employee={employee} size={44} />
            ) : null}
            <div>
              <h1 className="text-2xl font-bold text-ink">{employee ? employee.full_name : 'إجازات الموظف'}</h1>
              {employee && (
                <p className="num text-xs text-muted" dir="ltr">
                  {employee.employee_number}
                </p>
              )}
            </div>
          </div>
          <div>
            <Label className="text-xs font-semibold text-ink-2">السنة</Label>
            <Select value={String(year)} onValueChange={(v) => setYear(Number(v))}>
              <SelectTrigger className="mt-1.5 w-32">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {YEAR_OPTIONS.map((y) => (
                  <SelectItem key={y} value={String(y)}>
                    <span className="num" dir="ltr">
                      {y}
                    </span>
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {balancesLoading &&
          Array.from({ length: 3 }).map((_, i) => <Skeleton key={i} className="h-40 rounded-xl" />)}

        {!balancesLoading &&
          balances?.map((balance) => (
            <div key={balance.id} className="relative">
              <BalanceCard balance={balance} />
              <Button
                type="button"
                variant="outline"
                size="sm"
                className="absolute end-4 top-4 gap-1.5"
                onClick={() => setAdjustingBalance(balance)}
              >
                <SlidersHorizontal className="h-3.5 w-3.5" />
                تعديل
              </Button>
            </div>
          ))}

        {!balancesLoading && (balances?.length ?? 0) === 0 && (
          <p className="col-span-full py-6 text-center text-sm text-muted">
            لا توجد أرصدة إجازات مسجلة لهذا الموظف في سنة{' '}
            <span className="num" dir="ltr">
              {year}
            </span>
          </p>
        )}
      </div>

      <div className="mt-6 rounded-xl border border-hairline bg-surface">
        <div className="border-b border-hairline p-4">
          <p className="text-sm font-semibold text-ink">
            سجل الإجازات —{' '}
            <span className="num" dir="ltr">
              {year}
            </span>
          </p>
        </div>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="text-start">التواريخ</TableHead>
              <TableHead className="text-start">النوع</TableHead>
              <TableHead className="text-start">الأيام</TableHead>
              <TableHead className="text-start">الحالة</TableHead>
              <TableHead className="text-start">السبب</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {leavesLoading &&
              Array.from({ length: 3 }).map((_, i) => (
                <TableRow key={i}>
                  {Array.from({ length: 5 }).map((__, j) => (
                    <TableCell key={j}>
                      <Skeleton className="h-4 w-full" />
                    </TableCell>
                  ))}
                </TableRow>
              ))}

            {!leavesLoading && (leaves?.length ?? 0) === 0 && (
              <TableRow>
                <TableCell colSpan={5} className="py-10 text-center text-sm text-muted">
                  لا توجد طلبات إجازة مسجلة في سنة{' '}
                  <span className="num" dir="ltr">
                    {year}
                  </span>
                </TableCell>
              </TableRow>
            )}

            {leaves?.map((leave) => (
              <TableRow key={leave.id}>
                <TableCell className="whitespace-nowrap">
                  <span className="num" dir="ltr">
                    {formatDate(leave.start_date)} — {formatDate(leave.end_date)}
                  </span>
                </TableCell>
                <TableCell>
                  <LeaveTypeBadge leaveType={leave.leave_type} />
                </TableCell>
                <TableCell>
                  <span className="num" dir="ltr">
                    {leave.days}
                  </span>
                </TableCell>
                <TableCell>
                  <LeaveStatusBadge status={leave.status} />
                </TableCell>
                <TableCell className="max-w-[200px] truncate" title={leave.reason ?? undefined}>
                  {leave.reason || '—'}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>

      <Link href="/employees" className="mt-6 inline-flex items-center gap-1.5 text-sm text-ink-2 hover:text-brand">
        <ArrowRight className="h-4 w-4" />
        العودة إلى قائمة الموظفين
      </Link>

      <AdjustBalanceDialog
        balance={adjustingBalance}
        open={!!adjustingBalance}
        onOpenChange={(open) => !open && setAdjustingBalance(null)}
      />
    </div>
  );
}
