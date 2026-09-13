'use client';

import * as React from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { ChevronRight, Pencil } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { CaseStatusBadge, ClientStatusBadge, JobStatusBadge } from '@/components/recruitment/status-badges';
import { ClientFormDialog } from '@/app/(admin)/recruitment/clients/_components/client-form-dialog';
import { ContactsPanel } from '@/app/(admin)/recruitment/clients/[id]/_components/contacts-panel';
import { clientsApi, jobsApi, recruitmentCasesApi } from '@/lib/api/endpoints/recruitment';
import { formatDate } from '@/lib/attendance-format';
import { CLIENT_STATUS_META } from '@/lib/constants/recruitment-options';
import { hasPermission, useAuthStore } from '@/lib/stores/auth-store';
import type { JobRequirement, RecruitmentCase } from '@/lib/api/types';

export default function ClientDetailPage() {
  const params = useParams<{ id: string }>();
  const clientId = Number(params.id);
  const user = useAuthStore((s) => s.user);
  const canManage = hasPermission(user, 'manage-clients');
  const canViewCases = hasPermission(user, 'view-recruitment-cases');
  const canViewJobs = hasPermission(user, 'view-jobs');
  const [editOpen, setEditOpen] = React.useState(false);

  const { data: profileRes, isLoading } = useQuery({
    queryKey: ['clients', clientId],
    queryFn: async () => (await clientsApi.profile(clientId)).data.data,
    enabled: Number.isFinite(clientId),
  });

  const { data: casesRes } = useQuery({
    queryKey: ['client-cases', clientId],
    queryFn: async () => (await recruitmentCasesApi.listForClient(clientId, { per_page: 25 })).data,
    enabled: Number.isFinite(clientId) && canViewCases,
  });

  const { data: jobsRes } = useQuery({
    queryKey: ['client-jobs', clientId],
    queryFn: async () => (await jobsApi.list({ client_id: clientId, per_page: 25 })).data,
    enabled: Number.isFinite(clientId) && canViewJobs,
  });

  if (isLoading) {
    return <Skeleton className="h-72 rounded-xl" />;
  }
  if (!profileRes) return <p className="p-8 text-center text-sm text-muted">لم يتم العثور على العميل.</p>;

  const client = profileRes;
  const contacts = profileRes.contacts ?? [];
  const cases: RecruitmentCase[] = casesRes?.data ?? [];
  const jobs: JobRequirement[] = jobsRes?.data ?? [];

  return (
    <div className="space-y-6">
      <nav className="flex items-center gap-1 text-xs text-muted">
        <Link href="/recruitment/clients" className="hover:text-brand-ink">
          العملاء
        </Link>
        <ChevronRight className="h-3 w-3" />
        <span className="num" dir="ltr">
          {client.client_number}
        </span>
      </nav>

      <div className="rounded-xl border border-hairline bg-surface p-6">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <div className="flex items-center gap-3">
              <h1 className="text-2xl font-bold text-ink">{client.company_name}</h1>
              <ClientStatusBadge status={client.status} />
            </div>
            <p className="mt-1 num text-xs text-muted" dir="ltr">
              {client.client_number}
            </p>
            <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-ink-2">
              {client.country && <span>{client.country}{client.city ? ` — ${client.city}` : ''}</span>}
              {client.industry && <span>{client.industry}</span>}
              {client.account_manager && <span>مدير الحساب: {client.account_manager.name}</span>}
              <span>الحالة: {CLIENT_STATUS_META[client.status].label}</span>
            </div>
          </div>
          {canManage && (
            <Button type="button" variant="outline" className="gap-2" onClick={() => setEditOpen(true)}>
              <Pencil className="h-4 w-4" /> تعديل
            </Button>
          )}
        </div>
      </div>

      <Tabs defaultValue="overview">
        <TabsList>
          <TabsTrigger value="overview">نظرة عامة</TabsTrigger>
          <TabsTrigger value="contacts">جهات الاتصال ({contacts.length})</TabsTrigger>
          <TabsTrigger value="cases">الحملات ({cases.length})</TabsTrigger>
          <TabsTrigger value="jobs">الوظائف ({jobs.length})</TabsTrigger>
        </TabsList>

        <TabsContent value="overview" className="mt-4 space-y-4">
          <div className="grid gap-4 lg:grid-cols-2">
            <div className="rounded-xl border border-hairline bg-surface p-5">
              <h2 className="mb-3 text-sm font-semibold text-ink">التفاصيل</h2>
              <dl className="space-y-3 text-sm">
                <Row label="القطاع" value={client.industry} />
                <Row label="حجم الشركة" value={client.company_size} />
                <Row label="العنوان" value={client.address} />
                <Row label="الرقم الضريبي" value={client.tax_number} />
                <Row label="شروط الدفع" value={client.payment_terms} />
                <Row
                  label="الموقع"
                  value={
                    client.company_website ? (
                      <a href={client.company_website} target="_blank" rel="noreferrer" className="text-brand-ink hover:underline" dir="ltr">
                        زيارة
                      </a>
                    ) : null
                  }
                />
              </dl>
              {client.notes && (
                <div className="mt-4 border-t border-hairline pt-4">
                  <p className="text-xs font-semibold text-ink-2">ملاحظات</p>
                  <p className="mt-1 whitespace-pre-wrap text-sm text-ink">{client.notes}</p>
                </div>
              )}
            </div>
            <ContactsPanel clientId={client.id} contacts={contacts} canManage={canManage} />
          </div>
        </TabsContent>

        <TabsContent value="contacts" className="mt-4">
          <ContactsPanel clientId={client.id} contacts={contacts} canManage={canManage} />
        </TabsContent>

        <TabsContent value="cases" className="mt-4">
          <div className="rounded-xl border border-hairline bg-surface">
            {cases.length === 0 ? (
              <p className="py-8 text-center text-sm text-muted">لا حملات مسجلة.</p>
            ) : (
              <ul className="divide-y divide-hairline">
                {cases.map((c) => (
                  <li key={c.id}>
                    <Link
                      href={`/recruitment/cases/${c.id}`}
                      className="flex items-center justify-between gap-3 p-4 hover:bg-surface-2"
                    >
                      <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-semibold text-ink" title={c.title}>{c.title}</p>
                        <p className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-ink-2">
                          <span className="num" dir="ltr">{c.case_number}</span>
                          {c.started_at && <span>يبدأ: <span className="num" dir="ltr">{formatDate(c.started_at)}</span></span>}
                          {c.deadline && <span>ينتهي: <span className="num" dir="ltr">{formatDate(c.deadline)}</span></span>}
                        </p>
                      </div>
                      <CaseStatusBadge status={c.status} />
                    </Link>
                  </li>
                ))}
              </ul>
            )}
          </div>
        </TabsContent>

        <TabsContent value="jobs" className="mt-4">
          <div className="rounded-xl border border-hairline bg-surface">
            {jobs.length === 0 ? (
              <p className="py-8 text-center text-sm text-muted">لا وظائف مسجلة.</p>
            ) : (
              <ul className="divide-y divide-hairline">
                {jobs.map((j) => (
                  <li key={j.id}>
                    <Link
                      href={`/recruitment/jobs/${j.id}`}
                      className="flex items-center justify-between gap-3 p-4 hover:bg-surface-2"
                    >
                      <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-semibold text-ink" title={j.title}>{j.title}</p>
                        <p className="mt-1 num text-xs text-ink-2" dir="ltr">
                          {j.job_number}
                          {j.current_stage ? ` · ${j.current_stage.name}` : ''}
                        </p>
                      </div>
                      <JobStatusBadge status={j.status} />
                    </Link>
                  </li>
                ))}
              </ul>
            )}
          </div>
        </TabsContent>
      </Tabs>

      <ClientFormDialog open={editOpen} onOpenChange={setEditOpen} client={client} />
    </div>
  );
}

function Row({ label, value }: { label: string; value: React.ReactNode | string | null }) {
  return (
    <div className="grid grid-cols-[110px_1fr] items-baseline gap-3">
      <dt className="text-xs text-muted">{label}</dt>
      <dd className="text-sm text-ink">{value === null || value === undefined || value === '' ? <span className="text-xs text-muted">—</span> : value}</dd>
    </div>
  );
}
