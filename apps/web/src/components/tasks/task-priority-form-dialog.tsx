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
import { taskPrioritiesApi } from '@/lib/api/endpoints/task-config';
import type { TaskPriority, TaskPriorityPayload } from '@/lib/api/types';

const HEX_COLOR_REGEX = /^#([0-9a-fA-F]{6}|[0-9a-fA-F]{3})$/;
const DEFAULT_COLOR = '#e6a935';

function extractErrorMessage(err: unknown, fallback: string): string {
  const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
  return message || fallback;
}

const taskPriorityFormSchema = z.object({
  name: z.string().trim().min(1, 'اسم الأولوية مطلوب').max(100, 'الاسم طويل جداً'),
  code: z
    .string()
    .trim()
    .min(1, 'الرمز مطلوب')
    .max(30, 'الرمز طويل جداً')
    .regex(/^[a-zA-Z0-9_-]+$/, 'الرمز يجب أن يحتوي أحرف/أرقام إنجليزية فقط'),
  color: z.string().regex(HEX_COLOR_REGEX, 'صيغة اللون غير صحيحة (مثال: #E6A935)'),
  sort_order: z.number().min(0, 'القيمة يجب أن تكون صفر أو أكثر'),
});

type TaskPriorityFormValues = z.infer<typeof taskPriorityFormSchema>;

function buildDefaultValues(priority?: TaskPriority | null): TaskPriorityFormValues {
  if (!priority) return { name: '', code: '', color: DEFAULT_COLOR, sort_order: 0 };
  return { name: priority.name, code: priority.code, color: priority.color, sort_order: priority.sort_order };
}

type TaskPriorityFormDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  priority?: TaskPriority | null;
};

export function TaskPriorityFormDialog({ open, onOpenChange, priority }: TaskPriorityFormDialogProps) {
  const isEdit = !!priority;
  const queryClient = useQueryClient();

  const form = useForm<TaskPriorityFormValues>({
    resolver: zodResolver(taskPriorityFormSchema),
    defaultValues: buildDefaultValues(priority),
  });

  React.useEffect(() => {
    if (open) form.reset(buildDefaultValues(priority));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, priority?.id]);

  const mutation = useMutation({
    mutationFn: (values: TaskPriorityFormValues) => {
      const payload: TaskPriorityPayload = { ...values };
      return isEdit ? taskPrioritiesApi.update(priority.id, payload) : taskPrioritiesApi.create(payload);
    },
    onSuccess: () => {
      toast.success(isEdit ? 'تم تحديث الأولوية بنجاح' : 'تمت إضافة الأولوية بنجاح');
      queryClient.invalidateQueries({ queryKey: ['task-priorities'] });
      onOpenChange(false);
    },
    onError: (err: unknown) => toast.error(extractErrorMessage(err, 'حدث خطأ أثناء حفظ الأولوية')),
  });

  const onSubmit = form.handleSubmit((values) => mutation.mutate(values));

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{isEdit ? 'تعديل الأولوية' : 'إضافة أولوية جديدة'}</DialogTitle>
          <DialogDescription>
            {isEdit ? 'قم بتحديث بيانات الأولوية ثم احفظ التغييرات.' : 'أدخل بيانات الأولوية الجديدة.'}
          </DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form onSubmit={onSubmit} className="space-y-4">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <FormField
                control={form.control}
                name="name"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>اسم الأولوية</FormLabel>
                    <FormControl>
                      <Input {...field} autoFocus />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="code"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>الرمز</FormLabel>
                    <FormControl>
                      <Input dir="ltr" className="text-right" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            <FormField
              control={form.control}
              name="color"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>اللون المميز</FormLabel>
                  <div className="flex items-center gap-2">
                    <FormControl>
                      <input
                        type="color"
                        value={HEX_COLOR_REGEX.test(field.value) ? field.value : DEFAULT_COLOR}
                        onChange={(e) => field.onChange(e.target.value)}
                        className="h-9 w-11 cursor-pointer rounded-md border border-hairline bg-transparent p-1"
                        aria-label="اختيار اللون"
                      />
                    </FormControl>
                    <FormControl>
                      <Input dir="ltr" className="text-right" {...field} placeholder="#E6A935" />
                    </FormControl>
                  </div>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="sort_order"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>ترتيب العرض</FormLabel>
                  <FormControl>
                    <Input
                      type="number"
                      min={0}
                      dir="ltr"
                      className="num text-right sm:w-40"
                      value={field.value}
                      onChange={(e) => field.onChange(Number(e.target.value))}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                إلغاء
              </Button>
              <Button type="submit" disabled={mutation.isPending} className="bg-brand text-white hover:bg-brand-hover">
                {mutation.isPending && <Spinner className="text-white" />}
                {isEdit ? 'حفظ التغييرات' : 'إضافة الأولوية'}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
