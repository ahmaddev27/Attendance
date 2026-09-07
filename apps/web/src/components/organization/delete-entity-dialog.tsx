'use client';

import * as React from 'react';
import { useMutation, useQueryClient, type QueryKey } from '@tanstack/react-query';
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

type DeleteEntityDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  title: string;
  description: React.ReactNode;
  onDelete: () => Promise<unknown>;
  invalidateQueryKey: QueryKey;
  successMessage: string;
};

/**
 * Generic destructive-confirmation dialog shared by the departments, teams
 * and positions pages — only the copy and the delete call differ per entity.
 */
export function DeleteEntityDialog({
  open,
  onOpenChange,
  title,
  description,
  onDelete,
  invalidateQueryKey,
  successMessage,
}: DeleteEntityDialogProps) {
  const queryClient = useQueryClient();

  const mutation = useMutation({
    mutationFn: onDelete,
    onSuccess: () => {
      toast.success(successMessage);
      queryClient.invalidateQueries({ queryKey: invalidateQueryKey });
      onOpenChange(false);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'تعذر إتمام عملية الحذف');
    },
  });

  return (
    <AlertDialog open={open} onOpenChange={onOpenChange}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>{title}</AlertDialogTitle>
          <AlertDialogDescription>{description}</AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel disabled={mutation.isPending}>إلغاء</AlertDialogCancel>
          <AlertDialogAction
            disabled={mutation.isPending}
            className="bg-danger text-white hover:bg-danger/90"
            onClick={(e) => {
              e.preventDefault();
              mutation.mutate();
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
