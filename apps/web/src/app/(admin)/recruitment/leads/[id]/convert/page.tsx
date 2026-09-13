'use client';

import * as React from 'react';
import Link from 'next/link';
import { useParams, useRouter } from 'next/navigation';
import { useMutation, useQuery } from '@tanstack/react-query';
import { ChevronLeft, ChevronRight, Plus, Trash2 } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { clientsApi, leadsApi } from '@/lib/api/endpoints/recruitment';
import {
  CASE_PRIORITY_OPTIONS,
  EMPLOYMENT_TYPE_OPTIONS,
  WORK_MODE_OPTIONS,
} from '@/lib/constants/recruitment-options';
import { useAuthStore } from '@/lib/stores/auth-store';
import { cn } from '@/lib/utils';
import type {
  CasePriority,
  Client,
  ConvertLeadJobPayload,
  ConvertLeadPayload,
  JobEmploymentType,
  WorkMode,
} from '@/lib/api/types';

type ClientMode = 'create' | 'reuse';

type ClientDraft = {
  company_name: string;
  country: string;
  city: string;
  industry: string;
  company_size: string;
  company_website: string;
};

type CaseDraft = {
  title: string;
  description: string;
  priority: CasePriority;
  target_hires: string;
  started_at: string;
  deadline: string;
};

type JobDraft = {
  title: string;
  department: string;
  openings: string;
  employment_type: JobEmploymentType;
  work_mode: WorkMode;
  location: string;
  salary_min: string;
  salary_max: string;
  salary_currency: string;
  description: string;
};

const emptyJob = (): JobDraft => ({
  title: '',
  department: '',
  openings: '1',
  employment_type: 'full_time',
  work_mode: 'onsite',
  location: '',
  salary_min: '',
  salary_max: '',
  salary_currency: 'USD',
  description: '',
});

/**
 * Three-step wizard: (1) client — reuse existing or seed from the lead,
 * (2) recruitment case, (3) optional initial jobs. Posts the whole
 * bundle to POST /leads/{id}/convert.
 */
