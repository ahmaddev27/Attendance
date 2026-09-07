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
import { taskTagsApi } from '@/lib/api/endpoints/task-config';
import type { TaskTag, TaskTagPayload } from '@/lib/api/types';

const HEX_COLOR_REGEX = /^#([0-9a-fA-F]{6}|[0-9a-fA-F]{3})$/;
const DEFAULT_COLOR = '#2678c4';

function extractErrorMessage(err: unknown, fallback: string): string {
  const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
  return message || fallback;
}

const taskTagFormSchema = z.object({
  name: z.string().trim().min(1, 'اسم الوسم مطلوب').max(50, 'الاسم طويل جداً'),
  color: z.string().regex(HEX_COLOR_REGEX, 'صيغة اللون غير صحيحة (مثال: #2678C4)'),
});

type TaskTagFormValues = z.infer<typeof taskTagFormSchema>;

function buildDefaultValues(tag?: TaskTag | null): TaskTagFormValues {
  if (!tag) return { name: '', color: DEFAULT_COLOR };
  return { name: tag.name, color: tag.color };
}

type TaskTagFormDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  tag?: TaskTag | null;
};

export function TaskTagFormDialog({ open, onOpenChange, tag }: TaskTagFormDialogProps) {
  const isEdit = !!tag;
  const queryClient = useQueryClient();

  const form = useForm<TaskTagFormValues>({
    resolver: zodResolver(taskTagFormSchema),
    defaultValues: buildDefaultValues(tag),
  });

  React.useEffect(() => {
    if (open) form.reset(buildDefaultValues(tag));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, tag?.id]);

  const mutation = useMutation({
    mutationFn: (values: TaskTagFormValues) => {
      const payload: TaskTagPayload = { ...values };
      return isEdit ? taskTagsApi.update(tag.id, payload) : taskTagsApi.create(payload);
    },
    onSuccess: () => {
      toast.success(isEdit ? 'تم تحديث الوسم بنجاح' : 'تمت إضافة الوسم بنجاح');
      queryClient.invalidateQueries({ queryKey: ['task-tags'] });
      onOpenChange(false);
    },
    onError: (err: unknown) => toast.error(extractErrorMessage(err, 'حدث خطأ أثناء حفظ الوسم')),
  });

  const onSubmit = form.handleSubmit((values) => mutation.mutate(values));

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>{isEdit ? 'تعديل الوسم' : 'إضافة وسم جديد'}</DialogTitle>
          <DialogDescription>
            {isEdit ? 'قم بتحديث بيانات الوسم ثم احفظ التغييرات.' : 'أدخل بيانات الوسم الجديد.'}
          </DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form onSubmit={onSubmit} className="space-y-4">
            <FormField
              control={form.control}
              name="name"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>اسم الوسم</FormLabel>
                  <FormControl>
                    <Input {...field} autoFocus />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

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
                      <Input dir="ltr" className="text-right" {...field} placeholder="#2678C4" />
                    </FormControl>
                  </div>
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
                {isEdit ? 'حفظ التغييرات' : 'إضافة الوسم'}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
