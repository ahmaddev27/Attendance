'use client';

import * as React from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { format, parseISO } from 'date-fns';
import { arSA } from 'date-fns/locale';
import { CalendarIcon } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
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
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { EmployeePicker } from '@/components/employees/employee-picker';
import { departmentsApi } from '@/lib/api/endpoints/departments';
import { employeesApi } from '@/lib/api/endpoints/employees';
import { positionsApi } from '@/lib/api/endpoints/positions';
import { schedulesApi } from '@/lib/api/endpoints/schedules';
import { teamsApi } from '@/lib/api/endpoints/teams';
import type { Employee, EmployeeInput, EmployeeMini } from '@/lib/api/types';
import { EMPLOYMENT_TYPE_LABELS, GENDER_LABELS } from '@/lib/constants/employee-options';
import { cn } from '@/lib/utils';

const employeeFormSchema = z
  .object({
    first_name: z.string().trim().min(1, 'الاسم الأول مطلوب').max(100, 'الاسم الأول طويل جداً'),
    last_name: z.string().trim().min(1, 'اسم العائلة مطلوب').max(100, 'اسم العائلة طويل جداً'),
    email: z
      .string()
      .trim()
      .max(255, 'البريد الإلكتروني طويل جداً')
      .email('صيغة البريد الإلكتروني غير صحيحة')
      .optional()
      .or(z.literal('')),
    phone: z.string().trim().max(30, 'رقم الهاتف طويل جداً').optional().or(z.literal('')),
    department_id: z.number().nullable(),
    team_id: z.number().nullable(),
    position_id: z.number().nullable(),
    work_schedule_id: z.number({ required_error: 'جدول الدوام مطلوب' }).nullable(),
    employment_type: z.enum(['full_time', 'part_time', 'contractor', 'intern'], {
      required_error: 'نوع التوظيف مطلوب',
    }),
    joining_date: z.date({ required_error: 'تاريخ الالتحاق مطلوب' }),
    gender: z.enum(['male', 'female']).nullable(),
  })
  .refine((data) => data.department_id !== null, {
    message: 'القسم مطلوب',
    path: ['department_id'],
  })
  .refine((data) => data.work_schedule_id !== null, {
    message: 'جدول الدوام مطلوب — بدونه لن يُحسب الحضور',
    path: ['work_schedule_id'],
  });

type EmployeeFormValues = z.infer<typeof employeeFormSchema>;

function buildDefaultValues(employee?: Employee | null): EmployeeFormValues {
  if (!employee) {
    return {
      first_name: '',
      last_name: '',
      email: '',
      phone: '',
      department_id: null,
      team_id: null,
      position_id: null,
      work_schedule_id: null,
      employment_type: 'full_time',
      joining_date: new Date(),
      gender: null,
    };
  }

  return {
    first_name: employee.first_name,
    last_name: employee.last_name,
    email: employee.email ?? '',
    phone: employee.phone ?? '',
    department_id: employee.department?.id ?? null,
    team_id: employee.team?.id ?? null,
    position_id: employee.position?.id ?? null,
    work_schedule_id: employee.work_schedule_id ?? null,
    employment_type: employee.employment_type,
    joining_date: parseISO(employee.joining_date),
    gender: employee.gender,
  };
}

type EmployeeFormDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Present => edit mode; absent/null => create mode. */
  employee?: Employee | null;
};

