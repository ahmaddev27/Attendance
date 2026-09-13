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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { recruitmentPipelineStagesApi } from '@/lib/api/endpoints/recruitment';
import {
  CASE_PRIORITY_OPTIONS,
  STAGE_OWNER_RULE_OPTIONS,
} from '@/lib/constants/recruitment-options';
import type {
  CasePriority,
  RecruitmentPipelineStage,
  RecruitmentPipelineStagePayload,
  StageOwnerRuleType,
} from '@/lib/api/types';

/**
 * The only field keys the advance-stage endpoint validates today. Offering
 * them as checkboxes (instead of free text) keeps admins from typing a key
 * the backend would silently ignore.
 */
const REQUIRABLE_FIELDS: { key: string; label: string }[] = [
  { key: 'publication_url', label: 'رابط النشر' },
  { key: 'shortlist_ids', label: 'القائمة المختصرة' },
  { key: 'contract_terms', label: 'شروط العقد' },
];

const OWNER_RULES_WITH_VALUE: StageOwnerRuleType[] = ['role', 'specific'];

type FormValues = {
  code: string;
  name: string;
  description: string;
  owner_rule_type: StageOwnerRuleType;
  owner_rule_value: string;
  sla_hours: string;
  auto_generate_task: boolean;
  task_title_template: string;
  task_priority: CasePriority;
  requires_fields: string[];
  is_terminal: boolean;
};

const EMPTY: FormValues = {
  code: '',
  name: '',
  description: '',
  owner_rule_type: 'job_owner',
  owner_rule_value: '',
  sla_hours: '',
  auto_generate_task: true,
  task_title_template: '',
  task_priority: 'normal',
  requires_fields: [],
  is_terminal: false,
};

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  pipelineId: number;
  stage?: RecruitmentPipelineStage | null;
};

