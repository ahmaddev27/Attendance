'use client';

import * as React from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Pencil, Plus, Trash2, UserCog } from 'lucide-react';
import { toast } from 'sonner';

import { DataTable, type DataTableColumn } from '@/components/data-table/data-table';
import { FilterBar } from '@/components/data-table/filter-bar';
import { Button } from '@/components/ui/button';
import { AssignEmployeeDialog } from '@/components/organization/assign-employee-dialog';
import { ActiveToggle } from '@/components/organization/active-toggle';
import { DeleteEntityDialog } from '@/components/organization/delete-entity-dialog';
import { DepartmentFormDialog } from '@/components/organization/department-form-dialog';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { departmentsApi } from '@/lib/api/endpoints/departments';
import type { Department } from '@/lib/api/types';

const PER_PAGE = 20;

export default function DepartmentsPage() {
  const queryClient = useQueryClient();

  const [page, setPage] = React.useState(1);
  const [search, setSearch] = React.useState('');
  const debouncedSearch = useDebouncedValue(search);

  const [formOpen, setFormOpen] = React.useState(false);
  const [editingDepartment, setEditingDepartment] = React.useState<Department | null>(null);
  const [deletingDepartment, setDeletingDepartment] = React.useState<Department | null>(null);
  const [assignManagerTarget, setAssignManagerTarget] = React.useState<Department | null>(null);

  React.useEffect(() => {
    setPage(1);
  }, [debouncedSearch]);

  const { data, isLoading } = useQuery({
    queryKey: ['departments', 'list', { page, search: debouncedSearch }],
    queryFn: async () => {
      const { data } = await departmentsApi.list({ page, per_page: PER_PAGE, search: debouncedSearch || undefined });
      return data;
    },
    placeholderData: keepPreviousData,
  });

  const toggleActiveMutation = useMutation({
    mutationFn: ({ department, is_active }: { department: Department; is_active: boolean }) =>
      departmentsApi.update(department.id, {
        name: department.name,
        code: department.code,
        parent_id: department.parent_id,
        description: department.description ?? null,
        is_active,
      }),
    onSuccess: () => {
      toast.success('تم تحديث حالة القسم');
      queryClient.invalidateQueries({ queryKey: ['departments'] });
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'تعذر تحديث حالة القسم');
    },
  });

  const openCreateDialog = () => {
    setEditingDepartment(null);
    setFormOpen(true);
  };

  const openEditDialog = (department: Department) => {
    setEditingDepartment(department);
    setFormOpen(true);
  };

  const columns: DataTableColumn<Department>[] = [
    { key: 'name', header: 'اسم القسم', cell: (department) => <span className="font-medium text-ink">{department.name}</span> },
    { key: 'code', header: 'الرمز', cell: (department) => (department.code ? <span dir="ltr">{department.code}</span> : '—') },
    { key: 'parent', header: 'القسم الأعلى', cell: (department) => department.parent?.name ?? '—' },
    { key: 'manager', header: 'المدير', cell: (department) => department.manager?.full_name ?? '—' },
    {
      key: 'employees_count',
      header: 'عدد الموظفين',
      align: 'center',
      cell: (department) => (
        <span className="num" dir="ltr">
          {department.employees_count ?? 0}
        </span>
      ),
    },
    {
      key: 'is_active',
      header: 'الحالة',
      align: 'center',
      cell: (department) => (
        <ActiveToggle
          checked={department.is_active}
          pending={
            toggleActiveMutation.isPending && toggleActiveMutation.variables?.department.id === department.id
          }
          onCheckedChange={(checked) => toggleActiveMutation.mutate({ department, is_active: checked })}
        />
      ),
    },
  ];

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p className="text-xs font-medium text-muted">الأقسام</p>
          <h1 className="mt-1 text-2xl font-bold text-ink">إدارة الأقسام</h1>
        </div>
        <Button onClick={openCreateDialog} className="gap-2 bg-brand text-white hover:bg-brand-hover">
          <Plus className="h-4 w-4" />
          قسم جديد
        </Button>
      </div>

      <FilterBar searchValue={search} onSearchChange={setSearch} searchPlaceholder="بحث باسم القسم..." />

      <DataTable
        columns={columns}
        data={data?.data ?? []}
        rowKey={(department) => department.id}
        isLoading={isLoading}
        emptyMessage="لا توجد أقسام مطابقة لبحثك"
        actions={[
          { label: 'تعيين مدير', icon: UserCog, onClick: setAssignManagerTarget },
          { label: 'تعديل', icon: Pencil, onClick: openEditDialog },
          { label: 'حذف', icon: Trash2, variant: 'destructive', onClick: setDeletingDepartment },
        ]}
        pagination={data ? { meta: data.meta, onPageChange: setPage } : undefined}
      />

      <DepartmentFormDialog open={formOpen} onOpenChange={setFormOpen} department={editingDepartment} />

      <AssignEmployeeDialog
        open={!!assignManagerTarget}
        onOpenChange={(open) => !open && setAssignManagerTarget(null)}
        title="تعيين مدير القسم"
        description={`اختر الموظف الذي سيتولى إدارة قسم "${assignManagerTarget?.name ?? ''}".`}
        fieldLabel="المدير"
        currentEmployee={assignManagerTarget?.manager ?? null}
        onAssign={(employeeId) => departmentsApi.assignManager(assignManagerTarget!.id, employeeId)}
        invalidateQueryKey={['departments']}
        successMessage="تم تعيين مدير القسم بنجاح"
      />

      <DeleteEntityDialog
        open={!!deletingDepartment}
        onOpenChange={(open) => !open && setDeletingDepartment(null)}
        title="حذف القسم"
        description={
          <>
            هل أنت متأكد من حذف قسم <span className="font-semibold text-ink">{deletingDepartment?.name}</span>؟
            لا يمكن التراجع عن هذا الإجراء.
          </>
        }
        onDelete={() => departmentsApi.delete(deletingDepartment!.id)}
        invalidateQueryKey={['departments']}
        successMessage="تم حذف القسم بنجاح"
      />
    </div>
  );
}
