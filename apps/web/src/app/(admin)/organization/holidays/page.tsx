'use client';

import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Pencil, Plus, Trash2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Badge } from '@/components/ui/badge';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
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
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { holidaysApi } from '@/lib/api/endpoints/holidays';
import { formatWeekday } from '@/lib/attendance-format';
import type { Holiday, HolidayPayload, HolidayType } from '@/lib/api/types';

const TYPE_LABELS: Record<HolidayType, string> = {
  official: 'رسمية',
  company: 'شركة',
  special: 'خاصة',
};

const TYPE_BADGE_CLASS: Record<HolidayType, string> = {
  official: 'border-transparent bg-brand-soft text-brand-ink',
  company: 'border-transparent bg-success-soft text-success',
  special: 'border-transparent bg-warn-soft text-warn-ink',
};

type HolidayFormState = {
  date: string;
  name: string;
  type: HolidayType;
  is_recurring: boolean;
  description: string;
};

const EMPTY_FORM: HolidayFormState = {
  date: '',
  name: '',
  type: 'official',
  is_recurring: false,
  description: '',
};

function holidayToForm(holiday: Holiday): HolidayFormState {
  return {
    date: holiday.date,
    name: holiday.name,
    type: holiday.type,
    is_recurring: holiday.is_recurring,
    description: holiday.description ?? '',
  };
}

function formToPayload(form: HolidayFormState): HolidayPayload {
  return {
    date: form.date,
    name: form.name.trim(),
    type: form.type,
    is_recurring: form.is_recurring,
    description: form.description.trim() || null,
  };
}

const currentYear = new Date().getFullYear();
const YEAR_OPTIONS = [currentYear - 1, currentYear, currentYear + 1, currentYear + 2];

