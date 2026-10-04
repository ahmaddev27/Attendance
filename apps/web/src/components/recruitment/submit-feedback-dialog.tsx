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
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { ScorecardForm } from '@/components/recruitment/scorecard-form';
import {
  interviewsApi,
  screeningApi,
  INTERVIEW_RECOMMENDATION_LABEL,
} from '@/lib/api/endpoints/candidates';
import type {
  InterviewFeedback,
  InterviewRecommendation,
  ScreeningSchema,
} from '@/lib/api/types';

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  interviewId: number;
  /** Pipeline + stage drive the dynamic scorecard reused from screening. */
  pipelineId: number;
  stageId: number;
  /** Existing feedback for this (interview, interviewer) — triggers PATCH. */
  existing?: InterviewFeedback | null;
};

export function SubmitFeedbackDialog({
  open,
  onOpenChange,
  interviewId,
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

  const [scorecard, setScorecard] = React.useState<Record<string, string | number>>({});
  const [recommendation, setRecommendation] = React.useState<InterviewRecommendation>('hire');
  const [strengths, setStrengths] = React.useState('');
  const [weaknesses, setWeaknesses] = React.useState('');
  const [notes, setNotes] = React.useState('');

  React.useEffect(() => {
    if (!open) return;
    setScorecard(existing?.scorecard ?? {});
    setRecommendation(existing?.recommendation ?? 'hire');
    setStrengths(existing?.strengths ?? '');
    setWeaknesses(existing?.weaknesses ?? '');
    setNotes(existing?.notes ?? '');
  }, [open, existing]);

  const mutation = useMutation({
    mutationFn: async () => {
      const payload = {
        scorecard,
        recommendation,
        strengths: strengths.trim() || null,
        weaknesses: weaknesses.trim() || null,
        notes: notes.trim() || null,
      };
      if (existing) {
        return interviewsApi.updateFeedback(interviewId, existing.id, payload);
      }
      return interviewsApi.submitFeedback(interviewId, payload);
    },
    onSuccess: () => {
      toast.success('تم تقديم التقييم');
      qc.invalidateQueries({ queryKey: ['interview', interviewId] });
      qc.invalidateQueries({ queryKey: ['interview-feedbacks', interviewId] });
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
          <DialogTitle>تقديم تقييم المقابلة</DialogTitle>
          <DialogDescription>
            عبّئ البطاقة والتوصية. يستطيع كل مُحاوِر تعديل تقييمه لاحقاً — صفٌّ واحد لكل مُحاوِر.
          </DialogDescription>
        </DialogHeader>

        {schemaLoading ? (
          <div className="flex items-center justify-center p-6">
            <Spinner className="me-2 h-4 w-4" />
            جارٍ تحميل النموذج...
          </div>
        ) : !schema || !schema.fields?.length ? (
          <p className="rounded-lg bg-warn-soft p-4 text-sm text-warn-ink">
            لا يوجد نموذج تقييم مرتبط بهذه المرحلة.
          </p>
        ) : (
          <ScorecardForm schema={schema as ScreeningSchema} value={scorecard} onChange={setScorecard} />
        )}

        <div className="space-y-1.5">
          <Label className="text-xs text-ink-2">التوصية</Label>
          <RadioGroup
            value={recommendation}
            onValueChange={(v) => setRecommendation(v as InterviewRecommendation)}
            className="grid gap-2 sm:grid-cols-2"
          >
            {(Object.keys(INTERVIEW_RECOMMENDATION_LABEL) as InterviewRecommendation[]).map((r) => (
              <label key={r} className="flex cursor-pointer items-center gap-2 text-sm text-ink">
                <RadioGroupItem value={r} />
                {INTERVIEW_RECOMMENDATION_LABEL[r]}
              </label>
            ))}
          </RadioGroup>
        </div>

        <div className="grid gap-3 sm:grid-cols-2">
          <div className="space-y-1.5">
            <Label className="text-xs text-ink-2">نقاط القوة</Label>
            <Textarea value={strengths} onChange={(e) => setStrengths(e.target.value)} rows={3} />
          </div>
          <div className="space-y-1.5">
            <Label className="text-xs text-ink-2">نقاط الضعف</Label>
            <Textarea value={weaknesses} onChange={(e) => setWeaknesses(e.target.value)} rows={3} />
          </div>
        </div>
        <div className="space-y-1.5">
          <Label className="text-xs text-ink-2">ملاحظات إضافية</Label>
          <Textarea value={notes} onChange={(e) => setNotes(e.target.value)} rows={2} />
        </div>

        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            إلغاء
          </Button>
          <Button
            type="button"
            className="bg-brand text-white hover:bg-brand-hover"
            disabled={mutation.isPending}
            onClick={() => mutation.mutate()}
          >
            {mutation.isPending && <Spinner className="me-2 h-4 w-4" />}
            تقديم تقييم
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
