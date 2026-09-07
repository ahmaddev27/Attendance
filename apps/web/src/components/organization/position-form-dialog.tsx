'use client';

import * as React from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { departmentsApi } from '@/lib/api/endpoints/departments';
import { positionsApi } from '@/lib/api/endpoints/positions';
import type { Position, PositionInput } from '@/lib/api/types';

const NO_DEPARTMENT = '__none__';

const positionFormSchema = z.object({
  title: z.string().trim().min(1, 'المسمى الوظيفي مطلوب').max(150, 'المسمى الوظيفي طويل جداً'),
  code: z.string().trim().max(30, 'الرمز طويل جداً').optional().or(z.literal('')),
  department_id: z.number().nullable(),
  is_active: z.boolean(),
});

type PositionFormValues = z.infer<typeof positionFormSchema>;

function buildDefaultValues(position?: Position | null, defaultDepartmentId?: number): PositionFormValues {
  if (!position) {
    return { title: '', code: '', department_id: defaultDepartmentId ?? null, is_active: true };
  }
  return {
    title: position.title,
    code: position.code ?? '',
    department_id: position.department_id,
    is_active: position.is_active,
  };
}

type PositionFormDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  position?: Position | null;
  defaultDepartmentId?: number;
};

export function PositionFormDialog({ open, onOpenChange, position, defaultDepartmentId }: PositionFormDialogProps) {
  const isEdit = !!position;
  const queryClient = useQueryClient();

  const form = useForm<PositionFormValues>({
    resolver: zodResolver(positionFormSchema),
    defaultValues: buildDefaultValues(position, defaultDepartmentId),
  });

  React.useEffect(() => {
    if (open) form.reset(buildDefaultValues(position, defaultDepartmentId));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, position?.id]);

  const { data: departments } = useQuery({
    queryKey: ['departments', 'picker'],
    queryFn: async () => (await departmentsApi.list({ per_page: 200, is_active: true })).data.data,
    enabled: open,
    staleTime: 60_000,
  });

  const mutation = useMutation({
    mutationFn: (values: PositionFormValues) => {
      const payload: PositionInput = {
        title: values.title,
        code: values.code || null,
        department_id: values.department_id,
        is_active: values.is_active,
      };
      return isEdit ? positionsApi.update(position.id, payload) : positionsApi.create(payload);
    },
    onSuccess: () => {
      toast.success(isEdit ? 'تم تحديث المسمى الوظيفي بنجاح' : 'تمت إضافة المسمى الوظيفي بنجاح');
      queryClient.invalidateQueries({ queryKey: ['positions'] });
      onOpenChange(false);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'حدث خطأ أثناء حفظ المسمى الوظيفي');
    },
  });

  const onSubmit = form.handleSubmit((values) => mutation.mutate(values));

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{isEdit ? 'تعديل المسمى الوظيفي' : 'إضافة مسمى وظيفي جديد'}</DialogTitle>
          <DialogDescription>
            {isEdit ? 'قم بتحديث بيانات المسمى الوظيفي ثم احفظ التغييرات.' : 'أدخل بيانات المسمى الوظيفي الجديد.'}
          </DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form onSubmit={onSubmit} className="space-y-4">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <FormField
                control={form.control}
                name="title"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>المسمى الوظيفي</FormLabel>
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
                    <FormLabel>الرمز (اختياري)</FormLabel>
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
              name="department_id"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>القسم (اختياري)</FormLabel>
                  <Select
                    value={field.value != null ? String(field.value) : NO_DEPARTMENT}
                    onValueChange={(v) => field.onChange(v === NO_DEPARTMENT ? null : Number(v))}
                  >
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="بدون قسم محدد" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      <SelectItem value={NO_DEPARTMENT}>بدون قسم محدد</SelectItem>
                      {(departments ?? []).map((department) => (
                        <SelectItem key={department.id} value={String(department.id)}>
                          {department.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="is_active"
              render={({ field }) => (
                <FormItem className="flex flex-row items-center justify-between rounded-lg border border-hairline p-3">
                  <FormLabel className="cursor-pointer">المسمى نشط</FormLabel>
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
              <Button
                type="submit"
                disabled={mutation.isPending}
                className="bg-brand text-white hover:bg-brand-hover"
              >
                {mutation.isPending && <Spinner className="text-white" />}
                {isEdit ? 'حفظ التغييرات' : 'إضافة المسمى'}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
