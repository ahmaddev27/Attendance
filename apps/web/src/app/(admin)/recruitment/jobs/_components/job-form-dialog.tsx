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
import { OptionSelect } from '@/components/option-lists/option-select';
import {
  jobsApi,
  recruitmentCasesApi,
  recruitmentPipelinesApi,
} from '@/lib/api/endpoints/recruitment';
import {
  EMPLOYMENT_TYPE_OPTIONS,
  JOB_STATUS_OPTIONS,
  WORK_MODE_OPTIONS,
} from '@/lib/constants/recruitment-options';
import { useAuthStore } from '@/lib/stores/auth-store';
import type {
  JobEmploymentType,
  JobRequirement,
  JobRequirementPayload,
  JobRequirementStatus,
  WorkMode,
} from '@/lib/api/types';

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  job?: JobRequirement | null;
  defaultCaseId?: number;
};

const emptyValues = (ownerId?: number, caseId?: number) => ({
  recruitment_case_id: caseId ?? 0,
  pipeline_id: '',
  owner_id: ownerId ?? 0,
  title: '',
  department: '',
  openings: '1',
  employment_type: 'full_time' as JobEmploymentType,
  work_mode: 'onsite' as WorkMode,
  location: '',
  salary_min: '',
  salary_max: '',
  salary_currency: 'USD',
  required_experience_years: '',
  education_level: '',
  required_skills: '',
  nice_to_have_skills: '',
  required_languages: '',
  description: '',
  responsibilities: '',
  application_deadline: '',
  target_start_date: '',
  status: 'draft' as JobRequirementStatus,
});

