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
import { leadsApi } from '@/lib/api/endpoints/recruitment';
import { LEAD_SOURCE_OPTIONS, LEAD_STATUS_OPTIONS } from '@/lib/constants/recruitment-options';
import type { Lead, LeadPayload, LeadSource, LeadStatus } from '@/lib/api/types';

const leadFormSchema = z.object({
  company_name: z.string().trim().min(1, 'اسم الشركة مطلوب').max(200),
  company_website: z.string().trim().url('رابط غير صالح').max(255).optional().or(z.literal('')),
  industry: z.string().trim().max(100).optional().or(z.literal('')),
  company_size: z.string().trim().max(50).optional().or(z.literal('')),
  country: z.string().trim().max(100).optional().or(z.literal('')),
  city: z.string().trim().max(100).optional().or(z.literal('')),

  contact_person: z.string().trim().max(150).optional().or(z.literal('')),
  contact_position: z.string().trim().max(150).optional().or(z.literal('')),
  contact_email: z.string().trim().email('بريد إلكتروني غير صالح').max(150).optional().or(z.literal('')),
  contact_phone: z.string().trim().max(30).optional().or(z.literal('')),
  linkedin_url: z.string().trim().url('رابط غير صالح').max(255).optional().or(z.literal('')),

  source: z.string().min(1, 'مصدر مطلوب'),
  status: z.string().optional(),
  expected_hiring_volume: z.string().optional(),
  notes: z.string().trim().max(5000).optional().or(z.literal('')),
  next_followup_at: z.string().optional().or(z.literal('')),
});

type LeadFormValues = z.infer<typeof leadFormSchema>;

const EMPTY_VALUES: LeadFormValues = {
  company_name: '',
  company_website: '',
  industry: '',
  company_size: '',
  country: '',
  city: '',
  contact_person: '',
  contact_position: '',
  contact_email: '',
  contact_phone: '',
  linkedin_url: '',
  source: 'linkedin',
  status: 'new',
  expected_hiring_volume: '',
  notes: '',
  next_followup_at: '',
};

function buildValues(lead?: Lead | null): LeadFormValues {
  if (!lead) return EMPTY_VALUES;
  return {
    company_name: lead.company_name,
    company_website: lead.company_website ?? '',
    industry: lead.industry ?? '',
    company_size: lead.company_size ?? '',
    country: lead.country ?? '',
    city: lead.city ?? '',
    contact_person: lead.contact_person ?? '',
    contact_position: lead.contact_position ?? '',
    contact_email: lead.contact_email ?? '',
    contact_phone: lead.contact_phone ?? '',
    linkedin_url: lead.linkedin_url ?? '',
    source: lead.source,
    status: lead.status,
    expected_hiring_volume: lead.expected_hiring_volume?.toString() ?? '',
    notes: lead.notes ?? '',
    next_followup_at: lead.next_followup_at ? lead.next_followup_at.slice(0, 10) : '',
  };
}

function toPayload(values: LeadFormValues): LeadPayload {
  const nz = (v: string | undefined) => (v && v.trim() !== '' ? v.trim() : null);
  return {
    company_name: values.company_name.trim(),
    company_website: nz(values.company_website),
    industry: nz(values.industry),
    company_size: nz(values.company_size),
    country: nz(values.country),
    city: nz(values.city),
    contact_person: nz(values.contact_person),
    contact_position: nz(values.contact_position),
    contact_email: nz(values.contact_email),
    contact_phone: nz(values.contact_phone),
    linkedin_url: nz(values.linkedin_url),
    source: values.source as LeadSource,
    status: values.status ? (values.status as LeadStatus) : undefined,
    expected_hiring_volume: values.expected_hiring_volume ? Number(values.expected_hiring_volume) : null,
    notes: nz(values.notes),
    next_followup_at: nz(values.next_followup_at),
  };
}

function extractErrorMessage(err: unknown, fallback: string): string {
  const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
  return message || fallback;
}

type LeadFormDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  lead?: Lead | null;
};

