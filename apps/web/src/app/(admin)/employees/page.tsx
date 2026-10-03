'use client';

import * as React from 'react';
import { useRouter } from 'next/navigation';
import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { CalendarDays, KeyRound, Mail, MessageSquareText, Pencil, Plus, QrCode, ShieldCheck, Trash2, X } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { DataTable, type DataTableColumn } from '@/components/data-table/data-table';
import { FilterBar } from '@/components/data-table/filter-bar';
import { FilterSelect } from '@/components/data-table/filter-select';
import { EmployeeAvatar } from '@/components/employees/employee-avatar';
import { BulkEmailDialog } from '@/components/employees/bulk-email-dialog';
import { BulkSmsDialog } from '@/components/employees/bulk-sms-dialog';
import { DeleteEmployeeDialog } from '@/components/employees/delete-employee-dialog';
import { EmployeeFormDialog } from '@/components/employees/employee-form-dialog';
import { ResetPasswordDialog } from '@/components/employees/reset-password-dialog';
import { ResetScanPinDialog } from '@/components/employees/reset-scan-pin-dialog';
import { ChangeRoleDialog } from '@/components/employees/change-role-dialog';
import { ScanPinsDialog } from '@/components/attendance/scan-pins-dialog';
import { EmployeeStatusBadge } from '@/components/employees/employee-status-badge';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { departmentsApi } from '@/lib/api/endpoints/departments';
import { employeesApi } from '@/lib/api/endpoints/employees';
import { teamsApi } from '@/lib/api/endpoints/teams';
import { hasPermission, useAuthStore } from '@/lib/stores/auth-store';
import { useScopedCompanyId } from '@/lib/stores/company-scope-store';
import type { Employee, EmployeeStatus } from '@/lib/api/types';
import { EMPLOYEE_STATUS_OPTIONS } from '@/lib/constants/employee-options';
import { ROLE_OPTIONS } from '@/lib/constants/request-options';
import { cn } from '@/lib/utils';

const PER_PAGE = 20;

const ROLE_LABELS: Record<string, string> = Object.fromEntries(ROLE_OPTIONS.map((option) => [option.value, option.label]));

const SCAN_PIN_SOURCE_LABELS: Record<string, string> = {
  admin_reset: 'إعادة تعيين',
  bulk_issue: 'إصدار جماعي',
  self_change: 'الموظف غيّره',
};

/**
 * Column cell for the scan PIN column. Shows PRESENCE only — the plaintext
 * PIN is never on the Employee payload (bcrypt-hashed server-side; the
 * plaintext exists only in the one-shot ResetScanPinDialog response and
 * the employee's SMS). Three colour-coded states:
 *   - 🟢 "مسجّل" with the issue date + how it was issued
 *   - 🟡 "بدون رمز" when the employee could still receive one (has phone)
 *   - 🔴 "بدون رمز ولا جوال" when neither bulk issue nor SMS can reach them
 */
function ScanPinCell({ employee }: { employee: Employee }) {
  const info = employee.scan_pin;
  if (!info) {
    return <span className="text-xs text-muted">—</span>;
  }
  if (info.has_pin) {
    const date = info.set_at ? new Date(info.set_at).toLocaleDateString('ar') : null;
    const source = info.set_via ? SCAN_PIN_SOURCE_LABELS[info.set_via] ?? info.set_via : null;
    return (
      <div className="flex flex-col gap-0.5 text-xs">
        <span className="font-semibold text-success">● مسجّل</span>
        {date && (
          <span className="text-muted">
            {date}
            {source ? ` · ${source}` : ''}
          </span>
        )}
      </div>
    );
  }
  const hasPhone = employee.phone !== null && employee.phone.trim() !== '';
  return (
    <span className={cn('text-xs font-semibold', hasPhone ? 'text-amber-600' : 'text-danger')}>
      {hasPhone ? '○ بدون رمز' : '○ بدون رمز ولا جوال'}
    </span>
  );
}

