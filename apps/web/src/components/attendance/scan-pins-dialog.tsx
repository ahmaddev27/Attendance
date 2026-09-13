'use client';

import * as React from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { QrCode, Send, ShieldAlert } from 'lucide-react';
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
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { scanPinsApi } from '@/lib/api/endpoints/attendance';
import type { ScanPinIssueResult, UpdateScanPinEnforcementPayload } from '@/lib/api/types';
import { cn } from '@/lib/utils';

const SUMMARY_QUERY_KEY = ['scan-pins', 'summary'] as const;

type PendingConfirmation = 'issue' | 'force-enable' | null;

type ScanPinsDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
};

function extractErrorMessage(err: unknown, fallback: string): string {
  const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
  return message || fallback;
}

/**
 * Rollout console for the kiosk attendance PIN. Both actions that touch
 * every employee — bulk issue and enforcing while some still have no PIN —
 * need a second, explicit click.
 */
export function ScanPinsDialog({ open, onOpenChange }: ScanPinsDialogProps) {
  const queryClient = useQueryClient();
  const [confirmation, setConfirmation] = React.useState<PendingConfirmation>(null);
  const [issueResult, setIssueResult] = React.useState<ScanPinIssueResult | null>(null);

  React.useEffect(() => {
    if (open) {
      setConfirmation(null);
      setIssueResult(null);
    }
  }, [open]);

  const { data: summary, isLoading, isError, refetch } = useQuery({
    queryKey: SUMMARY_QUERY_KEY,
    queryFn: scanPinsApi.summary,
    enabled: open,
  });

  const issueMutation = useMutation({
    mutationFn: scanPinsApi.issueMissing,
    onSuccess: (result) => {
      setConfirmation(null);
      setIssueResult(result);
      toast.success('تم إصدار رموز الحضور');
      queryClient.invalidateQueries({ queryKey: SUMMARY_QUERY_KEY });
    },
    onError: (err: unknown) => {
      setConfirmation(null);
      toast.error(extractErrorMessage(err, 'تعذّر إصدار رموز الحضور'));
    },
  });

  const enforcementMutation = useMutation({
    mutationFn: (payload: UpdateScanPinEnforcementPayload) => scanPinsApi.updateEnforcement(payload),
    onSuccess: (next) => {
      setConfirmation(null);
      queryClient.setQueryData(SUMMARY_QUERY_KEY, next);
      toast.success(next.required ? 'أصبح رمز الحضور مطلوباً في صفحة المسح' : 'تم إيقاف طلب رمز الحضور في صفحة المسح');
    },
    onError: (err: unknown) => {
      setConfirmation(null);
      toast.error(extractErrorMessage(err, 'تعذّر تحديث حالة رمز الحضور'));
      queryClient.invalidateQueries({ queryKey: SUMMARY_QUERY_KEY });
    },
  });

  const busy = issueMutation.isPending || enforcementMutation.isPending;

  const handleEnforcementChange = (checked: boolean) => {
    if (!summary) return;
    if (checked && summary.without_pin > 0) {
      setConfirmation('force-enable');
      return;
    }
    enforcementMutation.mutate({ required: checked });
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2 text-start">
            <QrCode className="h-4 w-4 text-brand" />
            رموز الحضور
          </DialogTitle>
          <DialogDescription className="text-start">
            رمز من 4 أرقام يُدخله الموظف مع رقمه الوظيفي في صفحة مسح QR، حتى لا يستطيع أحد تسجيل الحضور عن غيره.
          </DialogDescription>
        </DialogHeader>

        {isLoading && (
          <div className="grid grid-cols-2 gap-3">
            {Array.from({ length: 4 }).map((_, index) => (
              <Skeleton key={index} className="h-16 w-full" />
            ))}
          </div>
        )}

        {isError && (
          <p className="rounded-md border border-danger-soft bg-danger-soft/40 px-3 py-2 text-sm text-danger">
            تعذّر تحميل ملخص رموز الحضور.{' '}
            <button type="button" onClick={() => refetch()} className="font-semibold underline">
              إعادة المحاولة
            </button>
          </p>
        )}

        {summary && (
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-3">
              <SummaryStat label="الموظفون النشطون" value={summary.active_employees} />
              <SummaryStat label="لديهم رمز حضور" value={summary.with_pin} tone="success" />
              <SummaryStat
                label="بدون رمز حضور"
                value={summary.without_pin}
                tone={summary.without_pin > 0 ? 'warning' : undefined}
              />
              <SummaryStat
                label="بدون رمز ولا رقم جوال"
                value={summary.without_pin_and_phone}
                tone={summary.without_pin_and_phone > 0 ? 'danger' : undefined}
              />
            </div>

            <section className="space-y-3 rounded-lg border border-hairline p-4">
              <div>
                <p className="text-sm font-semibold text-ink">إصدار الرموز الناقصة</p>
                <p className="mt-1 text-xs text-muted">
                  يُولَّد رمز لكل موظف نشط ليس لديه رمز ويُرسل له برسالة SMS. من ليس لديه رقم جوال
                  تُعاد تهيئة رمزه يدوياً من قائمة الموظفين.
                </p>
              </div>

              {confirmation === 'issue' ? (
                <div className="space-y-3 rounded-md border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
                  <p>
                    سيتم إصدار رمز حضور لـ <span className="num font-semibold">{summary.without_pin}</span> موظف
                    وإرسال رسالة SMS لكل من لديه رقم جوال. هل تريد المتابعة؟
                  </p>
                  <div className="flex justify-end gap-2">
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      onClick={() => setConfirmation(null)}
                      disabled={busy}
                    >
                      إلغاء
                    </Button>
                    <Button
                      type="button"
                      size="sm"
                      onClick={() => issueMutation.mutate()}
                      disabled={busy}
                      className="bg-brand text-white hover:bg-brand-hover"
                    >
                      {issueMutation.isPending && <Spinner className="text-white" />}
                      تأكيد الإرسال
                    </Button>
                  </div>
                </div>
              ) : (
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => {
                    setIssueResult(null);
                    setConfirmation('issue');
                  }}
                  disabled={busy || summary.without_pin === 0}
                  className="w-full gap-2"
                >
                  <Send className="h-4 w-4" />
                  إرسال الرموز للموظفين بدون رمز
                </Button>
              )}

              {issueResult && (
                <p className="rounded-md bg-success-soft p-3 text-xs text-success">
                  تم إصدار <span className="num font-semibold">{issueResult.issued}</span> رمز، وجُدولت{' '}
                  <span className="num font-semibold">{issueResult.sms_queued}</span> رسالة SMS.
                  {issueResult.without_phone > 0 && (
                    <>
                      {' '}
                      <span className="num font-semibold">{issueResult.without_phone}</span> موظف بلا رقم جوال —
                      أعد تعيين رموزهم من قائمة الموظفين لتسليمها يدوياً.
                    </>
                  )}
                </p>
              )}
            </section>

            <section className="space-y-3 rounded-lg border border-hairline p-4">
              <div className="flex items-start justify-between gap-4">
                <div>
                  <Label htmlFor="scan-pin-enforcement" className="text-sm font-semibold text-ink">
                    طلب رمز الحضور في صفحة المسح
                  </Label>
                  <p className="mt-1 text-xs text-muted">
                    عند التفعيل لا يُقبل التسجيل من صفحة المسح إلا بالرقم الوظيفي مع رمز الحضور. تطبيق
                    الجوال لا يتأثر.
                  </p>
                </div>
                <Switch
                  id="scan-pin-enforcement"
                  checked={summary.required}
                  onCheckedChange={handleEnforcementChange}
                  disabled={busy || confirmation === 'force-enable'}
                />
              </div>

              {confirmation === 'force-enable' && (
                <div className="space-y-3 rounded-md border border-danger-soft bg-danger-soft/40 p-3 text-xs text-danger">
                  <p className="flex items-start gap-2">
                    <ShieldAlert className="mt-0.5 h-4 w-4 shrink-0" />
                    <span>
                      يوجد <span className="num font-semibold">{summary.without_pin}</span> موظف نشط بدون رمز
                      حضور، ولن يتمكنوا من التسجيل من صفحة المسح حتى يحصلوا على رمز. هل تريد التفعيل رغم ذلك؟
                    </span>
                  </p>
                  <div className="flex justify-end gap-2">
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      onClick={() => setConfirmation(null)}
                      disabled={busy}
                    >
                      إلغاء
                    </Button>
                    <Button
                      type="button"
                      size="sm"
                      onClick={() => enforcementMutation.mutate({ required: true, force: true })}
                      disabled={busy}
                      className="bg-danger text-white hover:bg-danger/90"
                    >
                      {enforcementMutation.isPending && <Spinner className="text-white" />}
                      تفعيل رغم ذلك
                    </Button>
                  </div>
                </div>
              )}
            </section>
          </div>
        )}

        <DialogFooter className="mt-2">
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            إغلاق
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function SummaryStat({
  label,
  value,
  tone,
}: {
  label: string;
  value: number;
  tone?: 'success' | 'warning' | 'danger';
}) {
  return (
    <div className="rounded-lg border border-hairline bg-surface-2 p-3">
      <p className="text-[11px] text-muted">{label}</p>
      <p
        className={cn(
          'num mt-1 text-2xl font-bold text-ink',
          tone === 'success' && 'text-success',
          tone === 'warning' && 'text-amber-600',
          tone === 'danger' && 'text-danger',
        )}
      >
        {value}
      </p>
    </div>
  );
}