export function JobFormDialog({ open, onOpenChange, job, defaultCaseId }: Props) {
  const qc = useQueryClient();
  const user = useAuthStore((s) => s.user);
  const isEdit = !!job;
  const [values, setValues] = React.useState(() => emptyValues(user?.id, defaultCaseId));
  const [ownerInput, setOwnerInput] = React.useState('');

  const { data: cases } = useQuery({
    queryKey: ['recruitment-cases-picker'],
    queryFn: async () => (await recruitmentCasesApi.list({ per_page: 100, open_only: true })).data.data,
    enabled: open && !defaultCaseId && !isEdit,
    staleTime: 60_000,
  });

  const { data: pipelines } = useQuery({
    queryKey: ['recruitment-pipelines-picker'],
    queryFn: async () => (await recruitmentPipelinesApi.list({ per_page: 50, active_only: true })).data.data,
    enabled: open,
    staleTime: 60_000,
  });

  React.useEffect(() => {
    if (open) {
      if (job) {
        setValues({
          recruitment_case_id: job.recruitment_case_id,
          pipeline_id: job.pipeline_id ? String(job.pipeline_id) : '',
          owner_id: job.owner_id,
          title: job.title,
          department: job.department ?? '',
          openings: String(job.openings),
          employment_type: job.employment_type,
          work_mode: job.work_mode,
          location: job.location ?? '',
          salary_min: job.salary_min?.toString() ?? '',
          salary_max: job.salary_max?.toString() ?? '',
          salary_currency: job.salary_currency ?? 'USD',
          required_experience_years: job.required_experience_years?.toString() ?? '',
          education_level: job.education_level ?? '',
          required_skills: (job.required_skills ?? []).join(', '),
          nice_to_have_skills: (job.nice_to_have_skills ?? []).join(', '),
          required_languages: (job.required_languages ?? []).join(', '),
          description: job.description ?? '',
          responsibilities: job.responsibilities ?? '',
          application_deadline: job.application_deadline ?? '',
          target_start_date: job.target_start_date ?? '',
          status: job.status,
        });
        setOwnerInput(String(job.owner_id));
      } else {
        setValues(emptyValues(user?.id, defaultCaseId));
        setOwnerInput(user?.id ? String(user.id) : '');
      }
    }
  }, [open, job, defaultCaseId, user?.id]);

  const parseList = (s: string): string[] | null => {
    const parts = s.split(',').map((p) => p.trim()).filter(Boolean);
    return parts.length ? parts : null;
  };

  const mutation = useMutation({
    mutationFn: () => {
      const nz = (v: string | undefined) => (v && v.trim() !== '' ? v.trim() : null);
      const payload: JobRequirementPayload = {
        recruitment_case_id: values.recruitment_case_id,
        pipeline_id: values.pipeline_id ? Number(values.pipeline_id) : null,
        owner_id: Number(ownerInput) || values.owner_id,
        title: values.title.trim(),
        department: nz(values.department),
        openings: Number(values.openings || '1'),
        employment_type: values.employment_type,
        work_mode: values.work_mode,
        location: nz(values.location),
        salary_min: values.salary_min ? Number(values.salary_min) : null,
        salary_max: values.salary_max ? Number(values.salary_max) : null,
        salary_currency: nz(values.salary_currency),
        required_experience_years: values.required_experience_years ? Number(values.required_experience_years) : null,
        education_level: nz(values.education_level),
        required_skills: parseList(values.required_skills),
        nice_to_have_skills: parseList(values.nice_to_have_skills),
        required_languages: parseList(values.required_languages),
        description: nz(values.description),
        responsibilities: nz(values.responsibilities),
        application_deadline: nz(values.application_deadline),
        target_start_date: nz(values.target_start_date),
        status: values.status,
      };
      if (isEdit && job) {
        const { recruitment_case_id: _r, pipeline_id: _p, ...rest } = payload;
        return jobsApi.update(job.id, rest);
      }
      return jobsApi.create(payload);
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['jobs'] });
      qc.invalidateQueries({ queryKey: ['case-jobs'] });
      qc.invalidateQueries({ queryKey: ['client-jobs'] });
      toast.success(isEdit ? 'تم تحديث الوظيفة' : 'تم إنشاء الوظيفة');
      onOpenChange(false);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'تعذر الحفظ');
    },
  });

  const canSubmit =
    values.title.trim().length > 0 &&
    !!values.recruitment_case_id &&
    !!Number(ownerInput) &&
    Number(values.openings) > 0;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-3xl">
        <DialogHeader>
          <DialogTitle>{isEdit ? 'تعديل وظيفة' : 'وظيفة جديدة'}</DialogTitle>
          <DialogDescription>حدد الحملة، والمالك، وتفاصيل الوظيفة.</DialogDescription>
        </DialogHeader>
        <form
          onSubmit={(e) => {
            e.preventDefault();
            if (canSubmit) mutation.mutate();
          }}
          className="space-y-3"
        >
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            {!defaultCaseId && !isEdit && (
              <div>
                <Label className="text-xs font-semibold text-ink-2">الحملة</Label>
                <Select
                  value={values.recruitment_case_id ? String(values.recruitment_case_id) : ''}
                  onValueChange={(v) => setValues({ ...values, recruitment_case_id: Number(v) })}
                >
                  <SelectTrigger className="mt-1.5"><SelectValue placeholder="اختر حملة" /></SelectTrigger>
                  <SelectContent>
                    {(cases ?? []).map((c) => (
                      <SelectItem key={c.id} value={String(c.id)}>
                        {c.title}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            )}
            {!isEdit && (
              <div>
                <Label className="text-xs font-semibold text-ink-2">المسار</Label>
                <Select value={values.pipeline_id} onValueChange={(v) => setValues({ ...values, pipeline_id: v })}>
                  <SelectTrigger className="mt-1.5"><SelectValue placeholder="مسار افتراضي" /></SelectTrigger>
                  <SelectContent>
                    {(pipelines ?? []).map((p) => (
                      <SelectItem key={p.id} value={String(p.id)}>
                        {p.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            )}
            <div className="sm:col-span-2">
              <Label className="text-xs font-semibold text-ink-2">عنوان الوظيفة</Label>
              <Input className="mt-1.5" value={values.title} onChange={(e) => setValues({ ...values, title: e.target.value })} />
            </div>
            <div>
              <Label className="text-xs font-semibold text-ink-2">الإدارة</Label>
              <Input className="mt-1.5" value={values.department} onChange={(e) => setValues({ ...values, department: e.target.value })} />
            </div>
            <div>
              <Label className="text-xs font-semibold text-ink-2">عدد الشواغر</Label>
              <Input type="number" min={1} className="mt-1.5" value={values.openings} onChange={(e) => setValues({ ...values, openings: e.target.value })} />
            </div>
            <div>
              <Label className="text-xs font-semibold text-ink-2">نوع التوظيف</Label>
              <Select value={values.employment_type} onValueChange={(v) => setValues({ ...values, employment_type: v as JobEmploymentType })}>
                <SelectTrigger className="mt-1.5"><SelectValue /></SelectTrigger>
                <SelectContent>
                  {EMPLOYMENT_TYPE_OPTIONS.map((o) => (<SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>))}
                </SelectContent>
              </Select>
            </div>
            <div>
              <Label className="text-xs font-semibold text-ink-2">نمط العمل</Label>
              <Select value={values.work_mode} onValueChange={(v) => setValues({ ...values, work_mode: v as WorkMode })}>
                <SelectTrigger className="mt-1.5"><SelectValue /></SelectTrigger>
                <SelectContent>
                  {WORK_MODE_OPTIONS.map((o) => (<SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>))}
                </SelectContent>
              </Select>
            </div>
            <div>
              <Label className="text-xs font-semibold text-ink-2">الموقع</Label>
              <Input className="mt-1.5" value={values.location} onChange={(e) => setValues({ ...values, location: e.target.value })} />
            </div>
            <div>
              <Label className="text-xs font-semibold text-ink-2">أدنى راتب</Label>
              <Input type="number" min={0} className="mt-1.5" value={values.salary_min} onChange={(e) => setValues({ ...values, salary_min: e.target.value })} />
            </div>
            <div>
              <Label className="text-xs font-semibold text-ink-2">أعلى راتب</Label>
              <Input type="number" min={0} className="mt-1.5" value={values.salary_max} onChange={(e) => setValues({ ...values, salary_max: e.target.value })} />
            </div>
            <div>
              <Label className="text-xs font-semibold text-ink-2">عملة الراتب</Label>
              <OptionSelect list="currencies" className="mt-1.5" value={values.salary_currency} onValueChange={(v) => setValues({ ...values, salary_currency: v })} allowEmpty showValue />
            </div>
            <div>
              <Label className="text-xs font-semibold text-ink-2">سنوات الخبرة</Label>
              <Input type="number" min={0} className="mt-1.5" value={values.required_experience_years} onChange={(e) => setValues({ ...values, required_experience_years: e.target.value })} />
            </div>
            <div>
              <Label className="text-xs font-semibold text-ink-2">المستوى التعليمي</Label>
              <OptionSelect list="education_levels" className="mt-1.5" value={values.education_level} onValueChange={(v) => setValues({ ...values, education_level: v })} allowEmpty />
            </div>
            <div>
              <Label className="text-xs font-semibold text-ink-2">رقم المالك (user id)</Label>
              <Input className="mt-1.5" value={ownerInput} onChange={(e) => setOwnerInput(e.target.value.replace(/[^0-9]/g, ''))} dir="ltr" />
            </div>
            <div>
              <Label className="text-xs font-semibold text-ink-2">الحالة</Label>
              <Select value={values.status} onValueChange={(v) => setValues({ ...values, status: v as JobRequirementStatus })}>
                <SelectTrigger className="mt-1.5"><SelectValue /></SelectTrigger>
                <SelectContent>
                  {JOB_STATUS_OPTIONS.map((o) => (<SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>))}
                </SelectContent>
              </Select>
            </div>
          </div>
          <div>
            <Label className="text-xs font-semibold text-ink-2">المهارات المطلوبة (مفصولة بفاصلة)</Label>
            <Input className="mt-1.5" value={values.required_skills} onChange={(e) => setValues({ ...values, required_skills: e.target.value })} placeholder="PHP, Laravel, PostgreSQL" />
          </div>
          <div>
            <Label className="text-xs font-semibold text-ink-2">مهارات مفضّلة</Label>
            <Input className="mt-1.5" value={values.nice_to_have_skills} onChange={(e) => setValues({ ...values, nice_to_have_skills: e.target.value })} />
          </div>
          <div>
            <Label className="text-xs font-semibold text-ink-2">اللغات المطلوبة</Label>
            <Input className="mt-1.5" value={values.required_languages} onChange={(e) => setValues({ ...values, required_languages: e.target.value })} placeholder="العربية, الإنجليزية" />
          </div>
          <div>
            <Label className="text-xs font-semibold text-ink-2">الوصف</Label>
            <Textarea className="mt-1.5" rows={3} value={values.description} onChange={(e) => setValues({ ...values, description: e.target.value })} />
          </div>
          <div>
            <Label className="text-xs font-semibold text-ink-2">المسؤوليات</Label>
            <Textarea className="mt-1.5" rows={3} value={values.responsibilities} onChange={(e) => setValues({ ...values, responsibilities: e.target.value })} />
          </div>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <Label className="text-xs font-semibold text-ink-2">آخر موعد للتقديم</Label>
              <Input type="date" className="mt-1.5" value={values.application_deadline} onChange={(e) => setValues({ ...values, application_deadline: e.target.value })} />
            </div>
            <div>
              <Label className="text-xs font-semibold text-ink-2">تاريخ البدء المستهدف</Label>
              <Input type="date" className="mt-1.5" value={values.target_start_date} onChange={(e) => setValues({ ...values, target_start_date: e.target.value })} />
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
