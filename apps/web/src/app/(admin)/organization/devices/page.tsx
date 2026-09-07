'use client';

import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Eye, Pencil, Plus, RotateCw, Trash2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
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
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { QrDisplay } from '@/components/attendance/qr-display';
import { devicesApi } from '@/lib/api/endpoints/devices';
import type { AttendanceDevice, AttendanceDevicePayload } from '@/lib/api/types';

type DeviceFormState = {
  name: string;
  allowed_lat: string;
  allowed_lng: string;
  allowed_radius_meters: string;
  ip_whitelist: string;
  is_active: boolean;
};

const EMPTY_FORM: DeviceFormState = {
  name: '',
  allowed_lat: '',
  allowed_lng: '',
  allowed_radius_meters: '',
  ip_whitelist: '',
  is_active: true,
};

function deviceToForm(device: AttendanceDevice): DeviceFormState {
  return {
    name: device.name,
    allowed_lat: device.allowed_lat?.toString() ?? '',
    allowed_lng: device.allowed_lng?.toString() ?? '',
    allowed_radius_meters: device.allowed_radius_meters?.toString() ?? '',
    ip_whitelist: (device.ip_whitelist ?? []).join('\n'),
    is_active: device.is_active,
  };
}

function formToPayload(form: DeviceFormState): AttendanceDevicePayload {
  const toNumberOrNull = (v: string) => (v.trim() === '' ? null : Number(v));
  const ipList = form.ip_whitelist
    .split('\n')
    .map((line) => line.trim())
    .filter(Boolean);

  return {
    name: form.name.trim(),
    allowed_lat: toNumberOrNull(form.allowed_lat),
    allowed_lng: toNumberOrNull(form.allowed_lng),
    allowed_radius_meters: toNumberOrNull(form.allowed_radius_meters),
    ip_whitelist: ipList.length > 0 ? ipList : null,
    is_active: form.is_active,
  };
}

