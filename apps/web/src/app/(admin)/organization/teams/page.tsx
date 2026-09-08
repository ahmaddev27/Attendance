'use client';

import * as React from 'react';
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Pencil, Plus, Trash2, UserCog } from 'lucide-react';
import { toast } from 'sonner';

import { DataTable, type DataTableColumn } from '@/components/data-table/data-table';
import { FilterBar } from '@/components/data-table/filter-bar';
import { FilterSelect } from '@/components/data-table/filter-select';
import { Button } from '@/components/ui/button';
import { ActiveToggle } from '@/components/organization/active-toggle';
import { AssignEmployeeDialog } from '@/components/organization/assign-employee-dialog';
import { DeleteEntityDialog } from '@/components/organization/delete-entity-dialog';
import { TeamFormDialog } from '@/components/organization/team-form-dialog';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { departmentsApi } from '@/lib/api/endpoints/departments';
import { teamsApi } from '@/lib/api/endpoints/teams';
import type { Team } from '@/lib/api/types';

const PER_PAGE = 20;

export default function TeamsPage() {
  const queryClient = useQueryClient();

  const [page, setPage] = React.useState(1);
  const [search, setSearch] = React.useState('');
  const [departmentId, setDepartmentId] = React.useState<string | undefined>();
  const debouncedSearch = useDebouncedValue(search);

  const [formOpen, setFormOpen] = React.useState(false);
  const [editingTeam, setEditingTeam] = React.useState<Team | null>(null);
  const [deletingTeam, setDeletingTeam] = React.useState<Team | null>(null);
  const [assignLeaderTarget, setAssignLeaderTarget] = React.useState<Team | null>(null);

  React.useEffect(() => {
    setPage(1);
  }, [debouncedSearch, departmentId]);

  const { data: departments } = useQuery({
    queryKey: ['departments', 'filter-options'],
    queryFn: async () => (await departmentsApi.list({ per_page: 100, is_active: true })).data.data,
    staleTime: 60_000,
  });

  const { data, isLoading } = useQuery({
    queryKey: ['teams', 'list', { page, search: debouncedSearch, departmentId }],
    queryFn: async () => {
      const { data } = await teamsApi.list({
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
    mutationFn: ({ team, is_active }: { team: Team; is_active: boolean }) =>
      teamsApi.update(team.id, {
        name: team.name,
        department_id: team.department_id,
        description: team.description ?? null,
        is_active,
      }),
    onSuccess: () => {
      toast.success('تم تحديث حالة الفريق');
      queryClient.invalidateQueries({ queryKey: ['teams'] });
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'تعذر تحديث حالة الفريق');
    },
  });

  const openCreateDialog = () => {
    setEditingTeam(null);
    setFormOpen(true);
  };

  const openEditDialog = (team: Team) => {
    setEditingTeam(team);
    setFormOpen(true);
  };

  const columns: DataTableColumn<Team>[] = [
    { key: 'name', header: 'اسم الفريق', cell: (team) => <span className="font-medium text-ink">{team.name}</span> },
    { key: 'department', header: 'القسم', cell: (team) => team.department?.name ?? '—' },
    { key: 'leader', header: 'قائد الفريق', cell: (team) => team.leader?.full_name ?? '—' },
    {
      key: 'employees_count',
      header: 'عدد الموظفين',
      align: 'center',
      cell: (team) => (
        <span className="num" dir="ltr">
          {team.employees_count ?? 0}
        </span>
      ),
    },
    {
      key: 'is_active',
      header: 'الحالة',
      align: 'center',
      cell: (team) => (
        <ActiveToggle
          checked={team.is_active}
          pending={toggleActiveMutation.isPending && toggleActiveMutation.variables?.team.id === team.id}
          onCheckedChange={(checked) => toggleActiveMutation.mutate({ team, is_active: checked })}
        />
      ),
    },
  ];

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p className="text-xs font-medium text-muted">الفرق</p>
          <h1 className="mt-1 text-2xl font-bold text-ink">إدارة الفرق</h1>
        </div>
        <Button onClick={openCreateDialog} className="gap-2 bg-brand text-white hover:bg-brand-hover">
          <Plus className="h-4 w-4" />
          فريق جديد
        </Button>
      </div>

      <FilterBar searchValue={search} onSearchChange={setSearch} searchPlaceholder="بحث باسم الفريق...">
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
        rowKey={(team) => team.id}
        isLoading={isLoading}
        emptyMessage="لا توجد فرق مطابقة لبحثك"
        actions={[
          { label: 'تعيين قائد', icon: UserCog, onClick: setAssignLeaderTarget },
          { label: 'تعديل', icon: Pencil, onClick: openEditDialog },
          { label: 'حذف', icon: Trash2, variant: 'destructive', onClick: setDeletingTeam },
        ]}
        pagination={data ? { meta: data.meta, onPageChange: setPage } : undefined}
      />

      <TeamFormDialog
        open={formOpen}
        onOpenChange={setFormOpen}
        team={editingTeam}
        defaultDepartmentId={departmentId ? Number(departmentId) : undefined}
      />

      <AssignEmployeeDialog
        open={!!assignLeaderTarget}
        onOpenChange={(open) => !open && setAssignLeaderTarget(null)}
        title="تعيين قائد الفريق"
        description={`اختر الموظف الذي سيتولى قيادة فريق "${assignLeaderTarget?.name ?? ''}".`}
        fieldLabel="القائد"
        currentEmployee={assignLeaderTarget?.leader ?? null}
        onAssign={(employeeId) => teamsApi.assignLeader(assignLeaderTarget!.id, employeeId)}
        invalidateQueryKey={['teams']}
        successMessage="تم تعيين قائد الفريق بنجاح"
      />

      <DeleteEntityDialog
        open={!!deletingTeam}
        onOpenChange={(open) => !open && setDeletingTeam(null)}
        title="حذف الفريق"
        description={
          <>
            هل أنت متأكد من حذف فريق <span className="font-semibold text-ink">{deletingTeam?.name}</span>؟ لا
            يمكن التراجع عن هذا الإجراء.
          </>
        }
        onDelete={() => teamsApi.delete(deletingTeam!.id)}
        invalidateQueryKey={['teams']}
        successMessage="تم حذف الفريق بنجاح"
      />
    </div>
  );
}
