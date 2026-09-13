'use client';

import * as React from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { clientsApi, recruitmentCasesApi } from '@/lib/api/endpoints/recruitment';
import { CASE_PRIORITY_OPTIONS, CASE_STATUS_OPTIONS } from '@/lib/constants/recruitment-options';
import { useAuthStore } from '@/lib/stores/auth-store';
import type {
  CasePriority,
  RecruitmentCase,
  RecruitmentCasePayload,
  RecruitmentCaseStatus,
} from '@/lib/api/types';

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  caseData?: RecruitmentCase | null;
  /** When creating from within a Client detail, lock the client_id. */
  defaultClientId?: number;
};

const emptyValues = (defaultClientId?: number, ownerId?: number) => ({
  client_id: defaultClientId ?? 0,
  title: '',
  description: '',
  priority: 'normal' as CasePriority,
  status: 'draft' as RecruitmentCaseStatus,
  target_hires: '',
  started_at: '',
  deadline: '',
  owner_id: ownerId ?? 0,
});

export function CaseFormDialog({ open, onOpenChange, caseData, defaultClientId }: Props) {
  const qc = useQueryClient();
  const user = useAuthStore((s) => s.user);
  const isEdit = !!caseData;

  const [values, setValues] = React.useState(() => emptyValues(defaultClientId, user?.id));
  const [ownerInput, setOwnerInput] = React.useState('');

  const { data: clients } = useQuery({
    queryKey: ['clients-picker'],
    queryFn: async () => (await clientsApi.list({ per_page: 100 })).data.data,
    enabled: open && !defaultClientId && !isEdit,
    staleTime: 60_000,
  });

  React.useEffect(() => {
    if (open) {
      if (caseData) {
        setValues({
          client_id: caseData.client_id,
          title: caseData.title,
          description: caseData.description ?? '',
          priority: caseData.priority,
          status: caseData.status,
          target_hires: caseData.target_hires?.toString() ?? '',
          started_at: caseData.started_at ?? '',
          deadline: caseData.deadline ?? '',
          owner_id: caseData.owner_id,
        });
        setOwnerInput(String(caseData.owner_id));
      } else {
        setValues(emptyValues(defaultClientId, user?.id));
        setOwnerInput(user?.id ? String(user.id) : '');
      }
    }
  }, [open, caseData, defaultClientId, user?.id]);

  const mutation = useMutation({
    mutationFn: () => {
      const payload: RecruitmentCasePayload | Partial<RecruitmentCasePayload> = {
        client_id: values.client_id,
        title: values.title.trim(),
        description: values.description.trim() || null,
        priority: values.priority,
        status: values.status,
        target_hires: values.target_hires ? Number(values.target_hires) : null,
        started_at: values.started_at || null,
        deadline: values.deadline || null,
        owner_id: Number(ownerInput) || values.owner_id,
      };
      if (isEdit && caseData) {
        const { client_id: _c, ...patch } = payload as RecruitmentCasePayload;
        return recruitmentCasesApi.update(caseData.id, patch);
      }
      return recruitmentCasesApi.create(payload as RecruitmentCasePayload);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['recruitment-cases'] });
      qc.invalidateQueries({ queryKey: ['client-cases'] });
      toast.success(isEdit ? 'تم تحديث الحملة' : 'تم إنشاء الحملة');
      onOpenChange(false);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'تعذر الحفظ');
    },
  });

  const canSubmit = values.title.trim().length > 0 && !!values.client_id && !!Number(ownerInput);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-xl">
        <DialogHeader>
          <DialogTitle>{isEdit ? 'تعديل حملة' : 'حملة جديدة'}</DialogTitle>
          <DialogDescription>حدد العميل وعنوان الحملة وباقي البيانات.</DialogDescription>
        </DialogHeader>
        <form
          onSubmit={(e) => {
            e.preventDefault();
            if (canSubmit) mutation.mutate();
          }}
          className="space-y-3"
        >
          {!defaultClientId && !isEdit && (
            <div>
              <Label className="text-xs font-semibold text-ink-2">العميل</Label>
              <Select
                value={values.client_id ? String(values.client_id) : ''}
                onValueChange={(v) => setValues({ ...values, client_id: Number(v) })}
              >
                <SelectTrigger className="mt-1.5"><SelectValue placeholder="اختر عميلاً" /></SelectTrigger>
                <SelectContent>
                  {(clients ?? []).map((c) => (
                    <SelectItem key={c.id} value={String(c.id)}>
                      {c.company_name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          )}
          <div>
            <Label className="text-xs font-semibold text-ink-2">عنوان الحملة</Label>
            <Input className="mt-1.5" value={values.title} onChange={(e) => setValues({ ...values, title: e.target.value })} />
          </div>
          <div>
            <Label className="text-xs font-semibold text-ink-2">الوصف</Label>
            <Textarea className="mt-1.5" rows={3} value={values.description} onChange={(e) => setValues({ ...values, description: e.target.value })} />
          </div>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div>
              <Label className="text-xs font-semibold text-ink-2">الأولوية</Label>
              <Select value={values.priority} onValueChange={(v) => setValues({ ...values, priority: v as CasePriority })}>
                <SelectTrigger className="mt-1.5"><SelectValue /></SelectTrigger>
                <SelectContent>
                  {CASE_PRIORITY_OPTIONS.map((o) => (
                    <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div>
              <Label className="text-xs font-semibold text-ink-2">الحالة</Label>
              <Select value={values.status} onValueChange={(v) => setValues({ ...values, status: v as RecruitmentCaseStatus })}>
                <SelectTrigger className="mt-1.5"><SelectValue /></SelectTrigger>
                <SelectContent>
                  {CASE_STATUS_OPTIONS.map((o) => (
                    <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div>
              <Label className="text-xs font-semibold text-ink-2">عدد التوظيفات</Label>
              <Input type="number" min={0} className="mt-1.5" value={values.target_hires} onChange={(e) => setValues({ ...values, target_hires: e.target.value })} />
            </div>
            <div>
              <Label className="text-xs font-semibold text-ink-2">تاريخ البدء</Label>
              <Input type="date" className="mt-1.5" value={values.started_at} onChange={(e) => setValues({ ...values, started_at: e.target.value })} />
            </div>
            <div>
              <Label className="text-xs font-semibold text-ink-2">تاريخ الإغلاق</Label>
              <Input type="date" className="mt-1.5" value={values.deadline} onChange={(e) => setValues({ ...values, deadline: e.target.value })} />
            </div>
            <div>
              <Label className="text-xs font-semibold text-ink-2">رقم مالك الحملة (user id)</Label>
              <Input className="mt-1.5" value={ownerInput} onChange={(e) => setOwnerInput(e.target.value.replace(/[^0-9]/g, ''))} dir="ltr" />
            </div>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>إلغاء</Button>
            <Button type="submit" disabled={!canSubmit || mutation.isPending} className="bg-brand text-white hover:bg-brand-hover">
              {mutation.isPending && <Spinner className="text-white" />}
              {isEdit ? 'حفظ' : 'إنشاء'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
