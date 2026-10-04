'use client';

import * as React from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { CalendarClock, Check, ChevronLeft, MessageSquarePlus, Video, XCircle } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { CancelInterviewDialog } from '@/components/recruitment/cancel-interview-dialog';
import { ScheduleInterviewDialog } from '@/components/recruitment/schedule-interview-dialog';
import { SubmitFeedbackDialog } from '@/components/recruitment/submit-feedback-dialog';
import { InterviewKindBadge, InterviewStatusBadge } from '@/components/recruitment/status-badges';
import {
  applicationsApi,
  interviewsApi,
  INTERVIEW_RECOMMENDATION_LABEL,
} from '@/lib/api/endpoints/candidates';
import { jobsApi } from '@/lib/api/endpoints/recruitment';
import { formatDate, formatTime } from '@/lib/attendance-format';
import { hasPermission, useAuthStore } from '@/lib/stores/auth-store';
import type { InterviewFeedback } from '@/lib/api/types';

export default function InterviewDetailPage() {
  const params = useParams<{ id: string }>();
  const interviewId = Number(params.id);
  const qc = useQueryClient();
  const user = useAuthStore((s) => s.user);
  const canSchedule = hasPermission(user, 'schedule-interviews');
  const canFeedback = hasPermission(user, 'submit-interview-feedback');

  const [rescheduleOpen, setRescheduleOpen] = React.useState(false);
  const [cancelOpen, setCancelOpen] = React.useState(false);
  const [feedbackOpen, setFeedbackOpen] = React.useState(false);

  const { data: interview, isLoading } = useQuery({
    queryKey: ['interview', interviewId],
    queryFn: async () => (await interviewsApi.get(interviewId)).data.data,
    enabled: Number.isFinite(interviewId),
  });

  const { data: feedbackRes } = useQuery({
    queryKey: ['interview-feedbacks', interviewId],
    queryFn: async () => (await interviewsApi.feedbacks(interviewId)).data,
    enabled: Number.isFinite(interviewId),
  });

  // Feedback scorecards reuse the stage's schema, which needs the
  // application's current stage and the job's pipeline.
  const { data: application } = useQuery({
    queryKey: ['application', interview?.application_id],
    queryFn: async () => (await applicationsApi.get(interview!.application_id)).data.data,
    enabled: !!interview,
  });
  const { data: job } = useQuery({
    queryKey: ['jobs', application?.job?.id],
    queryFn: async () => (await jobsApi.get(application!.job!.id)).data.data,
    enabled: !!application?.job?.id,
  });

  const complete = useMutation({
    mutationFn: () => interviewsApi.complete(interviewId),
    onSuccess: () => {
      toast.success('تم إكمال المقابلة');
      qc.invalidateQueries({ queryKey: ['interview', interviewId] });
      qc.invalidateQueries({ queryKey: ['interviews'] });
    },
    onError: () => toast.error('تعذر إكمال المقابلة'),
  });

  if (isLoading) return <Skeleton className="h-96 rounded-xl" />;
  if (!interview) return <p className="p-8 text-center text-sm text-muted">لم يتم العثور على المقابلة.</p>;

  const feedbacks = feedbackRes?.data ?? interview.feedbacks ?? [];
  const average = feedbackRes?.meta?.average_score ?? interview.average_score ?? null;
  const myFeedback = feedbacks.find((f) => f.interviewer_user_id === user?.id) ?? null;
  const isScheduled = interview.status === 'scheduled';
  const pipelineId = job?.pipeline_id;
  const stageId = application?.current_stage?.id;

  return (
    <div className="space-y-6">
      <nav className="flex items-center gap-1 text-xs text-muted">
        <Link href="/recruitment/interviews" className="hover:text-brand-ink">المقابلات</Link>
        <ChevronLeft className="h-3 w-3" />
        <span className="num" dir="ltr">{interview.interview_number}</span>
      </nav>

      <Card className="border-hairline bg-surface shadow-none">
        <CardContent className="space-y-4 p-6">
          <div className="flex flex-wrap items-start justify-between gap-4">
            <div>
              <div className="flex flex-wrap items-center gap-3">
                <h1 className="text-2xl font-bold text-ink">
                  {interview.application?.candidate?.full_name ?? 'مقابلة'}
                </h1>
                <InterviewStatusBadge status={interview.status} />
                <InterviewKindBadge kind={interview.kind} />
              </div>
              <p className="mt-1 text-sm text-ink-2">
                {interview.application?.job?.title}
                {' · '}
                <Link
                  href={`/recruitment/applications/${interview.application_id}`}
                  className="text-brand-ink hover:underline"
                >
                  عرض الطلب
                </Link>
              </p>
            </div>
            <div className="flex flex-wrap gap-2">
              {canFeedback && pipelineId && stageId && interview.status !== 'cancelled' && (
                <Button
                  onClick={() => setFeedbackOpen(true)}
                  className="gap-2 bg-brand text-white hover:bg-brand-hover"
                >
                  <MessageSquarePlus className="h-4 w-4" /> {myFeedback ? 'تعديل تقييمي' : 'تقديم تقييم'}
                </Button>
              )}
              {canSchedule && isScheduled && (
                <>
                  <Button variant="outline" className="gap-2" onClick={() => setRescheduleOpen(true)}>
                    <CalendarClock className="h-4 w-4" /> إعادة جدولة
                  </Button>
                  <Button variant="outline" className="gap-2" onClick={() => complete.mutate()} disabled={complete.isPending}>
                    <Check className="h-4 w-4" /> إكمال
                  </Button>
                  <Button
                    variant="outline"
                    className="gap-2 text-danger hover:bg-danger-soft"
                    onClick={() => setCancelOpen(true)}
                  >
                    <XCircle className="h-4 w-4" /> إلغاء
                  </Button>
                </>
              )}
            </div>
          </div>

          <dl className="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2">
            <Info label="التاريخ">
              <span className="num" dir="ltr">{formatDate(interview.scheduled_at)}</span>
            </Info>
            <Info label="الوقت والمدة">
              <span className="num" dir="ltr">
                {formatTime(interview.scheduled_at)} · {interview.duration_minutes}د
              </span>
            </Info>
            <Info label="الموقع">{interview.location ?? '—'}</Info>
            <Info label="رابط الاجتماع">
              {interview.meeting_url ? (
                <a
                  href={interview.meeting_url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center gap-1 text-brand-ink hover:underline"
                >
                  <Video className="h-3.5 w-3.5" /> انضمام
                </a>
              ) : (
                '—'
              )}
            </Info>
          </dl>
          {interview.cancelled_reason && (
            <p className="text-sm text-danger">سبب الإلغاء: {interview.cancelled_reason}</p>
          )}
          {interview.meeting_notes && (
            <p className="whitespace-pre-wrap text-sm text-ink-2">{interview.meeting_notes}</p>
          )}
        </CardContent>
      </Card>

      <Card className="border-hairline bg-surface shadow-none">
        <CardContent className="space-y-4 p-6">
          <div className="flex items-center justify-between">
            <h2 className="text-sm font-semibold text-ink">التقييمات</h2>
            <span className="text-sm text-ink-2">
              المتوسط: <span className="num font-bold text-ink" dir="ltr">{average ?? '—'}</span>
            </span>
          </div>
          {feedbacks.length === 0 ? (
            <p className="text-sm text-muted">لم تُقدَّم تقييمات بعد.</p>
          ) : (
            feedbacks.map((f) => <FeedbackRow key={f.id} feedback={f} />)
          )}
        </CardContent>
      </Card>

      <ScheduleInterviewDialog open={rescheduleOpen} onOpenChange={setRescheduleOpen} interview={interview} />
      <CancelInterviewDialog open={cancelOpen} onOpenChange={setCancelOpen} interviewId={interviewId} />
      {pipelineId && stageId && (
        <SubmitFeedbackDialog
          open={feedbackOpen}
          onOpenChange={setFeedbackOpen}
          interviewId={interviewId}
          pipelineId={pipelineId}
          stageId={stageId}
          existing={myFeedback}
        />
      )}
    </div>
  );
}

function Info({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <dt className="text-xs text-muted">{label}</dt>
      <dd className="mt-0.5 text-sm text-ink">{children}</dd>
    </div>
  );
}

function FeedbackRow({ feedback }: { feedback: InterviewFeedback }) {
  return (
    <div className="space-y-2 rounded-lg border border-hairline p-4">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <span className="text-sm font-medium text-ink">{feedback.interviewer?.name ?? '—'}</span>
        <span className="text-xs text-ink-2">
          {INTERVIEW_RECOMMENDATION_LABEL[feedback.recommendation]}
          {' · '}
          <span className="num font-semibold" dir="ltr">{feedback.overall_score ?? '—'}</span>
        </span>
      </div>
      {feedback.strengths && <p className="text-sm text-success">{feedback.strengths}</p>}
      {feedback.weaknesses && <p className="text-sm text-danger">{feedback.weaknesses}</p>}
      {feedback.notes && <p className="whitespace-pre-wrap text-sm text-ink-2">{feedback.notes}</p>}
    </div>
  );
}
