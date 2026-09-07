'use client';

import * as React from 'react';
import { useRouter } from 'next/navigation';
import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { CalendarDays, Pencil, Plus, Trash2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { DataTable, type DataTableColumn } from '@/components/data-table/data-table';
import { FilterBar } from '@/components/data-table/filter-bar';
import { FilterSelect } from '@/components/data-table/filter-select';
import { EmployeeAvatar } from '@/components/employees/employee-avatar';
import { DeleteEmployeeDialog } from '@/components/employees/delete-employee-dialog';
import { EmployeeFormDialog } from '@/components/employees/employee-form-dialog';
import { EmployeeStatusBadge } from '@/components/employees/employee-status-badge';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { departmentsApi } from '@/lib/api/endpoints/departments';
import { employeesApi } from '@/lib/api/endpoints/employees';
import { teamsApi } from '@/lib/api/endpoints/teams';
import type { Employee, EmployeeStatus } from '@/lib/api/types';
import { EMPLOYEE_STATUS_OPTIONS } from '@/lib/constants/employee-options';

const PER_PAGE = 20;

export default function EmployeesPage() {
  const router = useRouter();
  const [page, setPage] = React.useState(1);
  const [search, setSearch] = React.useState('');
  const [departmentId, setDepartmentId] = React.useState<string | undefined>();
  const [teamId, setTeamId] = React.useState<string | undefined>();
  const [status, setStatus] = React.useState<string | undefined>();

  const [formOpen, setFormOpen] = React.useState(false);
  const [editingEmployee, setEditingEmployee] = React.useState<Employee | null>(null);
  const [deletingEmployee, setDeletingEmployee] = React.useState<Employee | null>(null);

  const debouncedSearch = useDebouncedValue(search);

  // Any filter change invalidates the current page number.
  React.useEffect(() => {
    setPage(1);
  }, [debouncedSearch, departmentId, teamId, status]);

  const { data: departments } = useQuery({
    queryKey: ['departments', 'filter-options'],
    queryFn: async () => (await departmentsApi.list({ per_page: 100, is_active: true })).data.data,
    staleTime: 60_000,
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
    staleTime: 60_000,
  });

  const { data, isLoading } = useQuery({
    queryKey: ['employees', 'list', { page, search: debouncedSearch, departmentId, teamId, status }],
    queryFn: async () => {
      const { data } = await employeesApi.list({
        page,
        per_page: PER_PAGE,
        search: debouncedSearch || undefined,
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
      key: 'status',
      header: 'الحالة',
      cell: (employee) => <EmployeeStatusBadge status={employee.status} />,
    },
  ];

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <Button onClick={openCreateDialog} className="gap-2 bg-brand text-white hover:bg-brand-hover">
          <Plus className="h-4 w-4" />
          موظف جديد
        </Button>
        <div>
          <p className="text-xs font-medium text-muted">الموظفون</p>
          <h1 className="mt-1 text-2xl font-bold text-ink">قائمة الموظفين</h1>
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

      <DataTable
        columns={columns}
        data={data?.data ?? []}
        rowKey={(employee) => employee.id}
        isLoading={isLoading}
        emptyMessage="لا يوجد موظفون مطابقون لبحثك"
        actions={[
          {
            label: 'عرض الإجازات',
            icon: CalendarDays,
            onClick: (employee) => router.push(`/employees/${employee.id}/leaves`),
          },
          { label: 'تعديل', icon: Pencil, onClick: openEditDialog },
          { label: 'حذف', icon: Trash2, variant: 'destructive', onClick: setDeletingEmployee },
        ]}
        pagination={data ? { meta: data.meta, onPageChange: setPage } : undefined}
      />

      <EmployeeFormDialog open={formOpen} onOpenChange={setFormOpen} employee={editingEmployee} />
      <DeleteEmployeeDialog
        employee={deletingEmployee}
        open={!!deletingEmployee}
        onOpenChange={(open) => !open && setDeletingEmployee(null)}
      />
    </div>
  );
}