export default function EmployeesPage() {
  const router = useRouter();
  const user = useAuthStore((s) => s.user);
  const canManageUsers = hasPermission(user, 'manage-users');
  // Same story as reset-password below: the leaves detail route is gated by
  // `permission:approve-leaves` on the API side and its three queries would
  // all 403 for a user who lacks it. Hide the row action instead of letting
  // them navigate into a broken page.
  const canApproveLeaves = hasPermission(user, 'approve-leaves');
  const [page, setPage] = React.useState(1);
  const [search, setSearch] = React.useState('');
  const [departmentId, setDepartmentId] = React.useState<string | undefined>();
  const [teamId, setTeamId] = React.useState<string | undefined>();
  const [status, setStatus] = React.useState<string | undefined>();

  const [formOpen, setFormOpen] = React.useState(false);
  const [editingEmployee, setEditingEmployee] = React.useState<Employee | null>(null);
  const [deletingEmployee, setDeletingEmployee] = React.useState<Employee | null>(null);
  const [resetPasswordEmployee, setResetPasswordEmployee] = React.useState<Employee | null>(null);
  const [resetScanPinEmployee, setResetScanPinEmployee] = React.useState<Employee | null>(null);
  const [scanPinsOpen, setScanPinsOpen] = React.useState(false);
  const [roleEmployee, setRoleEmployee] = React.useState<Employee | null>(null);
  // Row selection: keyed by employee id. Cleared on filter change AND on
  // every successful bulk send so the admin doesn't accidentally re-send
  // to the same batch twice. Values live outside the fetched page so a
  // paginate-then-return keeps earlier selections intact.
  const [selectedIds, setSelectedIds] = React.useState<Set<React.Key>>(() => new Set());
  const [bulkEmailOpen, setBulkEmailOpen] = React.useState(false);
  const [bulkSmsOpen, setBulkSmsOpen] = React.useState(false);

  const debouncedSearch = useDebouncedValue(search);
  // Soft Company Scoping — the header switcher writes to this store; the
  // list refetches automatically because scopedCompanyId is part of the
  // query key below.
  const scopedCompanyId = useScopedCompanyId();

  // Any filter change invalidates the current page number AND the row
  // selection — the admin's "I selected 5 engineers" intent doesn't
  // survive switching to "ops, status=terminated" since the user is
  // reasoning about a different roster.
  React.useEffect(() => {
    setPage(1);
    setSelectedIds(new Set());
  }, [debouncedSearch, departmentId, teamId, status, scopedCompanyId]);

  const { data: departments } = useQuery({
    queryKey: ['departments', 'filter-options'],
    queryFn: async () => (await departmentsApi.list({ per_page: 100, is_active: true })).data.data,
  });

  const { data: teams } = useQuery({
    queryKey: ['teams', 'filter-options', departmentId],
    queryFn: async () =>
      (
        await teamsApi.list({
          per_page: 100,
          is_active: true,
          department_id: departmentId ? Number(departmentId) : undefined,
        })
      ).data.data,
  });

  const { data, isLoading } = useQuery({
    queryKey: ['employees', 'list', { page, search: debouncedSearch, departmentId, teamId, status, scopedCompanyId }],
    queryFn: async () => {
      const { data } = await employeesApi.list({
        page,
        per_page: PER_PAGE,
        search: debouncedSearch || undefined,
        company_id: scopedCompanyId ?? undefined,
        department_id: departmentId ? Number(departmentId) : undefined,
        team_id: teamId ? Number(teamId) : undefined,
        status: status as EmployeeStatus | undefined,
      });
      return data;
    },
    placeholderData: keepPreviousData,
  });

  const openCreateDialog = () => {
    setEditingEmployee(null);
    setFormOpen(true);
  };

  const openEditDialog = (employee: Employee) => {
    setEditingEmployee(employee);
    setFormOpen(true);
  };

  const columns: DataTableColumn<Employee>[] = [
    {
      key: 'employee_number',
      header: 'الرقم الوظيفي',
      className: 'w-24',
      cell: (employee) => (
        <span className="num" dir="ltr">
          {employee.employee_number}
        </span>
      ),
    },
    {
      key: 'name',
      header: 'الاسم',
      cell: (employee) => (
        <div className="flex items-center gap-3">
          <EmployeeAvatar employee={employee} />
          <div className="min-w-0">
            <p className="truncate font-medium text-ink">{employee.full_name}</p>
            {employee.email && <p className="truncate text-xs text-muted" dir="ltr">{employee.email}</p>}
          </div>
        </div>
      ),
    },
    {
      key: 'department',
      header: 'القسم',
      cell: (employee) => employee.department?.name ?? '—',
    },
    {
      key: 'role',
      header: 'الدور',
      cell: (employee) => (employee.role ? ROLE_LABELS[employee.role] ?? employee.role : '—'),
    },
    {
      key: 'team',
      header: 'الفريق',
      cell: (employee) => employee.team?.name ?? '—',
    },
    {
      key: 'position',
      header: 'المسمى الوظيفي',
      cell: (employee) => employee.position?.title ?? '—',
    },
    {
      key: 'scan_pin',
      header: 'رمز الحضور',
      className: 'w-32',
      cell: (employee) => <ScanPinCell employee={employee} />,
    },
    {
      key: 'status',
      header: 'الحالة',
      cell: (employee) => <EmployeeStatusBadge status={employee.status} />,
    },
  ];

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p className="text-xs font-medium text-muted">الموظفون</p>
          <h1 className="mt-1 text-2xl font-bold text-ink">قائمة الموظفين</h1>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          {/* The rollout endpoints are gated by `permission:manage-users`. */}
          {canManageUsers && (
            <Button variant="outline" onClick={() => setScanPinsOpen(true)} className="gap-2">
              <QrCode className="h-4 w-4" />
              رموز الحضور
            </Button>
          )}
          <Button onClick={openCreateDialog} className="gap-2 bg-brand text-white hover:bg-brand-hover">
            <Plus className="h-4 w-4" />
            موظف جديد
          </Button>
        </div>
      </div>

      <FilterBar searchValue={search} onSearchChange={setSearch} searchPlaceholder="بحث بالاسم أو الرقم الوظيفي...">
        <FilterSelect
          value={departmentId}
          onChange={(value) => {
            setDepartmentId(value);
            setTeamId(undefined);
          }}
          options={(departments ?? []).map((d) => ({ value: String(d.id), label: d.name }))}
          placeholder="القسم"
          allLabel="كل الأقسام"
        />
        <FilterSelect
          value={teamId}
          onChange={setTeamId}
          options={(teams ?? []).map((t) => ({ value: String(t.id), label: t.name }))}
          placeholder="الفريق"
          allLabel="كل الفرق"
        />
        <FilterSelect
          value={status}
          onChange={setStatus}
          options={EMPLOYEE_STATUS_OPTIONS}
          placeholder="الحالة"
          allLabel="كل الحالات"
        />
      </FilterBar>

      {/* Bulk action bar — appears when at least one row is checked. The
          selected set lives outside the fetched page so paginating through
          the list keeps earlier selections intact until the admin either
          sends or changes a filter. */}
      {canManageUsers && selectedIds.size > 0 && (
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-brand/40 bg-brand-soft px-4 py-3">
          <div className="flex items-center gap-3 text-sm text-brand-ink">
            <span className="num font-semibold">{selectedIds.size}</span>
            <span>موظف محدد</span>
          </div>
          <div className="flex items-center gap-2">
            <Button
              type="button"
              variant="outline"
              size="sm"
              className="gap-2"
              onClick={() => setBulkEmailOpen(true)}
            >
              <Mail className="h-4 w-4" />
              إرسال بريد جماعي
            </Button>
            <Button
              type="button"
              variant="outline"
              size="sm"
              className="gap-2"
              onClick={() => setBulkSmsOpen(true)}
            >
              <MessageSquareText className="h-4 w-4" />
              إرسال SMS جماعي
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="gap-2 text-ink-2"
              onClick={() => setSelectedIds(new Set())}
            >
              <X className="h-4 w-4" />
              إلغاء التحديد
            </Button>
          </div>
        </div>
      )}

      <DataTable
        columns={columns}
        data={data?.data ?? []}
        rowKey={(employee) => employee.id}
        isLoading={isLoading}
        emptyMessage="لا يوجد موظفون مطابقون لبحثك"
        selection={
          canManageUsers
            ? {
                selectedIds,
                onChange: setSelectedIds,
              }
            : undefined
        }
        actions={[
          {
            label: 'عرض الإجازات',
            icon: CalendarDays,
            onClick: (employee) => router.push(`/employees/${employee.id}/leaves`),
            hidden: () => !canApproveLeaves,
          },
          { label: 'تعديل', icon: Pencil, onClick: openEditDialog },
          // The reset-password endpoint is gated by `permission:manage-users`
          // on the API side; hide the row action for users who cannot call it.
          {
            label: 'إعادة تعيين كلمة السر',
            icon: KeyRound,
            onClick: setResetPasswordEmployee,
            hidden: () => !canManageUsers,
          },
          {
            label: 'تغيير الدور',
            icon: ShieldCheck,
            onClick: setRoleEmployee,
            hidden: () => !canManageUsers,
          },
          {
            label: 'إعادة تعيين رمز الحضور',
            icon: QrCode,
            onClick: setResetScanPinEmployee,
            hidden: () => !canManageUsers,
          },
          { label: 'حذف', icon: Trash2, variant: 'destructive', onClick: setDeletingEmployee },
        ]}
        pagination={data ? { meta: data.meta, onPageChange: setPage } : undefined}
      />

      <EmployeeFormDialog open={formOpen} onOpenChange={setFormOpen} employee={editingEmployee} />
      <ResetPasswordDialog
        employee={resetPasswordEmployee}
        open={!!resetPasswordEmployee}
        onOpenChange={(open) => !open && setResetPasswordEmployee(null)}
      />
      <ResetScanPinDialog
        employee={resetScanPinEmployee}
        open={!!resetScanPinEmployee}
        onOpenChange={(open) => !open && setResetScanPinEmployee(null)}
      />
      <ScanPinsDialog open={scanPinsOpen} onOpenChange={setScanPinsOpen} />
      <ChangeRoleDialog
        employee={roleEmployee}
        open={!!roleEmployee}
        onOpenChange={(open) => !open && setRoleEmployee(null)}
      />
      <DeleteEmployeeDialog
        employee={deletingEmployee}
        open={!!deletingEmployee}
        onOpenChange={(open) => !open && setDeletingEmployee(null)}
      />

      {/* Bulk comms — reads straight from the selected rows on the current
          page's data. When the admin paginates and selects more rows the
          dialog sees the full union because `selectedIds` outlives the
          page fetch. */}
      <BulkEmailDialog
        open={bulkEmailOpen}
        onOpenChange={setBulkEmailOpen}
        recipients={(data?.data ?? []).filter((e) => selectedIds.has(e.id))}
        onSent={() => setSelectedIds(new Set())}
      />
      <BulkSmsDialog
        open={bulkSmsOpen}
        onOpenChange={setBulkSmsOpen}
        recipients={(data?.data ?? []).filter((e) => selectedIds.has(e.id))}
        onSent={() => setSelectedIds(new Set())}
      />
    </div>
  );
}
