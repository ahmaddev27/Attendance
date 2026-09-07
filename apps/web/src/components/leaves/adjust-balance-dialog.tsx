'use client';

import * as React from 'react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Spinner } from '@/components/ui/spinner';
import { LeaveTypeBadge } from '@/components/leaves/leave-type-badge';
import { leaveBalancesApi } from '@/lib/api/endpoints/leave-balances';
import type { LeaveBalance } from '@/lib/api/types';

type AdjustBalanceDialogProps = {
  balance: LeaveBalance | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
};

/**
 * Manual balance correction — e.g. a carry-over fix or a one-off grant.
 * `delta` is signed: positive adds days, negative deducts them.
 */
export function AdjustBalanceDialog({ balance, open, onOpenChange }: AdjustBalanceDialogProps) {
  const queryClient = useQueryClient();
  const [delta, setDelta] = React.useState('');
  const [reason, setReason] = React.useState('');
  const [error, setError] = React.useState<string | null>(null);

  React.useEffect(() => {
    if (open) {
      setDelta('');
      setReason('');
      setError(null);
    }
  }, [open, balance?.id]);

  const mutation = useMutation({
    mutationFn: (payload: { employee_id: number; leave_type_id: number; year: number; delta: number; reason: string }) =>
      leaveBalancesApi.adjust(payload),
    onSuccess: () => {
      toast.success('تم تعديل الرصيد بنجاح');
      queryClient.invalidateQueries({ queryKey: ['leave-balances'] });
      onOpenChange(false);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'تعذر تعديل الرصيد');
    },
  });

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    const deltaValue = Number(delta);
    if (!delta.trim() || Number.isNaN(deltaValue) || deltaValue === 0) {
      setError('قيمة التعديل مطلوبة ويجب ألا تساوي صفراً');
      return;
    }
    if (!reason.trim()) {
      setError('سبب التعديل مطلوب');
      return;
    }
    if (!balance) return;
    mutation.mutate({
      employee_id: balance.employee_id,
      leave_type_id: balance.leave_type_id,
      year: balance.year,
      delta: deltaValue,
      reason: reason.trim(),
    });
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>تعديل الرصيد</DialogTitle>
          <DialogDescription>
            {balance?.leave_type ? (
              <span className="flex items-center gap-2">
                <LeaveTypeBadge leaveType={balance.leave_type} />— سنة <span className="num">{balance.year}</span>
              </span>
            ) : (
              'أدخل قيمة التعديل والسبب.'
            )}
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={submit} className="space-y-4">
          <div>
            <Label className="text-xs font-semibold text-ink-2">قيمة التعديل (موجبة للإضافة، سالبة للخصم)</Label>
            <Input
              type="number"
              dir="ltr"
              className="num mt-1.5 text-right"
              value={delta}
              onChange={(e) => {
                setDelta(e.target.value);
                if (error) setError(null);
              }}
              placeholder="مثال: 3 أو -2"
              autoFocus
            />
          </div>
          <div>
            <Label className="text-xs font-semibold text-ink-2">السبب</Label>
            <Textarea
              value={reason}
              onChange={(e) => {
                setReason(e.target.value);
                if (error) setError(null);
              }}
              className="mt-1.5"
              rows={3}
            />
          </div>
          {error && <p className="text-xs text-danger">{error}</p>}

          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              إلغاء
            </Button>
            <Button type="submit" disabled={mutation.isPending} className="bg-brand text-white hover:bg-brand-hover">
              {mutation.isPending && <Spinner className="text-white" />}
              حفظ التعديل
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
