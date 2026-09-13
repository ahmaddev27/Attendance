'use client';

import * as React from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
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
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { clientsApi } from '@/lib/api/endpoints/recruitment';
import { CLIENT_STATUS_OPTIONS } from '@/lib/constants/recruitment-options';
import type { Client, ClientPayload, ClientStatus } from '@/lib/api/types';

const schema = z.object({
  company_name: z.string().trim().min(1, 'اسم الشركة مطلوب').max(200),
  company_website: z.string().trim().url('رابط غير صالح').max(255).optional().or(z.literal('')),
  industry: z.string().trim().max(100).optional().or(z.literal('')),
  company_size: z.string().trim().max(50).optional().or(z.literal('')),
  country: z.string().trim().max(100).optional().or(z.literal('')),
  city: z.string().trim().max(100).optional().or(z.literal('')),
  address: z.string().trim().max(1000).optional().or(z.literal('')),
  tax_number: z.string().trim().max(50).optional().or(z.literal('')),
  payment_terms: z.string().trim().max(50).optional().or(z.literal('')),
  payment_terms_notes: z.string().trim().max(2000).optional().or(z.literal('')),
  status: z.string().optional(),
  notes: z.string().trim().max(5000).optional().or(z.literal('')),
});

type FormValues = z.infer<typeof schema>;

const EMPTY: FormValues = {
  company_name: '',
  company_website: '',
  industry: '',
  company_size: '',
  country: '',
  city: '',
  address: '',
  tax_number: '',
  payment_terms: '',
  payment_terms_notes: '',
  status: 'active',
  notes: '',
};

function buildValues(client?: Client | null): FormValues {
  if (!client) return EMPTY;
  return {
    company_name: client.company_name,
    company_website: client.company_website ?? '',
    industry: client.industry ?? '',
    company_size: client.company_size ?? '',
    country: client.country ?? '',
    city: client.city ?? '',
    address: client.address ?? '',
    tax_number: client.tax_number ?? '',
    payment_terms: client.payment_terms ?? '',
    payment_terms_notes: client.payment_terms_notes ?? '',
    status: client.status,
    notes: client.notes ?? '',
  };
}

function toPayload(values: FormValues): ClientPayload {
  const nz = (v: string | undefined) => (v && v.trim() !== '' ? v.trim() : null);
  return {
    company_name: values.company_name.trim(),
    company_website: nz(values.company_website),
    industry: nz(values.industry),
    company_size: nz(values.company_size),
    country: nz(values.country),
    city: nz(values.city),
    address: nz(values.address),
    tax_number: nz(values.tax_number),
    payment_terms: nz(values.payment_terms),
    payment_terms_notes: nz(values.payment_terms_notes),
    status: values.status ? (values.status as ClientStatus) : undefined,
    notes: nz(values.notes),
  };
}

type ClientFormDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  client?: Client | null;
};

