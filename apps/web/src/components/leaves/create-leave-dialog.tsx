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
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { EmployeePicker } from '@/components/employees/employee-picker';
import { LeaveTypeBadge } from '@/components/leaves/leave-type-badge';
import { leaveRequestsApi } from '@/lib/api/endpoints/leaves';
import { leaveTypesApi } from '@/lib/api/endpoints/leave-types';
import { estimateWorkingDays } from '@/lib/leave-format';
import type { EmployeeSummary } from '@/lib/api/types';

const createLeaveSchema = z
  .object({
    leave_type_id: z.number({ required_error: 'نوع الإجازة مطلوب' }).min(1, 'نوع الإجازة مطلوب'),
    start_date: z.string().min(1, 'تاريخ البداية مطلوب'),
    end_date: z.string().min(1, 'تاريخ النهاية مطلوب'),
    reason: z.string().trim().max(1000, 'السبب طويل جداً').optional().or(z.literal('')),
  })
  .refine((data) => !data.start_date || !data.end_date || data.end_date >= data.start_date, {
    message: 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية أو يساويه',
    path: ['end_date'],
  });

type CreateLeaveFormValues = z.infer<typeof createLeaveSchema>;

const EMPTY_VALUES: CreateLeaveFormValues = {
  leave_type_id: 0,
  start_date: '',
  end_date: '',
  reason: '',
};

type CreateLeaveDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
};

/** Admin-only "submit on behalf of an employee" leave request dialog. */
export function CreateLeaveDialog({ open, onOpenChange }: CreateLeaveDialogProps) {
  const queryClient = useQueryClient();
  const [employee, setEmployee] = React.useState<EmployeeSummary | null>(null);
  const [employeeError, setEmployeeError] = React.useState<string | null>(null);

  const form = useForm<CreateLeaveFormValues>({
    resolver: zodResolver(createLeaveSchema),
    defaultValues: EMPTY_VALUES,
  });

  React.useEffect(() => {
    if (open) {
      form.reset(EMPTY_VALUES);
      setEmployee(null);
      setEmployeeError(null);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open]);

  const { data: leaveTypes } = useQuery({
    queryKey: ['leave-types', 'picker'],
    queryFn: async () => (await leaveTypesApi.list()).data.data,
    enabled: open,
    staleTime: 60_000,
  });

  const activeLeaveTypes = (leaveTypes ?? []).filter((type) => type.is_active);

  const startDate = form.watch('start_date');
  const endDate = form.watch('end_date');
  const estimatedDays = estimateWorkingDays(startDate, endDate);

  const mutation = useMutation({
    mutationFn: (values: CreateLeaveFormValues) =>
      leaveRequestsApi.create({
        employee_id: employee!.id,
        leave_type_id: values.leave_type_id,
        start_date: values.start_date,
        end_date: values.end_date,
        reason: values.reason || undefined,
      }),
    onSuccess: () => {
      toast.success('تم إنشاء طلب الإجازة بنجاح');
      queryClient.invalidateQueries({ queryKey: ['leave-requests'] });
      onOpenChange(false);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'تعذر إنشاء طلب الإجازة');
    },
  });

  const onSubmit = form.handleSubmit((values) => {
    if (!employee) {
      setEmployeeError('الموظف مطلوب');
      return;
    }
    mutation.mutate(values);
  });

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>طلب إجازة جديد</DialogTitle>
          <DialogDescription>أنشئ طلب إجازة نيابة عن أحد الموظفين.</DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form onSubmit={onSubmit} className="space-y-4">
            <div className="flex flex-col gap-2">
              <Label>الموظف</Label>
              <EmployeePicker
                value={employee}
                onChange={(value) => {
                  setEmployee(value);
                  if (value) setEmployeeError(null);
                }}
                placeholder="اختر الموظف"
              />
              {employeeError && <p className="text-xs text-danger">{employeeError}</p>}
            </div>

            <FormField
              control={form.control}
              name="leave_type_id"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>نوع الإجازة</FormLabel>
                  <Select
                    value={field.value ? String(field.value) : undefined}
                    onValueChange={(v) => field.onChange(Number(v))}
                  >
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="اختر نوع الإجازة" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {activeLeaveTypes.map((type) => (
                        <SelectItem key={type.id} value={String(type.id)}>
                          <LeaveTypeBadge leaveType={type} />
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <FormField
                control={form.control}
                name="start_date"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>تاريخ البداية</FormLabel>
                    <FormControl>
                      <Input type="date" dir="ltr" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="end_date"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>تاريخ النهاية</FormLabel>
                    <FormControl>
                      <Input type="date" dir="ltr" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            {estimatedDays > 0 && (
              <p className="text-xs text-muted">
                عدد الأيام (تقديري): <span className="num font-medium text-ink-2">{estimatedDays}</span>
              </p>
            )}

            <FormField
              control={form.control}
              name="reason"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>السبب (اختياري)</FormLabel>
                  <FormControl>
                    <Textarea rows={3} {...field} />
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
                إنشاء الطلب
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
