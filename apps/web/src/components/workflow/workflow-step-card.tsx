'use client';

import { CheckCircle2, Clock, GripVertical, Pencil, RotateCcw, Send, Trash2 } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ApproverRefDisplay } from '@/components/workflow/approver-ref-display';
import { APPROVER_TYPE_LABELS } from '@/lib/constants/request-options';
import type { WorkflowStep } from '@/lib/api/types';
import type { DragHandleProps } from '@/components/workflow/sortable-list';

type WorkflowStepCardProps = {
  step: WorkflowStep;
  dragHandle: DragHandleProps;
  onEdit: () => void;
  onDelete: () => void;
};

/** One row in the workflow step editor — order badge, approver info, permission chips, actions. */
export function WorkflowStepCard({ step, dragHandle, onEdit, onDelete }: WorkflowStepCardProps) {
  return (
    <div className="flex items-start gap-3 rounded-xl border border-hairline bg-surface p-4">
      <button
        type="button"
        className="mt-1 cursor-grab touch-none text-muted hover:text-ink-2 active:cursor-grabbing"
        aria-label="اسحب لإعادة الترتيب"
        {...dragHandle.attributes}
        {...dragHandle.listeners}
      >
        <GripVertical className="h-4 w-4" />
      </button>

      <div className="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-brand-soft text-xs font-semibold text-brand-ink">
        {step.step_order}
      </div>

      <div className="min-w-0 flex-1 space-y-2">
        <div className="flex flex-wrap items-center gap-2">
          <p className="font-medium text-ink">{step.name}</p>
          <Badge className="border-transparent bg-surface-2 text-ink-2">{APPROVER_TYPE_LABELS[step.approver_type]}</Badge>
        </div>

        {['specific_employee', 'specific_role', 'form_field'].includes(step.approver_type) && (
          <p className="text-xs text-muted">
            <ApproverRefDisplay approver_type={step.approver_type} approver_ref={step.approver_ref} />
          </p>
        )}

        <div className="flex flex-wrap items-center gap-3 text-xs text-muted">
          {step.can_reject && (
            <span className="inline-flex items-center gap-1">
              <CheckCircle2 className="h-3.5 w-3.5 text-danger" /> يمكن الرفض
            </span>
          )}
          {step.can_return && (
            <span className="inline-flex items-center gap-1">
              <RotateCcw className="h-3.5 w-3.5 text-warn" /> يمكن الإرجاع
            </span>
          )}
          {step.can_forward && (
            <span className="inline-flex items-center gap-1">
              <Send className="h-3.5 w-3.5 text-brand-ink" /> يمكن التحويل
            </span>
          )}
          {step.sla_hours != null && (
            <span className="inline-flex items-center gap-1">
              <Clock className="h-3.5 w-3.5" />
              <span className="num" dir="ltr">
                {step.sla_hours}
              </span>{' '}
              ساعة
            </span>
          )}
        </div>
      </div>

      <div className="flex shrink-0 items-center gap-1">
        <Button type="button" variant="ghost" size="icon" title="تعديل" onClick={onEdit}>
          <Pencil className="h-4 w-4" />
        </Button>
        <Button
          type="button"
          variant="ghost"
          size="icon"
          title="حذف"
          className="text-danger hover:bg-danger-soft hover:text-danger"
          onClick={onDelete}
        >
          <Trash2 className="h-4 w-4" />
        </Button>
      </div>
    </div>
  );
}
