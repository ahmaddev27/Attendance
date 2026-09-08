'use client';

import * as React from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { toast } from 'sonner';

import { DataTable, type DataTableColumn } from '@/components/data-table/data-table';
import { FilterBar } from '@/components/data-table/filter-bar';
import { FilterSelect } from '@/components/data-table/filter-select';
import { Button } from '@/components/ui/button';
import { ActiveToggle } from '@/components/organization/active-toggle';
import { DeleteEntityDialog } from '@/components/organization/delete-entity-dialog';
import { PositionFormDialog } from '@/components/organization/position-form-dialog';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { departmentsApi } from '@/lib/api/endpoints/departments';
import { positionsApi } from '@/lib/api/endpoints/positions';
import type { Position } from '@/lib/api/types';

const PER_PAGE = 20;

export default function PositionsPage() {
  const queryClient = useQueryClient();

  const [page, setPage] = React.useState(1);
  const [search, setSearch] = React.useState('');
  const [departmentId, setDepartmentId] = React.useState<string | undefined>();
  const debouncedSearch = useDebouncedValue(search);

  const [formOpen, setFormOpen] = React.useState(false);
  const [editingPosition, setEditingPosition] = React.useState<Position | null>(null);
  const [deletingPosition, setDeletingPosition] = React.useState<Position | null>(null);

  React.useEffect(() => {
    setPage(1);
  }, [debouncedSearch, departmentId]);

  const { data: departments } = useQuery({
    queryKey: ['departments', 'filter-options'],
    queryFn: async () => (await departmentsApi.list({ per_page: 100, is_active: true })).data.data,
    staleTime: 60_000,
  });

  const { data, isLoading } = useQuery({
    queryKey: ['positions', 'list', { page, search: debouncedSearch, departmentId }],
    queryFn: async () => {
      const { data } = await positionsApi.list({
        page,
        per_page: PER_PAGE,
        search: debouncedSearch || undefined,
        department_id: departmentId ? Number(departmentId) : undefined,
      });
      return data;
    },
    placeholderData: keepPreviousData,
  });

  const toggleActiveMutation = useMutation({
    mutationFn: ({ position, is_active }: { position: Position; is_active: boolean }) =>
      positionsApi.update(position.id, {
        title: position.title,
        code: position.code,
        department_id: position.department_id,
        is_active,
      }),
    onSuccess: () => {
      toast.success('تم تحديث حالة المسمى الوظيفي');
      queryClient.invalidateQueries({ queryKey: ['positions'] });
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'تعذر تحديث حالة المسمى الوظيفي');
    },
  });

  const openCreateDialog = () => {
    setEditingPosition(null);
    setFormOpen(true);
  };

  const openEditDialog = (position: Position) => {
    setEditingPosition(position);
    setFormOpen(true);
  };

  const columns: DataTableColumn<Position>[] = [
    { key: 'title', header: 'المسمى الوظيفي', cell: (position) => <span className="font-medium text-ink">{position.title}</span> },
    { key: 'code', header: 'الرمز', cell: (position) => (position.code ? <span dir="ltr">{position.code}</span> : '—') },
    { key: 'department', header: 'القسم', cell: (position) => position.department?.name ?? '—' },
    {
      key: 'employees_count',
      header: 'عدد الموظفين',
      align: 'center',
      cell: (position) => (
        <span className="num" dir="ltr">
          {position.employees_count ?? 0}
        </span>
      ),
    },
    {
      key: 'is_active',
      header: 'الحالة',
      align: 'center',
      cell: (position) => (
        <ActiveToggle
          checked={position.is_active}
          pending={
            toggleActiveMutation.isPending && toggleActiveMutation.variables?.position.id === position.id
          }
          onCheckedChange={(checked) => toggleActiveMutation.mutate({ position, is_active: checked })}
        />
      ),
    },
  ];

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <Button onClick={openCreateDialog} className="gap-2 bg-brand text-white hover:bg-brand-hover">
          <Plus className="h-4 w-4" />
          مسمى وظيفي جديد
        </Button>
        <div>
          <p className="text-xs font-medium text-muted">المسميات الوظيفية</p>
          <h1 className="mt-1 text-2xl font-bold text-ink">إدارة المسميات الوظيفية</h1>
        </div>
      </div>

      <FilterBar searchValue={search} onSearchChange={setSearch} searchPlaceholder="بحث بالمسمى الوظيفي...">
        <FilterSelect
          value={departmentId}
          onChange={setDepartmentId}
          options={(departments ?? []).map((department) => ({ value: String(department.id), label: department.name }))}
          placeholder="القسم"
          allLabel="كل الأقسام"
        />
      </FilterBar>

      <DataTable
        columns={columns}
        data={data?.data ?? []}
        rowKey={(position) => position.id}
        isLoading={isLoading}
        emptyMessage="لا توجد مسميات وظيفية مطابقة لبحثك"
        actions={[
          { label: 'تعديل', icon: Pencil, onClick: openEditDialog },
          { label: 'حذف', icon: Trash2, variant: 'destructive', onClick: setDeletingPosition },
        ]}
        pagination={data ? { meta: data.meta, onPageChange: setPage } : undefined}
      />

      <PositionFormDialog
        open={formOpen}
        onOpenChange={setFormOpen}
        position={editingPosition}
        defaultDepartmentId={departmentId ? Number(departmentId) : undefined}
      />

      <DeleteEntityDialog
        open={!!deletingPosition}
        onOpenChange={(open) => !open && setDeletingPosition(null)}
        title="حذف المسمى الوظيفي"
        description={
          <>
            هل أنت متأكد من حذف المسمى الوظيفي{' '}
            <span className="font-semibold text-ink">{deletingPosition?.title}</span>؟ لا يمكن التراجع عن هذا
            الإجراء.
          </>
        }
        onDelete={() => positionsApi.delete(deletingPosition!.id)}
        invalidateQueryKey={['positions']}
        successMessage="تم حذف المسمى الوظيفي بنجاح"
      />
    </div>
  );
}
