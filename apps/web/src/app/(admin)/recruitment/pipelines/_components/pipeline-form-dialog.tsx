'use client';

import * as React from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { recruitmentPipelinesApi } from '@/lib/api/endpoints/recruitment';
import type { RecruitmentPipeline, RecruitmentPipelinePayload } from '@/lib/api/types';

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  pipeline?: RecruitmentPipeline | null;
};

export function PipelineFormDialog({ open, onOpenChange, pipeline }: Props) {
  const qc = useQueryClient();
  const isEdit = !!pipeline;
  const [values, setValues] = React.useState<RecruitmentPipelinePayload>({
    name: '',
    code: '',
    description: '',
    is_default: false,
    is_active: true,
  });

  React.useEffect(() => {
    if (open) {
      setValues({
        name: pipeline?.name ?? '',
        code: pipeline?.code ?? '',
        description: pipeline?.description ?? '',
        is_default: pipeline?.is_default ?? false,
        is_active: pipeline?.is_active ?? true,
      });
    }
  }, [open, pipeline]);

  const mutation = useMutation({
    mutationFn: () => {
      const payload: RecruitmentPipelinePayload = {
        ...values,
        description: values.description?.trim() || null,
      };
      if (isEdit && pipeline) return recruitmentPipelinesApi.update(pipeline.id, payload);
      return recruitmentPipelinesApi.create(payload);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['recruitment-pipelines'] });
      toast.success(isEdit ? 'تم تحديث المسار' : 'تم إنشاء المسار');
      onOpenChange(false);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'تعذر الحفظ');
    },
  });

  const canSubmit = values.name.trim().length > 0 && values.code.trim().length > 0;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{isEdit ? 'تعديل مسار' : 'مسار جديد'}</DialogTitle>
        </DialogHeader>
        <form
          onSubmit={(e) => {
            e.preventDefault();
            if (canSubmit) mutation.mutate();
          }}
          className="space-y-3"
        >
          <div>
            <Label className="text-xs font-semibold text-ink-2">اسم المسار</Label>
            <Input className="mt-1.5" value={values.name} onChange={(e) => setValues({ ...values, name: e.target.value })} />
          </div>
          <div>
            <Label className="text-xs font-semibold text-ink-2">الرمز (code)</Label>
            <Input className="mt-1.5" value={values.code} onChange={(e) => setValues({ ...values, code: e.target.value })} dir="ltr" disabled={isEdit} />
          </div>
          <div>
            <Label className="text-xs font-semibold text-ink-2">الوصف</Label>
            <Textarea className="mt-1.5" rows={3} value={values.description ?? ''} onChange={(e) => setValues({ ...values, description: e.target.value })} />
          </div>
          <div className="flex items-center gap-4">
            <label className="flex items-center gap-2 text-sm text-ink">
              <input type="checkbox" checked={!!values.is_active} onChange={(e) => setValues({ ...values, is_active: e.target.checked })} className="h-4 w-4 rounded border-hairline" />
              نشط
            </label>
            <label className="flex items-center gap-2 text-sm text-ink">
              <input type="checkbox" checked={!!values.is_default} onChange={(e) => setValues({ ...values, is_default: e.target.checked })} className="h-4 w-4 rounded border-hairline" />
              افتراضي
            </label>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>إلغاء</Button>
            <Button type="submit" disabled={!canSubmit || mutation.isPending} className="bg-brand text-white hover:bg-brand-hover">
              {mutation.isPending && <Spinner className="text-white" />}
              حفظ
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