export function ClientFormDialog({ open, onOpenChange, client }: ClientFormDialogProps) {
  const qc = useQueryClient();
  const isEdit = !!client;
  const [forceDuplicate, setForceDuplicate] = React.useState(false);

  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: buildValues(client),
  });

  React.useEffect(() => {
    if (open) {
      form.reset(buildValues(client));
      setForceDuplicate(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, client?.id]);

  const mutation = useMutation({
    mutationFn: (values: FormValues) => {
      const payload = toPayload(values);
      if (isEdit && client) return clientsApi.update(client.id, payload);
      return clientsApi.create({ ...payload, force: forceDuplicate || undefined });
    },
    onSuccess: () => {
      toast.success(isEdit ? 'تم تحديث العميل' : 'تم إنشاء عميل جديد');
      qc.invalidateQueries({ queryKey: ['clients'] });
      onOpenChange(false);
    },
    onError: (err: unknown) => {
      const status = (err as { response?: { status?: number } })?.response?.status;
      const data = (err as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } })?.response?.data;
      if (!isEdit && status === 422 && data?.errors?.company_name) {
        setForceDuplicate(true);
        toast.warning(data.errors.company_name[0] ?? 'يوجد عميل مشابه — أعد المحاولة للتأكيد.');
        return;
      }
      toast.error(data?.message || 'تعذر حفظ العميل');
    },
  });

  const onSubmit = form.handleSubmit((v) => mutation.mutate(v));

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>{isEdit ? 'تعديل عميل' : 'عميل جديد'}</DialogTitle>
          <DialogDescription>{isEdit ? 'حدّث بيانات العميل.' : 'أدخل بيانات العميل.'}</DialogDescription>
        </DialogHeader>
        <Form {...form}>
          <form onSubmit={onSubmit} className="space-y-4">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <FormField control={form.control} name="company_name" render={({ field }) => (
                <FormItem className="sm:col-span-2">
                  <FormLabel>اسم الشركة</FormLabel>
                  <FormControl><Input {...field} /></FormControl>
                  <FormMessage />
                </FormItem>
              )} />
              <FormField control={form.control} name="industry" render={({ field }) => (
                <FormItem><FormLabel>القطاع</FormLabel><FormControl><Input {...field} /></FormControl><FormMessage /></FormItem>
              )} />
              <FormField control={form.control} name="company_size" render={({ field }) => (
                <FormItem><FormLabel>حجم الشركة</FormLabel><FormControl><Input {...field} /></FormControl><FormMessage /></FormItem>
              )} />
              <FormField control={form.control} name="country" render={({ field }) => (
                <FormItem><FormLabel>الدولة</FormLabel><FormControl><Input {...field} /></FormControl><FormMessage /></FormItem>
              )} />
              <FormField control={form.control} name="city" render={({ field }) => (
                <FormItem><FormLabel>المدينة</FormLabel><FormControl><Input {...field} /></FormControl><FormMessage /></FormItem>
              )} />
              <FormField control={form.control} name="address" render={({ field }) => (
                <FormItem className="sm:col-span-2"><FormLabel>العنوان</FormLabel><FormControl><Input {...field} /></FormControl><FormMessage /></FormItem>
              )} />
              <FormField control={form.control} name="company_website" render={({ field }) => (
                <FormItem className="sm:col-span-2"><FormLabel>الموقع</FormLabel><FormControl><Input {...field} placeholder="https://" /></FormControl><FormMessage /></FormItem>
              )} />
              <FormField control={form.control} name="tax_number" render={({ field }) => (
                <FormItem><FormLabel>الرقم الضريبي</FormLabel><FormControl><Input {...field} /></FormControl><FormMessage /></FormItem>
              )} />
              <FormField control={form.control} name="payment_terms" render={({ field }) => (
                <FormItem><FormLabel>شروط الدفع</FormLabel><FormControl><Input {...field} placeholder="مثال: NET-30" /></FormControl><FormMessage /></FormItem>
              )} />
              <FormField control={form.control} name="status" render={({ field }) => (
                <FormItem>
                  <FormLabel>الحالة</FormLabel>
                  <Select value={field.value} onValueChange={field.onChange}>
                    <FormControl><SelectTrigger><SelectValue /></SelectTrigger></FormControl>
                    <SelectContent>
                      {CLIENT_STATUS_OPTIONS.map((opt) => (
                        <SelectItem key={opt.value} value={opt.value}>{opt.label}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )} />
            </div>
            <FormField control={form.control} name="notes" render={({ field }) => (
              <FormItem><FormLabel>ملاحظات</FormLabel><FormControl><Textarea rows={3} {...field} /></FormControl><FormMessage /></FormItem>
            )} />

            {forceDuplicate && (
              <p className="rounded-md border border-warn-soft bg-warn-soft/50 p-2 text-xs text-warn-ink">
                تم اكتشاف تشابه مع عميل قائم — أعد الحفظ للتأكيد.
              </p>
            )}
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>إلغاء</Button>
              <Button type="submit" disabled={mutation.isPending} className="bg-brand text-white hover:bg-brand-hover">
                {mutation.isPending && <Spinner className="text-white" />}
                {isEdit ? 'حفظ' : 'إنشاء'}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
