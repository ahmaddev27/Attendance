'use client';

import * as React from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { applicationsApi } from '@/lib/api/endpoints/candidates';

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  applicationId: number;
};

/**
 * Reject dialog — forces the admin to leave a reason, which is stored
 * on the application for the audit log and the candidate rejection
 * email template.
 */
export function RejectApplicationDialog({ open, onOpenChange, applicationId }: Props) {
  const qc = useQueryClient();
  const [reason, setReason] = React.useState('');
  const [error, setError] = React.useState<string | null>(null);

  React.useEffect(() => {
    if (!open) {
      setReason('');
      setError(null);
    }
  }, [open]);

  const mutation = useMutation({
    mutationFn: () => applicationsApi.reject(applicationId, { reason: reason.trim() }),
    onSuccess: () => {
      toast.success('تم رفض الطلب');
      qc.invalidateQueries({ queryKey: ['application', applicationId] });
      qc.invalidateQueries({ queryKey: ['job-applications'] });
      qc.invalidateQueries({ queryKey: ['job-shortlist'] });
      onOpenChange(false);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'تعذر الرفض');
    },
  });

  return (
    <AlertDialog open={open} onOpenChange={onOpenChange}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>رفض طلب التقديم</AlertDialogTitle>
          <AlertDialogDescription>
            ستُغلق هذه المحاولة ولا يمكن نقلها لاحقاً إلى مرحلة أخرى.
          </AlertDialogDescription>
        </AlertDialogHeader>

        <div className="space-y-1.5">
          <Label className="text-xs text-ink-2">سبب الرفض</Label>
          <Textarea
            value={reason}
            onChange={(e) => setReason(e.target.value)}
            rows={3}
            placeholder="مثلاً: الخبرة أقل من المطلوب."
          />
        </div>

        {error && <p className="text-xs text-danger">{error}</p>}

        <AlertDialogFooter>
          <AlertDialogCancel>تراجع</AlertDialogCancel>
          <AlertDialogAction
            className="bg-danger text-white hover:bg-danger/90"
            disabled={mutation.isPending}
            onClick={(event) => {
              event.preventDefault();
              if (!reason.trim()) {
                setError('السبب مطلوب.');
                return;
              }
              setError(null);
              mutation.mutate();
            }}
          >
            رفض
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}
