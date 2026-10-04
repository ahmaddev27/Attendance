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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { candidatesApi, CANDIDATE_STATUS_LABEL } from '@/lib/api/endpoints/candidates';
import type { Candidate, CandidatePayload, CandidateStatus } from '@/lib/api/types';

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  candidate?: Candidate | null;
};

const AVAILABILITY_LABEL: Record<string, string> = {
  immediate: 'فوري',
  '2_weeks': 'أسبوعين',
  '1_month': 'شهر',
  negotiable: 'قابل للتفاوض',
};

const empty = (): FormState => ({
  full_name: '',
  email: '',
  phone: '',
  country: '',
  city: '',
  linkedin_url: '',
  portfolio_url: '',
  headline: '',
  current_title: '',
  current_company: '',
  years_of_experience: '',
  expected_salary_min: '',
  expected_salary_max: '',
  salary_currency: 'USD',
  availability: '',
  skills: '',
  languages: '',
  notes: '',
  status: 'active',
});

type FormState = {
  full_name: string;
  email: string;
  phone: string;
  country: string;
  city: string;
  linkedin_url: string;
  portfolio_url: string;
  headline: string;
  current_title: string;
  current_company: string;
  years_of_experience: string;
  expected_salary_min: string;
  expected_salary_max: string;
  salary_currency: string;
  availability: string;
  skills: string;
  languages: string;
  notes: string;
  status: CandidateStatus;
};

const splitCsv = (raw: string): string[] =>
  raw
    .split(',')
    .map((item) => item.trim())
    .filter((item) => item.length > 0);

