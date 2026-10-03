'use client';

import * as React from 'react';
import { useMutation } from '@tanstack/react-query';
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
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { employeesApi } from '@/lib/api/endpoints/employees';
import type { Employee } from '@/lib/api/types';

type BulkSmsDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  recipients: Employee[];
  onSent?: () => void;
};

// Server-side cap on body length (BulkSmsRequest). 500 chars = up to ~3
// GSM-7 segments; we show the segment estimate live so the admin sees the
// per-recipient cost before sending.
const MAX_BODY = 500;
const GSM_SEGMENT_SIZE = 160;

/**
 * Admin bulk-SMS composer. SMS is metered through MTC, so this dialog is
 * noisier than its email sibling: it shows the recipient count, the
 * skipped-no-phone count, the per-SMS segment estimate, and warns that
 * each segment is billed per recipient.
 */
export function BulkSmsDialog({ open, onOpenChange, recipients, onSent }: BulkSmsDialogProps) {
  const [body, setBody] = React.useState('');

  React.useEffect(() => {
    if (open) setBody('');
  }, [open]);

  const withPhoneCount = React.useMemo(
    () => recipients.filter((r) => r.phone && r.phone.trim() !== '').length,
    [recipients],
  );
  const skippedCount = recipients.length - withPhoneCount;

  // Carrier splits any message >160 chars into N segments and bills each
  // separately. Show the admin the (chars → segments) math up front.
  const segments = body.length === 0 ? 0 : Math.max(1, Math.ceil(body.length / GSM_SEGMENT_SIZE));

  const mutation = useMutation({
    mutationFn: () =>
      employeesApi.bulkSms({
        employee_ids: recipients.map((r) => r.id),
        body: body.trim(),
      }),
    onSuccess: (res) => {
      const data = res.data.data;
      toast.success('تم إرسال الرسائل', {
        description: `تم تجهيز ${data.queued} رسالة؛ تم تخطّي ${data.skipped_no_phone} موظفاً بدون رقم جوّال.`,
      });
      onOpenChange(false);
      onSent?.();
    },
    onError: (err: unknown) => {
      const message =
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message ||
        'تعذر إرسال الرسائل القصيرة';
      toast.error(message);
    },
  });

  const canSubmit =
    recipients.length > 0 && withPhoneCount > 0 && body.trim().length > 0 && !mutation.isPending;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-xl">
        <DialogHeader>
          <DialogTitle>إرسال SMS جماعي</DialogTitle>
          <DialogDescription>
            سيتم إرسال نفس الرسالة إلى كل موظف مُحدَّد لديه رقم جوّال.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <div className="rounded-lg border border-hairline bg-surface-muted p-3 text-sm">
            <div className="flex items-center justify-between">
              <span className="text-ink-2">عدد المحدَّدين</span>
              <span className="num font-semibold">{recipients.length}</span>
            </div>
            <div className="mt-1 flex items-center justify-between">
              <span className="text-ink-2">سيصلهم SMS</span>
              <span className="num font-semibold text-success">{withPhoneCount}</span>
            </div>
            {skippedCount > 0 && (
              <p className="mt-2 text-xs text-amber-600">
                {skippedCount} موظف بدون رقم جوّال سيتم تخطّيهم.
              </p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="bulk-sms-body">نص الرسالة</Label>
            <Textarea
              id="bulk-sms-body"
              rows={6}
              maxLength={MAX_BODY}
              value={body}
              onChange={(e) => setBody(e.target.value)}
              placeholder="اكتب رسالتك القصيرة هنا..."
            />
            <div className="flex items-center justify-between text-[11px]">
              <p className="text-amber-600">
                تنبيه: SMS مدفوع. تُحتسب التكلفة لكل جزء ولكل مستلم.
              </p>
              <p className="num text-muted" dir="ltr">
                {body.length} / {MAX_BODY} ({segments} جزء)
              </p>
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" type="button" onClick={() => onOpenChange(false)}>
            إلغاء
          </Button>
          <Button
            type="button"
            disabled={!canSubmit}
            onClick={() => mutation.mutate()}
            className="bg-brand text-white hover:bg-brand-hover"
          >
            {mutation.isPending && <Spinner className="text-white" />}
            إرسال إلى {withPhoneCount} موظفاً
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
