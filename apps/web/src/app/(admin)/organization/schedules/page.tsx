'use client';

import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Clock, Pencil, Plus, Trash2, Users } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
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
import { schedulesApi } from '@/lib/api/endpoints/schedules';
import { ARABIC_WEEKDAYS_SHORT } from '@/lib/attendance-format';
import type { WorkSchedule, WorkSchedulePayload } from '@/lib/api/types';

type ScheduleFormState = {
  name: string;
  workdays: number[];
  is_flexible: boolean;
  check_in_time: string;
  check_out_time: string;
  min_hours_per_day: string;
  grace_late_minutes: string;
  grace_early_leave_minutes: string;
  is_active: boolean;
};

const EMPTY_FORM: ScheduleFormState = {
  name: '',
  workdays: [0, 1, 2, 3, 4], // Sunday-Thursday default work week
  is_flexible: false,
  check_in_time: '08:00',
  check_out_time: '16:00',
  min_hours_per_day: '8',
  grace_late_minutes: '10',
  grace_early_leave_minutes: '10',
  is_active: true,
};

function scheduleToForm(schedule: WorkSchedule): ScheduleFormState {
  return {
    name: schedule.name,
    workdays: schedule.workdays,
    is_flexible: schedule.is_flexible,
    check_in_time: schedule.check_in_time ?? '08:00',
    check_out_time: schedule.check_out_time ?? '16:00',
    min_hours_per_day: String(schedule.min_hours_per_day),
    grace_late_minutes: String(schedule.grace_late_minutes),
    grace_early_leave_minutes: String(schedule.grace_early_leave_minutes),
    is_active: schedule.is_active,
  };
}

function formToPayload(form: ScheduleFormState): WorkSchedulePayload {
  return {
    name: form.name.trim(),
    check_in_time: form.is_flexible ? null : form.check_in_time,
    check_out_time: form.is_flexible ? null : form.check_out_time,
    min_hours_per_day: Number(form.min_hours_per_day) || 0,
    grace_late_minutes: Number(form.grace_late_minutes) || 0,
    grace_early_leave_minutes: Number(form.grace_early_leave_minutes) || 0,
    workdays: [...form.workdays].sort((a, b) => a - b),
    is_flexible: form.is_flexible,
    is_active: form.is_active,
  };
}

function workdaysLabel(workdays: number[]): string {
  if (workdays.length === 7) return 'كل أيام الأسبوع';
  return [...workdays]
    .sort((a, b) => a - b)
    .map((d) => ARABIC_WEEKDAYS_SHORT[d])
    .join('، ');
}

