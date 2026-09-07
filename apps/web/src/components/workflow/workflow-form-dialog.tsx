'use client';

import * as React from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useMutation, useQueryClient } from '@tanstack/react-query';
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
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { workflowsApi } from '@/lib/api/endpoints/workflows';
import type { Workflow, WorkflowPayload } from '@/lib/api/types';

const workflowFormSchema = z.object({
  name: z.string().trim().min(1, 'اسم المسار مطلوب').max(150, 'الاسم طويل جداً'),
  description: z.string().trim().max(1000, 'الوصف طويل جداً').optional().or(z.literal('')),
  is_active: z.boolean(),
});

type WorkflowFormValues = z.infer<typeof workflowFormSchema>;

function buildDefaultValues(workflow?: Workflow | null): WorkflowFormValues {
  if (!workflow) return { name: '', description: '', is_active: true };
  return {
    name: workflow.name,
    description: workflow.description ?? '',
    is_active: workflow.is_active,
  };
}

type WorkflowFormDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  workflow?: Workflow | null;
};

/** Create/edit modal for a workflow's own metadata — steps are managed on the detail page. */
export function WorkflowFormDialog({ open, onOpenChange, workflow }: WorkflowFormDialogProps) {
  const isEdit = !!workflow;
  const queryClient = useQueryClient();

  const form = useForm<WorkflowFormValues>({
    resolver: zodResolver(workflowFormSchema),
    defaultValues: buildDefaultValues(workflow),
  });

  React.useEffect(() => {
    if (open) form.reset(buildDefaultValues(workflow));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, workflow?.id]);

  const mutation = useMutation({
    mutationFn: (values: WorkflowFormValues) => {
      const payload: WorkflowPayload = {
        name: values.name,
        description: values.description || null,
        is_active: values.is_active,
      };
      return isEdit ? workflowsApi.update(workflow.id, payload) : workflowsApi.create(payload);
    },
    onSuccess: () => {
      toast.success(isEdit ? 'تم تحديث مسار العمل بنجاح' : 'تمت إضافة مسار العمل بنجاح');
      queryClient.invalidateQueries({ queryKey: ['workflows'] });
      if (isEdit) queryClient.invalidateQueries({ queryKey: ['workflows', workflow.id] });
      onOpenChange(false);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'حدث خطأ أثناء حفظ مسار العمل');
    },
  });

  const onSubmit = form.handleSubmit((values) => mutation.mutate(values));

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{isEdit ? 'تعديل مسار العمل' : 'إضافة مسار عمل جديد'}</DialogTitle>
          <DialogDescription>
            {isEdit
              ? 'قم بتحديث بيانات المسار ثم احفظ التغييرات.'
              : 'أدخل بيانات المسار — يمكنك إضافة خطوات الاعتماد بعد الإنشاء.'}
          </DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form onSubmit={onSubmit} className="space-y-4">
            <FormField
              control={form.control}
              name="name"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>اسم المسار</FormLabel>
                  <FormControl>
                    <Input {...field} autoFocus />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="description"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>الوصف (اختياري)</FormLabel>
                  <FormControl>
                    <Textarea rows={3} {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="is_active"
              render={({ field }) => (
                <FormItem className="flex flex-row items-center justify-between rounded-lg border border-hairline p-3">
                  <FormLabel className="cursor-pointer">المسار نشط</FormLabel>
                  <FormControl>
                    <Switch checked={field.value} onCheckedChange={field.onChange} />
                  </FormControl>
                </FormItem>
              )}
            />

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                إلغاء
              </Button>
              <Button type="submit" disabled={mutation.isPending} className="bg-brand text-white hover:bg-brand-hover">
                {mutation.isPending && <Spinner className="text-white" />}
                {isEdit ? 'حفظ التغييرات' : 'إضافة المسار'}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
