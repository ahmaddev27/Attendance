'use client';

import * as React from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { format, parseISO } from 'date-fns';
import { arSA } from 'date-fns/locale';
import { CalendarIcon, FileText, ImageIcon, Trash2, Upload } from 'lucide-react';
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
import { EmployeeAvatar } from '@/components/employees/employee-avatar';
import { EmployeePicker } from '@/components/employees/employee-picker';
import { departmentsApi } from '@/lib/api/endpoints/departments';
import { employeesApi } from '@/lib/api/endpoints/employees';
import { positionsApi } from '@/lib/api/endpoints/positions';
import { schedulesApi } from '@/lib/api/endpoints/schedules';
import { teamsApi } from '@/lib/api/endpoints/teams';
import { useScopedCompanyId } from '@/lib/stores/company-scope-store';
import type { Department, Employee, EmployeeInput, EmployeeMini, Team } from '@/lib/api/types';
import { EMPLOYMENT_TYPE_LABELS, GENDER_LABELS } from '@/lib/constants/employee-options';
import { cn } from '@/lib/utils';

// Server-side file-size caps mirrored from UploadNationalIdImageRequest /
// UploadEmploymentContractRequest. Checked client-side so the user sees
// the limit before the upload round-trips with a 422.
const MAX_ID_IMAGE_BYTES = 5 * 1024 * 1024;
const MAX_CONTRACT_BYTES = 10 * 1024 * 1024;
// Mirrors UploadEmployeeAvatarRequest (2 MB, raster only).
const MAX_AVATAR_BYTES = 2 * 1024 * 1024;
const AVATAR_ACCEPT = 'image/jpeg,image/png,image/webp';

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
    // Same rule as the API (StoreEmployeeRequest::PHONE_PATTERN); the SMS
    // service adds the country code, so local numbers are fine.
    phone: z
      .string()
      .trim()
      .max(20, 'رقم الهاتف طويل جداً')
      .regex(/^\+?[\d\s\-().]{7,20}$/, 'رقم الهاتف غير صالح. اكتبه مثل 0599123456 أو +970599123456')
      .optional()
      .or(z.literal('')),
    department_id: z.number().nullable(),
    team_id: z.number().nullable(),
    position_id: z.number().nullable(),
    work_schedule_id: z.number({ required_error: 'جدول الدوام مطلوب' }).nullable(),
    employment_type: z.enum(['full_time', 'part_time', 'contractor', 'intern'], {
      required_error: 'نوع التوظيف مطلوب',
    }),
    joining_date: z.date({ required_error: 'تاريخ الالتحاق مطلوب' }),
    birth_date: z.date().nullable(),
    gender: z.enum(['male', 'female']).nullable(),
    national_id: z
      .string()
      .trim()
      .max(50, 'الرقم الوطني طويل جداً')
      .optional()
      .or(z.literal('')),
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
      birth_date: null,
      gender: null,
      national_id: '',
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
    birth_date: employee.birth_date ? parseISO(employee.birth_date) : null,
    gender: employee.gender,
    national_id: employee.national_id ?? '',
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
  // Soft Company Scoping — the admin header switcher writes to this store.
  // In CREATE mode we default the new employee's company_id to the active
  // scope and narrow the department / team dropdowns to that company so a
  // طاقات-scoped admin can't accidentally seed an employee under test.
  // In EDIT mode we leave the picker lists wide open so the employee's
  // existing (potentially different-company) department stays selectable.
  const scopedCompanyId = useScopedCompanyId();
  // Held as EmployeeMini because the initial seed from the Employee resource
  // only ships {id, full_name}; the picker itself also only reads those two
  // fields off the current value. A fresh selection from the picker is a
  // full EmployeeSummary (assignable to EmployeeMini).
  const [directManager, setDirectManager] = React.useState<EmployeeMini | null>(
    employee?.direct_manager ?? null
  );

  // Avatar is staged here and only sent on Save (edit mode), so cancelling
  // the dialog never changes the stored photo. `removeAvatar` marks an
  // existing photo for deletion.
  const [avatarFile, setAvatarFile] = React.useState<File | null>(null);
  const [removeAvatar, setRemoveAvatar] = React.useState(false);

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
      setAvatarFile(null);
      setRemoveAvatar(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, employee?.id]);

  const departmentId = form.watch('department_id');
  const teamId = form.watch('team_id');

  // Picker lists are narrowed to the active company only in create mode.
  // Edit mode keeps the full list so the existing selection never silently
  // falls out of the dropdown when it belongs to a different company.
  const pickerCompanyFilter = isEdit ? undefined : scopedCompanyId ?? undefined;

  const { data: departments } = useQuery({
    queryKey: ['departments', 'picker', { company_id: pickerCompanyFilter ?? null }],
    queryFn: async () =>
      (
        await departmentsApi.list({
          per_page: 100,
          is_active: true,
          company_id: pickerCompanyFilter,
        })
      ).data.data,
    enabled: open,
  });

  // Fetch ALL departments once (unfiltered) to resolve a team -> company
  // for the cross-company warning below. Small payload (≤200), already a
  // common fetch elsewhere, and the cross-company check wouldn't work with
  // only the scoped list (the chosen team's dept wouldn't be there).
  const { data: allDepartments } = useQuery({
    queryKey: ['departments', 'picker', 'all'],
    queryFn: async () => (await departmentsApi.list({ per_page: 200 })).data.data,
    enabled: open && scopedCompanyId !== null,
  });

  const { data: teams } = useQuery({
    queryKey: ['teams', 'by-department', departmentId],
    queryFn: async () =>
      (await teamsApi.list({ department_id: departmentId ?? undefined, per_page: 100, is_active: true })).data
        .data,
    enabled: open && departmentId != null,
  });

  // Cross-company warning (not a block — the owner may deliberately override
  // the scope, e.g. a super-admin moving an employee between companies).
  // Fires when the user picks a team/department whose company differs from
  // the active scope.
  const crossCompanyWarning = React.useMemo((): string | null => {
    if (scopedCompanyId === null || !allDepartments) return null;

    const resolveDeptCompany = (deptId: number | null): number | null => {
      if (deptId === null) return null;
      const dept = allDepartments.find((d: Department) => d.id === deptId);
      return dept?.company_id ?? null;
    };

    // Team wins when both are set — team.department.company is what the
    // server derives company_id from (see EmployeeService::syncCompanyIdFromTeam).
    if (teamId != null) {
      const team = (teams ?? []).find((t: Team) => t.id === teamId);
      const teamCompanyId = team ? resolveDeptCompany(team.department_id) : null;
      if (teamCompanyId !== null && teamCompanyId !== scopedCompanyId) {
        return 'الفريق المختار يتبع شركة مختلفة عن الشركة المفعّلة في المحوّل. سيُنسب الموظف للشركة التابعة للفريق.';
      }
    } else if (departmentId != null) {
      const deptCompanyId = resolveDeptCompany(departmentId);
      if (deptCompanyId !== null && deptCompanyId !== scopedCompanyId) {
        return 'القسم المختار يتبع شركة مختلفة عن الشركة المفعّلة في المحوّل. سيُنسب الموظف للشركة التابعة للقسم.';
      }
    }

    return null;
  }, [scopedCompanyId, allDepartments, teams, teamId, departmentId]);

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
        birth_date: values.birth_date ? format(values.birth_date, 'yyyy-MM-dd') : null,
        gender: values.gender,
        national_id: values.national_id ? values.national_id.trim() : null,
        direct_manager_id: directManager?.id ?? null,
      };
      // Soft Company Scoping: on create, pin the new employee to the active
      // scope. Service-side the server will still re-derive from team when
      // present, but passing it here guarantees a correct bucket in the rare
      // case the admin picks neither a team nor a dept (and matches the
      // owner's "I switched companies" mental model). Deliberately NOT sent
      // on edit — the server keeps the stored value and may re-derive from a
      // changed team on its own.
      if (!isEdit && scopedCompanyId !== null) {
        payload.company_id = scopedCompanyId;
      }
      if (!isEdit) return employeesApi.create(payload);

      const updated = await employeesApi.update(employee.id, payload);
      // Separate endpoint keyed by employee id, so it runs after the
      // profile update succeeds.
      if (avatarFile) {
        await employeesApi.uploadAvatar(employee.id, avatarFile);
      } else if (removeAvatar && employee.avatar_url) {
        await employeesApi.deleteAvatar(employee.id);
      }
      return updated;
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
            ? `أُرسلت بيانات الدخول بـ SMS (تابع وصولها من الإعدادات ← آخر رسائل SMS). كلمة السر: ${password}`
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
            {crossCompanyWarning && (
              <div
                role="alert"
                className="rounded-lg border border-warn-soft bg-warn-soft/40 p-3 text-xs text-warn-ink"
              >
                {crossCompanyWarning}
              </div>
            )}
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
                      <Input dir="ltr" inputMode="tel" placeholder="0599123456" className="num text-right" {...field} />
                    </FormControl>
                    <p className="text-[11px] text-muted">
                      رقم محلي أو دولي — تُضاف مقدمة الدولة تلقائياً عند إرسال SMS.
                    </p>
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

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <FormField
                control={form.control}
                name="birth_date"
                render={({ field }) => (
                  <FormItem className="flex flex-col">
                    <FormLabel>تاريخ الميلاد (اختياري)</FormLabel>
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
                          selected={field.value ?? undefined}
                          defaultMonth={field.value ?? undefined}
                          onSelect={(date) => field.onChange(date ?? null)}
                        />
                      </PopoverContent>
                    </Popover>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="national_id"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>الرقم الوطني (اختياري)</FormLabel>
                    <FormControl>
                      <Input
                        dir="ltr"
                        inputMode="numeric"
                        className="num text-right"
                        placeholder="مثال: 199912345"
                        {...field}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            {/* The two uploads are per-employee endpoints — only available in
                edit mode. On create, the admin saves first then re-opens the
                dialog to attach files. */}
            {isEdit && employee && (
              <AvatarField
                fullName={employee.full_name}
                currentUrl={removeAvatar ? null : employee.avatar_url}
                file={avatarFile}
                onFileChange={(file) => {
                  setAvatarFile(file);
                  if (file) setRemoveAvatar(false);
                }}
                onRemove={() => {
                  setAvatarFile(null);
                  setRemoveAvatar(true);
                }}
              />
            )}
            {isEdit && employee && (
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <EmployeeFileField
                  employeeId={employee.id}
                  label="صورة الهوية (اختياري)"
                  hint="JPG / PNG / PDF — حتى 5 ميغابايت"
                  accept="image/jpeg,image/png,image/webp,application/pdf"
                  maxBytes={MAX_ID_IMAGE_BYTES}
                  initialUrl={employee.national_id_image_url}
                  hasFile={employee.has_national_id_image}
                  kind="national_id"
                />
                <EmployeeFileField
                  employeeId={employee.id}
                  label="عقد التوظيف (اختياري)"
                  hint="PDF أو صورة — حتى 10 ميغابايت"
                  accept="application/pdf,image/jpeg,image/png,image/webp"
                  maxBytes={MAX_CONTRACT_BYTES}
                  initialUrl={employee.employment_contract_url}
                  hasFile={employee.has_employment_contract}
                  kind="contract"
                />
              </div>
            )}

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

/**
 * One file slot per employee: upload, preview (image → thumbnail, PDF →
 * "عرض الملف" link), replace, or remove. Each slot talks to its own
 * endpoint, so the parent form doesn't have to track a per-file state
 * machine or send paths back on Save — the server persists the column in
 * the same request that writes the file.
 */
function EmployeeFileField({
  employeeId,
  label,
  hint,
  accept,
  maxBytes,
  initialUrl,
  hasFile: initialHasFile,
  kind,
}: {
  employeeId: number;
  label: string;
  hint: string;
  accept: string;
  maxBytes: number;
  initialUrl: string | null;
  hasFile: boolean;
  kind: 'national_id' | 'contract';
}) {
  const queryClient = useQueryClient();
  const inputRef = React.useRef<HTMLInputElement | null>(null);
  // Local mirror of server state — flipped on a successful upload/delete so
  // the UI reflects the new state without waiting for the parent list
  // query to refetch. The signed URL has a 30-min TTL; we don't bother
  // refreshing it mid-dialog, the next list refetch brings a fresh one.
  const [hasFile, setHasFile] = React.useState(initialHasFile);
  const [currentUrl, setCurrentUrl] = React.useState<string | null>(initialUrl);
  const [uploading, setUploading] = React.useState(false);

  React.useEffect(() => {
    setHasFile(initialHasFile);
    setCurrentUrl(initialUrl);
  }, [employeeId, initialHasFile, initialUrl]);

  const invalidateEmployeeQueries = () => {
    queryClient.invalidateQueries({ queryKey: ['employees'] });
  };

  const onPickFile = (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0] ?? null;
    if (!file) return;

    if (file.size > maxBytes) {
      toast.error('حجم الملف يتجاوز الحد المسموح به');
      if (inputRef.current) inputRef.current.value = '';
      return;
    }

    setUploading(true);
    const uploader =
      kind === 'national_id'
        ? employeesApi.uploadNationalIdImage(employeeId, file)
        : employeesApi.uploadEmploymentContract(employeeId, file);

    uploader
      .then(() => {
        toast.success('تم رفع الملف بنجاح');
        setHasFile(true);
        // Server returns the raw path, not a signed URL — invalidate so the
        // list refetch brings back the fresh signed URL for preview.
        setCurrentUrl(null);
        invalidateEmployeeQueries();
      })
      .catch((err: unknown) => {
        const message =
          (err as { response?: { data?: { message?: string } } })?.response?.data?.message ||
          'تعذر رفع الملف';
        toast.error(message);
      })
      .finally(() => {
        setUploading(false);
        if (inputRef.current) inputRef.current.value = '';
      });
  };

  const onRemove = () => {
    setUploading(true);
    const remover =
      kind === 'national_id'
        ? employeesApi.deleteNationalIdImage(employeeId)
        : employeesApi.deleteEmploymentContract(employeeId);

    remover
      .then(() => {
        toast.success('تم حذف الملف');
        setHasFile(false);
        setCurrentUrl(null);
        invalidateEmployeeQueries();
      })
      .catch(() => toast.error('تعذر حذف الملف'))
      .finally(() => setUploading(false));
  };

  // Guess "is this a renderable image preview?" from the URL extension —
  // the signed URL is to a storage key whose extension we preserved on
  // upload. PDFs and other blobs fall back to a plain "open" link.
  const isImagePreview = React.useMemo(() => {
    if (!currentUrl) return false;
    const lower = currentUrl.toLowerCase();
    return ['.jpg', '.jpeg', '.png', '.webp'].some((ext) => lower.includes(ext));
  }, [currentUrl]);

  return (
    <div className="flex flex-col gap-2">
      <Label>{label}</Label>
      <div className="rounded-lg border border-hairline bg-surface-muted p-3">
        {hasFile ? (
          <div className="flex items-center gap-3">
            {isImagePreview && currentUrl ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img
                src={currentUrl}
                alt={label}
                className="h-16 w-16 rounded-md border border-hairline object-cover"
              />
            ) : (
              <div className="flex h-16 w-16 items-center justify-center rounded-md border border-hairline bg-surface text-ink-2">
                {kind === 'national_id' ? (
                  <ImageIcon className="h-6 w-6" />
                ) : (
                  <FileText className="h-6 w-6" />
                )}
              </div>
            )}
            <div className="flex flex-1 flex-col gap-1 text-xs">
              <span className="font-semibold text-success">● الملف مرفوع</span>
              {currentUrl && (
                <a
                  href={currentUrl}
                  target="_blank"
                  rel="noreferrer"
                  className="text-brand underline"
                >
                  عرض الملف
                </a>
              )}
            </div>
            <div className="flex items-center gap-1">
              <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={uploading}
                onClick={() => inputRef.current?.click()}
                title="استبدال الملف"
              >
                <Upload className="h-3.5 w-3.5" />
                استبدال
              </Button>
              <Button
                type="button"
                variant="ghost"
                size="icon"
                disabled={uploading}
                onClick={onRemove}
                title="حذف الملف"
                className="text-danger hover:bg-danger-soft hover:text-danger"
              >
                <Trash2 className="h-4 w-4" />
              </Button>
            </div>
          </div>
        ) : (
          <div className="flex items-center justify-between gap-3">
            <div className="flex flex-col gap-1 text-xs text-muted">
              <span>لا يوجد ملف مرفوع</span>
              <span>{hint}</span>
            </div>
            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={uploading}
              onClick={() => inputRef.current?.click()}
            >
              {uploading && <Spinner className="h-3 w-3" />}
              <Upload className="h-3.5 w-3.5" />
              رفع
            </Button>
          </div>
        )}
      </div>
      <input
        ref={inputRef}
        type="file"
        accept={accept}
        className="hidden"
        onChange={onPickFile}
      />
    </div>
  );
}

