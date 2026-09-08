'use client';

import * as React from 'react';
import { useRouter } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { ListTree, Pencil, Plus, Trash2 } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable, type DataTableColumn } from '@/components/data-table/data-table';
import { DeleteEntityDialog } from '@/components/organization/delete-entity-dialog';
import { WorkflowFormDialog } from '@/components/workflow/workflow-form-dialog';
import { workflowsApi } from '@/lib/api/endpoints/workflows';
import type { Workflow } from '@/lib/api/types';

export default function WorkflowsPage() {
  const router = useRouter();

  const { data: workflows, isLoading } = useQuery({
    queryKey: ['workflows'],
    queryFn: async () => (await workflowsApi.list()).data.data,
  });

  const [formOpen, setFormOpen] = React.useState(false);
  const [editingWorkflow, setEditingWorkflow] = React.useState<Workflow | null>(null);
  const [deletingWorkflow, setDeletingWorkflow] = React.useState<Workflow | null>(null);

  const openCreate = () => {
    setEditingWorkflow(null);
    setFormOpen(true);
  };

  const openEdit = (workflow: Workflow) => {
    setEditingWorkflow(workflow);
    setFormOpen(true);
  };

  const columns: DataTableColumn<Workflow>[] = [
    { key: 'name', header: 'اسم المسار', cell: (workflow) => <span className="font-medium text-ink">{workflow.name}</span> },
    {
      key: 'description',
      header: 'الوصف',
      cell: (workflow) => (
        <span className="block max-w-[280px] truncate text-ink-2" title={workflow.description ?? undefined}>
          {workflow.description || '—'}
        </span>
      ),
    },
    {
      key: 'steps_count',
      header: 'عدد الخطوات',
      align: 'center',
      cell: (workflow) => (
        <span className="num" dir="ltr">
          {workflow.steps?.length ?? 0}
        </span>
      ),
    },
    {
      key: 'is_active',
      header: 'الحالة',
      align: 'center',
      cell: (workflow) => (
        <Badge
          className={
            workflow.is_active
              ? 'border-transparent bg-success-soft text-success'
              : 'border-transparent bg-surface-2 text-muted'
          }
        >
          {workflow.is_active ? 'نشط' : 'غير نشط'}
        </Badge>
      ),
    },
  ];

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <Button onClick={openCreate} className="gap-2 bg-brand text-white hover:bg-brand-hover">
          <Plus className="h-4 w-4" />
          مسار عمل جديد
        </Button>
        <div>
          <p className="text-xs font-medium text-muted">مسارات العمل</p>
          <h1 className="mt-1 text-2xl font-bold text-ink">إدارة مسارات العمل</h1>
        </div>
      </div>

      <DataTable
        columns={columns}
        data={workflows ?? []}
        rowKey={(workflow) => workflow.id}
        isLoading={isLoading}
        emptyMessage="لا توجد مسارات عمل مسجلة بعد"
        actions={[
          { label: 'خطوات الاعتماد', icon: ListTree, onClick: (workflow) => router.push(`/workflows/${workflow.id}`) },
          { label: 'تعديل', icon: Pencil, onClick: openEdit },
          { label: 'حذف', icon: Trash2, variant: 'destructive', onClick: setDeletingWorkflow },
        ]}
      />

      <WorkflowFormDialog open={formOpen} onOpenChange={setFormOpen} workflow={editingWorkflow} />

      <DeleteEntityDialog
        open={!!deletingWorkflow}
        onOpenChange={(open) => !open && setDeletingWorkflow(null)}
        title="حذف مسار العمل"
        description={
          <>
            هل أنت متأكد من حذف مسار <span className="font-semibold text-ink">{deletingWorkflow?.name}</span>؟ لا يمكن
            التراجع عن هذا الإجراء، وقد يفشل الحذف إذا كان المسار مستخدماً في أنواع طلبات قائمة.
          </>
        }
        onDelete={() => workflowsApi.delete(deletingWorkflow!.id)}
        invalidateQueryKey={['workflows']}
        successMessage="تم حذف مسار العمل بنجاح"
      />
    </div>
  );
}
