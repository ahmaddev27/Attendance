'use client';

import * as React from 'react';
import { useMutation } from '@tanstack/react-query';
import { Copy, QrCode, RefreshCcw } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { scanPinsApi } from '@/lib/api/endpoints/attendance';
import type { Employee, ScanPinResetResult } from '@/lib/api/types';
import { cn } from '@/lib/utils';

type ResetScanPinDialogProps = {
  employee: Employee | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
};

/**
 * The reset response is the only place a plaintext PIN exists outside the
 * employee's SMS, so it lives in state just long enough to be shown once
 * and is dropped as soon as the dialog closes.
 */
export function ResetScanPinDialog({ employee, open, onOpenChange }: ResetScanPinDialogProps) {
  const [result, setResult] = React.useState<ScanPinResetResult | null>(null);

  React.useEffect(() => {
    if (!open) {
      setResult(null);
    }
  }, [open]);

  const mutation = useMutation({
    mutationFn: (employeeId: number) => scanPinsApi.reset(employeeId),
    onSuccess: (data) => {
      setResult(data);
      toast.success('تم تعيين رمز حضور جديد');
    },
    onError: (err: unknown) => {
      const message =
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message
        ?? 'تعذّر إعادة تعيين رمز الحضور';
      toast.error(message);
    },
  });

  const copyPin = async () => {
    if (!result) return;
    try {
      await navigator.clipboard.writeText(result.pin);
      toast.success('تم النسخ');
    } catch {
      toast.error('تعذّر النسخ — انسخ يدوياً');
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2 text-start">
            <QrCode className="h-4 w-4 text-brand" />
            إعادة تعيين رمز الحضور
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
              سيتم توليد رمز حضور جديد من 4 أرقام وإرساله للموظف عبر SMS، ويتوقف رمزه الحالي عن العمل فوراً.
              هل تريد المتابعة؟
            </p>
          </div>
        )}

        {result && (
          <div className="space-y-3">
            <p
              className={cn(
                'rounded-lg p-3 text-xs',
                result.sms_queued ? 'bg-success-soft text-success' : 'border border-amber-200 bg-amber-50 text-amber-900',
              )}
            >
              {result.sms_queued
                ? 'تم إرسال الرمز الجديد إلى جوال الموظف برسالة SMS.'
                : 'لم تُرسل رسالة SMS (لا يوجد رقم جوال أو تعذّر الإرسال) — سلّم الرمز للموظف بنفسك.'}
            </p>
            <div className="flex items-center gap-2">
              <div
                className="num flex-1 rounded-lg border border-hairline bg-ground px-3 py-3 text-center font-mono text-3xl font-bold tracking-[0.5em] text-ink"
                dir="ltr"
              >
                {result.pin}
              </div>
              <Button type="button" variant="outline" size="icon" onClick={copyPin} aria-label="نسخ الرمز">
                <Copy className="h-4 w-4" />
              </Button>
            </div>
            <p className="text-xs font-medium text-danger">
              لن يظهر هذا الرمز مرة أخرى بعد إغلاق النافذة.
            </p>
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
                onClick={() => employee && mutation.mutate(employee.id)}
                disabled={mutation.isPending || !employee}
                className="gap-2"
              >
                {mutation.isPending ? (
                  <RefreshCcw className="h-3.5 w-3.5 animate-spin" />
                ) : (
                  <QrCode className="h-3.5 w-3.5" />
                )}
                تأكيد إعادة التعيين
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