export function CandidateFormDialog({ open, onOpenChange, candidate }: Props) {
  const qc = useQueryClient();
  const [values, setValues] = React.useState<FormState>(empty);
  const [localError, setLocalError] = React.useState<string | null>(null);

  const isEdit = !!candidate;

  React.useEffect(() => {
    if (!open) return;
    if (candidate) {
      setValues({
        full_name: candidate.full_name,
        email: candidate.email ?? '',
        phone: candidate.phone ?? '',
        country: candidate.country ?? '',
        city: candidate.city ?? '',
        linkedin_url: candidate.linkedin_url ?? '',
        portfolio_url: candidate.portfolio_url ?? '',
        headline: candidate.headline ?? '',
        current_title: candidate.current_title ?? '',
        current_company: candidate.current_company ?? '',
        years_of_experience:
          candidate.years_of_experience !== null ? String(candidate.years_of_experience) : '',
        expected_salary_min:
          candidate.expected_salary_min !== null ? String(candidate.expected_salary_min) : '',
        expected_salary_max:
          candidate.expected_salary_max !== null ? String(candidate.expected_salary_max) : '',
        salary_currency: candidate.salary_currency ?? 'USD',
        availability: candidate.availability ?? '',
        skills: (candidate.skills ?? []).join(', '),
        languages: (candidate.languages ?? []).join(', '),
        notes: candidate.notes ?? '',
        status: candidate.status,
      });
    } else {
      setValues(empty());
    }
    setLocalError(null);
  }, [open, candidate]);

  const mutation = useMutation({
    mutationFn: async (payload: CandidatePayload) => {
      if (candidate) {
        const res = await candidatesApi.update(candidate.id, payload);
        return res.data.data;
      }
      const res = await candidatesApi.create(payload);
      return res.data.data;
    },
    onSuccess: () => {
      toast.success(isEdit ? 'تم تحديث المرشّح' : 'تم إضافة المرشّح');
      qc.invalidateQueries({ queryKey: ['candidates'] });
      onOpenChange(false);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || (isEdit ? 'تعذر التحديث' : 'تعذر الحفظ'));
    },
  });

  const buildPayload = (): CandidatePayload | null => {
    if (!values.full_name.trim()) {
      setLocalError('الاسم الكامل مطلوب.');
      return null;
    }
    if (!values.email.trim() && !values.phone.trim()) {
      setLocalError('يجب إدخال البريد الإلكتروني أو رقم الهاتف على الأقل.');
      return null;
    }
    setLocalError(null);
    return {
      full_name: values.full_name.trim(),
      email: values.email.trim() || null,
      phone: values.phone.trim() || null,
      country: values.country.trim() || null,
      city: values.city.trim() || null,
      linkedin_url: values.linkedin_url.trim() || null,
      portfolio_url: values.portfolio_url.trim() || null,
      headline: values.headline.trim() || null,
      current_title: values.current_title.trim() || null,
      current_company: values.current_company.trim() || null,
      years_of_experience: values.years_of_experience ? Number(values.years_of_experience) : null,
      expected_salary_min: values.expected_salary_min ? Number(values.expected_salary_min) : null,
      expected_salary_max: values.expected_salary_max ? Number(values.expected_salary_max) : null,
      salary_currency: values.salary_currency.trim() || null,
      availability: (values.availability || null) as CandidatePayload['availability'],
      skills: values.skills ? splitCsv(values.skills) : null,
      languages: values.languages ? splitCsv(values.languages) : null,
      notes: values.notes.trim() || null,
      status: values.status,
    };
  };

  const handleSubmit = (event: React.FormEvent) => {
    event.preventDefault();
    const payload = buildPayload();
    if (!payload) return;
    mutation.mutate(payload);
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{isEdit ? 'تعديل مرشّح' : 'مرشّح جديد'}</DialogTitle>
          <DialogDescription>
            كل الحقول اختيارية باستثناء الاسم؛ يجب إدخال البريد الإلكتروني أو رقم الهاتف على الأقل.
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid gap-3 sm:grid-cols-2">
            <Field label="الاسم الكامل" required>
              <Input
                value={values.full_name}
                onChange={(e) => setValues({ ...values, full_name: e.target.value })}
                maxLength={200}
              />
            </Field>
            <Field label="المسمى المختصر (العنوان)">
              <Input
                value={values.headline}
                onChange={(e) => setValues({ ...values, headline: e.target.value })}
                maxLength={200}
              />
            </Field>
            <Field label="البريد الإلكتروني">
              <Input
                type="email"
                dir="ltr"
                value={values.email}
                onChange={(e) => setValues({ ...values, email: e.target.value })}
              />
            </Field>
            <Field label="رقم الهاتف">
              <Input
                dir="ltr"
                value={values.phone}
                onChange={(e) => setValues({ ...values, phone: e.target.value })}
              />
            </Field>
            <Field label="الدولة">
              <Input
                value={values.country}
                onChange={(e) => setValues({ ...values, country: e.target.value })}
              />
            </Field>
            <Field label="المدينة">
              <Input
                value={values.city}
                onChange={(e) => setValues({ ...values, city: e.target.value })}
              />
            </Field>
            <Field label="LinkedIn">
              <Input
                dir="ltr"
                value={values.linkedin_url}
                onChange={(e) => setValues({ ...values, linkedin_url: e.target.value })}
              />
            </Field>
            <Field label="رابط الأعمال">
              <Input
                dir="ltr"
                value={values.portfolio_url}
                onChange={(e) => setValues({ ...values, portfolio_url: e.target.value })}
              />
            </Field>
            <Field label="المسمى الحالي">
              <Input
                value={values.current_title}
                onChange={(e) => setValues({ ...values, current_title: e.target.value })}
              />
            </Field>
            <Field label="الشركة الحالية">
              <Input
                value={values.current_company}
                onChange={(e) => setValues({ ...values, current_company: e.target.value })}
              />
            </Field>
            <Field label="سنوات الخبرة">
              <Input
                type="number"
                min={0}
                max={60}
                dir="ltr"
                value={values.years_of_experience}
                onChange={(e) => setValues({ ...values, years_of_experience: e.target.value })}
              />
            </Field>
            <Field label="التوفر">
              <Select
                value={values.availability || '__none__'}
                onValueChange={(v) => setValues({ ...values, availability: v === '__none__' ? '' : v })}
              >
                <SelectTrigger>
                  <SelectValue placeholder="اختر" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="__none__">—</SelectItem>
                  {Object.entries(AVAILABILITY_LABEL).map(([v, l]) => (
                    <SelectItem key={v} value={v}>
                      {l}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>
            <Field label="الراتب المتوقّع — الأدنى">
              <Input
                type="number"
                min={0}
                dir="ltr"
                value={values.expected_salary_min}
                onChange={(e) => setValues({ ...values, expected_salary_min: e.target.value })}
              />
            </Field>
            <Field label="الراتب المتوقّع — الأعلى">
              <Input
                type="number"
                min={0}
                dir="ltr"
                value={values.expected_salary_max}
                onChange={(e) => setValues({ ...values, expected_salary_max: e.target.value })}
              />
            </Field>
            <Field label="العملة">
              <Input
                maxLength={3}
                dir="ltr"
                value={values.salary_currency}
                onChange={(e) => setValues({ ...values, salary_currency: e.target.value.toUpperCase() })}
              />
            </Field>
            <Field label="الحالة">
              <Select
                value={values.status}
                onValueChange={(v) => setValues({ ...values, status: v as CandidateStatus })}
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {Object.entries(CANDIDATE_STATUS_LABEL).map(([v, l]) => (
                    <SelectItem key={v} value={v}>
                      {l}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>
          </div>

          <Field label="المهارات (افصل بينها بفواصل)">
            <Input
              value={values.skills}
              onChange={(e) => setValues({ ...values, skills: e.target.value })}
              placeholder="PHP, Laravel, Next.js"
            />
          </Field>
          <Field label="اللغات (افصل بينها بفواصل)">
            <Input
              value={values.languages}
              onChange={(e) => setValues({ ...values, languages: e.target.value })}
              placeholder="العربية, الإنجليزية"
            />
          </Field>
          <Field label="ملاحظات">
            <Textarea
              value={values.notes}
              onChange={(e) => setValues({ ...values, notes: e.target.value })}
              rows={3}
            />
          </Field>

          {localError && <p className="text-xs text-danger">{localError}</p>}

          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              إلغاء
            </Button>
            <Button
              type="submit"
              className="bg-brand text-white hover:bg-brand-hover"
              disabled={mutation.isPending}
            >
              {mutation.isPending && <Spinner className="me-2 h-4 w-4" />}
              {isEdit ? 'حفظ التعديلات' : 'إضافة'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function Field({
  label,
  required,
  children,
}: {
  label: string;
  required?: boolean;
  children: React.ReactNode;
}) {
  return (
    <div className="space-y-1.5">
      <Label className="text-xs text-ink-2">
        {label}
        {required && <span className="ms-1 text-danger">*</span>}
      </Label>
      {children}
    </div>
  );
}
