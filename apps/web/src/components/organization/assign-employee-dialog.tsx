'use client';

import * as React from 'react';
import { useMutation, useQueryClient, type QueryKey } from '@tanstack/react-query';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { EmployeePicker } from '@/components/employees/employee-picker';
import type { EmployeeSummary } from '@/lib/api/types';

type AssignEmployeeDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  title: string;
  description: string;
  fieldLabel: string;
  currentEmployee: EmployeeSummary | null;
  onAssign: (employeeId: number | null) => Promise<unknown>;
  invalidateQueryKey: QueryKey;
  successMessage: string;
};

/**
 * Generic "pick an employee for role X on record Y" dialog — powers both the
 * department "Assign Manager" and team "Assign Leader" row actions. The
 * actual HTTP call is supplied by the caller (departmentsApi.assignManager /
 * teamsApi.assignLeader), this component only owns the picker UI, the
 * mutation lifecycle, and the success/error toasts.
 */
export function AssignEmployeeDialog({
  open,
  onOpenChange,
  title,
  description,
  fieldLabel,
  currentEmployee,
  onAssign,
  invalidateQueryKey,
  successMessage,
}: AssignEmployeeDialogProps) {
  const queryClient = useQueryClient();
  const [selected, setSelected] = React.useState<EmployeeSummary | null>(currentEmployee);

  React.useEffect(() => {
    if (open) setSelected(currentEmployee);
  }, [open, currentEmployee]);

  const mutation = useMutation({
    mutationFn: () => onAssign(selected?.id ?? null),
    onSuccess: () => {
      toast.success(successMessage);
      queryClient.invalidateQueries({ queryKey: invalidateQueryKey });
      onOpenChange(false);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'حدث خطأ أثناء الحفظ');
    },
  });

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
          <DialogDescription>{description}</DialogDescription>
        </DialogHeader>

        <div className="space-y-2">
          <p className="text-sm font-medium text-ink-2">{fieldLabel}</p>
          <EmployeePicker value={selected} onChange={setSelected} placeholder="اختر موظفاً..." />
        </div>

        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            إلغاء
          </Button>
          <Button
            type="button"
            onClick={() => mutation.mutate()}
            disabled={mutation.isPending}
            className="bg-brand text-white hover:bg-brand-hover"
          >
            {mutation.isPending && <Spinner className="text-white" />}
            حفظ
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