export function EmployeeFormDialog({ open, onOpenChange, employee }: EmployeeFormDialogProps) {
  const isEdit = !!employee;
  const queryClient = useQueryClient();
  // Held as EmployeeMini because the initial seed from the Employee resource
  // only ships {id, full_name}; the picker itself also only reads those two
  // fields off the current value. A fresh selection from the picker is a
  // full EmployeeSummary (assignable to EmployeeMini).
  const [directManager, setDirectManager] = React.useState<EmployeeMini | null>(
    employee?.direct_manager ?? null
  );

  const form = useForm<EmployeeFormValues>({
    resolver: zodResolver(employeeFormSchema),
    defaultValues: buildDefaultValues(employee),
  });

  // Re-seed the form (and the direct-manager side-state) every time the
  // dialog opens, so switching between "edit employee A" -> close -> "edit
  // employee B" never leaks A's values into B's form.
  React.useEffect(() => {
    if (open) {
      form.reset(buildDefaultValues(employee));
      setDirectManager(employee?.direct_manager ?? null);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, employee?.id]);

  const departmentId = form.watch('department_id');

  const { data: departments } = useQuery({
    queryKey: ['departments', 'picker'],
    queryFn: async () => (await departmentsApi.list({ per_page: 100, is_active: true })).data.data,
    enabled: open,
  });

  const { data: teams } = useQuery({
    queryKey: ['teams', 'by-department', departmentId],
    queryFn: async () =>
      (await teamsApi.list({ department_id: departmentId ?? undefined, per_page: 100, is_active: true })).data
        .data,
    enabled: open && departmentId != null,
  });

  const { data: positions } = useQuery({
    queryKey: ['positions', 'by-department', departmentId],
    queryFn: async () =>
      (await positionsApi.list({ department_id: departmentId ?? undefined, per_page: 100, is_active: true }))
        .data.data,
    enabled: open && departmentId != null,
  });

  const { data: schedules } = useQuery({
    queryKey: ['schedules', 'picker'],
    // schedulesApi.list() already unwraps to WorkSchedule[] — no .data.data.
    queryFn: () => schedulesApi.list(),
    enabled: open,
  });

  const mutation = useMutation({
    mutationFn: async (values: EmployeeFormValues) => {
      const payload: EmployeeInput = {
        first_name: values.first_name,
        last_name: values.last_name,
        email: values.email || null,
        phone: values.phone || null,
        department_id: values.department_id,
        team_id: values.team_id,
        position_id: values.position_id,
        work_schedule_id: values.work_schedule_id,
        employment_type: values.employment_type,
        joining_date: format(values.joining_date, 'yyyy-MM-dd'),
        gender: values.gender,
        direct_manager_id: directManager?.id ?? null,
      };
      return isEdit ? employeesApi.update(employee.id, payload) : employeesApi.create(payload);
    },
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['employees'] });
      onOpenChange(false);

      if (isEdit) {
        toast.success('تم تحديث بيانات الموظف بنجاح');
        return;
      }

      // The API returns `generated_password` on create — the plaintext
      // password we minted for the new employee. It was also enqueued as
      // a welcome SMS, but we surface it here as a manual fallback: if
      // the employee has no phone (SMS was skipped) or the carrier
      // silently drops it, the admin has one chance to copy it.
      const created = (res as { data?: { data?: { generated_password?: string; phone?: string | null } } })
        ?.data?.data;
      const password = created?.generated_password;
      const hasPhone = !!(created?.phone && created.phone.trim());

      if (password) {
        toast.success('تمت إضافة الموظف — كلمة السر أدناه', {
          duration: 20000,
          description: hasPhone
            ? `تم إرسال بيانات الدخول عبر SMS. كلمة السر: ${password}`
            : `الموظف بدون رقم جوّال، سلّمه كلمة السر يدوياً: ${password}`,
          action: {
            label: 'نسخ',
            onClick: () => {
              navigator.clipboard.writeText(password).catch(() => {
                /* copy blocked (older Safari, HTTP context) — the string is visible in the toast anyway */
              });
            },
          },
        });
      } else {
        toast.success('تمت إضافة الموظف بنجاح');
      }
    },
    onError: (err: unknown) => {
      const message =
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message ||
        'حدث خطأ أثناء حفظ بيانات الموظف';
      toast.error(message);
    },
  });

  const onSubmit = form.handleSubmit((values) => mutation.mutate(values));

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>{isEdit ? 'تعديل بيانات الموظف' : 'إضافة موظف جديد'}</DialogTitle>
          <DialogDescription>
            {isEdit ? 'قم بتحديث بيانات الموظف ثم احفظ التغييرات.' : 'أدخل بيانات الموظف الجديد.'}
          </DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form onSubmit={onSubmit} className="space-y-5">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <FormField
                control={form.control}
                name="first_name"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>الاسم الأول</FormLabel>
                    <FormControl>
                      <Input {...field} autoFocus />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="last_name"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>اسم العائلة</FormLabel>
                    <FormControl>
                      <Input {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="email"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>البريد الإلكتروني (اختياري)</FormLabel>
                    <FormControl>
                      <Input type="email" dir="ltr" className="text-right" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="phone"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>رقم الهاتف (اختياري)</FormLabel>
                    <FormControl>
                      <Input dir="ltr" className="num text-right" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
              <FormField
                control={form.control}
                name="department_id"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>القسم</FormLabel>
                    <Select
                      value={field.value != null ? String(field.value) : undefined}
                      onValueChange={(v) => {
                        field.onChange(Number(v));
                        form.setValue('team_id', null);
                        form.setValue('position_id', null);
                      }}
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
                name="team_id"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>الفريق (اختياري)</FormLabel>
                    <Select
                      value={field.value != null ? String(field.value) : undefined}
                      onValueChange={(v) => field.onChange(v ? Number(v) : null)}
                      disabled={departmentId == null}
                    >
                      <FormControl>
                        <SelectTrigger>
                          <SelectValue placeholder={departmentId == null ? 'اختر القسم أولاً' : 'اختر الفريق'} />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        {(teams ?? []).map((team) => (
                          <SelectItem key={team.id} value={String(team.id)}>
                            {team.name}
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
                name="position_id"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>المسمى الوظيفي (اختياري)</FormLabel>
                    <Select
                      value={field.value != null ? String(field.value) : undefined}
                      onValueChange={(v) => field.onChange(v ? Number(v) : null)}
                      disabled={departmentId == null}
                    >
                      <FormControl>
                        <SelectTrigger>
                          <SelectValue placeholder={departmentId == null ? 'اختر القسم أولاً' : 'اختر المسمى'} />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        {(positions ?? []).map((position) => (
                          <SelectItem key={position.id} value={String(position.id)}>
                            {position.title}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            <FormField
              control={form.control}
              name="work_schedule_id"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>جدول الدوام</FormLabel>
                  <Select
                    value={field.value != null ? String(field.value) : undefined}
                    onValueChange={(v) => field.onChange(v ? Number(v) : null)}
                  >
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="اختر جدول الدوام" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {(schedules ?? []).map((schedule) => (
                        <SelectItem key={schedule.id} value={String(schedule.id)}>
                          {schedule.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <p className="text-xs text-muted">
                    مطلوب لحساب الحضور والملخص الشهري. إذا لم يكن مضبوطاً، لن تظهر تقارير الملخص.
                  </p>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="employment_type"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>نوع التوظيف</FormLabel>
                  <FormControl>
                    <RadioGroup
                      value={field.value}
                      onValueChange={field.onChange}
                      className="grid grid-cols-2 gap-2 sm:grid-cols-4"
                    >
                      {Object.entries(EMPLOYMENT_TYPE_LABELS).map(([value, label]) => (
                        <Label
                          key={value}
                          htmlFor={`employment_type_${value}`}
                          className={cn(
                            'flex cursor-pointer items-center gap-2 rounded-lg border border-hairline px-3 py-2 text-sm font-normal transition-colors',
                            field.value === value && 'border-brand bg-brand-soft font-medium text-brand-ink'
                          )}
                        >
                          <RadioGroupItem value={value} id={`employment_type_${value}`} />
                          {label}
                        </Label>
                      ))}
                    </RadioGroup>
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <FormField
                control={form.control}
                name="joining_date"
                render={({ field }) => (
                  <FormItem className="flex flex-col">
                    <FormLabel>تاريخ الالتحاق</FormLabel>
                    <Popover>
                      <PopoverTrigger asChild>
                        <FormControl>
                          <Button
                            type="button"
                            variant="outline"
                            className={cn(
                              'justify-start text-right font-normal',
                              !field.value && 'text-muted-foreground'
                            )}
                          >
                            <CalendarIcon className="h-4 w-4 opacity-60" />
                            {field.value
                              ? format(field.value, 'd MMMM yyyy', { locale: arSA })
                              : 'اختر التاريخ'}
                          </Button>
                        </FormControl>
                      </PopoverTrigger>
                      <PopoverContent className="w-auto p-0" align="start">
                        <Calendar
                          mode="single"
                          selected={field.value}
                          defaultMonth={field.value}
                          onSelect={(date) => date && field.onChange(date)}
                        />
                      </PopoverContent>
                    </Popover>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="gender"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>الجنس (اختياري)</FormLabel>
                    <FormControl>
                      <RadioGroup
                        value={field.value ?? undefined}
                        onValueChange={field.onChange}
                        className="grid grid-cols-2 gap-2"
                      >
                        {Object.entries(GENDER_LABELS).map(([value, label]) => (
                          <Label
                            key={value}
                            htmlFor={`gender_${value}`}
                            className={cn(
                              'flex cursor-pointer items-center gap-2 rounded-lg border border-hairline px-3 py-2 text-sm font-normal transition-colors',
                              field.value === value && 'border-brand bg-brand-soft font-medium text-brand-ink'
                            )}
                          >
                            <RadioGroupItem value={value} id={`gender_${value}`} />
                            {label}
                          </Label>
                        ))}
                      </RadioGroup>
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            <div className="flex flex-col gap-2">
              <Label>المدير المباشر (اختياري)</Label>
              <EmployeePicker
                value={directManager}
                onChange={setDirectManager}
                excludeId={employee?.id}
                placeholder="اختر المدير المباشر"
              />
            </div>

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
                {isEdit ? 'حفظ التغييرات' : 'إضافة الموظف'}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
