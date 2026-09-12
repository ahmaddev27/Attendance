'use client';

import * as React from 'react';
import { useMutation } from '@tanstack/react-query';
import { Copy, KeyRound, RefreshCcw } from 'lucide-react';
import { toast } from 'sonner';

import { apiClient } from '@/lib/api/client';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import type { Employee } from '@/lib/api/types';

type ResetResponse = {
  data: {
    employee_id: number;
    employee_number: number;
    identifier: string;
    password: string;
    user_created: boolean;
  };
};

type Props = {
  employee: Employee | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
};

/**
 * Auto-generate flow only. The backend always mints a random 12-character
 * password and SMS-es it to the employee; there is no admin-supplied
 * password path (EmployeeController::resetPassword ignored it, which made
 * the previous "manual" tab a lie — the admin's typed value was silently
 * discarded and a random one was surfaced instead).
 *
 * The plaintext is shown here once as a fallback for when the SMS didn't
 * reach the employee. If the admin closes the dialog before copying, they
 * have to reset again — no plaintext is ever stored on the server after
 * hashing.
 */
export function ResetPasswordDialog({ employee, open, onOpenChange }: Props) {
  const [result, setResult] = React.useState<ResetResponse['data'] | null>(null);

  // Reset local state whenever the dialog is opened for a new employee.
  React.useEffect(() => {
    if (open) {
      setResult(null);
    }
  }, [open, employee?.id]);

  const mutation = useMutation({
    mutationFn: async () => {
      const { data } = await apiClient.post<ResetResponse>(
        `/employees/${employee!.id}/reset-password`,
        {},
      );
      return data.data;
    },
    onSuccess: (data) => {
      setResult(data);
      toast.success(
        data.user_created
          ? 'تم إنشاء الحساب وتعيين كلمة السر'
          : 'تم تحديث كلمة السر',
      );
    },
    onError: (err: unknown) => {
      const msg =
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message
        ?? 'تعذر تحديث كلمة السر';
      toast.error(msg);
    },
  });

  const copyPassword = async () => {
    if (!result) return;
    try {
      await navigator.clipboard.writeText(result.password);
      toast.success('تم النسخ');
    } catch {
      toast.error('تعذر النسخ — انسخ يدوياً');
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2 text-start">
            <KeyRound className="h-4 w-4 text-brand" />
            إعادة تعيين كلمة السر
          </DialogTitle>
        </DialogHeader>

        {employee && !result && (
          <div className="space-y-4">
            <p className="text-sm text-ink-2">
              الموظف: <span className="font-semibold text-ink">{employee.full_name}</span>
              {' · '}
              <span className="num" dir="ltr">#{employee.employee_number}</span>
            </p>

            <p className="rounded-lg bg-brand-soft/50 p-3 text-xs text-brand-ink">
              سيتم توليد كلمة سر جديدة تلقائياً وإرسالها للموظف عبر SMS.
              ستظهر مرة واحدة فقط بعد الحفظ لتتمكن من نسخها إن لزم الأمر.
            </p>
          </div>
        )}

        {/* Success state — show plaintext once */}
        {result && (
          <div className="space-y-3">
            <p className="rounded-lg bg-success-soft p-3 text-xs text-success">
              {result.user_created
                ? 'تم إنشاء حساب دخول جديد للموظف.'
                : 'تم تحديث كلمة السر بنجاح.'}
              <br />
              انسخ البيانات الآن — لن تظهر كلمة السر مرة أخرى.
            </p>
            <div className="space-y-2">
              <div>
                <Label className="text-[11px] text-muted">اسم الدخول</Label>
                <div className="mt-1 rounded-lg border border-hairline bg-ground px-3 py-2 font-mono text-sm" dir="ltr">
                  {result.identifier}
                </div>
              </div>
              <div>
                <Label className="text-[11px] text-muted">كلمة السر</Label>
                <div className="mt-1 flex items-center gap-2">
                  <div className="flex-1 rounded-lg border border-hairline bg-ground px-3 py-2 font-mono text-sm" dir="ltr">
                    {result.password}
                  </div>
                  <Button type="button" variant="outline" size="icon" onClick={copyPassword}>
                    <Copy className="h-4 w-4" />
                  </Button>
                </div>
              </div>
            </div>
          </div>
        )}

        <DialogFooter className="mt-2">
          {!result && (
            <>
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                إلغاء
              </Button>
              <Button
                type="button"
                onClick={() => mutation.mutate()}
                disabled={mutation.isPending || !employee}
                className="gap-2"
              >
                {mutation.isPending ? (
                  <RefreshCcw className="h-3.5 w-3.5 animate-spin" />
                ) : (
                  <KeyRound className="h-3.5 w-3.5" />
                )}
                تعيين
              </Button>
            </>
          )}
          {result && (
            <Button type="button" onClick={() => onOpenChange(false)}>
              إغلاق
            </Button>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
