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
import { taskStatusesApi } from '@/lib/api/endpoints/task-config';
import type { TaskStatus, TaskStatusPayload } from '@/lib/api/types';

const HEX_COLOR_REGEX = /^#([0-9a-fA-F]{6}|[0-9a-fA-F]{3})$/;
const DEFAULT_COLOR = '#5b6478';

function extractErrorMessage(err: unknown, fallback: string): string {
  const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
  return message || fallback;
}

const taskStatusFormSchema = z.object({
  name: z.string().trim().min(1, 'اسم الحالة مطلوب').max(100, 'الاسم طويل جداً'),
  code: z
    .string()
    .trim()
    .min(1, 'الرمز مطلوب')
    .max(30, 'الرمز طويل جداً')
    .regex(/^[a-zA-Z0-9_-]+$/, 'الرمز يجب أن يحتوي أحرف/أرقام إنجليزية فقط'),
  color: z.string().regex(HEX_COLOR_REGEX, 'صيغة اللون غير صحيحة (مثال: #5B6478)'),
  sort_order: z.number().min(0, 'القيمة يجب أن تكون صفر أو أكثر'),
  is_done_state: z.boolean(),
  is_cancelled_state: z.boolean(),
});

type TaskStatusFormValues = z.infer<typeof taskStatusFormSchema>;

function buildDefaultValues(status?: TaskStatus | null): TaskStatusFormValues {
  if (!status) {
    return { name: '', code: '', color: DEFAULT_COLOR, sort_order: 0, is_done_state: false, is_cancelled_state: false };
  }
  return {
    name: status.name,
    code: status.code,
    color: status.color,
    sort_order: status.sort_order,
    is_done_state: status.is_done_state,
    is_cancelled_state: status.is_cancelled_state,
  };
}

type TaskStatusFormDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  status?: TaskStatus | null;
};

export function TaskStatusFormDialog({ open, onOpenChange, status }: TaskStatusFormDialogProps) {
  const isEdit = !!status;
  const queryClient = useQueryClient();

  const form = useForm<TaskStatusFormValues>({
    resolver: zodResolver(taskStatusFormSchema),
    defaultValues: buildDefaultValues(status),
  });

  React.useEffect(() => {
    if (open) form.reset(buildDefaultValues(status));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, status?.id]);

  const mutation = useMutation({
    mutationFn: (values: TaskStatusFormValues) => {
      const payload: TaskStatusPayload = { ...values };
      return isEdit ? taskStatusesApi.update(status.id, payload) : taskStatusesApi.create(payload);
    },
    onSuccess: () => {
      toast.success(isEdit ? 'تم تحديث الحالة بنجاح' : 'تمت إضافة الحالة بنجاح');
      queryClient.invalidateQueries({ queryKey: ['task-statuses'] });
      onOpenChange(false);
    },
    onError: (err: unknown) => toast.error(extractErrorMessage(err, 'حدث خطأ أثناء حفظ الحالة')),
  });

  const onSubmit = form.handleSubmit((values) => mutation.mutate(values));

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{isEdit ? 'تعديل حالة المهمة' : 'إضافة حالة جديدة'}</DialogTitle>
          <DialogDescription>
            {isEdit ? 'قم بتحديث بيانات الحالة ثم احفظ التغييرات.' : 'أدخل بيانات حالة المهمة الجديدة.'}
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
                    <FormLabel>اسم الحالة</FormLabel>
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
                      <Input dir="ltr" className="text-right" {...field} placeholder="#5B6478" />
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

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <FormField
                control={form.control}
                name="is_done_state"
                render={({ field }) => (
                  <FormItem className="flex flex-row items-center justify-between rounded-lg border border-hairline p-3">
                    <FormLabel className="cursor-pointer">تعني اكتمال المهمة</FormLabel>
                    <FormControl>
                      <Switch checked={field.value} onCheckedChange={field.onChange} />
                    </FormControl>
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="is_cancelled_state"
                render={({ field }) => (
                  <FormItem className="flex flex-row items-center justify-between rounded-lg border border-hairline p-3">
                    <FormLabel className="cursor-pointer">تعني إلغاء المهمة</FormLabel>
                    <FormControl>
                      <Switch checked={field.value} onCheckedChange={field.onChange} />
                    </FormControl>
                  </FormItem>
                )}
              />
            </div>

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                إلغاء
              </Button>
              <Button type="submit" disabled={mutation.isPending} className="bg-brand text-white hover:bg-brand-hover">
                {mutation.isPending && <Spinner className="text-white" />}
                {isEdit ? 'حفظ التغييرات' : 'إضافة الحالة'}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
