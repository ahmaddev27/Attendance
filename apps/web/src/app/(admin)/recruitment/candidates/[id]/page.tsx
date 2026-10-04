'use client';

import * as React from 'react';
import Link from 'next/link';
import { useParams, useRouter } from 'next/navigation';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { ChevronLeft, Download, Pencil, Trash2 } from 'lucide-react';
import { toast } from 'sonner';

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { DataTable, type DataTableColumn } from '@/components/data-table/data-table';
import { CandidateFormDialog } from '@/components/recruitment/candidate-form-dialog';
import { ApplicationStatusBadge } from '@/components/recruitment/status-badges';
import { candidatesApi, CANDIDATE_STATUS_LABEL } from '@/lib/api/endpoints/candidates';
import { formatDate } from '@/lib/attendance-format';
import { hasPermission, useAuthStore } from '@/lib/stores/auth-store';
import type { CandidateApplication } from '@/lib/api/types';

export default function CandidateProfilePage() {
  const params = useParams<{ id: string }>();
  const candidateId = Number(params.id);
  const router = useRouter();
  const qc = useQueryClient();
  const canManage = hasPermission(useAuthStore((s) => s.user), 'manage-candidates');
  const [editOpen, setEditOpen] = React.useState(false);
  const [deleteOpen, setDeleteOpen] = React.useState(false);

  const { data: candidate, isLoading } = useQuery({
    queryKey: ['candidates', candidateId],
    queryFn: async () => (await candidatesApi.get(candidateId)).data.data,
    enabled: Number.isFinite(candidateId),
  });

  const { data: applications, isLoading: appsLoading } = useQuery({
    queryKey: ['candidates', candidateId, 'applications'],
    queryFn: async () => (await candidatesApi.applications(candidateId, { per_page: 50 })).data.data,
    enabled: Number.isFinite(candidateId),
  });

  const deleteMutation = useMutation({
    mutationFn: () => candidatesApi.remove(candidateId),
    onSuccess: () => {
      toast.success('تم حذف المرشّح');
      qc.invalidateQueries({ queryKey: ['candidates'] });
      router.push('/recruitment/candidates');
    },
    onError: () => toast.error('تعذر الحذف'),
  });

  if (isLoading) return <Skeleton className="h-96 rounded-xl" />;
  if (!candidate) return <p className="p-8 text-center text-sm text-muted">لم يتم العثور على المرشّح.</p>;

  const columns: DataTableColumn<CandidateApplication>[] = [
    {
      key: 'job',
      header: 'الوظيفة',
      cell: (a) => (
        <Link href={`/recruitment/applications/${a.id}`} className="font-medium text-ink hover:underline">
          {a.job?.title ?? '—'}
        </Link>
      ),
    },
    { key: 'stage', header: 'المرحلة', cell: (a) => a.current_stage?.name ?? '—' },
    { key: 'status', header: 'الحالة', cell: (a) => <ApplicationStatusBadge status={a.status} /> },
    {
      key: 'applied',
      header: 'تاريخ التقديم',
      cell: (a) => (
        <span className="num" dir="ltr">
          {formatDate(a.applied_at)}
        </span>
      ),
    },
  ];

  return (
    <div className="space-y-6">
      <nav className="flex items-center gap-1 text-xs text-muted">
        <Link href="/recruitment/candidates" className="hover:text-brand-ink">بنك المرشّحين</Link>
        <ChevronLeft className="h-3 w-3" />
        <span className="num" dir="ltr">{candidate.candidate_number}</span>
      </nav>

      <Card className="border-hairline bg-surface shadow-none">
        <CardContent className="flex flex-wrap items-start justify-between gap-4 p-6">
          <div>
            <div className="flex items-center gap-3">
              <h1 className="text-2xl font-bold text-ink">{candidate.full_name}</h1>
              <span className="rounded-full bg-surface-2 px-2.5 py-0.5 text-[11px] font-semibold text-ink-2">
                {CANDIDATE_STATUS_LABEL[candidate.status]}
              </span>
            </div>
            {candidate.headline && <p className="mt-1 text-sm text-ink-2">{candidate.headline}</p>}
          </div>
          <div className="flex flex-wrap gap-2">
            {candidate.has_resume && candidate.resume_url && (
              <Button asChild variant="outline" className="gap-2">
                <a href={candidate.resume_url} target="_blank" rel="noopener noreferrer">
                  <Download className="h-4 w-4" /> السيرة الذاتية
                </a>
              </Button>
            )}
            {canManage && (
              <>
                <Button variant="outline" className="gap-2" onClick={() => setEditOpen(true)}>
                  <Pencil className="h-4 w-4" /> تعديل
                </Button>
                <Button
                  variant="outline"
                  className="gap-2 text-danger hover:bg-danger-soft"
                  onClick={() => setDeleteOpen(true)}
                >
                  <Trash2 className="h-4 w-4" /> حذف
                </Button>
              </>
            )}
          </div>
        </CardContent>
      </Card>

      <Tabs defaultValue="profile">
        <TabsList>
          <TabsTrigger value="profile">الملف</TabsTrigger>
          <TabsTrigger value="applications">الطلبات</TabsTrigger>
        </TabsList>

        <TabsContent value="profile" className="mt-4 space-y-4">
          <Card className="border-hairline bg-surface shadow-none">
            <CardContent className="p-6">
              <h2 className="mb-4 text-sm font-semibold text-ink">بيانات التعريف</h2>
              <dl className="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
                <Field label="رقم المرشّح" value={candidate.candidate_number} ltr />
                <Field label="البريد الإلكتروني" value={candidate.email} ltr />
                <Field label="الهاتف" value={candidate.phone} ltr />
                <Field label="المدينة" value={[candidate.city, candidate.country].filter(Boolean).join('، ') || null} />
                <Field label="المسمى الحالي" value={candidate.current_title} />
                <Field label="الشركة الحالية" value={candidate.current_company} />
                <Field label="سنوات الخبرة" value={candidate.years_of_experience?.toString() ?? null} ltr />
                <div>
                  <dt className="text-xs text-muted">LinkedIn</dt>
                  <dd className="mt-0.5 text-sm">
                    {candidate.linkedin_url ? (
                      <a
                        href={candidate.linkedin_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        dir="ltr"
                        className="num text-brand-ink hover:underline"
                      >
                        {candidate.linkedin_url}
                      </a>
                    ) : (
                      '—'
                    )}
                  </dd>
                </div>
              </dl>
            </CardContent>
          </Card>

          <div className="grid gap-4 md:grid-cols-2">
            <ChipsCard title="المهارات" items={candidate.skills} />
            <ChipsCard title="اللغات" items={candidate.languages} />
          </div>

          <Card className="border-hairline bg-surface shadow-none">
            <CardContent className="p-6">
              <h2 className="mb-2 text-sm font-semibold text-ink">ملاحظات</h2>
              <p className="whitespace-pre-wrap text-sm text-ink-2">{candidate.notes || '—'}</p>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="applications" className="mt-4">
          <DataTable
            columns={columns}
            data={applications ?? []}
            rowKey={(row) => row.id}
            isLoading={appsLoading}
            emptyMessage="لا توجد طلبات لهذا المرشّح"
          />
        </TabsContent>
      </Tabs>

      <CandidateFormDialog open={editOpen} onOpenChange={setEditOpen} candidate={candidate} />

      <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>حذف المرشّح</AlertDialogTitle>
            <AlertDialogDescription>سيتم أرشفة {candidate.full_name}.</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>إلغاء</AlertDialogCancel>
            <AlertDialogAction
              className="bg-danger text-white hover:bg-danger/90"
              onClick={() => deleteMutation.mutate()}
            >
              حذف
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

function Field({ label, value, ltr }: { label: string; value: string | null | undefined; ltr?: boolean }) {
  const numeric = ltr && !!value;
  return (
    <div>
      <dt className="text-xs text-muted">{label}</dt>
      <dd className={numeric ? 'num mt-0.5 text-sm text-ink' : 'mt-0.5 text-sm text-ink'} dir={numeric ? 'ltr' : undefined}>
        {value || '—'}
      </dd>
    </div>
  );
}

function ChipsCard({ title, items }: { title: string; items: string[] | null }) {
  return (
    <Card className="border-hairline bg-surface shadow-none">
      <CardContent className="p-6">
        <h2 className="mb-3 text-sm font-semibold text-ink">{title}</h2>
        {items?.length ? (
          <div className="flex flex-wrap gap-2">
            {items.map((item) => (
              <span key={item} className="rounded-full bg-brand-soft px-3 py-1 text-xs font-medium text-brand-ink">
                {item}
              </span>
            ))}
          </div>
        ) : (
          <p className="text-sm text-muted">—</p>
        )}
      </CardContent>
    </Card>
  );
}