export function StageFormDialog({ open, onOpenChange, pipelineId, stage }: Props) {
  const qc = useQueryClient();
  const isEdit = !!stage;
  const [values, setValues] = React.useState<FormValues>(EMPTY);

  React.useEffect(() => {
    if (!open) return;
    setValues(
      stage
        ? {
            code: stage.code,
            name: stage.name,
            description: stage.description ?? '',
            owner_rule_type: stage.owner_rule_type,
            owner_rule_value: stage.owner_rule_value ?? '',
            sla_hours: stage.sla_hours === null ? '' : String(stage.sla_hours),
            auto_generate_task: stage.auto_generate_task,
            task_title_template: stage.task_title_template ?? '',
            task_priority: stage.task_priority ?? 'normal',
            requires_fields: stage.requires_fields ?? [],
            is_terminal: stage.is_terminal,
          }
        : EMPTY,
    );
  }, [open, stage]);

  const needsOwnerValue = OWNER_RULES_WITH_VALUE.includes(values.owner_rule_type);

  const mutation = useMutation({
    mutationFn: () => {
      const payload: RecruitmentPipelineStagePayload = {
        code: values.code.trim(),
        name: values.name.trim(),
        description: values.description.trim() || null,
        owner_rule_type: values.owner_rule_type,
        owner_rule_value: needsOwnerValue ? values.owner_rule_value.trim() || null : null,
        sla_hours: values.sla_hours.trim() === '' ? null : Number(values.sla_hours),
        auto_generate_task: values.auto_generate_task,
        task_title_template: values.auto_generate_task ? values.task_title_template.trim() || null : null,
        task_priority: values.auto_generate_task ? values.task_priority : null,
        requires_fields: values.requires_fields,
        is_terminal: values.is_terminal,
      };
      if (isEdit && stage) return recruitmentPipelineStagesApi.update(pipelineId, stage.id, payload);
      return recruitmentPipelineStagesApi.create(pipelineId, payload);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['recruitment-pipelines'] });
      toast.success(isEdit ? 'تم تحديث المرحلة' : 'تمت إضافة المرحلة');
      onOpenChange(false);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'تعذر الحفظ');
    },
  });

  const canSubmit =
    values.name.trim().length > 0 &&
    values.code.trim().length > 0 &&
    (!needsOwnerValue || values.owner_rule_value.trim().length > 0);

  const toggleRequiredField = (key: string, checked: boolean) => {
    setValues((prev) => ({
      ...prev,
      requires_fields: checked
        ? Array.from(new Set([...prev.requires_fields, key]))
        : prev.requires_fields.filter((k) => k !== key),
    }));
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-xl">
        <DialogHeader>
          <DialogTitle>{isEdit ? 'تعديل مرحلة' : 'مرحلة جديدة'}</DialogTitle>
        </DialogHeader>
        <form
          onSubmit={(e) => {
            e.preventDefault();
            if (canSubmit) mutation.mutate();
          }}
          className="space-y-4"
        >
          <div className="grid gap-3 sm:grid-cols-2">
            <div>
              <Label className="text-xs font-semibold text-ink-2">اسم المرحلة</Label>
              <Input className="mt-1.5" value={values.name} onChange={(e) => setValues({ ...values, name: e.target.value })} />
            </div>
            <div>
              <Label className="text-xs font-semibold text-ink-2">الرمز (code)</Label>
              <Input
                className="mt-1.5"
                value={values.code}
                onChange={(e) => setValues({ ...values, code: e.target.value })}
                dir="ltr"
                disabled={isEdit}
                placeholder="publish"
              />
            </div>
          </div>

          <div>
            <Label className="text-xs font-semibold text-ink-2">الوصف</Label>
            <Textarea className="mt-1.5" rows={2} value={values.description} onChange={(e) => setValues({ ...values, description: e.target.value })} />
          </div>

          <div className="grid gap-3 sm:grid-cols-2">
            <div>
              <Label className="text-xs font-semibold text-ink-2">مالك المرحلة</Label>
              <Select
                value={values.owner_rule_type}
                onValueChange={(v) => setValues({ ...values, owner_rule_type: v as StageOwnerRuleType })}
              >
                <SelectTrigger className="mt-1.5"><SelectValue /></SelectTrigger>
                <SelectContent>
                  {STAGE_OWNER_RULE_OPTIONS.map((o) => (
                    <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            {needsOwnerValue && (
              <div>
                <Label className="text-xs font-semibold text-ink-2">
                  {values.owner_rule_type === 'role' ? 'الدور / الصلاحية' : 'معرّف المستخدم'}
                </Label>
                <Input
                  className="mt-1.5"
                  value={values.owner_rule_value}
                  onChange={(e) => setValues({ ...values, owner_rule_value: e.target.value })}
                  dir="ltr"
                  placeholder={values.owner_rule_type === 'role' ? 'publish-jobs' : '12'}
                />
              </div>
            )}
            <div>
              <Label className="text-xs font-semibold text-ink-2">مهلة الإنجاز (ساعات)</Label>
              <Input
                type="number"
                min={1}
                className="mt-1.5"
                value={values.sla_hours}
                onChange={(e) => setValues({ ...values, sla_hours: e.target.value })}
                placeholder="بدون مهلة"
              />
            </div>
          </div>

          <div className="rounded-lg border border-hairline bg-surface-2/40 p-3">
            <label className="flex items-center gap-2 text-sm text-ink">
              <input
                type="checkbox"
                checked={values.auto_generate_task}
                onChange={(e) => setValues({ ...values, auto_generate_task: e.target.checked })}
                className="h-4 w-4 rounded border-hairline"
              />
              توليد مهمة تلقائياً لمالك المرحلة عند الوصول إليها
            </label>
            {values.auto_generate_task && (
              <div className="mt-3 grid gap-3 sm:grid-cols-[1fr_160px]">
                <div>
                  <Label className="text-xs font-semibold text-ink-2">عنوان المهمة</Label>
                  <Input
                    className="mt-1.5"
                    value={values.task_title_template}
                    onChange={(e) => setValues({ ...values, task_title_template: e.target.value })}
                    placeholder="يُستخدم اسم المرحلة إن تُرك فارغاً"
                  />
                </div>
                <div>
                  <Label className="text-xs font-semibold text-ink-2">الأولوية</Label>
                  <Select
                    value={values.task_priority}
                    onValueChange={(v) => setValues({ ...values, task_priority: v as CasePriority })}
                  >
                    <SelectTrigger className="mt-1.5"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      {CASE_PRIORITY_OPTIONS.map((o) => (
                        <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              </div>
            )}
          </div>

          <div>
            <Label className="text-xs font-semibold text-ink-2">حقول مطلوبة قبل مغادرة المرحلة</Label>
            <div className="mt-1.5 flex flex-wrap gap-x-5 gap-y-2">
              {REQUIRABLE_FIELDS.map((f) => (
                <label key={f.key} className="flex items-center gap-2 text-sm text-ink">
                  <input
                    type="checkbox"
                    checked={values.requires_fields.includes(f.key)}
                    onChange={(e) => toggleRequiredField(f.key, e.target.checked)}
                    className="h-4 w-4 rounded border-hairline"
                  />
                  {f.label}
                </label>
              ))}
            </div>
          </div>

          <label className="flex items-center gap-2 text-sm text-ink">
            <input
              type="checkbox"
              checked={values.is_terminal}
              onChange={(e) => setValues({ ...values, is_terminal: e.target.checked })}
              className="h-4 w-4 rounded border-hairline"
            />
            مرحلة نهائية (تُقفل الوظيفة عند الوصول إليها)
          </label>

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