export default function WorkSchedulesPage() {
  const queryClient = useQueryClient();
  const { data: schedules, isLoading } = useQuery({
    queryKey: ['work-schedules'],
    queryFn: schedulesApi.list,
  });

  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingSchedule, setEditingSchedule] = useState<WorkSchedule | null>(null);
  const [form, setForm] = useState<ScheduleFormState>(EMPTY_FORM);
  const [deletingSchedule, setDeletingSchedule] = useState<WorkSchedule | null>(null);

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['work-schedules'] });

  const saveMutation = useMutation({
    mutationFn: (payload: WorkSchedulePayload) =>
      editingSchedule ? schedulesApi.update(editingSchedule.id, payload) : schedulesApi.create(payload),
    onSuccess: () => {
      toast.success(editingSchedule ? 'تم تحديث الجدول' : 'تم إضافة الجدول');
      setDialogOpen(false);
      invalidate();
    },
    onError: () => toast.error('تعذر حفظ بيانات الجدول'),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => schedulesApi.remove(id),
    onSuccess: () => {
      toast.success('تم حذف الجدول');
      setDeletingSchedule(null);
      invalidate();
    },
    onError: () => toast.error('تعذر حذف الجدول'),
  });

  const openCreate = () => {
    setEditingSchedule(null);
    setForm(EMPTY_FORM);
    setDialogOpen(true);
  };

  const openEdit = (schedule: WorkSchedule) => {
    setEditingSchedule(schedule);
    setForm(scheduleToForm(schedule));
    setDialogOpen(true);
  };

  const toggleWorkday = (day: number) => {
    setForm((prev) => ({
      ...prev,
      workdays: prev.workdays.includes(day)
        ? prev.workdays.filter((d) => d !== day)
        : [...prev.workdays, day],
    }));
  };

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!form.name.trim()) {
      toast.error('اسم الجدول مطلوب');
      return;
    }
    if (form.workdays.length === 0) {
      toast.error('اختر يوم عمل واحد على الأقل');
      return;
    }
    saveMutation.mutate(formToPayload(form));
  };

  return (
    <div>
      <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="text-xs text-muted">إدارة النظام</p>
          <h1 className="mt-1 text-2xl font-bold text-ink">جداول العمل</h1>
        </div>
        <Button type="button" onClick={openCreate} className="bg-brand text-white hover:bg-brand-hover">
          <Plus className="h-4 w-4" />
          إضافة جدول
        </Button>
      </div>

      {isLoading ? (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {Array.from({ length: 3 }).map((_, i) => (
            <Skeleton key={i} className="h-48 rounded-xl" />
          ))}
        </div>
      ) : (schedules?.length ?? 0) === 0 ? (
        <p className="py-10 text-center text-sm text-muted">لا توجد جداول عمل بعد</p>
      ) : (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {schedules?.map((schedule) => (
            <Card key={schedule.id} className="border-hairline bg-surface shadow-none">
              <CardContent className="p-5">
                <div className="mb-3 flex items-start justify-between">
                  <div>
                    <h2 className="font-bold text-ink">{schedule.name}</h2>
                    <Badge
                      className={
                        schedule.is_active
                          ? 'mt-1 border-transparent bg-success-soft text-success'
                          : 'mt-1 border-transparent bg-surface-2 text-muted'
                      }
                    >
                      {schedule.is_active ? 'مفعل' : 'معطل'}
                    </Badge>
                  </div>
                  <div className="flex gap-1">
                    <Button type="button" variant="ghost" size="icon" title="تعديل" onClick={() => openEdit(schedule)}>
                      <Pencil className="h-4 w-4" />
                    </Button>
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      title="حذف"
                      className="text-danger hover:bg-danger-soft hover:text-danger"
                      onClick={() => setDeletingSchedule(schedule)}
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </div>
                </div>

                <p className="text-sm text-ink-2">{workdaysLabel(schedule.workdays)}</p>

                <div className="mt-3 flex items-center gap-2 text-sm text-ink">
                  <Clock className="h-4 w-4 shrink-0 text-muted" />
                  {schedule.is_flexible ? (
                    <span>مرن</span>
                  ) : (
                    <span className="num" dir="ltr">
                      {schedule.check_in_time?.slice(0, 5)} - {schedule.check_out_time?.slice(0, 5)}
                    </span>
                  )}
                </div>

                <div className="mt-3 grid grid-cols-2 gap-2 text-xs text-muted">
                  <p>
                    الحد الأدنى للساعات: <span className="num font-semibold text-ink">{schedule.min_hours_per_day}</span>
                  </p>
                  <p>
                    سماحية التأخير: <span className="num font-semibold text-ink">{schedule.grace_late_minutes}د</span>
                  </p>
                  <p>
                    سماحية الانصراف المبكر:{' '}
                    <span className="num font-semibold text-ink">{schedule.grace_early_leave_minutes}د</span>
                  </p>
                  <p className="flex items-center gap-1">
                    <Users className="h-3.5 w-3.5" />
                    <span className="num font-semibold text-ink">{schedule.employees_count ?? '—'}</span> موظف
                  </p>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="max-w-lg max-h-[85vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{editingSchedule ? 'تعديل الجدول' : 'إضافة جدول عمل جديد'}</DialogTitle>
          </DialogHeader>
          <form onSubmit={submit} className="space-y-4">
            <div>
              <Label className="text-xs font-semibold text-ink-2">اسم الجدول</Label>
              <Input
                value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
                className="mt-1.5"
                required
                autoFocus
              />
            </div>

            <div>
              <Label className="text-xs font-semibold text-ink-2">أيام العمل</Label>
              <div className="mt-2 grid grid-cols-4 gap-2 sm:grid-cols-7">
                {ARABIC_WEEKDAYS_SHORT.map((label, day) => (
                  <label
                    key={day}
                    className="flex flex-col items-center gap-1.5 rounded-lg border border-hairline p-2 text-xs text-ink-2"
                  >
                    <Checkbox
                      checked={form.workdays.includes(day)}
                      onCheckedChange={() => toggleWorkday(day)}
                    />
                    {label}
                  </label>
                ))}
              </div>
            </div>

            <div className="flex items-center justify-between rounded-lg border border-hairline p-3">
              <Label className="text-sm font-medium text-ink">جدول مرن (بدون أوقات ثابتة)</Label>
              <Switch
                checked={form.is_flexible}
                onCheckedChange={(checked) => setForm({ ...form, is_flexible: checked })}
              />
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div>
                <Label className="text-xs font-semibold text-ink-2">وقت الحضور</Label>
                <Input
                  type="time"
                  value={form.check_in_time}
                  onChange={(e) => setForm({ ...form, check_in_time: e.target.value })}
                  className="mt-1.5 num"
                  dir="ltr"
                  disabled={form.is_flexible}
                />
              </div>
              <div>
                <Label className="text-xs font-semibold text-ink-2">وقت الانصراف</Label>
                <Input
                  type="time"
                  value={form.check_out_time}
                  onChange={(e) => setForm({ ...form, check_out_time: e.target.value })}
                  className="mt-1.5 num"
                  dir="ltr"
                  disabled={form.is_flexible}
                />
              </div>
            </div>

            <div className="grid grid-cols-3 gap-3">
              <div>
                <Label className="text-xs font-semibold text-ink-2">الحد الأدنى للساعات</Label>
                <Input
                  type="number"
                  min={0}
                  step="0.5"
                  value={form.min_hours_per_day}
                  onChange={(e) => setForm({ ...form, min_hours_per_day: e.target.value })}
                  className="mt-1.5 num"
                  dir="ltr"
                />
              </div>
              <div>
                <Label className="text-xs font-semibold text-ink-2">سماحية التأخير (د)</Label>
                <Input
                  type="number"
                  min={0}
                  value={form.grace_late_minutes}
                  onChange={(e) => setForm({ ...form, grace_late_minutes: e.target.value })}
                  className="mt-1.5 num"
                  dir="ltr"
                />
              </div>
              <div>
                <Label className="text-xs font-semibold text-ink-2">سماحية الانصراف المبكر (د)</Label>
                <Input
                  type="number"
                  min={0}
                  value={form.grace_early_leave_minutes}
                  onChange={(e) => setForm({ ...form, grace_early_leave_minutes: e.target.value })}
                  className="mt-1.5 num"
                  dir="ltr"
                />
              </div>
            </div>

            <div className="flex items-center justify-between rounded-lg border border-hairline p-3">
              <Label className="text-sm font-medium text-ink">الجدول مفعل</Label>
              <Switch
                checked={form.is_active}
                onCheckedChange={(checked) => setForm({ ...form, is_active: checked })}
              />
            </div>

            <DialogFooter>
              <Button type="submit" disabled={saveMutation.isPending} className="bg-brand text-white hover:bg-brand-hover">
                {saveMutation.isPending && <Spinner />}
                حفظ
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <AlertDialog open={Boolean(deletingSchedule)} onOpenChange={(open) => !open && setDeletingSchedule(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>حذف الجدول</AlertDialogTitle>
            <AlertDialogDescription>
              هل أنت متأكد من حذف جدول &quot;{deletingSchedule?.name}&quot;؟ قد يؤثر هذا على الموظفين المرتبطين به.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>إلغاء</AlertDialogCancel>
            <AlertDialogAction
              className="bg-danger text-white hover:bg-danger/90"
              onClick={() => deletingSchedule && deleteMutation.mutate(deletingSchedule.id)}
            >
              حذف
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