export default function HolidaysPage() {
  const queryClient = useQueryClient();
  const [year, setYear] = useState(currentYear);
  const [typeFilter, setTypeFilter] = useState<HolidayType | 'all'>('all');

  const { data: holidays, isLoading } = useQuery({
    queryKey: ['holidays', year, typeFilter],
    queryFn: () => holidaysApi.list({ year, type: typeFilter === 'all' ? undefined : typeFilter }),
  });

  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingHoliday, setEditingHoliday] = useState<Holiday | null>(null);
  const [form, setForm] = useState<HolidayFormState>(EMPTY_FORM);
  const [deletingHoliday, setDeletingHoliday] = useState<Holiday | null>(null);

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['holidays'] });

  const saveMutation = useMutation({
    mutationFn: (payload: HolidayPayload) =>
      editingHoliday ? holidaysApi.update(editingHoliday.id, payload) : holidaysApi.create(payload),
    onSuccess: () => {
      toast.success(editingHoliday ? 'تم تحديث العطلة' : 'تم إضافة العطلة');
      setDialogOpen(false);
      invalidate();
    },
    onError: () => toast.error('تعذر حفظ بيانات العطلة'),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => holidaysApi.remove(id),
    onSuccess: () => {
      toast.success('تم حذف العطلة');
      setDeletingHoliday(null);
      invalidate();
    },
    onError: () => toast.error('تعذر حذف العطلة'),
  });

  const openCreate = () => {
    setEditingHoliday(null);
    setForm(EMPTY_FORM);
    setDialogOpen(true);
  };

  const openEdit = (holiday: Holiday) => {
    setEditingHoliday(holiday);
    setForm(holidayToForm(holiday));
    setDialogOpen(true);
  };

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!form.date || !form.name.trim()) {
      toast.error('التاريخ واسم العطلة مطلوبان');
      return;
    }
    saveMutation.mutate(formToPayload(form));
  };

  return (
    <div>
      <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="text-xs text-muted">إدارة النظام</p>
          <h1 className="mt-1 text-2xl font-bold text-ink">العطل الرسمية</h1>
        </div>
        <Button type="button" onClick={openCreate} className="bg-brand text-white hover:bg-brand-hover">
          <Plus className="h-4 w-4" />
          إضافة عطلة
        </Button>
      </div>

      <div className="mb-4 flex flex-wrap gap-3">
        <Select value={String(year)} onValueChange={(v) => setYear(Number(v))}>
          <SelectTrigger className="w-32">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {YEAR_OPTIONS.map((y) => (
              <SelectItem key={y} value={String(y)}>
                <span className="num">{y}</span>
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Select value={typeFilter} onValueChange={(v) => setTypeFilter(v as HolidayType | 'all')}>
          <SelectTrigger className="w-40">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">كل الأنواع</SelectItem>
            <SelectItem value="official">رسمية</SelectItem>
            <SelectItem value="company">شركة</SelectItem>
            <SelectItem value="special">خاصة</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div className="rounded-xl border border-hairline bg-surface">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="text-right">التاريخ</TableHead>
              <TableHead className="text-right">الاسم</TableHead>
              <TableHead className="text-right">النوع</TableHead>
              <TableHead className="text-right">متكررة</TableHead>
              <TableHead className="text-right">إجراءات</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {isLoading &&
              Array.from({ length: 3 }).map((_, i) => (
                <TableRow key={i}>
                  {Array.from({ length: 5 }).map((__, j) => (
                    <TableCell key={j}>
                      <Skeleton className="h-4 w-full" />
                    </TableCell>
                  ))}
                </TableRow>
              ))}

            {!isLoading && (holidays?.length ?? 0) === 0 && (
              <TableRow>
                <TableCell colSpan={5} className="py-10 text-center text-sm text-muted">
                  لا توجد عطل مسجلة لهذه السنة
                </TableCell>
              </TableRow>
            )}

            {holidays?.map((holiday) => (
              <TableRow key={holiday.id}>
                <TableCell>
                  <span className="num font-medium text-ink">{holiday.date}</span>
                  <span className="mr-2 text-xs text-muted">{formatWeekday(holiday.date)}</span>
                </TableCell>
                <TableCell className="font-medium text-ink">{holiday.name}</TableCell>
                <TableCell>
                  <Badge className={TYPE_BADGE_CLASS[holiday.type]}>{TYPE_LABELS[holiday.type]}</Badge>
                </TableCell>
                <TableCell>
                  {holiday.is_recurring ? (
                    <Badge className="border-transparent bg-surface-2 text-ink-2">تتكرر سنوياً</Badge>
                  ) : (
                    <span className="text-xs text-muted">—</span>
                  )}
                </TableCell>
                <TableCell>
                  <div className="flex items-center gap-1">
                    <Button type="button" variant="ghost" size="icon" title="تعديل" onClick={() => openEdit(holiday)}>
                      <Pencil className="h-4 w-4" />
                    </Button>
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      title="حذف"
                      className="text-danger hover:bg-danger-soft hover:text-danger"
                      onClick={() => setDeletingHoliday(holiday)}
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </div>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle>{editingHoliday ? 'تعديل العطلة' : 'إضافة عطلة جديدة'}</DialogTitle>
          </DialogHeader>
          <form onSubmit={submit} className="space-y-4">
            <div className="grid grid-cols-2 gap-3">
              <div>
                <Label className="text-xs font-semibold text-ink-2">التاريخ</Label>
                <Input
                  type="date"
                  value={form.date}
                  onChange={(e) => setForm({ ...form, date: e.target.value })}
                  className="mt-1.5"
                  dir="ltr"
                  required
                />
              </div>
              <div>
                <Label className="text-xs font-semibold text-ink-2">اسم العطلة</Label>
                <Input
                  value={form.name}
                  onChange={(e) => setForm({ ...form, name: e.target.value })}
                  className="mt-1.5"
                  required
                />
              </div>
            </div>

            <div>
              <Label className="text-xs font-semibold text-ink-2">النوع</Label>
              <RadioGroup
                value={form.type}
                onValueChange={(v) => setForm({ ...form, type: v as HolidayType })}
                className="mt-2 flex gap-4"
              >
                {(Object.keys(TYPE_LABELS) as HolidayType[]).map((type) => (
                  <label key={type} className="flex items-center gap-2 text-sm text-ink">
                    <RadioGroupItem value={type} />
                    {TYPE_LABELS[type]}
                  </label>
                ))}
              </RadioGroup>
            </div>

            <label className="flex items-center gap-2 text-sm text-ink">
              <Checkbox
                checked={form.is_recurring}
                onCheckedChange={(checked) => setForm({ ...form, is_recurring: checked === true })}
              />
              تتكرر كل سنة في نفس التاريخ
            </label>

            <div>
              <Label className="text-xs font-semibold text-ink-2">وصف (اختياري)</Label>
              <Textarea
                value={form.description}
                onChange={(e) => setForm({ ...form, description: e.target.value })}
                className="mt-1.5"
                rows={3}
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

      <AlertDialog open={Boolean(deletingHoliday)} onOpenChange={(open) => !open && setDeletingHoliday(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>حذف العطلة</AlertDialogTitle>
            <AlertDialogDescription>
              هل أنت متأكد من حذف عطلة &quot;{deletingHoliday?.name}&quot;؟
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>إلغاء</AlertDialogCancel>
            <AlertDialogAction
              className="bg-danger text-white hover:bg-danger/90"
              onClick={() => deletingHoliday && deleteMutation.mutate(deletingHoliday.id)}
            >
              حذف
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
