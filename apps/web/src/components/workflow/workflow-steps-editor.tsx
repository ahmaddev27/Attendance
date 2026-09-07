'use client';

import * as React from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { ListTree, Plus } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { DeleteEntityDialog } from '@/components/organization/delete-entity-dialog';
import { SortableList } from '@/components/workflow/sortable-list';
import { WorkflowStepCard } from '@/components/workflow/workflow-step-card';
import { WorkflowStepFormDialog } from '@/components/workflow/workflow-step-form-dialog';
import { workflowStepsApi } from '@/lib/api/endpoints/workflows';
import type { Workflow, WorkflowStep } from '@/lib/api/types';

type WorkflowStepsEditorProps = {
  workflow: Workflow;
};

/**
 * Steps list + drag-to-reorder + add/edit/delete for a single workflow.
 * Kept as its own component so the `[id]/page.tsx` route stays a thin
 * "fetch the workflow, render the header + this editor" shell.
 */
export function WorkflowStepsEditor({ workflow }: WorkflowStepsEditorProps) {
  const queryClient = useQueryClient();

  // Local copy so drag reordering feels instant instead of waiting on a
  // refetch — reconciled back to the server response on success/failure.
  const [steps, setSteps] = React.useState<WorkflowStep[]>(() => sortSteps(workflow.steps));
  React.useEffect(() => {
    setSteps(sortSteps(workflow.steps));
  }, [workflow.steps]);

  const [formOpen, setFormOpen] = React.useState(false);
  const [editingStep, setEditingStep] = React.useState<WorkflowStep | null>(null);
  const [deletingStep, setDeletingStep] = React.useState<WorkflowStep | null>(null);

  const reorderMutation = useMutation({
    mutationFn: (nextSteps: WorkflowStep[]) =>
      workflowStepsApi.reorder(workflow.id, {
        steps: nextSteps.map((step, index) => ({ id: step.id, step_order: index + 1 })),
      }),
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'تعذر حفظ الترتيب الجديد');
      queryClient.invalidateQueries({ queryKey: ['workflows', workflow.id] });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['workflows', workflow.id] });
    },
  });

  const handleReorder = (nextSteps: WorkflowStep[]) => {
    setSteps(nextSteps);
    reorderMutation.mutate(nextSteps);
  };

  const openCreate = () => {
    setEditingStep(null);
    setFormOpen(true);
  };

  const openEdit = (step: WorkflowStep) => {
    setEditingStep(step);
    setFormOpen(true);
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-lg font-semibold text-ink">خطوات الاعتماد</h2>
        <Button type="button" size="sm" onClick={openCreate} className="gap-1.5 bg-brand text-white hover:bg-brand-hover">
          <Plus className="h-4 w-4" />
          إضافة خطوة
        </Button>
      </div>

      {steps.length === 0 ? (
        <div className="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-hairline bg-surface py-14 text-muted">
          <ListTree className="h-8 w-8" />
          <p className="text-sm">لا توجد خطوات اعتماد بعد — أضف أول خطوة لهذا المسار</p>
        </div>
      ) : (
        <SortableList
          items={steps}
          getId={(step) => String(step.id)}
          onReorder={handleReorder}
          renderItem={(step, dragHandle) => (
            <WorkflowStepCard
              step={step}
              dragHandle={dragHandle}
              onEdit={() => openEdit(step)}
              onDelete={() => setDeletingStep(step)}
            />
          )}
        />
      )}

      <WorkflowStepFormDialog
        open={formOpen}
        onOpenChange={setFormOpen}
        workflowId={workflow.id}
        step={editingStep}
        nextOrder={steps.length + 1}
      />

      <DeleteEntityDialog
        open={!!deletingStep}
        onOpenChange={(open) => !open && setDeletingStep(null)}
        title="حذف الخطوة"
        description={
          <>
            هل أنت متأكد من حذف خطوة <span className="font-semibold text-ink">{deletingStep?.name}</span>؟ لا يمكن
            التراجع عن هذا الإجراء.
          </>
        }
        onDelete={() => workflowStepsApi.delete(workflow.id, deletingStep!.id)}
        invalidateQueryKey={['workflows', workflow.id]}
        successMessage="تم حذف الخطوة بنجاح"
      />
    </div>
  );
}

function sortSteps(steps: WorkflowStep[]): WorkflowStep[] {
  return [...steps].sort((a, b) => a.step_order - b.step_order);
}
