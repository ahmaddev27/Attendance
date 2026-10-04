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
import { interviewsApi } from '@/lib/api/endpoints/candidates';

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  interviewId: number;
};

/** Cancel an interview with an optional reason kept for the audit trail. */
export function CancelInterviewDialog({ open, onOpenChange, interviewId }: Props) {
  const qc = useQueryClient();
  const [reason, setReason] = React.useState('');

  React.useEffect(() => {
    if (!open) setReason('');
  }, [open]);

  const mutation = useMutation({
    mutationFn: () =>
      interviewsApi.cancel(interviewId, { cancelled_reason: reason.trim() || undefined }),
    onSuccess: () => {
      toast.success('تم إلغاء المقابلة');
      qc.invalidateQueries({ queryKey: ['interview', interviewId] });
      qc.invalidateQueries({ queryKey: ['interviews'] });
      onOpenChange(false);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'تعذر الإلغاء');
    },
  });

  return (
    <AlertDialog open={open} onOpenChange={onOpenChange}>
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>إلغاء المقابلة</AlertDialogTitle>
          <AlertDialogDescription>سيتم إشعار المشاركين بالإلغاء.</AlertDialogDescription>
        </AlertDialogHeader>
        <div className="space-y-2">
          <Label htmlFor="cancel-reason">السبب (اختياري)</Label>
          <Textarea id="cancel-reason" value={reason} onChange={(e) => setReason(e.target.value)} rows={3} />
        </div>
        <AlertDialogFooter>
          <AlertDialogCancel>رجوع</AlertDialogCancel>
          <AlertDialogAction
            className="bg-danger text-white hover:bg-danger/90"
            disabled={mutation.isPending}
            onClick={(e) => {
              e.preventDefault();
              mutation.mutate();
            }}
          >
            إلغاء المقابلة
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}
