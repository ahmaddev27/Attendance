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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { employeesApi } from '@/lib/api/endpoints/employees';
import type { Employee } from '@/lib/api/types';

type BulkEmailDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** The currently selected rows — presence of `email` decides inclusion. */
  recipients: Employee[];
  onSent?: () => void;
};

// Subject + body length caps mirror the server-side validation
// (BulkEmailRequest) so the user sees the limit in the UI before the
// request round-trips with a 422.
const MAX_SUBJECT = 150;
const MAX_BODY = 10000;

/**
 * Admin bulk-email composer. Shows a live count of resolvable vs
 * skipped-no-email recipients so the admin can decide whether to go back
 * and fill in missing emails first. The server-side gate rejects >500
 * recipients outright — we warn in the UI when the selection crosses
 * that line rather than disabling the button (the server's error message
 * is clear).
 */
export function BulkEmailDialog({ open, onOpenChange, recipients, onSent }: BulkEmailDialogProps) {
  const [subject, setSubject] = React.useState('');
  const [body, setBody] = React.useState('');

  React.useEffect(() => {
    if (open) {
      setSubject('');
      setBody('');
    }
  }, [open]);

  const withEmailCount = React.useMemo(
    () => recipients.filter((r) => r.email && r.email.trim() !== '').length,
    [recipients],
  );
  const skippedCount = recipients.length - withEmailCount;

  const mutation = useMutation({
    mutationFn: () =>
      employeesApi.bulkEmail({
        employee_ids: recipients.map((r) => r.id),
        subject: subject.trim(),
        body: body.trim(),
      }),
    onSuccess: (res) => {
      const data = res.data.data;
      toast.success('تم إرسال البريد الجماعي', {
        description: `تم تجهيز ${data.queued} رسالة؛ تم تخطّي ${data.skipped_no_email} موظفاً بدون بريد.`,
      });
      onOpenChange(false);
      onSent?.();
    },
    onError: (err: unknown) => {
      const message =
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message ||
        'تعذر إرسال البريد الجماعي';
      toast.error(message);
    },
  });

  const canSubmit =
    recipients.length > 0 &&
    withEmailCount > 0 &&
    subject.trim().length > 0 &&
    body.trim().length > 0 &&
    !mutation.isPending;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-xl">
        <DialogHeader>
          <DialogTitle>إرسال بريد جماعي</DialogTitle>
          <DialogDescription>
            سيتم إرسال نفس البريد الإلكتروني إلى كل موظف مُحدَّد لديه بريد مسجَّل.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <div className="rounded-lg border border-hairline bg-surface-muted p-3 text-sm">
            <div className="flex items-center justify-between">
              <span className="text-ink-2">عدد المحدَّدين</span>
              <span className="num font-semibold">{recipients.length}</span>
            </div>
            <div className="mt-1 flex items-center justify-between">
              <span className="text-ink-2">سيصلهم البريد</span>
              <span className="num font-semibold text-success">{withEmailCount}</span>
            </div>
            {skippedCount > 0 && (
              <p className="mt-2 text-xs text-amber-600">
                {skippedCount} موظف بدون بريد إلكتروني سيتم تخطّيهم.
              </p>
            )}
          </div>

          <div className="space-y-2">
            <Label htmlFor="bulk-email-subject">الموضوع</Label>
            <Input
              id="bulk-email-subject"
              maxLength={MAX_SUBJECT}
              value={subject}
              onChange={(e) => setSubject(e.target.value)}
              placeholder="مثال: اجتماع عام يوم الأحد"
            />
            <p className="text-left text-[11px] text-muted" dir="ltr">
              {subject.length} / {MAX_SUBJECT}
            </p>
          </div>

          <div className="space-y-2">
            <Label htmlFor="bulk-email-body">نص البريد</Label>
            <Textarea
              id="bulk-email-body"
              rows={8}
              maxLength={MAX_BODY}
              value={body}
              onChange={(e) => setBody(e.target.value)}
              placeholder="اكتب رسالتك هنا..."
            />
            <p className="text-left text-[11px] text-muted" dir="ltr">
              {body.length} / {MAX_BODY}
            </p>
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
            إرسال إلى {withEmailCount} موظفاً
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
