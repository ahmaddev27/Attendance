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
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { ScorecardForm } from '@/components/recruitment/scorecard-form';
import { screeningApi } from '@/lib/api/endpoints/candidates';
import type {
  CandidateApplication,
  CandidateScreening,
  ScreeningSchema,
} from '@/lib/api/types';

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  application: CandidateApplication;
  pipelineId: number;
  stageId: number;
  existing: CandidateScreening | null;
};

/**
 * Screening scorecard dialog — pulls the stage's schema and submits
 * the answers. The backend computes `overall_score` + `passed`; the
 * client only sends raw values. Edits of a submitted screening
 * overwrite the single row (Phase 2 is history-free, see D5).
 */
export function ScreeningFormDialog({
  open,
  onOpenChange,
  application,
  pipelineId,
  stageId,
  existing,
}: Props) {
  const qc = useQueryClient();

  const { data: schema, isLoading: schemaLoading } = useQuery({
    queryKey: ['screening-schema', pipelineId, stageId],
    queryFn: async () => (await screeningApi.schema(pipelineId, stageId)).data.data,
    enabled: open && Number.isFinite(pipelineId) && Number.isFinite(stageId),
  });

  const [values, setValues] = React.useState<Record<string, string | number>>({});
  const [notes, setNotes] = React.useState('');

  React.useEffect(() => {
    if (!open) return;
    setValues(existing?.scorecard ?? {});
    setNotes(existing?.notes ?? '');
  }, [open, existing]);

  const mutation = useMutation({
    mutationFn: () =>
      screeningApi.submit(application.id, { scorecard: values, notes: notes || null }),
    onSuccess: () => {
      toast.success('تم حفظ نموذج الفرز');
      qc.invalidateQueries({ queryKey: ['application', application.id] });
      qc.invalidateQueries({ queryKey: ['application-screening', application.id] });
      onOpenChange(false);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'تعذر الحفظ');
    },
  });

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
        <DialogHeader>
          <DialogTitle>نموذج الفرز</DialogTitle>
          <DialogDescription>
            عبّئ الحقول للمرشّح. تُحسب درجة النجاح تلقائياً وفق أوزان المرحلة.
          </DialogDescription>
        </DialogHeader>

        {schemaLoading ? (
          <div className="flex items-center justify-center p-8">
            <Spinner className="me-2 h-4 w-4" />
            جارٍ تحميل النموذج...
          </div>
        ) : !schema || !schema.fields?.length ? (
          <p className="rounded-lg bg-warn-soft p-4 text-sm text-warn-ink">
            هذه المرحلة لا تحتوي على نموذج فرز معرّف. أضف الحقول من إعدادات المسار أولاً.
          </p>
        ) : (
          <>
            <ScorecardForm schema={schema as ScreeningSchema} value={values} onChange={setValues} />
            <div className="space-y-1.5">
              <label className="text-xs text-ink-2">ملاحظات</label>
              <Textarea value={notes} onChange={(e) => setNotes(e.target.value)} rows={3} />
            </div>
          </>
        )}

        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            إلغاء
          </Button>
          <Button
            type="button"
            className="bg-brand text-white hover:bg-brand-hover"
            disabled={!schema || mutation.isPending}
            onClick={() => mutation.mutate()}
          >
            {mutation.isPending && <Spinner className="me-2 h-4 w-4" />}
            حفظ
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
