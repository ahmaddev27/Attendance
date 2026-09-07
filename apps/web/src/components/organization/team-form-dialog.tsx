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
import { teamsApi } from '@/lib/api/endpoints/teams';
import type { Team, TeamInput } from '@/lib/api/types';

const teamFormSchema = z.object({
  name: z.string().trim().min(1, 'اسم الفريق مطلوب').max(150, 'اسم الفريق طويل جداً'),
  department_id: z.number({ invalid_type_error: 'القسم مطلوب' }).nullable(),
  description: z.string().trim().max(1000, 'الوصف طويل جداً').optional().or(z.literal('')),
  is_active: z.boolean(),
}).refine((data) => data.department_id !== null, {
  message: 'القسم مطلوب',
  path: ['department_id'],
});

type TeamFormValues = z.infer<typeof teamFormSchema>;

function buildDefaultValues(team?: Team | null, defaultDepartmentId?: number): TeamFormValues {
  if (!team) {
    return { name: '', department_id: defaultDepartmentId ?? null, description: '', is_active: true };
  }
  return {
    name: team.name,
    department_id: team.department_id,
    description: team.description ?? '',
    is_active: team.is_active,
  };
}

type TeamFormDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  team?: Team | null;
  /** Pre-selects a department when creating a team from a department-filtered view. */
  defaultDepartmentId?: number;
};

export function TeamFormDialog({ open, onOpenChange, team, defaultDepartmentId }: TeamFormDialogProps) {
  const isEdit = !!team;
  const queryClient = useQueryClient();

  const form = useForm<TeamFormValues>({
    resolver: zodResolver(teamFormSchema),
    defaultValues: buildDefaultValues(team, defaultDepartmentId),
  });

  React.useEffect(() => {
    if (open) form.reset(buildDefaultValues(team, defaultDepartmentId));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, team?.id]);

  const { data: departments } = useQuery({
    queryKey: ['departments', 'picker'],
    queryFn: async () => (await departmentsApi.list({ per_page: 200, is_active: true })).data.data,
    enabled: open,
    staleTime: 60_000,
  });

  const mutation = useMutation({
    mutationFn: (values: TeamFormValues) => {
      const payload: TeamInput = {
        name: values.name,
        department_id: values.department_id as number,
        description: values.description || null,
        is_active: values.is_active,
      };
      return isEdit ? teamsApi.update(team.id, payload) : teamsApi.create(payload);
    },
    onSuccess: () => {
      toast.success(isEdit ? 'تم تحديث الفريق بنجاح' : 'تمت إضافة الفريق بنجاح');
      queryClient.invalidateQueries({ queryKey: ['teams'] });
      onOpenChange(false);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'حدث خطأ أثناء حفظ بيانات الفريق');
    },
  });

  const onSubmit = form.handleSubmit((values) => mutation.mutate(values));

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{isEdit ? 'تعديل الفريق' : 'إضافة فريق جديد'}</DialogTitle>
          <DialogDescription>
            {isEdit ? 'قم بتحديث بيانات الفريق ثم احفظ التغييرات.' : 'أدخل بيانات الفريق الجديد.'}
          </DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form onSubmit={onSubmit} className="space-y-4">
            <FormField
              control={form.control}
              name="name"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>اسم الفريق</FormLabel>
                  <FormControl>
                    <Input {...field} autoFocus />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="department_id"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>القسم</FormLabel>
                  <Select
                    value={field.value != null ? String(field.value) : undefined}
                    onValueChange={(v) => field.onChange(Number(v))}
                  >
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="اختر القسم" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
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
                  <FormLabel className="cursor-pointer">الفريق نشط</FormLabel>
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
                {isEdit ? 'حفظ التغييرات' : 'إضافة الفريق'}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
