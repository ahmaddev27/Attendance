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
import { EmployeePicker } from '@/components/employees/employee-picker';
import { employeesApi } from '@/lib/api/endpoints/employees';
import { workflowStepsApi } from '@/lib/api/endpoints/workflows';
import { APPROVER_TYPE_OPTIONS, ROLE_OPTIONS } from '@/lib/constants/request-options';
import type { ApproverType, EmployeeSummary, WorkflowStep, WorkflowStepPayload } from '@/lib/api/types';

/** Approver types that need a value picked in `approver_ref` before the step can be saved. */
const REF_REQUIRED_TYPES: ApproverType[] = ['specific_employee', 'specific_role', 'form_field'];

const workflowStepFormSchema = z
  .object({
    name: z.string().trim().min(1, 'اسم الخطوة مطلوب').max(150, 'الاسم طويل جداً'),
    approver_type: z.enum(['direct_manager', 'department_manager', 'specific_employee', 'specific_role', 'form_field']),
    approver_ref: z.string().trim().max(150).nullable(),
    can_reject: z.boolean(),
    can_return: z.boolean(),
    can_forward: z.boolean(),
    sla_hours: z.number().min(1, 'يجب أن تكون أكبر من صفر').nullable(),
  })
  .refine((data) => !REF_REQUIRED_TYPES.includes(data.approver_type) || !!data.approver_ref?.trim(), {
    message: 'هذا الحقل مطلوب لنوع المعتمد المحدد',
    path: ['approver_ref'],
  });

type WorkflowStepFormValues = z.infer<typeof workflowStepFormSchema>;

function buildDefaultValues(step?: WorkflowStep | null): WorkflowStepFormValues {
  if (!step) {
    return {
      name: '',
      approver_type: 'direct_manager',
      approver_ref: null,
      can_reject: true,
      can_return: false,
      can_forward: false,
      sla_hours: null,
    };
  }
  return {
    name: step.name,
    approver_type: step.approver_type,
    approver_ref: step.approver_ref,
    can_reject: step.can_reject,
    can_return: step.can_return,
    can_forward: step.can_forward,
    sla_hours: step.sla_hours,
  };
}

type WorkflowStepFormDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  workflowId: number;
  step?: WorkflowStep | null;
  /** step_order assigned to a brand-new step — appended to the end of the list. */
  nextOrder: number;
};

