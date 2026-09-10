'use client';

import * as React from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
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
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { FormSchemaBuilder } from '@/components/workflow/form-schema-builder';
import { requestTypesApi } from '@/lib/api/endpoints/request-types';
import { workflowsApi } from '@/lib/api/endpoints/workflows';
import { resolveLucideIcon } from '@/lib/dynamic-icon';
import type { FormField as RequestFormField, RequestType, RequestTypePayload } from '@/lib/api/types';

const HEX_COLOR_REGEX = /^#([0-9a-fA-F]{6}|[0-9a-fA-F]{3})$/;
const DEFAULT_COLOR = '#2678c4';
const CODE_REGEX = /^[a-zA-Z0-9_-]+$/;

const formFieldSchema = z.object({
  key: z.string().trim().min(1, 'مفتاح الحقل مطلوب').regex(/^[a-zA-Z_][a-zA-Z0-9_]*$/, 'يجب أن يبدأ بحرف ويحتوي أحرف/أرقام/شرطة سفلية فقط'),
  label: z.string().trim().min(1, 'تسمية الحقل مطلوبة'),
  type: z.enum(['text', 'textarea', 'number', 'date', 'select', 'checkbox', 'file', 'employee']),
  required: z.boolean(),
  options: z.array(z.string()).optional(),
  min: z.number().optional(),
  max: z.number().optional(),
  placeholder: z.string().optional(),
});

const requestTypeFormSchema = z.object({
  name: z.string().trim().min(1, 'اسم نوع الطلب مطلوب').max(100, 'الاسم طويل جداً'),
  code: z.string().trim().min(1, 'الرمز مطلوب').max(30, 'الرمز طويل جداً').regex(CODE_REGEX, 'الرمز يجب أن يحتوي أحرف/أرقام إنجليزية فقط'),
  description: z.string().trim().max(1000, 'الوصف طويل جداً').optional().or(z.literal('')),
  icon: z.string().trim().max(60, 'اسم الأيقونة طويل جداً').optional().or(z.literal('')),
  color: z.string().regex(HEX_COLOR_REGEX, 'صيغة اللون غير صحيحة (مثال: #2678C4)'),
  workflow_id: z.number({ invalid_type_error: 'مسار العمل مطلوب' }).min(1, 'مسار العمل مطلوب'),
  is_active: z.boolean(),
  sort_order: z.number().min(0, 'القيمة يجب أن تكون صفر أو أكثر'),
  form_schema: z.array(formFieldSchema).superRefine((fields, ctx) => {
    const keys = fields.map((field) => field.key.trim()).filter(Boolean);
    const duplicates = [...new Set(keys.filter((key, index) => keys.indexOf(key) !== index))];
    if (duplicates.length > 0) {
      ctx.addIssue({ code: z.ZodIssueCode.custom, message: `مفاتيح مكررة: ${duplicates.join(', ')}` });
    }
  }),
});

type RequestTypeFormValues = z.infer<typeof requestTypeFormSchema>;

function buildDefaultValues(requestType?: RequestType | null): RequestTypeFormValues {
  if (!requestType) {
    return {
      name: '',
      code: '',
      description: '',
      icon: '',
      color: DEFAULT_COLOR,
      workflow_id: 0,
      is_active: true,
      sort_order: 0,
      form_schema: [],
    };
  }
  return {
    name: requestType.name,
    code: requestType.code,
    description: requestType.description ?? '',
    icon: requestType.icon ?? '',
    color: requestType.color,
    workflow_id: requestType.workflow_id,
    is_active: requestType.is_active,
    sort_order: requestType.sort_order,
    form_schema: requestType.form_schema,
  };
}

type RequestTypeFormDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  requestType?: RequestType | null;
};

