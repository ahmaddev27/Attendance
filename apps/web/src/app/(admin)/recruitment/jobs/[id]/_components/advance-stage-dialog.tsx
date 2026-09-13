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
import { jobsApi, recruitmentPipelinesApi } from '@/lib/api/endpoints/recruitment';
import type { AdvanceJobStagePayload, JobRequirement, RecruitmentPipelineStage } from '@/lib/api/types';

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  job: JobRequirement;
};

/**
 * Advance a job to its next pipeline stage. Loads the stages of the
 * job's pipeline so the user can pick the target stage; any
 * `requires_fields` on the target stage surface as extra inputs.
 */
export function AdvanceStageDialog({ open, onOpenChange, job }: Props) {
  const qc = useQueryClient();
  const [targetStageId, setTargetStageId] = React.useState<string>('');
  const [fields, setFields] = React.useState<Record<string, string>>({});
  const [handoffNote, setHandoffNote] = React.useState('');

  const { data: pipelineRes } = useQuery({
    queryKey: ['recruitment-pipelines', job.pipeline_id],
    queryFn: async () => (await recruitmentPipelinesApi.get(job.pipeline_id!)).data.data,
    enabled: open && !!job.pipeline_id,
  });

  const stages = React.useMemo<RecruitmentPipelineStage[]>(
    () => [...(pipelineRes?.stages ?? [])].sort((a, b) => a.display_order - b.display_order),
    [pipelineRes],
  );

  const currentIndex = stages.findIndex((s) => s.id === job.current_stage_id);
  const defaultNext = currentIndex >= 0 ? stages[currentIndex + 1] : null;

  React.useEffect(() => {
    if (open) {
      setTargetStageId(defaultNext ? String(defaultNext.id) : '');
      setFields({});
      setHandoffNote('');
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, defaultNext?.id]);

  const targetStage = stages.find((s) => String(s.id) === targetStageId) ?? null;

  const mutation = useMutation({
    mutationFn: () => {
      const payload: AdvanceJobStagePayload = {
        target_stage_id: targetStageId ? Number(targetStageId) : undefined,
        handoff_note: handoffNote.trim() || null,
      };
      const collected: Record<string, unknown> = {};
      if (targetStage?.requires_fields?.length) {
        for (const key of targetStage.requires_fields) {
          const raw = fields[key]?.trim();
          if (!raw) continue;
          if (key === 'shortlist_ids') {
            collected[key] = raw
              .split(',')
              .map((v) => Number(v.trim()))
              .filter((n) => Number.isFinite(n) && n > 0);
          } else {
            collected[key] = raw;
          }
        }
      }
      if (Object.keys(collected).length > 0) {
        payload.fields = collected as AdvanceJobStagePayload['fields'];
      }
      return jobsApi.advanceStage(job.id, payload);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['jobs'] });
      qc.invalidateQueries({ queryKey: ['case-jobs'] });
      toast.success('تم نقل الوظيفة إلى المرحلة التالية');
      onOpenChange(false);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      const errors = (err as { response?: { data?: { errors?: Record<string, string[]> } } })?.response?.data?.errors;
      const first = errors ? Object.values(errors)[0]?.[0] : undefined;
      toast.error(first || message || 'تعذر النقل');
    },
  });

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>الانتقال إلى المرحلة التالية</DialogTitle>
          <DialogDescription>
            اختر المرحلة الجديدة وقم بتعبئة الحقول المطلوبة.
          </DialogDescription>
        </DialogHeader>
        <form
          onSubmit={(e) => {
            e.preventDefault();
            mutation.mutate();
          }}
          className="space-y-3"
        >
          <div>
            <Label className="text-xs font-semibold text-ink-2">المرحلة المستهدفة</Label>
            <Select value={targetStageId} onValueChange={setTargetStageId}>
              <SelectTrigger className="mt-1.5"><SelectValue placeholder="اختر مرحلة" /></SelectTrigger>
              <SelectContent>
                {stages
                  .filter((s) => s.id !== job.current_stage_id)
                  .map((s) => (
                    <SelectItem key={s.id} value={String(s.id)}>
                      {s.name}
                    </SelectItem>
                  ))}
              </SelectContent>
            </Select>
          </div>

          {targetStage?.requires_fields?.length ? (
            <div className="space-y-3 rounded-md border border-hairline bg-surface-2 p-3">
              <p className="text-xs font-semibold text-ink-2">حقول مطلوبة للانتقال</p>
              {targetStage.requires_fields.map((key) => (
                <div key={key}>
                  <Label className="text-xs font-semibold text-ink-2">{key}</Label>
                  <Input
                    className="mt-1.5"
                    value={fields[key] ?? ''}
                    onChange={(e) => setFields({ ...fields, [key]: e.target.value })}
                    placeholder={key === 'publication_url' ? 'https://' : undefined}
                    dir="ltr"
                  />
                </div>
              ))}
            </div>
          ) : null}

          <div>
            <Label className="text-xs font-semibold text-ink-2">ملاحظة تسليم (اختياري)</Label>
            <Textarea className="mt-1.5" rows={3} value={handoffNote} onChange={(e) => setHandoffNote(e.target.value)} />
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>إلغاء</Button>
            <Button type="submit" disabled={!targetStageId || mutation.isPending} className="bg-brand text-white hover:bg-brand-hover">
              {mutation.isPending && <Spinner className="text-white" />}
              نقل
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