export function WorkflowStepFormDialog({ open, onOpenChange, workflowId, step, nextOrder }: WorkflowStepFormDialogProps) {
  const isEdit = !!step;
  const queryClient = useQueryClient();
  const [selectedEmployee, setSelectedEmployee] = React.useState<EmployeeSummary | null>(null);

  const form = useForm<WorkflowStepFormValues>({
    resolver: zodResolver(workflowStepFormSchema),
    defaultValues: buildDefaultValues(step),
  });

  React.useEffect(() => {
    if (open) {
      form.reset(buildDefaultValues(step));
      setSelectedEmployee(null);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, step?.id]);

  const approverType = form.watch('approver_type');
  const approverRef = form.watch('approver_ref');

  // When editing a "specific employee" step, the saved ref is only an id —
  // hydrate the actual employee record so the picker shows a name, not a number.
  const hydratedEmployeeId = approverType === 'specific_employee' && approverRef ? Number(approverRef) : null;
  const { data: hydratedEmployee } = useQuery({
    queryKey: ['employees', 'hydrate', hydratedEmployeeId],
    queryFn: async () => (await employeesApi.get(hydratedEmployeeId as number)).data.data,
    enabled: open && !!hydratedEmployeeId && !Number.isNaN(hydratedEmployeeId),
  });

  React.useEffect(() => {
    if (hydratedEmployee) setSelectedEmployee(hydratedEmployee);
  }, [hydratedEmployee]);

  const handleApproverTypeChange = (value: ApproverType) => {
    form.setValue('approver_type', value);
    form.setValue('approver_ref', null);
    setSelectedEmployee(null);
  };

  const mutation = useMutation({
    mutationFn: (values: WorkflowStepFormValues) => {
      const payload: WorkflowStepPayload = {
        name: values.name,
        approver_type: values.approver_type,
        approver_ref: REF_REQUIRED_TYPES.includes(values.approver_type) ? values.approver_ref : null,
        can_reject: values.can_reject,
        can_return: values.can_return,
        can_forward: values.can_forward,
        sla_hours: values.sla_hours,
      };
      return isEdit
        ? workflowStepsApi.update(workflowId, step.id, payload)
        : workflowStepsApi.create(workflowId, payload);
    },
    onSuccess: () => {
      toast.success(isEdit ? 'تم تحديث الخطوة بنجاح' : 'تمت إضافة الخطوة بنجاح');
      queryClient.invalidateQueries({ queryKey: ['workflows', workflowId] });
      onOpenChange(false);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'حدث خطأ أثناء حفظ الخطوة');
    },
  });

  const onSubmit = form.handleSubmit((values) => mutation.mutate(values));

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{isEdit ? 'تعديل الخطوة' : 'إضافة خطوة جديدة'}</DialogTitle>
          <DialogDescription>
            {isEdit
              ? 'قم بتحديث بيانات هذه الخطوة من مسار الاعتماد.'
              : `ستُضاف كخطوة رقم ${nextOrder} في هذا المسار.`}
          </DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form onSubmit={onSubmit} className="space-y-4">
            <FormField
              control={form.control}
              name="name"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>اسم الخطوة</FormLabel>
                  <FormControl>
                    <Input {...field} autoFocus placeholder="مثال: اعتماد مدير القسم" />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="approver_type"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>نوع المعتمد</FormLabel>
                  <Select value={field.value} onValueChange={handleApproverTypeChange}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {APPROVER_TYPE_OPTIONS.map((opt) => (
                        <SelectItem key={opt.value} value={opt.value}>
                          {opt.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />

            {approverType === 'specific_employee' && (
              <FormField
                control={form.control}
                name="approver_ref"
                render={() => (
                  <FormItem>
                    <FormLabel>الموظف المعتمد</FormLabel>
                    <FormControl>
                      <EmployeePicker
                        value={selectedEmployee}
                        onChange={(employee) => {
                          setSelectedEmployee(employee);
                          form.setValue('approver_ref', employee ? String(employee.id) : null, {
                            shouldValidate: true,
                          });
                        }}
                        placeholder="اختر الموظف المعتمد..."
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            )}

            {approverType === 'specific_role' && (
              <FormField
                control={form.control}
                name="approver_ref"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>الدور المعتمد</FormLabel>
                    <Select value={field.value ?? undefined} onValueChange={field.onChange}>
                      <FormControl>
                        <SelectTrigger>
                          <SelectValue placeholder="اختر الدور" />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        {ROLE_OPTIONS.map((opt) => (
                          <SelectItem key={opt.value} value={opt.value}>
                            {opt.label}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <FormMessage />
                  </FormItem>
                )}
              />
            )}

            {approverType === 'form_field' && (
              <FormField
                control={form.control}
                name="approver_ref"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>مفتاح الحقل في نموذج الطلب</FormLabel>
                    <FormControl>
                      <Input
                        dir="ltr"
                        className="text-right"
                        placeholder="مثال: reviewer_employee_id"
                        {...field}
                        value={field.value ?? ''}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            )}

            <FormField
              control={form.control}
              name="sla_hours"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>مهلة الاستجابة بالساعات (اختياري)</FormLabel>
                  <FormControl>
                    <Input
                      type="number"
                      min={1}
                      dir="ltr"
                      className="num text-right"
                      value={field.value ?? ''}
                      placeholder="بدون حد"
                      onChange={(e) => field.onChange(e.target.value === '' ? null : Number(e.target.value))}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
              <FormField
                control={form.control}
                name="can_reject"
                render={({ field }) => (
                  <FormItem className="flex flex-row items-center justify-between rounded-lg border border-hairline p-3">
                    <FormLabel className="cursor-pointer">يمكن الرفض</FormLabel>
                    <FormControl>
                      <Switch checked={field.value} onCheckedChange={field.onChange} />
                    </FormControl>
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="can_return"
                render={({ field }) => (
                  <FormItem className="flex flex-row items-center justify-between rounded-lg border border-hairline p-3">
                    <FormLabel className="cursor-pointer">يمكن الإرجاع</FormLabel>
                    <FormControl>
                      <Switch checked={field.value} onCheckedChange={field.onChange} />
                    </FormControl>
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="can_forward"
                render={({ field }) => (
                  <FormItem className="flex flex-row items-center justify-between rounded-lg border border-hairline p-3">
                    <FormLabel className="cursor-pointer">يمكن التحويل</FormLabel>
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
                {isEdit ? 'حفظ التغييرات' : 'إضافة الخطوة'}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
