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
import { leaveTypesApi } from '@/lib/api/endpoints/leave-types';
import type { LeaveType, LeaveTypePayload } from '@/lib/api/types';

const HEX_COLOR_REGEX = /^#([0-9a-fA-F]{6}|[0-9a-fA-F]{3})$/;
const DEFAULT_COLOR = '#2678c4';

const leaveTypeFormSchema = z.object({
  name: z.string().trim().min(1, 'اسم نوع الإجازة مطلوب').max(100, 'الاسم طويل جداً'),
  code: z
    .string()
    .trim()
    .min(1, 'الرمز مطلوب')
    .max(30, 'الرمز طويل جداً')
    .regex(/^[a-zA-Z0-9_-]+$/, 'الرمز يجب أن يحتوي أحرف/أرقام إنجليزية فقط'),
  is_paid: z.boolean(),
  is_balance_based: z.boolean(),
  default_annual_entitlement: z.number().min(0, 'القيمة يجب أن تكون صفر أو أكثر'),
  allow_negative_balance: z.boolean(),
  requires_attachment: z.boolean(),
  max_consecutive_days: z.number().min(1, 'القيمة يجب أن تكون أكبر من صفر').nullable(),
  min_notice_days: z.number().min(0, 'القيمة يجب أن تكون صفر أو أكثر'),
  color: z.string().regex(HEX_COLOR_REGEX, 'صيغة اللون غير صحيحة (مثال: #2678C4)'),
  is_active: z.boolean(),
  sort_order: z.number().min(0, 'القيمة يجب أن تكون صفر أو أكثر'),
});

type LeaveTypeFormValues = z.infer<typeof leaveTypeFormSchema>;

function buildDefaultValues(leaveType?: LeaveType | null): LeaveTypeFormValues {
  if (!leaveType) {
    return {
      name: '',
      code: '',
      is_paid: true,
      is_balance_based: true,
      default_annual_entitlement: 0,
      allow_negative_balance: false,
      requires_attachment: false,
      max_consecutive_days: null,
      min_notice_days: 0,
      color: DEFAULT_COLOR,
      is_active: true,
      sort_order: 0,
    };
  }
  return {
    name: leaveType.name,
    code: leaveType.code,
    is_paid: leaveType.is_paid,
    is_balance_based: leaveType.is_balance_based,
    default_annual_entitlement: leaveType.default_annual_entitlement,
    allow_negative_balance: leaveType.allow_negative_balance,
    requires_attachment: leaveType.requires_attachment,
    max_consecutive_days: leaveType.max_consecutive_days,
    min_notice_days: leaveType.min_notice_days,
    color: leaveType.color,
    is_active: leaveType.is_active,
    sort_order: leaveType.sort_order,
  };
}

type LeaveTypeFormDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  leaveType?: LeaveType | null;
};

export function LeaveTypeFormDialog({ open, onOpenChange, leaveType }: LeaveTypeFormDialogProps) {
  const isEdit = !!leaveType;
  const queryClient = useQueryClient();

  const form = useForm<LeaveTypeFormValues>({
    resolver: zodResolver(leaveTypeFormSchema),
    defaultValues: buildDefaultValues(leaveType),
  });

  React.useEffect(() => {
    if (open) form.reset(buildDefaultValues(leaveType));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, leaveType?.id]);

  const mutation = useMutation({
    mutationFn: (values: LeaveTypeFormValues) => {
      const payload: LeaveTypePayload = { ...values };
      return isEdit ? leaveTypesApi.update(leaveType.id, payload) : leaveTypesApi.create(payload);
    },
    onSuccess: () => {
      toast.success(isEdit ? 'تم تحديث نوع الإجازة بنجاح' : 'تمت إضافة نوع الإجازة بنجاح');
      queryClient.invalidateQueries({ queryKey: ['leave-types'] });
      onOpenChange(false);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'حدث خطأ أثناء حفظ نوع الإجازة');
    },
  });

  const onSubmit = form.handleSubmit((values) => mutation.mutate(values));

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>{isEdit ? 'تعديل نوع الإجازة' : 'إضافة نوع إجازة جديد'}</DialogTitle>
          <DialogDescription>
            {isEdit ? 'قم بتحديث بيانات نوع الإجازة ثم احفظ التغييرات.' : 'أدخل بيانات نوع الإجازة الجديد.'}
          </DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form onSubmit={onSubmit} className="space-y-5">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <FormField
                control={form.control}
                name="name"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>اسم نوع الإجازة</FormLabel>
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
                      <Input dir="ltr" className="text-right" {...field} placeholder="#2678C4" />
                    </FormControl>
                  </div>
                  <FormMessage />
                </FormItem>
              )}
            />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
              <FormField
                control={form.control}
                name="default_annual_entitlement"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>الرصيد السنوي الافتراضي</FormLabel>
                    <FormControl>
                      <Input
                        type="number"
                        min={0}
                        dir="ltr"
                        className="num text-right"
                        value={field.value}
                        onChange={(e) => field.onChange(Number(e.target.value))}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="max_consecutive_days"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>أقصى عدد أيام متتالية (اختياري)</FormLabel>
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
              <FormField
                control={form.control}
                name="min_notice_days"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>الحد الأدنى للإشعار المسبق (أيام)</FormLabel>
                    <FormControl>
                      <Input
                        type="number"
                        min={0}
                        dir="ltr"
                        className="num text-right"
                        value={field.value}
                        onChange={(e) => field.onChange(Number(e.target.value))}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

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
                name="is_paid"
                render={({ field }) => (
                  <FormItem className="flex flex-row items-center justify-between rounded-lg border border-hairline p-3">
                    <FormLabel className="cursor-pointer">إجازة مدفوعة الأجر</FormLabel>
                    <FormControl>
                      <Switch checked={field.value} onCheckedChange={field.onChange} />
                    </FormControl>
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="is_balance_based"
                render={({ field }) => (
                  <FormItem className="flex flex-row items-center justify-between rounded-lg border border-hairline p-3">
                    <FormLabel className="cursor-pointer">تعتمد على رصيد</FormLabel>
                    <FormControl>
                      <Switch checked={field.value} onCheckedChange={field.onChange} />
                    </FormControl>
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="allow_negative_balance"
                render={({ field }) => (
                  <FormItem className="flex flex-row items-center justify-between rounded-lg border border-hairline p-3">
                    <FormLabel className="cursor-pointer">السماح برصيد سالب</FormLabel>
                    <FormControl>
                      <Switch checked={field.value} onCheckedChange={field.onChange} />
                    </FormControl>
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="requires_attachment"
                render={({ field }) => (
                  <FormItem className="flex flex-row items-center justify-between rounded-lg border border-hairline p-3">
                    <FormLabel className="cursor-pointer">يتطلب مرفقاً</FormLabel>
                    <FormControl>
                      <Switch checked={field.value} onCheckedChange={field.onChange} />
                    </FormControl>
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="is_active"
                render={({ field }) => (
                  <FormItem className="flex flex-row items-center justify-between rounded-lg border border-hairline p-3 sm:col-span-2">
                    <FormLabel className="cursor-pointer">نوع الإجازة نشط</FormLabel>
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
                {isEdit ? 'حفظ التغييرات' : 'إضافة النوع'}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
