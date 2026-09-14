'use client';

import * as React from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { RefreshCcw, ShieldCheck } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { employeesApi } from '@/lib/api/endpoints/employees';
import { ROLE_OPTIONS } from '@/lib/constants/request-options';
import { hasPermission, useAuthStore } from '@/lib/stores/auth-store';
import type { Employee } from '@/lib/api/types';

type ChangeRoleDialogProps = {
  employee: Employee | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
};

/**
 * Each login account holds one role. The API refuses super-admin unless the
 * caller is a super-admin and keeps the last active super-admin in place, so
 * the option is only offered to super-admins here.
 */
export function ChangeRoleDialog({ employee, open, onOpenChange }: ChangeRoleDialogProps) {
  const qc = useQueryClient();
  const user = useAuthStore((s) => s.user);
  const isSuperAdmin = (user?.roles ?? []).includes('super-admin');
  const [role, setRole] = React.useState<string>('');

  React.useEffect(() => {
    if (open) {
      setRole(employee?.role ?? '');
    }
  }, [open, employee]);

  const options = ROLE_OPTIONS.filter((option) => option.value !== 'super-admin' || isSuperAdmin);

  const mutation = useMutation({
    mutationFn: () => employeesApi.assignRole(employee!.id, role),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['employees'] });
      toast.success('تم تغيير الدور');
      onOpenChange(false);
    },
    onError: (err: unknown) => {
      const response = (err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } })?.response?.data;
      toast.error(response?.errors?.role?.[0] ?? response?.message ?? 'تعذّر تغيير الدور');
    },
  });

  const canSubmit = !!employee && role !== '' && role !== employee.role && hasPermission(user, 'manage-users');

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2 text-start">
            <ShieldCheck className="h-4 w-4 text-brand" />
            تغيير الدور
          </DialogTitle>
        </DialogHeader>

        {employee && (
          <div className="space-y-4">
            <p className="text-sm text-ink-2">
              الموظف: <span className="font-semibold text-ink">{employee.full_name}</span>
              {' · '}
              <span className="num" dir="ltr">#{employee.employee_number}</span>
            </p>

            <div>
              <Label className="text-xs font-semibold text-ink-2">الدور</Label>
              <Select value={role} onValueChange={setRole}>
                <SelectTrigger className="mt-1.5">
                  <SelectValue placeholder="اختر الدور" />
                </SelectTrigger>
                <SelectContent>
                  {options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                      {option.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <p className="rounded-lg bg-brand-soft/50 p-3 text-xs text-brand-ink">
              الدور يحدد ما يراه الموظف من صفحات وما يستطيع فعله. لكل حساب دور واحد، والتغيير يسري فوراً.
            </p>
          </div>
        )}

        <DialogFooter className="mt-2">
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            إلغاء
          </Button>
          <Button type="button" onClick={() => mutation.mutate()} disabled={!canSubmit || mutation.isPending} className="gap-2">
            {mutation.isPending ? <RefreshCcw className="h-3.5 w-3.5 animate-spin" /> : <ShieldCheck className="h-3.5 w-3.5" />}
            حفظ الدور
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
