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
import { employeesApi } from '@/lib/api/endpoints/employees';
import type { Employee } from '@/lib/api/types';

type DeleteEmployeeDialogProps = {
  employee: Employee | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
};

function extractErrorMessage(err: unknown, fallback: string): string {
  const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
  return message || fallback;
}

export function DeleteEmployeeDialog({ employee, open, onOpenChange }: DeleteEmployeeDialogProps) {
  const queryClient = useQueryClient();

  const mutation = useMutation({
    mutationFn: (id: number) => employeesApi.delete(id),
    onSuccess: () => {
      toast.success('تم حذف الموظف بنجاح');
      queryClient.invalidateQueries({ queryKey: ['employees'] });
      onOpenChange(false);
    },
    onError: (err: unknown) => {
      toast.error(extractErrorMessage(err, 'تعذر حذف الموظف'));
    },
  });

  return (
    <AlertDialog open={open} onOpenChange={onOpenChange}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>حذف الموظف</AlertDialogTitle>
          <AlertDialogDescription>
            هل أنت متأكد من حذف الموظف <span className="font-semibold text-ink">{employee?.full_name}</span>؟
            يمكن استعادته لاحقاً من سجل الموظفين المحذوفين.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel disabled={mutation.isPending}>إلغاء</AlertDialogCancel>
          <AlertDialogAction
            disabled={mutation.isPending}
            className="bg-danger text-white hover:bg-danger/90"
            onClick={(e) => {
              e.preventDefault();
              if (employee) mutation.mutate(employee.id);
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
