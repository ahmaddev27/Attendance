'use client';

import * as React from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { CalendarPlus, ChevronLeft, ClipboardCheck, Undo2, XCircle } from 'lucide-react';
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
import { RejectApplicationDialog } from '@/components/recruitment/reject-application-dialog';
import { ScheduleInterviewDialog } from '@/components/recruitment/schedule-interview-dialog';
import { ScreeningFormDialog } from '@/components/recruitment/screening-form-dialog';
import {
  ApplicationStatusBadge,
  InterviewKindBadge,
  InterviewStatusBadge,
} from '@/components/recruitment/status-badges';
import { applicationsApi, interviewsApi, screeningApi } from '@/lib/api/endpoints/candidates';
import { jobsApi } from '@/lib/api/endpoints/recruitment';
import { formatDate, formatTime } from '@/lib/attendance-format';
import { hasPermission, useAuthStore } from '@/lib/stores/auth-store';
import { cn } from '@/lib/utils';
import type {
  CandidateApplication,
  CandidateScreening,
  Interview,
  ScreeningSchema,
} from '@/lib/api/types';

const CLOSED = ['rejected', 'withdrawn', 'hired'];

export default function ApplicationDetailPage() {
  const params = useParams<{ id: string }>();
  const applicationId = Number(params.id);
  const qc = useQueryClient();
  const user = useAuthStore((s) => s.user);
  const canManage = hasPermission(user, 'manage-candidates');
  const canScreen = hasPermission(user, 'screen-candidates');
  const canSchedule = hasPermission(user, 'schedule-interviews');

  const [rejectOpen, setRejectOpen] = React.useState(false);
  const [withdrawOpen, setWithdrawOpen] = React.useState(false);
  const [screeningOpen, setScreeningOpen] = React.useState(false);
  const [scheduleOpen, setScheduleOpen] = React.useState(false);

  const { data: application, isLoading } = useQuery({
    queryKey: ['application', applicationId],
    queryFn: async () => (await applicationsApi.get(applicationId)).data.data,
    enabled: Number.isFinite(applicationId),
  });

  const jobId = application?.job?.id;
  const stageId = application?.current_stage?.id;

  // The application resource only embeds a job snapshot; the pipeline id
  // needed for the screening schema lives on the full job record.
  const { data: job } = useQuery({
    queryKey: ['jobs', jobId],
    queryFn: async () => (await jobsApi.get(jobId!)).data.data,
    enabled: !!jobId,
  });
  const pipelineId = job?.pipeline_id;

  const { data: screening } = useQuery({
    queryKey: ['application-screening', applicationId],
    queryFn: async () => (await screeningApi.get(applicationId)).data.data,
    enabled: Number.isFinite(applicationId),
  });

  const { data: schema } = useQuery({
    queryKey: ['screening-schema', pipelineId, stageId],
    queryFn: async () => (await screeningApi.schema(pipelineId!, stageId!)).data.data,
    enabled: !!pipelineId && !!stageId && canScreen,
  });

  const { data: interviews, isLoading: interviewsLoading } = useQuery({
    queryKey: ['interviews', { application_id: applicationId }],
    queryFn: async () => (await interviewsApi.list({ application_id: applicationId, per_page: 50 })).data.data,
    enabled: Number.isFinite(applicationId),
  });

  const withdraw = useMutation({
    mutationFn: () => applicationsApi.withdraw(applicationId),
    onSuccess: () => {
      toast.success('تم سحب الطلب');
      qc.invalidateQueries({ queryKey: ['application', applicationId] });
      qc.invalidateQueries({ queryKey: ['job-applications'] });
      setWithdrawOpen(false);
    },
    onError: () => toast.error('تعذر سحب الطلب'),
  });

  if (isLoading) return <Skeleton className="h-96 rounded-xl" />;
  if (!application) return <p className="p-8 text-center text-sm text-muted">لم يتم العثور على الطلب.</p>;

  const isClosed = CLOSED.includes(application.status);

  return (
    <div className="space-y-6">
      <nav className="flex items-center gap-1 text-xs text-muted">
        {application.job && (
          <>
            <Link href={`/recruitment/jobs/${application.job.id}/candidates`} className="hover:text-brand-ink">
              {application.job.title}
            </Link>
            <ChevronLeft className="h-3 w-3" />
          </>
        )}
        <span className="num" dir="ltr">{application.application_number}</span>
      </nav>

      <Card className="border-hairline bg-surface shadow-none">
        <CardContent className="flex flex-wrap items-center justify-between gap-4 p-6">
          <div>
            <div className="flex items-center gap-3">
              <h1 className="text-2xl font-bold text-ink">{application.candidate?.full_name ?? '—'}</h1>
              <ApplicationStatusBadge status={application.status} />
            </div>
            <p className="mt-1 text-sm text-ink-2">{application.job?.title}</p>
          </div>
        </CardContent>
      </Card>

      <Tabs defaultValue="overview">
        <TabsList>
          <TabsTrigger value="overview">نظرة عامة</TabsTrigger>
          <TabsTrigger value="screening">الفرز</TabsTrigger>
          <TabsTrigger value="interviews">المقابلات</TabsTrigger>
          <TabsTrigger value="actions">الإجراءات</TabsTrigger>
        </TabsList>

        <TabsContent value="overview" className="mt-4">
          <OverviewCard application={application} />
        </TabsContent>

        <TabsContent value="screening" className="mt-4">
          <ScreeningPanel
            screening={screening ?? null}
            schema={schema ?? null}
            canScreen={canScreen && !!pipelineId && !!stageId && !isClosed}
            onOpen={() => setScreeningOpen(true)}
          />
        </TabsContent>

        <TabsContent value="interviews" className="mt-4 space-y-4">
          {canSchedule && !isClosed && (
            <div className="flex justify-end">
              <Button onClick={() => setScheduleOpen(true)} className="gap-2 bg-brand text-white hover:bg-brand-hover">
                <CalendarPlus className="h-4 w-4" /> جدولة مقابلة
              </Button>
            </div>
          )}
          <InterviewsTable interviews={interviews ?? []} isLoading={interviewsLoading} />
        </TabsContent>

        <TabsContent value="actions" className="mt-4">
          <Card className="border-hairline bg-surface shadow-none">
            <CardContent className="flex flex-wrap gap-3 p-6">
              {canManage && !isClosed ? (
                <>
                  <Button
                    variant="outline"
                    className="gap-2 text-danger hover:bg-danger-soft"
                    onClick={() => setRejectOpen(true)}
                  >
                    <XCircle className="h-4 w-4" /> رفض الطلب
                  </Button>
                  <Button variant="outline" className="gap-2" onClick={() => setWithdrawOpen(true)}>
                    <Undo2 className="h-4 w-4" /> سحب الطلب
                  </Button>
                </>
              ) : (
                <p className="text-sm text-muted">لا توجد إجراءات متاحة لهذا الطلب.</p>
              )}
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      <RejectApplicationDialog open={rejectOpen} onOpenChange={setRejectOpen} applicationId={applicationId} />
      <ScheduleInterviewDialog open={scheduleOpen} onOpenChange={setScheduleOpen} applicationId={applicationId} />
      {pipelineId && stageId && (
        <ScreeningFormDialog
          open={screeningOpen}
          onOpenChange={setScreeningOpen}
          application={application}
          pipelineId={pipelineId}
          stageId={stageId}
          existing={screening ?? null}
        />
      )}

      <AlertDialog open={withdrawOpen} onOpenChange={setWithdrawOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>سحب الطلب</AlertDialogTitle>
            <AlertDialogDescription>
              سيتم تسجيل أن المرشّح انسحب من هذه الوظيفة.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>إلغاء</AlertDialogCancel>
            <AlertDialogAction
              className="bg-brand text-white hover:bg-brand-hover"
              onClick={() => withdraw.mutate()}
            >
              سحب
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

function OverviewCard({ application }: { application: CandidateApplication }) {
  const rows: Array<[string, React.ReactNode]> = [
    [
      'المرشّح',
      application.candidate ? (
        <Link href={`/recruitment/candidates/${application.candidate.id}`} className="text-brand-ink hover:underline">
          {application.candidate.full_name}
        </Link>
      ) : (
        '—'
      ),
    ],
    [
      'الوظيفة',
      application.job ? (
        <Link href={`/recruitment/jobs/${application.job.id}`} className="text-brand-ink hover:underline">
          {application.job.title}
        </Link>
      ) : (
        '—'
      ),
    ],
    ['الحالة', <ApplicationStatusBadge key="s" status={application.status} />],
    ['المرحلة', application.current_stage?.name ?? '—'],
    [
      'تاريخ التقديم',
      <span key="d" className="num" dir="ltr">{formatDate(application.applied_at)}</span>,
    ],
  ];
  if (application.rejection_reason) rows.push(['سبب الرفض', application.rejection_reason]);

  return (
    <Card className="border-hairline bg-surface shadow-none">
      <CardContent className="p-6">
        <dl className="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
          {rows.map(([label, value]) => (
            <div key={label}>
              <dt className="text-xs text-muted">{label}</dt>
              <dd className="mt-0.5 text-sm text-ink">{value}</dd>
            </div>
          ))}
        </dl>
      </CardContent>
    </Card>
  );
}

function ScreeningPanel({
  screening,
  schema,
  canScreen,
  onOpen,
}: {
  screening: CandidateScreening | null;
  schema: ScreeningSchema | null;
  canScreen: boolean;
  onOpen: () => void;
}) {
  const labelOf = (key: string) => schema?.fields.find((f) => f.key === key)?.label ?? key;

  return (
    <Card className="border-hairline bg-surface shadow-none">
      <CardContent className="space-y-4 p-6">
        {screening ? (
          <>
            <div className="flex flex-wrap items-center gap-3">
              <span className="text-sm text-ink-2">
                النتيجة:{' '}
                <span className="num font-bold text-ink" dir="ltr">{screening.overall_score ?? '—'}</span>
              </span>
              <span className={cn('text-sm font-semibold', screening.passed ? 'text-success' : 'text-danger')}>
                {screening.passed ? 'اجتاز' : 'لم يجتز'}
              </span>
            </div>
            <dl className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              {Object.entries(screening.scorecard).map(([key, value]) => (
                <div key={key} className="rounded-lg border border-hairline p-3">
                  <dt className="text-xs text-muted">{labelOf(key)}</dt>
                  <dd className="num mt-1 text-sm font-medium text-ink" dir="ltr">{String(value)}</dd>
                </div>
              ))}
            </dl>
            {screening.notes && <p className="whitespace-pre-wrap text-sm text-ink-2">{screening.notes}</p>}
            {screening.scored_by && (
              <p className="text-xs text-muted">
                قيّمه {screening.scored_by.name} —{' '}
                <span className="num" dir="ltr">{formatDate(screening.scored_at)}</span>
              </p>
            )}
          </>
        ) : (
          <p className="text-sm text-muted">لم يتم إجراء الفرز بعد.</p>
        )}
        {canScreen && (
          <Button onClick={onOpen} className="gap-2 bg-brand text-white hover:bg-brand-hover">
            <ClipboardCheck className="h-4 w-4" /> {screening ? 'تعديل التقييم' : 'تقديم تقييم'}
          </Button>
        )}
      </CardContent>
    </Card>
  );
}

function InterviewsTable({ interviews, isLoading }: { interviews: Interview[]; isLoading: boolean }) {
  const columns: DataTableColumn<Interview>[] = [
    {
      key: 'when',
      header: 'الموعد',
      cell: (i) => (
        <Link href={`/recruitment/interviews/${i.id}`} className="num hover:underline" dir="ltr">
          {formatDate(i.scheduled_at)} {formatTime(i.scheduled_at)}
        </Link>
      ),
    },
    { key: 'kind', header: 'النوع', cell: (i) => <InterviewKindBadge kind={i.kind} /> },
    { key: 'status', header: 'الحالة', cell: (i) => <InterviewStatusBadge status={i.status} /> },
  ];
  return (
    <DataTable
      columns={columns}
      data={interviews}
      rowKey={(row) => row.id}
      isLoading={isLoading}
      emptyMessage="لا توجد مقابلات"
    />
  );
}
