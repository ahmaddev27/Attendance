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
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { interviewsApi, INTERVIEW_KIND_LABEL } from '@/lib/api/endpoints/candidates';
import type { Interview, InterviewKind, InterviewPayload } from '@/lib/api/types';

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Either applicationId (create) or interview (reschedule) is required. */
  applicationId?: number;
  interview?: Interview | null;
};

/**
 * Create / reschedule an interview. Enforces the "at least one of
 * location / meeting_url" rule client-side so the user sees a clear
 * error before the server round-trip — the backend repeats the check.
 */
export function ScheduleInterviewDialog({
  open,
  onOpenChange,
  applicationId,
  interview,
}: Props) {
  const qc = useQueryClient();
  const isReschedule = !!interview;

  const [kind, setKind] = React.useState<InterviewKind>('internal');
  const [scheduledAt, setScheduledAt] = React.useState('');
  const [duration, setDuration] = React.useState('60');
  const [location, setLocation] = React.useState('');
  const [meetingUrl, setMeetingUrl] = React.useState('');
  const [notes, setNotes] = React.useState('');
  const [error, setError] = React.useState<string | null>(null);

  React.useEffect(() => {
    if (!open) return;
    if (interview) {
      setKind(interview.kind);
      setScheduledAt(interview.scheduled_at ? interview.scheduled_at.slice(0, 16) : '');
      setDuration(String(interview.duration_minutes));
      setLocation(interview.location ?? '');
      setMeetingUrl(interview.meeting_url ?? '');
      setNotes(interview.meeting_notes ?? '');
    } else {
      setKind('internal');
      setScheduledAt('');
      setDuration('60');
      setLocation('');
      setMeetingUrl('');
      setNotes('');
    }
    setError(null);
  }, [open, interview]);

  const mutation = useMutation({
    mutationFn: async () => {
      const payload: InterviewPayload = {
        kind,
        scheduled_at: new Date(scheduledAt).toISOString(),
        duration_minutes: Number(duration) || 60,
        location: location.trim() || null,
        meeting_url: meetingUrl.trim() || null,
        meeting_notes: notes.trim() || null,
      };
      if (isReschedule) {
        return interviewsApi.reschedule(interview!.id, {
          scheduled_at: payload.scheduled_at,
          duration_minutes: payload.duration_minutes,
        });
      }
      if (!applicationId) throw new Error('applicationId missing');
      return interviewsApi.store(applicationId, payload);
    },
    onSuccess: () => {
      toast.success(isReschedule ? 'تم إعادة الجدولة' : 'تم جدولة المقابلة');
      qc.invalidateQueries({ queryKey: ['interviews'] });
      qc.invalidateQueries({ queryKey: ['application', applicationId] });
      onOpenChange(false);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'تعذر الحفظ');
    },
  });

  const handleSubmit = (event: React.FormEvent) => {
    event.preventDefault();
    if (!scheduledAt) {
      setError('يجب تحديد وقت المقابلة.');
      return;
    }
    if (!isReschedule && !location.trim() && !meetingUrl.trim()) {
      setError('أدخل الموقع أو رابط اللقاء على الأقل.');
      return;
    }
    setError(null);
    mutation.mutate();
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-xl">
        <DialogHeader>
          <DialogTitle>{isReschedule ? 'إعادة جدولة المقابلة' : 'جدولة مقابلة'}</DialogTitle>
          <DialogDescription>
            {isReschedule
              ? 'سيُحفظ ارتباط بالمقابلة الأصلية للتدقيق.'
              : 'سيتم إخطار المرشّح والفريق تلقائياً.'}
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4">
          {!isReschedule && (
            <div className="space-y-1.5">
              <Label className="text-xs text-ink-2">نوع المقابلة</Label>
              <RadioGroup
                value={kind}
                onValueChange={(v) => setKind(v as InterviewKind)}
                className="flex gap-6"
              >
                {(Object.keys(INTERVIEW_KIND_LABEL) as InterviewKind[]).map((k) => (
                  <label key={k} className="flex cursor-pointer items-center gap-2 text-sm text-ink">
                    <RadioGroupItem value={k} />
                    {INTERVIEW_KIND_LABEL[k]}
                  </label>
                ))}
              </RadioGroup>
            </div>
          )}

          <div className="grid gap-3 sm:grid-cols-2">
            <div className="space-y-1.5">
              <Label className="text-xs text-ink-2">وقت المقابلة</Label>
              <Input
                type="datetime-local"
                dir="ltr"
                value={scheduledAt}
                onChange={(e) => setScheduledAt(e.target.value)}
              />
            </div>
            <div className="space-y-1.5">
              <Label className="text-xs text-ink-2">المدة (دقائق)</Label>
              <Input
                type="number"
                min={15}
                step={15}
                dir="ltr"
                value={duration}
                onChange={(e) => setDuration(e.target.value)}
              />
            </div>
          </div>

          {!isReschedule && (
            <>
              <div className="space-y-1.5">
                <Label className="text-xs text-ink-2">الموقع</Label>
                <Input
                  value={location}
                  onChange={(e) => setLocation(e.target.value)}
                  placeholder="عنوان المقر أو الغرفة"
                />
              </div>
              <div className="space-y-1.5">
                <Label className="text-xs text-ink-2">رابط اللقاء</Label>
                <Input
                  dir="ltr"
                  value={meetingUrl}
                  onChange={(e) => setMeetingUrl(e.target.value)}
                  placeholder="https://..."
                />
              </div>
              <div className="space-y-1.5">
                <Label className="text-xs text-ink-2">ملاحظات</Label>
                <Textarea value={notes} onChange={(e) => setNotes(e.target.value)} rows={2} />
              </div>
            </>
          )}

          {error && <p className="text-xs text-danger">{error}</p>}

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
              {isReschedule ? 'إعادة الجدولة' : 'جدولة'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
