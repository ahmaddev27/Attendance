'use client';

import * as React from 'react';
import Link from 'next/link';
import { useParams, useRouter } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { ArrowRightLeft, ChevronRight, Mail, MapPin, Pencil, Phone, Plus, User } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { LeadStatusBadge } from '@/components/recruitment/status-badges';
import { LeadFormDialog } from '@/app/(admin)/recruitment/leads/_components/lead-form-dialog';
import { AddActivityDialog } from '@/app/(admin)/recruitment/leads/_components/add-activity-dialog';
import { useOptionLists } from '@/hooks/use-option-lists';
import { leadActivitiesApi, leadsApi } from '@/lib/api/endpoints/recruitment';
import { formatDate } from '@/lib/attendance-format';
import { LEAD_ACTIVITY_TYPE_LABELS } from '@/lib/constants/recruitment-options';
import { hasPermission, useAuthStore } from '@/lib/stores/auth-store';
import { cn } from '@/lib/utils';

/** Lead detail — contact card, meta, activity timeline, actions. */
export default function LeadDetailPage() {
  const params = useParams<{ id: string }>();
  const router = useRouter();
  const user = useAuthStore((s) => s.user);
  const canManage = hasPermission(user, 'manage-leads');
  const canConvert = hasPermission(user, 'convert-leads');

  const leadId = Number(params.id);
  const [editOpen, setEditOpen] = React.useState(false);
  const [activityOpen, setActivityOpen] = React.useState(false);

  const { labelOf } = useOptionLists();

  const { data: leadRes, isLoading } = useQuery({
    queryKey: ['leads', leadId],
    queryFn: async () => (await leadsApi.get(leadId)).data.data,
    enabled: Number.isFinite(leadId),
  });

  const { data: activitiesRes } = useQuery({
    queryKey: ['lead-activities', leadId],
    queryFn: async () => (await leadActivitiesApi.list(leadId, { per_page: 50 })).data,
    enabled: Number.isFinite(leadId),
  });

  if (isLoading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-24 rounded-xl" />
        <div className="grid gap-4 lg:grid-cols-3">
          <Skeleton className="h-72 rounded-xl lg:col-span-2" />
          <Skeleton className="h-72 rounded-xl" />
        </div>
      </div>
    );
  }

  if (!leadRes) {
    return <p className="p-8 text-center text-sm text-muted">لم يتم العثور على السجل.</p>;
  }

  const lead = leadRes;
  const activities = activitiesRes?.data ?? [];
  const isConverted = !!lead.converted_at;

  return (
    <div className="space-y-6">
      <nav className="flex items-center gap-1 text-xs text-muted">
        <Link href="/recruitment/leads" className="hover:text-brand-ink">
          العملاء المحتملون
        </Link>
        <ChevronRight className="h-3 w-3" />
        <span className="num" dir="ltr">
          {lead.lead_number}
        </span>
      </nav>

      <div className="rounded-xl border border-hairline bg-surface p-6">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <div className="flex items-center gap-3">
              <h1 className="text-2xl font-bold text-ink">{lead.company_name}</h1>
              <LeadStatusBadge status={lead.status} />
            </div>
            <p className="mt-1 num text-xs text-muted" dir="ltr">
              {lead.lead_number}
            </p>
            <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-ink-2">
              {lead.country && (
                <span className="flex items-center gap-1.5">
                  <MapPin className="h-3.5 w-3.5" /> {lead.country}
                  {lead.city ? ` — ${lead.city}` : ''}
                </span>
              )}
              {lead.industry && <span>{labelOf('industries', lead.industry)}</span>}
              <span>المصدر: {labelOf('lead_sources', lead.source)}</span>
              {lead.owner && (
                <span className="flex items-center gap-1.5">
                  <User className="h-3.5 w-3.5" /> {lead.owner.name}
                </span>
              )}
            </div>
          </div>
          <div className="flex flex-wrap gap-2">
            {canManage && !isConverted && (
              <Button type="button" variant="outline" className="gap-2" onClick={() => setActivityOpen(true)}>
                <Plus className="h-4 w-4" /> نشاط
              </Button>
            )}
            {canManage && (
              <Button type="button" variant="outline" className="gap-2" onClick={() => setEditOpen(true)}>
                <Pencil className="h-4 w-4" /> تعديل
              </Button>
            )}
            {canConvert && !isConverted && (
              <Button
                type="button"
                className="gap-2 bg-brand text-white hover:bg-brand-hover"
                onClick={() => router.push(`/recruitment/leads/${lead.id}/convert`)}
              >
                <ArrowRightLeft className="h-4 w-4" /> تحويل إلى عميل
              </Button>
            )}
          </div>
        </div>
        {isConverted && lead.converted_client && (
          <div className="mt-4 rounded-lg border border-success-soft bg-success-soft/50 p-3 text-sm text-success">
            تم تحويل هذا السجل إلى عميل{' '}
            <Link
              href={`/recruitment/clients/${lead.converted_client.id}`}
              className="font-semibold underline"
            >
              {lead.converted_client.company_name}
            </Link>{' '}
            بتاريخ{' '}
            <span className="num" dir="ltr">
              {formatDate(lead.converted_at)}
            </span>
            .
          </div>
        )}
      </div>

      <div className="grid gap-4 lg:grid-cols-3">
        <section className="space-y-4 lg:col-span-2">
          <div className="rounded-xl border border-hairline bg-surface p-5">
            <h2 className="mb-4 text-sm font-semibold text-ink">جهة الاتصال</h2>
            <dl className="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
              <MetaRow label="الاسم" value={lead.contact_person} />
              <MetaRow label="المسمى" value={lead.contact_position} />
              <MetaRow
                label="البريد"
                value={
                  lead.contact_email ? (
                    <a href={`mailto:${lead.contact_email}`} className="flex items-center gap-1 text-brand-ink hover:underline" dir="ltr">
                      <Mail className="h-3.5 w-3.5" /> {lead.contact_email}
                    </a>
                  ) : null
                }
              />
              <MetaRow
                label="الهاتف"
                value={
                  lead.contact_phone ? (
                    <a href={`tel:${lead.contact_phone}`} className="flex items-center gap-1 text-brand-ink hover:underline" dir="ltr">
                      <Phone className="h-3.5 w-3.5" /> {lead.contact_phone}
                    </a>
                  ) : null
                }
              />
              <MetaRow
                label="لينكدإن"
                value={
                  lead.linkedin_url ? (
                    <a href={lead.linkedin_url} target="_blank" rel="noreferrer" className="text-brand-ink hover:underline" dir="ltr">
                      رابط
                    </a>
                  ) : null
                }
              />
              <MetaRow label="حجم التوظيف المتوقع" value={lead.expected_hiring_volume ?? null} isNumber />
            </dl>
            {lead.notes && (
              <div className="mt-4 border-t border-hairline pt-4">
                <p className="text-xs font-semibold text-ink-2">ملاحظات</p>
                <p className="mt-1 whitespace-pre-wrap text-sm text-ink">{lead.notes}</p>
              </div>
            )}
          </div>

          <div className="rounded-xl border border-hairline bg-surface p-5">
            <div className="mb-4 flex items-center justify-between">
              <h2 className="text-sm font-semibold text-ink">النشاطات</h2>
              {canManage && !isConverted && (
                <Button type="button" size="sm" variant="outline" className="gap-1.5" onClick={() => setActivityOpen(true)}>
                  <Plus className="h-3.5 w-3.5" /> إضافة
                </Button>
              )}
            </div>
            {activities.length === 0 ? (
              <p className="py-6 text-center text-sm text-muted">لا نشاطات مسجلة بعد.</p>
            ) : (
              <ol className="space-y-4">
                {activities.map((a) => (
                  <li key={a.id} className="flex gap-3">
                    <div className="mt-1 h-2 w-2 shrink-0 rounded-full bg-brand" aria-hidden="true" />
                    <div className="min-w-0 flex-1">
                      <div className="flex flex-wrap items-center gap-2 text-xs text-ink-2">
                        <span className="font-semibold text-ink">
                          {LEAD_ACTIVITY_TYPE_LABELS[a.type] ?? a.type}
                        </span>
                        {a.user && <span>· {a.user.name}</span>}
                        <span className="num" dir="ltr">
                          · {formatDateTime(a.occurred_at ?? a.created_at)}
                        </span>
                      </div>
                      {a.subject && <p className="mt-1 text-sm font-medium text-ink">{a.subject}</p>}
                      {a.body && <p className="mt-1 whitespace-pre-wrap text-sm text-ink-2">{a.body}</p>}
                    </div>
                  </li>
                ))}
              </ol>
            )}
          </div>
        </section>

        <aside className="space-y-4">
          <div className="rounded-xl border border-hairline bg-surface p-5">
            <h2 className="mb-4 text-sm font-semibold text-ink">التفاصيل</h2>
            <dl className="space-y-3">
              <MetaRow label="آخر تواصل" value={lead.last_contact_at ? formatDateTime(lead.last_contact_at) : null} isNumber />
              <MetaRow label="متابعة تالية" value={lead.next_followup_at ? formatDateTime(lead.next_followup_at) : null} isNumber />
              <MetaRow label="حجم الشركة" value={labelOf('company_sizes', lead.company_size)} />
              <MetaRow
                label="موقع الشركة"
                value={
                  lead.company_website ? (
                    <a href={lead.company_website} target="_blank" rel="noreferrer" className="text-brand-ink hover:underline" dir="ltr">
                      زيارة
                    </a>
                  ) : null
                }
              />
              <MetaRow label="أنشئ في" value={formatDateTime(lead.created_at)} isNumber />
              <MetaRow label="آخر تحديث" value={formatDateTime(lead.updated_at)} isNumber />
              {lead.lost_reason && <MetaRow label="سبب الخسارة" value={lead.lost_reason} />}
            </dl>
          </div>
        </aside>
      </div>

      <LeadFormDialog open={editOpen} onOpenChange={setEditOpen} lead={lead} />
      <AddActivityDialog open={activityOpen} onOpenChange={setActivityOpen} leadId={lead.id} />
    </div>
  );
}

function MetaRow({
  label,
  value,
  isNumber = false,
}: {
  label: string;
  value: React.ReactNode | string | number | null;
  isNumber?: boolean;
}) {
  const display: React.ReactNode = value === null || value === undefined || value === '' ? (
    <span className="text-xs text-muted">—</span>
  ) : (
    value
  );
  return (
    <div className="grid grid-cols-[100px_1fr] items-baseline gap-3">
      <dt className="text-xs text-muted">{label}</dt>
      <dd className={cn('text-sm text-ink', isNumber && 'num')} dir={isNumber ? 'ltr' : undefined}>
        {display}
      </dd>
    </div>
  );
}

function formatDateTime(iso: string | null | undefined): string {
  if (!iso) return '—';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '—';
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}