export default function AttendanceDevicesPage() {
  const queryClient = useQueryClient();
  const { data: devices, isLoading } = useQuery({
    queryKey: ['attendance-devices'],
    queryFn: devicesApi.list,
  });

  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingDevice, setEditingDevice] = useState<AttendanceDevice | null>(null);
  const [form, setForm] = useState<DeviceFormState>(EMPTY_FORM);
  const [qrDevice, setQrDevice] = useState<AttendanceDevice | null>(null);
  const [deletingDevice, setDeletingDevice] = useState<AttendanceDevice | null>(null);

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['attendance-devices'] });

  const saveMutation = useMutation({
    mutationFn: (payload: AttendanceDevicePayload) =>
      editingDevice ? devicesApi.update(editingDevice.id, payload) : devicesApi.create(payload),
    onSuccess: () => {
      toast.success(editingDevice ? 'تم تحديث الجهاز' : 'تم إضافة الجهاز');
      setDialogOpen(false);
      invalidate();
    },
    onError: () => toast.error('تعذر حفظ بيانات الجهاز'),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => devicesApi.remove(id),
    onSuccess: () => {
      toast.success('تم حذف الجهاز');
      setDeletingDevice(null);
      invalidate();
    },
    onError: () => toast.error('تعذر حذف الجهاز'),
  });

  const rotateMutation = useMutation({
    mutationFn: (id: number) => devicesApi.rotate(id),
    onSuccess: (updated) => {
      toast.success('تم تدوير رمز QR');
      setQrDevice(updated);
      invalidate();
    },
    onError: () => toast.error('تعذر تدوير الرمز'),
  });

  const openCreate = () => {
    setEditingDevice(null);
    setForm(EMPTY_FORM);
    setDialogOpen(true);
  };

  const openEdit = (device: AttendanceDevice) => {
    setEditingDevice(device);
    setForm(deviceToForm(device));
    setDialogOpen(true);
  };

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!form.name.trim()) {
      toast.error('اسم الجهاز مطلوب');
      return;
    }
    saveMutation.mutate(formToPayload(form));
  };

  const scanUrl = (token: string) =>
    typeof window !== 'undefined' ? `${window.location.origin}/scan/${token}` : `/scan/${token}`;

  return (
    <div>
      <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
          <p className="text-xs text-muted">إدارة النظام</p>
          <h1 className="mt-1 text-2xl font-bold text-ink">أجهزة QR للحضور</h1>
        </div>
        <Button type="button" onClick={openCreate} className="bg-brand text-white hover:bg-brand-hover">
          <Plus className="h-4 w-4" />
          إضافة جهاز
        </Button>
      </div>

      <div className="rounded-xl border border-hairline bg-surface">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead className="text-right">الاسم</TableHead>
              <TableHead className="text-right">الموقع</TableHead>
              <TableHead className="text-right">تقييد IP</TableHead>
              <TableHead className="text-right">الحالة</TableHead>
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

            {!isLoading && (devices?.length ?? 0) === 0 && (
              <TableRow>
                <TableCell colSpan={5} className="py-10 text-center text-sm text-muted">
                  لا توجد أجهزة مسجلة بعد
                </TableCell>
              </TableRow>
            )}

            {devices?.map((device) => (
              <TableRow key={device.id}>
                <TableCell className="font-medium text-ink">{device.name}</TableCell>
                <TableCell className="num">
                  {device.allowed_lat != null && device.allowed_lng != null
                    ? `${device.allowed_lat}, ${device.allowed_lng}`
                    : 'غير محدد'}
                </TableCell>
                <TableCell className="num">{device.ip_whitelist?.length ?? 0}</TableCell>
                <TableCell>
                  <Badge
                    className={
                      device.is_active
                        ? 'border-transparent bg-success-soft text-success'
                        : 'border-transparent bg-surface-2 text-muted'
                    }
                  >
                    {device.is_active ? 'مفعل' : 'معطل'}
                  </Badge>
                </TableCell>
                <TableCell>
                  <div className="flex items-center gap-1">
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      title="عرض رمز QR"
                      onClick={() => setQrDevice(device)}
                    >
                      <Eye className="h-4 w-4" />
                    </Button>
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      title="تدوير الرمز"
                      disabled={rotateMutation.isPending}
                      onClick={() => rotateMutation.mutate(device.id)}
                    >
                      <RotateCw className="h-4 w-4" />
                    </Button>
                    <Button type="button" variant="ghost" size="icon" title="تعديل" onClick={() => openEdit(device)}>
                      <Pencil className="h-4 w-4" />
                    </Button>
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      title="حذف"
                      className="text-danger hover:bg-danger-soft hover:text-danger"
                      onClick={() => setDeletingDevice(device)}
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

      {/* Create / edit dialog */}
      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle>{editingDevice ? 'تعديل الجهاز' : 'إضافة جهاز جديد'}</DialogTitle>
          </DialogHeader>
          <form onSubmit={submit} className="space-y-4">
            <div>
              <Label className="text-xs font-semibold text-ink-2">اسم الجهاز</Label>
              <Input
                value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
                className="mt-1.5"
                required
                autoFocus
              />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <Label className="text-xs font-semibold text-ink-2">خط العرض (Lat)</Label>
                <Input
                  type="number"
                  step="any"
                  value={form.allowed_lat}
                  onChange={(e) => setForm({ ...form, allowed_lat: e.target.value })}
                  className="mt-1.5 num"
                  dir="ltr"
                  placeholder="اختياري"
                />
              </div>
              <div>
                <Label className="text-xs font-semibold text-ink-2">خط الطول (Lng)</Label>
                <Input
                  type="number"
                  step="any"
                  value={form.allowed_lng}
                  onChange={(e) => setForm({ ...form, allowed_lng: e.target.value })}
                  className="mt-1.5 num"
                  dir="ltr"
                  placeholder="اختياري"
                />
              </div>
            </div>
            <div>
              <Label className="text-xs font-semibold text-ink-2">نطاق السماح (متر)</Label>
              <Input
                type="number"
                min={0}
                value={form.allowed_radius_meters}
                onChange={(e) => setForm({ ...form, allowed_radius_meters: e.target.value })}
                className="mt-1.5 num"
                dir="ltr"
                placeholder="اختياري — بدون تحديد نطاق المسافة"
              />
            </div>
            <div>
              <Label className="text-xs font-semibold text-ink-2">قائمة IP المسموح بها</Label>
              <Textarea
                value={form.ip_whitelist}
                onChange={(e) => setForm({ ...form, ip_whitelist: e.target.value })}
                className="mt-1.5 num"
                dir="ltr"
                rows={3}
                placeholder={'عنوان IP واحد لكل سطر\nاتركه فارغاً للسماح لأي عنوان'}
              />
            </div>
            <div className="flex items-center justify-between rounded-lg border border-hairline p-3">
              <Label className="text-sm font-medium text-ink">الجهاز مفعل</Label>
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

      {/* QR dialog */}
      <Dialog open={Boolean(qrDevice)} onOpenChange={(open) => !open && setQrDevice(null)}>
        <DialogContent className="max-w-sm">
          <DialogHeader>
            <DialogTitle>رمز QR — {qrDevice?.name}</DialogTitle>
          </DialogHeader>
          {qrDevice && (
            <div className="flex flex-col items-center gap-4 py-2">
              <QrDisplay
                value={scanUrl(qrDevice.qr_token)}
                rotatesEverySeconds={qrDevice.qr_rotates_every_seconds}
                lastRotatedAt={qrDevice.last_token_rotated_at}
              />
              <Button
                type="button"
                variant="outline"
                className="w-full"
                disabled={rotateMutation.isPending}
                onClick={() => rotateMutation.mutate(qrDevice.id)}
              >
                {rotateMutation.isPending ? <Spinner /> : <RotateCw className="h-4 w-4" />}
                تدوير الرمز الآن
              </Button>
            </div>
          )}
        </DialogContent>
      </Dialog>

      {/* Delete confirmation */}
      <AlertDialog open={Boolean(deletingDevice)} onOpenChange={(open) => !open && setDeletingDevice(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>حذف الجهاز</AlertDialogTitle>
            <AlertDialogDescription>
              هل أنت متأكد من حذف جهاز &quot;{deletingDevice?.name}&quot;؟ لا يمكن التراجع عن هذا الإجراء.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>إلغاء</AlertDialogCancel>
            <AlertDialogAction
              className="bg-danger text-white hover:bg-danger/90"
              onClick={() => deletingDevice && deleteMutation.mutate(deletingDevice.id)}
            >
              حذف
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