export function RequestTypeFormDialog({ open, onOpenChange, requestType }: RequestTypeFormDialogProps) {
  const isEdit = !!requestType;
  const queryClient = useQueryClient();

  const form = useForm<RequestTypeFormValues>({
    resolver: zodResolver(requestTypeFormSchema),
    defaultValues: buildDefaultValues(requestType),
  });

  React.useEffect(() => {
    if (open) form.reset(buildDefaultValues(requestType));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, requestType?.id]);

  const { data: workflows } = useQuery({
    queryKey: ['workflows', 'picker'],
    queryFn: async () => (await workflowsApi.list()).data.data,
    enabled: open,
  });

  const mutation = useMutation({
    mutationFn: (values: RequestTypeFormValues) => {
      const payload: RequestTypePayload = {
        name: values.name,
        code: values.code,
        description: values.description || null,
        icon: values.icon || null,
        color: values.color,
        workflow_id: values.workflow_id,
        form_schema: values.form_schema as RequestFormField[],
        is_active: values.is_active,
        sort_order: values.sort_order,
      };
      return isEdit ? requestTypesApi.update(requestType.id, payload) : requestTypesApi.create(payload);
    },
    onSuccess: () => {
      toast.success(isEdit ? 'تم تحديث نوع الطلب بنجاح' : 'تمت إضافة نوع الطلب بنجاح');
      queryClient.invalidateQueries({ queryKey: ['request-types'] });
      onOpenChange(false);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'حدث خطأ أثناء حفظ نوع الطلب');
    },
  });

  const onSubmit = form.handleSubmit((values) => mutation.mutate(values));

  const iconName = form.watch('icon');
  const IconPreview = resolveLucideIcon(iconName);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>{isEdit ? 'تعديل نوع الطلب' : 'إضافة نوع طلب جديد'}</DialogTitle>
          <DialogDescription>
            {isEdit ? 'قم بتحديث بيانات نوع الطلب ثم احفظ التغييرات.' : 'أدخل بيانات نوع الطلب الجديد وحقول نموذجه.'}
          </DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form onSubmit={onSubmit} className="space-y-5">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <FormField
                control={form.control}
                name="name"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>اسم نوع الطلب</FormLabel>
                    <FormControl>
                      <Input {...field} autoFocus />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="code"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>الرمز</FormLabel>
                    <FormControl>
                      <Input dir="ltr" className="text-right" {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            <FormField
              control={form.control}
              name="description"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>الوصف (اختياري)</FormLabel>
                  <FormControl>
                    <Textarea rows={2} {...field} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <FormField
                control={form.control}
                name="icon"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>أيقونة Lucide (اختياري)</FormLabel>
                    <div className="flex items-center gap-2">
                      <FormControl>
                        <Input dir="ltr" className="text-right" placeholder="FileText" {...field} />
                      </FormControl>
                      <div className="grid h-9 w-9 shrink-0 place-items-center rounded-md border border-hairline bg-surface-2">
                        {IconPreview ? <IconPreview className="h-4 w-4 text-ink-2" /> : <span className="text-xs text-muted">—</span>}
                      </div>
                    </div>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="color"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>اللون المميز</FormLabel>
                    <div className="flex items-center gap-2">
                      <FormControl>
                        <input
                          type="color"
                          value={HEX_COLOR_REGEX.test(field.value) ? field.value : DEFAULT_COLOR}
                          onChange={(e) => field.onChange(e.target.value)}
                          className="h-9 w-11 cursor-pointer rounded-md border border-hairline bg-transparent p-1"
                          aria-label="اختيار اللون"
                        />
                      </FormControl>
                      <FormControl>
                        <Input dir="ltr" className="text-right" {...field} placeholder="#2678C4" />
                      </FormControl>
                    </div>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>

            <FormField
              control={form.control}
              name="workflow_id"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>مسار العمل</FormLabel>
                  <Select
                    value={field.value ? String(field.value) : undefined}
                    onValueChange={(v) => field.onChange(Number(v))}
                  >
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="اختر مسار العمل" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {(workflows ?? []).map((workflow) => (
                        <SelectItem key={workflow.id} value={String(workflow.id)}>
                          {workflow.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <FormField
                control={form.control}
                name="sort_order"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>ترتيب العرض</FormLabel>
                    <FormControl>
                      <Input
                        type="number"
                        min={0}
                        dir="ltr"
                        className="num text-right"
                        value={field.value}
                        onChange={(e) => field.onChange(Number(e.target.value))}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="is_active"
                render={({ field }) => (
                  <FormItem className="flex flex-row items-center justify-between rounded-lg border border-hairline p-3">
                    <FormLabel className="cursor-pointer">نوع الطلب نشط</FormLabel>
                    <FormControl>
                      <Switch checked={field.value} onCheckedChange={field.onChange} />
                    </FormControl>
                  </FormItem>
                )}
              />
            </div>

            <FormField
              control={form.control}
              name="form_schema"
              render={({ field }) => (
                <FormItem>
                  <FormSchemaBuilder
                    value={field.value}
                    onChange={field.onChange}
                    resetToken={open ? requestType?.id ?? 'new' : undefined}
                  />
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
                {isEdit ? 'حفظ التغييرات' : 'إضافة النوع'}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