/** Create/edit dialog for a Lead. Delegates duplicate-warning handling to the backend. */
export function LeadFormDialog({ open, onOpenChange, lead }: LeadFormDialogProps) {
  const queryClient = useQueryClient();
  const isEdit = !!lead;
  const [forceDuplicate, setForceDuplicate] = React.useState(false);

  const form = useForm<LeadFormValues>({
    resolver: zodResolver(leadFormSchema),
    defaultValues: buildValues(lead),
  });

  React.useEffect(() => {
    if (open) {
      form.reset(buildValues(lead));
      setForceDuplicate(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, lead?.id]);

  const mutation = useMutation({
    mutationFn: (values: LeadFormValues) => {
      const payload = toPayload(values);
      if (isEdit && lead) return leadsApi.update(lead.id, payload);
      return leadsApi.create({ ...payload, force: forceDuplicate || undefined });
    },
    onSuccess: () => {
      toast.success(isEdit ? 'تم تحديث العميل المحتمل' : 'تم إنشاء عميل محتمل جديد');
      queryClient.invalidateQueries({ queryKey: ['leads'] });
      onOpenChange(false);
    },
    onError: (err: unknown) => {
      const status = (err as { response?: { status?: number } })?.response?.status;
      const data = (err as { response?: { data?: { errors?: Record<string, string[]> } } })?.response?.data;
      if (!isEdit && status === 422 && data?.errors?.company_name) {
        // Backend soft-dedup returns 422 with company_name error. Offer force insert.
        setForceDuplicate(true);
        toast.warning(data.errors.company_name[0] ?? 'يوجد عميل محتمل مشابه — أعد المحاولة للتأكيد.');
        return;
      }
      toast.error(extractErrorMessage(err, 'تعذر حفظ العميل المحتمل'));
    },
  });

  const onSubmit = form.handleSubmit((values) => mutation.mutate(values));

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[92vh] overflow-y-auto sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>{isEdit ? 'تعديل عميل محتمل' : 'عميل محتمل جديد'}</DialogTitle>
          <DialogDescription>
            {isEdit ? 'حدّث بيانات العميل المحتمل.' : 'أدخل معلومات الشركة وجهة الاتصال.'}
          </DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form onSubmit={onSubmit} className="space-y-4">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <FormField
                control={form.control}
                name="company_name"
                render={({ field }) => (
                  <FormItem className="sm:col-span-2">
                    <FormLabel>اسم الشركة</FormLabel>
                    <FormControl>
                      <Input {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="industry"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>القطاع</FormLabel>
                    <FormControl>
                      <Input {...field} placeholder="مثال: تقنية، صناعة" />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="company_size"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>حجم الشركة</FormLabel>
                    <FormControl>
                      <Input {...field} placeholder="مثال: 51-200" />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="country"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>الدولة</FormLabel>
                    <FormControl>
                      <Input {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="city"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>المدينة</FormLabel>
                    <FormControl>
                      <Input {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="company_website"
                render={({ field }) => (
                  <FormItem className="sm:col-span-2">
                    <FormLabel>موقع الشركة</FormLabel>
                    <FormControl>
                      <Input {...field} placeholder="https://" />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <FormField
                control={form.control}
                name="contact_person"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>جهة الاتصال</FormLabel>
                    <FormControl>
                      <Input {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="contact_position"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>المسمى الوظيفي</FormLabel>
                    <FormControl>
                      <Input {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="contact_email"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>البريد الإلكتروني</FormLabel>
                    <FormControl>
                      <Input type="email" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="contact_phone"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>الهاتف</FormLabel>
                    <FormControl>
                      <Input {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="linkedin_url"
                render={({ field }) => (
                  <FormItem className="sm:col-span-2">
                    <FormLabel>لينكدإن</FormLabel>
                    <FormControl>
                      <Input {...field} placeholder="https://linkedin.com/company/..." />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
              <FormField
                control={form.control}
                name="source"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>المصدر</FormLabel>
                    <Select value={field.value} onValueChange={field.onChange}>
                      <FormControl>
                        <SelectTrigger>
                          <SelectValue />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        {LEAD_SOURCE_OPTIONS.map((opt) => (
                          <SelectItem key={opt.value} value={opt.value}>
                            {opt.label}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="status"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>الحالة</FormLabel>
                    <Select value={field.value} onValueChange={field.onChange}>
                      <FormControl>
                        <SelectTrigger>
                          <SelectValue />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        {LEAD_STATUS_OPTIONS.map((opt) => (
                          <SelectItem key={opt.value} value={opt.value}>
                            {opt.label}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="expected_hiring_volume"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>حجم التوظيف المتوقع</FormLabel>
                    <FormControl>
                      <Input type="number" min={0} {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            <FormField
              control={form.control}
              name="next_followup_at"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>موعد المتابعة القادم</FormLabel>
                  <FormControl>
                    <Input type="date" {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="notes"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>ملاحظات</FormLabel>
                  <FormControl>
                    <Textarea rows={3} {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            {forceDuplicate && (
              <p className="rounded-md border border-warn-soft bg-warn-soft/50 p-2 text-xs text-warn-ink">
                تم اكتشاف تشابه مع سجل قائم — أعد الحفظ للتأكيد على الإنشاء رغم ذلك.
              </p>
            )}

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                إلغاء
              </Button>
              <Button type="submit" disabled={mutation.isPending} className="bg-brand text-white hover:bg-brand-hover">
                {mutation.isPending && <Spinner className="text-white" />}
                {isEdit ? 'حفظ التعديلات' : 'إنشاء'}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