export default function ConvertLeadPage() {
  const params = useParams<{ id: string }>();
  const router = useRouter();
  const user = useAuthStore((s) => s.user);
  const leadId = Number(params.id);
  const [step, setStep] = React.useState(1);

  const { data: leadRes, isLoading } = useQuery({
    queryKey: ['leads', leadId],
    queryFn: async () => (await leadsApi.get(leadId)).data.data,
    enabled: Number.isFinite(leadId),
  });

  const [clientMode, setClientMode] = React.useState<ClientMode>('create');
  const [reuseClientId, setReuseClientId] = React.useState<number | null>(null);
  const [reuseSearch, setReuseSearch] = React.useState('');
  const [clientDraft, setClientDraft] = React.useState<ClientDraft>({
    company_name: '',
    country: '',
    city: '',
    industry: '',
    company_size: '',
    company_website: '',
  });
  const [caseDraft, setCaseDraft] = React.useState<CaseDraft>({
    title: '',
    description: '',
    priority: 'normal',
    target_hires: '',
    started_at: '',
    deadline: '',
  });
  const [jobs, setJobs] = React.useState<JobDraft[]>([]);
  const [prefilled, setPrefilled] = React.useState(false);

  const { data: clientOptions } = useQuery({
    queryKey: ['clients-lookup', reuseSearch],
    queryFn: async () => (await clientsApi.list({ search: reuseSearch || undefined, per_page: 15 })).data.data,
    enabled: clientMode === 'reuse',
  });

  // Prefill from lead once loaded.
  React.useEffect(() => {
    if (!leadRes || prefilled) return;
    setClientDraft({
      company_name: leadRes.company_name,
      country: leadRes.country ?? '',
      city: leadRes.city ?? '',
      industry: leadRes.industry ?? '',
      company_size: leadRes.company_size ?? '',
      company_website: leadRes.company_website ?? '',
    });
    setCaseDraft((prev) => ({
      ...prev,
      title: `حملة ${leadRes.company_name}`,
    }));
    setPrefilled(true);
  }, [leadRes, prefilled]);

  const convertMutation = useMutation({
    mutationFn: () => {
      if (!user) throw new Error('missing-user');
      const payload: ConvertLeadPayload = {
        case: {
          title: caseDraft.title.trim(),
          description: caseDraft.description.trim() || null,
          priority: caseDraft.priority,
          target_hires: caseDraft.target_hires ? Number(caseDraft.target_hires) : null,
          started_at: caseDraft.started_at || null,
          deadline: caseDraft.deadline || null,
          owner_id: user.id,
        },
      };
      if (clientMode === 'reuse') {
        if (!reuseClientId) throw new Error('اختر عميلاً موجوداً أولاً');
        payload.reuse_client_id = reuseClientId;
      } else {
        payload.client = {
          company_name: clientDraft.company_name.trim(),
          country: clientDraft.country.trim() || null,
          city: clientDraft.city.trim() || null,
          industry: clientDraft.industry.trim() || null,
          company_size: clientDraft.company_size.trim() || null,
          company_website: clientDraft.company_website.trim() || null,
          account_manager_id: user.id,
        };
      }
      if (jobs.length > 0) {
        payload.jobs = jobs.map<ConvertLeadJobPayload>((j) => ({
          title: j.title.trim(),
          department: j.department.trim() || null,
          openings: Number(j.openings || '1'),
          employment_type: j.employment_type,
          work_mode: j.work_mode,
          location: j.location.trim() || null,
          salary_min: j.salary_min ? Number(j.salary_min) : null,
          salary_max: j.salary_max ? Number(j.salary_max) : null,
          salary_currency: j.salary_currency.trim() || null,
          description: j.description.trim() || null,
          owner_id: user.id,
        }));
      }
      return leadsApi.convert(leadId, payload);
    },
    onSuccess: (res) => {
      toast.success('تم تحويل العميل المحتمل بنجاح');
      const clientId = res.data.data.client.id;
      router.push(`/recruitment/clients/${clientId}`);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      const errors = (err as { response?: { data?: { errors?: Record<string, string[]> } } })?.response?.data?.errors;
      const first = errors ? Object.values(errors)[0]?.[0] : undefined;
      toast.error(first || message || 'تعذر إتمام التحويل');
    },
  });

  if (isLoading) {
    return <Skeleton className="h-96 rounded-xl" />;
  }
  if (!leadRes) {
    return <p className="p-8 text-center text-sm text-muted">لم يتم العثور على السجل.</p>;
  }
  if (leadRes.converted_at) {
    return (
      <div className="rounded-xl border border-hairline bg-surface p-6 text-center">
        <p className="text-sm text-ink">هذا العميل المحتمل تم تحويله بالفعل.</p>
        <Button asChild variant="outline" className="mt-4">
          <Link href={`/recruitment/leads/${leadRes.id}`}>عودة</Link>
        </Button>
      </div>
    );
  }

  const canGoNext = (() => {
    if (step === 1) {
      if (clientMode === 'reuse') return !!reuseClientId;
      return clientDraft.company_name.trim().length > 0;
    }
    if (step === 2) return caseDraft.title.trim().length > 0;
    if (step === 3) {
      // Jobs may be empty; but if any exist, all must have title + openings.
      return jobs.every((j) => j.title.trim() && Number(j.openings) > 0);
    }
    return false;
  })();

  return (
    <div className="space-y-6">
      <nav className="flex items-center gap-1 text-xs text-muted">
        <Link href="/recruitment/leads" className="hover:text-brand-ink">
          العملاء المحتملون
        </Link>
        <ChevronRight className="h-3 w-3" />
        <Link href={`/recruitment/leads/${leadId}`} className="num hover:text-brand-ink" dir="ltr">
          {leadRes.lead_number}
        </Link>
        <ChevronRight className="h-3 w-3" />
        <span>تحويل</span>
      </nav>

      <div>
        <p className="text-xs font-medium text-muted">التوظيف</p>
        <h1 className="mt-1 text-2xl font-bold text-ink">تحويل {leadRes.company_name} إلى عميل</h1>
      </div>

      <StepsBar step={step} labels={['العميل', 'الحملة', 'الوظائف (اختياري)']} />

      {step === 1 && (
        <div className="space-y-4 rounded-xl border border-hairline bg-surface p-5">
          <div className="flex gap-2">
            <ModeButton active={clientMode === 'create'} onClick={() => setClientMode('create')}>
              إنشاء عميل جديد
            </ModeButton>
            <ModeButton active={clientMode === 'reuse'} onClick={() => setClientMode('reuse')}>
              استخدام عميل قائم
            </ModeButton>
          </div>

          {clientMode === 'create' && (
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <FieldLabel label="اسم الشركة">
                <Input value={clientDraft.company_name} onChange={(e) => setClientDraft({ ...clientDraft, company_name: e.target.value })} />
              </FieldLabel>
              <FieldLabel label="الدولة">
                <Input value={clientDraft.country} onChange={(e) => setClientDraft({ ...clientDraft, country: e.target.value })} />
              </FieldLabel>
              <FieldLabel label="المدينة">
                <Input value={clientDraft.city} onChange={(e) => setClientDraft({ ...clientDraft, city: e.target.value })} />
              </FieldLabel>
              <FieldLabel label="القطاع">
                <Input value={clientDraft.industry} onChange={(e) => setClientDraft({ ...clientDraft, industry: e.target.value })} />
              </FieldLabel>
              <FieldLabel label="حجم الشركة">
                <Input value={clientDraft.company_size} onChange={(e) => setClientDraft({ ...clientDraft, company_size: e.target.value })} />
              </FieldLabel>
              <FieldLabel label="الموقع">
                <Input value={clientDraft.company_website} onChange={(e) => setClientDraft({ ...clientDraft, company_website: e.target.value })} placeholder="https://" />
              </FieldLabel>
            </div>
          )}

          {clientMode === 'reuse' && (
            <div className="space-y-3">
              <FieldLabel label="ابحث عن عميل">
                <Input value={reuseSearch} onChange={(e) => setReuseSearch(e.target.value)} placeholder="اسم الشركة أو رقمها" />
              </FieldLabel>
              <ul className="max-h-72 divide-y divide-hairline overflow-y-auto rounded-md border border-hairline">
                {(clientOptions ?? []).map((c: Client) => (
                  <li key={c.id}>
                    <button
                      type="button"
                      onClick={() => setReuseClientId(c.id)}
                      className={cn(
                        'flex w-full items-center justify-between gap-3 px-3 py-2 text-start hover:bg-surface-2',
                        reuseClientId === c.id && 'bg-brand-soft',
                      )}
                    >
                      <div>
                        <p className="text-sm font-medium text-ink">{c.company_name}</p>
                        <p className="num text-xs text-muted" dir="ltr">
                          {c.client_number}
                          {c.country ? ` · ${c.country}` : ''}
                        </p>
                      </div>
                    </button>
                  </li>
                ))}
                {(clientOptions ?? []).length === 0 && (
                  <li className="p-3 text-center text-sm text-muted">لا نتائج</li>
                )}
              </ul>
            </div>
          )}
        </div>
      )}

      {step === 2 && (
        <div className="space-y-4 rounded-xl border border-hairline bg-surface p-5">
          <FieldLabel label="عنوان الحملة">
            <Input value={caseDraft.title} onChange={(e) => setCaseDraft({ ...caseDraft, title: e.target.value })} />
          </FieldLabel>
          <FieldLabel label="الوصف">
            <Textarea rows={3} value={caseDraft.description} onChange={(e) => setCaseDraft({ ...caseDraft, description: e.target.value })} />
          </FieldLabel>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <FieldLabel label="الأولوية">
              <Select value={caseDraft.priority} onValueChange={(v) => setCaseDraft({ ...caseDraft, priority: v as CasePriority })}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {CASE_PRIORITY_OPTIONS.map((opt) => (
                    <SelectItem key={opt.value} value={opt.value}>
                      {opt.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </FieldLabel>
            <FieldLabel label="عدد التوظيفات المستهدفة">
              <Input type="number" min={0} value={caseDraft.target_hires} onChange={(e) => setCaseDraft({ ...caseDraft, target_hires: e.target.value })} />
            </FieldLabel>
            <FieldLabel label="تاريخ البدء">
              <Input type="date" value={caseDraft.started_at} onChange={(e) => setCaseDraft({ ...caseDraft, started_at: e.target.value })} />
            </FieldLabel>
          </div>
          <FieldLabel label="تاريخ الإغلاق">
            <Input type="date" value={caseDraft.deadline} onChange={(e) => setCaseDraft({ ...caseDraft, deadline: e.target.value })} />
          </FieldLabel>
        </div>
      )}

      {step === 3 && (
        <div className="space-y-4">
          {jobs.map((job, index) => (
            <div key={index} className="space-y-3 rounded-xl border border-hairline bg-surface p-5">
              <div className="flex items-center justify-between">
                <h3 className="text-sm font-semibold text-ink">وظيفة #{index + 1}</h3>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  className="text-danger hover:bg-danger-soft"
                  aria-label="حذف"
                  onClick={() => setJobs(jobs.filter((_, i) => i !== index))}
                >
                  <Trash2 className="h-4 w-4" />
                </Button>
              </div>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <FieldLabel label="عنوان الوظيفة">
                  <Input value={job.title} onChange={(e) => updateJob(index, { title: e.target.value })} />
                </FieldLabel>
                <FieldLabel label="الإدارة">
                  <Input value={job.department} onChange={(e) => updateJob(index, { department: e.target.value })} />
                </FieldLabel>
                <FieldLabel label="عدد الشواغر">
                  <Input type="number" min={1} value={job.openings} onChange={(e) => updateJob(index, { openings: e.target.value })} />
                </FieldLabel>
                <FieldLabel label="نمط العمل">
                  <Select value={job.work_mode} onValueChange={(v) => updateJob(index, { work_mode: v as WorkMode })}>
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {WORK_MODE_OPTIONS.map((opt) => (
                        <SelectItem key={opt.value} value={opt.value}>
                          {opt.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </FieldLabel>
                <FieldLabel label="نوع التوظيف">
                  <Select value={job.employment_type} onValueChange={(v) => updateJob(index, { employment_type: v as JobEmploymentType })}>
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {EMPLOYMENT_TYPE_OPTIONS.map((opt) => (
                        <SelectItem key={opt.value} value={opt.value}>
                          {opt.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </FieldLabel>
                <FieldLabel label="الموقع">
                  <Input value={job.location} onChange={(e) => updateJob(index, { location: e.target.value })} />
                </FieldLabel>
                <FieldLabel label="أدنى راتب">
                  <Input type="number" min={0} value={job.salary_min} onChange={(e) => updateJob(index, { salary_min: e.target.value })} />
                </FieldLabel>
                <FieldLabel label="أعلى راتب">
                  <Input type="number" min={0} value={job.salary_max} onChange={(e) => updateJob(index, { salary_max: e.target.value })} />
                </FieldLabel>
                <FieldLabel label="عملة الراتب">
                  <Input value={job.salary_currency} onChange={(e) => updateJob(index, { salary_currency: e.target.value.toUpperCase().slice(0, 3) })} />
                </FieldLabel>
              </div>
              <FieldLabel label="الوصف">
                <Textarea rows={2} value={job.description} onChange={(e) => updateJob(index, { description: e.target.value })} />
              </FieldLabel>
            </div>
          ))}
          <Button
            type="button"
            variant="outline"
            className="gap-2"
            onClick={() => setJobs([...jobs, emptyJob()])}
          >
            <Plus className="h-4 w-4" />
            إضافة وظيفة
          </Button>
        </div>
      )}

      <div className="flex flex-wrap items-center justify-between gap-3">
        <Button type="button" variant="outline" disabled={step === 1} onClick={() => setStep(step - 1)}>
          <ChevronRight className="ms-1 h-4 w-4" /> السابق
        </Button>
        {step < 3 ? (
          <Button
            type="button"
            className="bg-brand text-white hover:bg-brand-hover"
            disabled={!canGoNext}
            onClick={() => setStep(step + 1)}
          >
            التالي <ChevronLeft className="me-1 h-4 w-4" />
          </Button>
        ) : (
          <Button
            type="button"
            className="bg-success text-white hover:bg-success/90"
            disabled={!canGoNext || convertMutation.isPending}
            onClick={() => convertMutation.mutate()}
          >
            {convertMutation.isPending && <Spinner className="text-white" />}
            إتمام التحويل
          </Button>
        )}
      </div>
    </div>
  );

  function updateJob(index: number, patch: Partial<JobDraft>) {
    setJobs(jobs.map((j, i) => (i === index ? { ...j, ...patch } : j)));
  }
}

function StepsBar({ step, labels }: { step: number; labels: string[] }) {
  return (
    <ol className="flex items-center gap-2">
      {labels.map((label, i) => {
        const n = i + 1;
        const active = step === n;
        const done = step > n;
        return (
          <li key={label} className="flex flex-1 items-center gap-2">
            <div
              className={cn(
                'grid h-8 w-8 shrink-0 place-items-center rounded-full text-xs font-bold',
                done && 'bg-success text-white',
                active && 'bg-brand text-white',
                !done && !active && 'bg-surface-2 text-ink-2',
              )}
            >
              {n}
            </div>
            <span className={cn('text-sm', active ? 'font-semibold text-ink' : 'text-ink-2')}>{label}</span>
            {i < labels.length - 1 && <div className="mx-2 h-px flex-1 bg-hairline" />}
          </li>
        );
      })}
    </ol>
  );
}

function ModeButton({ active, onClick, children }: { active: boolean; onClick: () => void; children: React.ReactNode }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={cn(
        'rounded-md border px-3 py-1.5 text-sm transition-colors',
        active ? 'border-brand bg-brand-soft text-brand-ink' : 'border-hairline bg-surface text-ink-2 hover:bg-surface-2',
      )}
    >
      {children}
    </button>
  );
}

function FieldLabel({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="space-y-1.5">
      <Label className="text-xs font-semibold text-ink-2">{label}</Label>
      {children}
    </div>
  );
}