/**
 * Drag/drop + picker for the employee photo. Purely controlled: it only
 * stages a File and previews it; the dialog uploads on Save.
 */
function AvatarField({
  fullName,
  currentUrl,
  file,
  onFileChange,
  onRemove,
}: {
  fullName: string;
  currentUrl: string | null;
  file: File | null;
  onFileChange: (file: File | null) => void;
  onRemove: () => void;
}) {
  const inputRef = React.useRef<HTMLInputElement | null>(null);
  const [dragging, setDragging] = React.useState(false);
  const [previewUrl, setPreviewUrl] = React.useState<string | null>(null);

  React.useEffect(() => {
    if (!file) {
      setPreviewUrl(null);
      return;
    }
    const url = URL.createObjectURL(file);
    setPreviewUrl(url);
    return () => URL.revokeObjectURL(url);
  }, [file]);

  const accept = (candidate: File | undefined) => {
    if (!candidate) return;
    if (!AVATAR_ACCEPT.split(',').includes(candidate.type)) {
      toast.error('الصيغ المسموحة: JPG أو PNG أو WebP');
      return;
    }
    if (candidate.size > MAX_AVATAR_BYTES) {
      toast.error('حجم الصورة يتجاوز 2 ميغابايت');
      return;
    }
    onFileChange(candidate);
  };

  const shownUrl = previewUrl ?? currentUrl;

  return (
    <div className="flex flex-col gap-2">
      <Label>الصورة الشخصية (اختياري)</Label>
      <div
        onDragOver={(e) => {
          e.preventDefault();
          setDragging(true);
        }}
        onDragLeave={() => setDragging(false)}
        onDrop={(e) => {
          e.preventDefault();
          setDragging(false);
          accept(e.dataTransfer.files?.[0]);
        }}
        className={cn(
          'flex items-center gap-3 rounded-lg border border-dashed border-hairline bg-surface-muted p-3',
          dragging && 'border-brand bg-brand-soft',
        )}
      >
        <EmployeeAvatar employee={{ full_name: fullName, avatar_url: shownUrl }} size={64} />
        <div className="flex flex-1 flex-col gap-1 text-xs text-muted">
          <span>اسحب الصورة هنا أو اختر ملفاً</span>
          <span>JPG / PNG / WebP — حتى 2 ميغابايت، تُحفظ عند الضغط على حفظ</span>
        </div>
        <Button type="button" variant="outline" size="sm" onClick={() => inputRef.current?.click()}>
          <Upload className="h-3.5 w-3.5" />
          اختيار
        </Button>
        {shownUrl && (
          <Button
            type="button"
            variant="ghost"
            size="icon"
            onClick={() => (file ? onFileChange(null) : onRemove())}
            title="إزالة الصورة"
            className="text-danger hover:bg-danger-soft hover:text-danger"
          >
            <Trash2 className="h-4 w-4" />
          </Button>
        )}
      </div>
      <input
        ref={inputRef}
        type="file"
        accept={AVATAR_ACCEPT}
        className="hidden"
        onChange={(e) => {
          accept(e.target.files?.[0]);
          e.target.value = '';
        }}
      />
    </div>
  );
}
