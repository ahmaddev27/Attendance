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
import { Textarea } from '@/components/ui/textarea';
import { departmentsApi } from '@/lib/api/endpoints/departments';
import type { Department, DepartmentInput } from '@/lib/api/types';

const NO_PARENT = '__none__';

const departmentFormSchema = z.object({
  name: z.string().trim().min(1, 'اسم القسم مطلوب').max(150, 'اسم القسم طويل جداً'),
  code: z.string().trim().max(30, 'الرمز طويل جداً').optional().or(z.literal('')),
  parent_id: z.number().nullable(),
  description: z.string().trim().max(1000, 'الوصف طويل جداً').optional().or(z.literal('')),
  is_active: z.boolean(),
});

type DepartmentFormValues = z.infer<typeof departmentFormSchema>;

function buildDefaultValues(department?: Department | null): DepartmentFormValues {
  if (!department) {
    return { name: '', code: '', parent_id: null, description: '', is_active: true };
  }
  return {
    name: department.name,
    code: department.code ?? '',
    parent_id: department.parent_id,
    description: department.description ?? '',
    is_active: department.is_active,
  };
}

/**
 * Walks the department list to find every descendant of `rootId`, so the
 * parent picker can exclude them — picking a descendant (or itself) as the
 * new parent would create a cycle in the org tree.
 */
function getDescendantIds(departments: Department[], rootId: number): Set<number> {
  const childrenByParent = new Map<number, number[]>();
  departments.forEach((department) => {
    if (department.parent_id != null) {
      const siblings = childrenByParent.get(department.parent_id) ?? [];
      siblings.push(department.id);
      childrenByParent.set(department.parent_id, siblings);
    }
  });

  const descendants = new Set<number>();
  const stack = [rootId];
  while (stack.length) {
    const current = stack.pop() as number;
    (childrenByParent.get(current) ?? []).forEach((childId) => {
      if (!descendants.has(childId)) {
        descendants.add(childId);
        stack.push(childId);
      }
    });
  }
  return descendants;
}

type DepartmentFormDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  department?: Department | null;
};

export function DepartmentFormDialog({ open, onOpenChange, department }: DepartmentFormDialogProps) {
  const isEdit = !!department;
  const queryClient = useQueryClient();

  const form = useForm<DepartmentFormValues>({
    resolver: zodResolver(departmentFormSchema),
    defaultValues: buildDefaultValues(department),
  });

  React.useEffect(() => {
    if (open) form.reset(buildDefaultValues(department));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, department?.id]);

  const { data: departments } = useQuery({
    queryKey: ['departments', 'parent-picker'],
    queryFn: async () => (await departmentsApi.list({ per_page: 200 })).data.data,
    enabled: open,
  });

  const invalidParentIds = React.useMemo(() => {
    if (!department || !departments) return new Set<number>();
    return new Set([department.id, ...getDescendantIds(departments, department.id)]);
  }, [department, departments]);

  const parentOptions = (departments ?? []).filter((candidate) => !invalidParentIds.has(candidate.id));

  const mutation = useMutation({
    mutationFn: (values: DepartmentFormValues) => {
      const payload: DepartmentInput = {
        name: values.name,
        code: values.code || null,
        parent_id: values.parent_id,
        description: values.description || null,
        is_active: values.is_active,
      };
      return isEdit ? departmentsApi.update(department.id, payload) : departmentsApi.create(payload);
    },
    onSuccess: () => {
      toast.success(isEdit ? 'تم تحديث القسم بنجاح' : 'تمت إضافة القسم بنجاح');
      queryClient.invalidateQueries({ queryKey: ['departments'] });
      onOpenChange(false);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'حدث خطأ أثناء حفظ بيانات القسم');
    },
  });

  const onSubmit = form.handleSubmit((values) => mutation.mutate(values));

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{isEdit ? 'تعديل القسم' : 'إضافة قسم جديد'}</DialogTitle>
          <DialogDescription>
            {isEdit ? 'قم بتحديث بيانات القسم ثم احفظ التغييرات.' : 'أدخل بيانات القسم الجديد.'}
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
                    <FormLabel>اسم القسم</FormLabel>
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
              name="parent_id"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>القسم الأعلى (اختياري)</FormLabel>
                  <Select
                    value={field.value != null ? String(field.value) : NO_PARENT}
                    onValueChange={(v) => field.onChange(v === NO_PARENT ? null : Number(v))}
                  >
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="بدون قسم أعلى" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      <SelectItem value={NO_PARENT}>بدون قسم أعلى</SelectItem>
                      {parentOptions.map((option) => (
                        <SelectItem key={option.id} value={String(option.id)}>
                          {option.name}
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
                  <FormLabel className="cursor-pointer">القسم نشط</FormLabel>
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
                {isEdit ? 'حفظ التغييرات' : 'إضافة القسم'}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
