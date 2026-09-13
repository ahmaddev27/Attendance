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
import { leadActivitiesApi } from '@/lib/api/endpoints/recruitment';
import { LEAD_ACTIVITY_TYPE_OPTIONS } from '@/lib/constants/recruitment-options';
import type { LeadActivityPayload, LeadActivityType } from '@/lib/api/types';

const activitySchema = z.object({
  type: z.string().min(1),
  subject: z.string().trim().max(200).optional().or(z.literal('')),
  body: z.string().trim().max(5000).optional().or(z.literal('')),
  occurred_at: z.string().min(1, 'التاريخ مطلوب'),
});

type ActivityFormValues = z.infer<typeof activitySchema>;

function defaultOccurredAt(): string {
  // ISO local (yyyy-MM-ddTHH:mm) so <input type="datetime-local"> accepts it.
  const now = new Date();
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
}

type AddActivityDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  leadId: number;
};

/** Log a call/meeting/email/note against a Lead. */
export function AddActivityDialog({ open, onOpenChange, leadId }: AddActivityDialogProps) {
  const queryClient = useQueryClient();

  const form = useForm<ActivityFormValues>({
    resolver: zodResolver(activitySchema),
    defaultValues: {
      type: 'call',
      subject: '',
      body: '',
      occurred_at: defaultOccurredAt(),
    },
  });

  React.useEffect(() => {
    if (open) {
      form.reset({
        type: 'call',
        subject: '',
        body: '',
        occurred_at: defaultOccurredAt(),
      });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open]);

  const mutation = useMutation({
    mutationFn: (values: ActivityFormValues) => {
      const payload: LeadActivityPayload = {
        type: values.type as LeadActivityType,
        subject: values.subject?.trim() || null,
        body: values.body?.trim() || null,
        occurred_at: values.occurred_at,
      };
      return leadActivitiesApi.create(leadId, payload);
    },
    onSuccess: () => {
      toast.success('تم إضافة النشاط');
      queryClient.invalidateQueries({ queryKey: ['lead-activities', leadId] });
      queryClient.invalidateQueries({ queryKey: ['leads', leadId] });
      onOpenChange(false);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'تعذر إضافة النشاط');
    },
  });

  const onSubmit = form.handleSubmit((values) => mutation.mutate(values));

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>نشاط جديد</DialogTitle>
          <DialogDescription>سجّل مكالمة أو اجتماعاً أو ملاحظة على العميل المحتمل.</DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form onSubmit={onSubmit} className="space-y-4">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <FormField
                control={form.control}
                name="type"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>النوع</FormLabel>
                    <Select value={field.value} onValueChange={field.onChange}>
                      <FormControl>
                        <SelectTrigger>
                          <SelectValue />
                        </SelectTrigger>
                      </FormControl>
                      <SelectContent>
                        {LEAD_ACTIVITY_TYPE_OPTIONS.map((opt) => (
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
                name="occurred_at"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>حصل في</FormLabel>
                    <FormControl>
                      <Input type="datetime-local" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            <FormField
              control={form.control}
              name="subject"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>العنوان</FormLabel>
                  <FormControl>
                    <Input {...field} placeholder="مثال: مكالمة تعريفية مع علي" />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="body"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>التفاصيل</FormLabel>
                  <FormControl>
                    <Textarea rows={4} {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                إلغاء
              </Button>
              <Button type="submit" disabled={mutation.isPending} className="bg-brand text-white hover:bg-brand-hover">
                {mutation.isPending && <Spinner className="text-white" />}
                إضافة
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
