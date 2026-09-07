'use client';

import { useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Spinner } from '@/components/ui/spinner';
import { tasksApi } from '@/lib/api/endpoints/tasks';
import type { Task } from '@/lib/api/types';

type DeleteTaskDialogProps = {
  task: Task | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
};

function extractErrorMessage(err: unknown, fallback: string): string {
  const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
  return message || fallback;
}

/** Soft-delete confirmation used by the tasks list — mirrors `DeleteEmployeeDialog`'s pattern. */
export function DeleteTaskDialog({ task, open, onOpenChange }: DeleteTaskDialogProps) {
  const queryClient = useQueryClient();

  const mutation = useMutation({
    mutationFn: (id: number) => tasksApi.delete(id),
    onSuccess: () => {
      toast.success('تم حذف المهمة بنجاح');
      queryClient.invalidateQueries({ queryKey: ['tasks'] });
      onOpenChange(false);
    },
    onError: (err: unknown) => toast.error(extractErrorMessage(err, 'تعذر حذف المهمة')),
  });

  return (
    <AlertDialog open={open} onOpenChange={onOpenChange}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>حذف المهمة</AlertDialogTitle>
          <AlertDialogDescription>
            هل أنت متأكد من حذف المهمة <span className="font-semibold text-ink">{task?.title}</span>؟ يمكن استعادتها لاحقاً من قبل مسؤول النظام.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel disabled={mutation.isPending}>إلغاء</AlertDialogCancel>
          <AlertDialogAction
            disabled={mutation.isPending}
            className="bg-danger text-white hover:bg-danger/90"
            onClick={(e) => {
              e.preventDefault();
              if (task) mutation.mutate(task.id);
            }}
          >
            {mutation.isPending && <Spinner className="text-white" />}
            حذف
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}
