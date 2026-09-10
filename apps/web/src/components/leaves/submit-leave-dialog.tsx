'use client';

import * as React from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Paperclip } from 'lucide-react';

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
import { Textarea } from '@/components/ui/textarea';
import { LeaveTypeBadge } from '@/components/leaves/leave-type-badge';
import { leaveTypesApi } from '@/lib/api/endpoints/leave-types';
import { myLeavesApi } from '@/lib/api/endpoints/leaves';
import { estimateWorkingDays } from '@/lib/leave-format';
import type { LeaveType } from '@/lib/api/types';

const submitLeaveSchema = z
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

type SubmitLeaveFormValues = z.infer<typeof submitLeaveSchema>;

const EMPTY_VALUES: SubmitLeaveFormValues = {
  leave_type_id: 0,
  start_date: '',
  end_date: '',
  reason: '',
};

type SubmitLeaveDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
};

/**
 * Employee self-service "request a leave" dialog. Type options come from the
 * employee's own balances query (per spec) rather than the full leave-types
 * list, so only types the employee actually has an entitlement for — with
 * their live available-day count — are offered.
 */
export function SubmitLeaveDialog({ open, onOpenChange }: SubmitLeaveDialogProps) {
  const queryClient = useQueryClient();
  const [apiError, setApiError] = React.useState<string | null>(null);

  const form = useForm<SubmitLeaveFormValues>({
    resolver: zodResolver(submitLeaveSchema),
    defaultValues: EMPTY_VALUES,
  });

  React.useEffect(() => {
    if (open) {
      form.reset(EMPTY_VALUES);
      setApiError(null);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open]);

  const { data: balances } = useQuery({
    queryKey: ['my-leave-balances'],
    queryFn: async () => (await myLeavesApi.balances()).data.data,
    enabled: open,
    staleTime: 30_000,
  });

  // Non-balance-based leave types (unpaid leave, etc.) never appear in the
  // employee's /me/balances response — `LeaveBalanceService::accrueForYear`
  // only seeds rows for balance-based types. Fetch the full catalog and
  // merge the leftover, active non-balance types below so the picker
  // exposes every type the employee is actually allowed to request.
  const { data: leaveTypes } = useQuery({
    queryKey: ['leave-types'],
    queryFn: async () => (await leaveTypesApi.list()).data.data,
    enabled: open,
  });

  const leaveTypeId = form.watch('leave_type_id');
  const startDate = form.watch('start_date');
  const endDate = form.watch('end_date');
  const estimatedDays = estimateWorkingDays(startDate, endDate);

  const selectedBalance = (balances ?? []).find((balance) => balance.leave_type_id === leaveTypeId);
  const nonBalanceTypes: LeaveType[] = React.useMemo(() => {
    const balanceTypeIds = new Set((balances ?? []).map((b) => b.leave_type_id));
    return (leaveTypes ?? []).filter(
      (type) => type.is_active && !type.is_balance_based && !balanceTypeIds.has(type.id),
    );
  }, [balances, leaveTypes]);
  const selectedNonBalanceType = nonBalanceTypes.find((type) => type.id === leaveTypeId);
  const requiresAttachment =
    (selectedBalance?.leave_type?.requires_attachment ?? selectedNonBalanceType?.requires_attachment) ?? false;

  const mutation = useMutation({
    mutationFn: (values: SubmitLeaveFormValues) =>
      myLeavesApi.submit({
        leave_type_id: values.leave_type_id,
        start_date: values.start_date,
        end_date: values.end_date,
        reason: values.reason || undefined,
      }),
    onSuccess: () => {
      toast.success('تم إرسال طلب الإجازة بنجاح');
      queryClient.invalidateQueries({ queryKey: ['my-leaves'] });
      queryClient.invalidateQueries({ queryKey: ['my-leave-balances'] });
      onOpenChange(false);
    },
    onError: (err: unknown) => {
      // Balance-insufficient and validation errors surface as a Laravel
      // 422 with a human-readable `message` — shown inline so it stays
      // visible next to the fields, not just as a toast that disappears.
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      setApiError(message || 'تعذر إرسال طلب الإجازة، حاول مرة أخرى');
    },
  });

  const onSubmit = form.handleSubmit((values) => {
    setApiError(null);
    mutation.mutate(values);
  });

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>طلب إجازة جديد</DialogTitle>
          <DialogDescription>عبّئ البيانات التالية لإرسال طلب إجازة للمراجعة.</DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form onSubmit={onSubmit} className="space-y-4">
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
                      {(balances ?? []).map((balance) =>
                        balance.leave_type ? (
                          <SelectItem key={`balance-${balance.leave_type_id}`} value={String(balance.leave_type_id)}>
                            <div className="flex w-full items-center justify-between gap-3">
                              <LeaveTypeBadge leaveType={balance.leave_type} />
                              <span className="num text-xs text-muted">متاح: {balance.available}</span>
                            </div>
                          </SelectItem>
                        ) : null
                      )}
                      {nonBalanceTypes.map((type) => (
                        <SelectItem key={`type-${type.id}`} value={String(type.id)}>
                          <div className="flex w-full items-center justify-between gap-3">
                            <LeaveTypeBadge leaveType={type} />
                            <span className="text-xs text-muted">غير مستند إلى رصيد</span>
                          </div>
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
              <p className="rounded-lg bg-brand-soft px-3 py-2 text-sm text-brand-ink">
                عدد الأيام: <span className="num font-semibold">{estimatedDays}</span>
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

            {requiresAttachment && (
              <div>
                <label className="flex items-center gap-2 text-sm font-medium text-ink">
                  <Paperclip className="h-4 w-4 text-muted" />
                  مرفق مطلوب لهذا النوع من الإجازة
                </label>
                {/* Actual upload wiring is out of scope for this pass — the
                    file picker is a placeholder until the API exposes an
                    attachment upload endpoint. */}
                <Input type="file" className="mt-1.5" disabled />
                <p className="mt-1 text-xs text-muted">سيتم تفعيل رفع المرفقات قريباً</p>
              </div>
            )}

            {apiError && (
              <p className="rounded-lg bg-danger-soft px-3 py-2 text-sm text-danger">{apiError}</p>
            )}

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                إلغاء
              </Button>
              <Button type="submit" disabled={mutation.isPending} className="bg-brand text-white hover:bg-brand-hover">
                {mutation.isPending && <Spinner className="text-white" />}
                إرسال الطلب
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
