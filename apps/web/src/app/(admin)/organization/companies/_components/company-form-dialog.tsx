'use client';

import * as React from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Trash2, Upload } from 'lucide-react';
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
import { companiesApi } from '@/lib/api/endpoints/companies';
import type { Company, CompanyInput } from '@/lib/api/types';

/**
 * The short-list of IANA zones a TAQAT operator is realistically going to
 * pick from. The backend still validates against PHP's full
 * DateTimeZone::listIdentifiers() catalog, so adding another entry here
 * is a UI-only change.
 */
const TIMEZONE_OPTIONS: ReadonlyArray<{ value: string; label: string }> = [
  { value: 'Asia/Amman', label: 'عمّان (Asia/Amman)' },
  { value: 'Asia/Gaza', label: 'غزة (Asia/Gaza)' },
  { value: 'Asia/Hebron', label: 'الخليل (Asia/Hebron)' },
  { value: 'Asia/Jerusalem', label: 'القدس (Asia/Jerusalem)' },
  { value: 'Asia/Beirut', label: 'بيروت (Asia/Beirut)' },
  { value: 'Asia/Damascus', label: 'دمشق (Asia/Damascus)' },
  { value: 'Asia/Baghdad', label: 'بغداد (Asia/Baghdad)' },
  { value: 'Asia/Riyadh', label: 'الرياض (Asia/Riyadh)' },
  { value: 'Asia/Dubai', label: 'دبي (Asia/Dubai)' },
  { value: 'Africa/Cairo', label: 'القاهرة (Africa/Cairo)' },
  { value: 'UTC', label: 'UTC' },
];

const companyFormSchema = z.object({
  name: z.string().trim().min(1, 'اسم الشركة مطلوب').max(200, 'اسم الشركة طويل جداً'),
  timezone: z.string().trim().min(1, 'المنطقة الزمنية مطلوبة'),
});

type CompanyFormValues = z.infer<typeof companyFormSchema>;

function buildDefaultValues(company?: Company | null): CompanyFormValues {
  if (!company) {
    return { name: '', timezone: 'Asia/Amman' };
  }
  return { name: company.name, timezone: company.timezone };
}

type CompanyFormDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  company?: Company | null;
};

const LOGO_MAX_BYTES = 2 * 1024 * 1024;
const LOGO_ACCEPT = 'image/jpeg,image/png,image/webp';

export function CompanyFormDialog({ open, onOpenChange, company }: CompanyFormDialogProps) {
  const isEdit = !!company;
  const queryClient = useQueryClient();
  const fileInputRef = React.useRef<HTMLInputElement | null>(null);
  const [previewUrl, setPreviewUrl] = React.useState<string | null>(null);

  const form = useForm<CompanyFormValues>({
    resolver: zodResolver(companyFormSchema),
    defaultValues: buildDefaultValues(company),
  });

  React.useEffect(() => {
    if (open) {
      form.reset(buildDefaultValues(company));
      setPreviewUrl(company?.logo_url ?? null);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, company?.id]);

  const invalidateCompanies = () => {
    queryClient.invalidateQueries({ queryKey: ['companies'] });
  };

  const saveMutation = useMutation({
    mutationFn: (values: CompanyFormValues) => {
      const payload: CompanyInput = { name: values.name, timezone: values.timezone };
      return isEdit ? companiesApi.update(company.id, payload) : companiesApi.create(payload);
    },
    onSuccess: () => {
      toast.success(isEdit ? 'تم تحديث الشركة بنجاح' : 'تمت إضافة الشركة بنجاح');
      invalidateCompanies();
      onOpenChange(false);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'تعذر حفظ بيانات الشركة');
    },
  });

  const uploadLogoMutation = useMutation({
    mutationFn: (file: File) => companiesApi.uploadLogo(company!.id, file),
    onSuccess: ({ data }) => {
      toast.success('تم رفع الشعار');
      setPreviewUrl(data.data.logo_url);
      invalidateCompanies();
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'تعذر رفع الشعار');
    },
  });

  const removeLogoMutation = useMutation({
    mutationFn: () => companiesApi.removeLogo(company!.id),
    onSuccess: () => {
      toast.success('تم إزالة الشعار');
      setPreviewUrl(null);
      invalidateCompanies();
    },
    onError: () => {
      toast.error('تعذر إزالة الشعار');
    },
  });

  const onFilePick = (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    event.target.value = ''; // reset so re-picking the same file still fires
    if (!file) return;

    if (file.size > LOGO_MAX_BYTES) {
      toast.error('حجم الشعار يجب أن يكون أقل من 2 ميجابايت');
      return;
    }
    if (!LOGO_ACCEPT.split(',').includes(file.type)) {
      toast.error('الصيغ المسموحة: JPG، PNG، WEBP');
      return;
    }

    uploadLogoMutation.mutate(file);
  };

  const onSubmit = form.handleSubmit((values) => saveMutation.mutate(values));

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>{isEdit ? 'تعديل الشركة' : 'إضافة شركة جديدة'}</DialogTitle>
          <DialogDescription>
            {isEdit ? 'حدّث بيانات الشركة ثم احفظ التغييرات.' : 'أدخل بيانات الشركة الجديدة.'}
          </DialogDescription>
        </DialogHeader>

        <Form {...form}>
          <form onSubmit={onSubmit} className="space-y-4">
            <FormField
              control={form.control}
              name="name"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>اسم الشركة</FormLabel>
                  <FormControl>
                    <Input {...field} autoFocus />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name="timezone"
              render={({ field }) => (
                <FormItem>
                  <FormLabel>المنطقة الزمنية</FormLabel>
                  <Select value={field.value} onValueChange={field.onChange}>
                    <FormControl>
                      <SelectTrigger>
                        <SelectValue placeholder="اختر منطقة زمنية" />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {TIMEZONE_OPTIONS.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                          {option.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />

            {isEdit && (
              <div className="space-y-2">
                <p className="text-sm font-medium text-ink">شعار الشركة</p>
                <div className="flex items-center gap-4 rounded-lg border border-hairline bg-surface-2 p-3">
                  <div className="grid h-16 w-16 shrink-0 place-items-center overflow-hidden rounded-lg border border-hairline bg-surface">
                    {previewUrl ? (
                      // eslint-disable-next-line @next/next/no-img-element
                      <img src={previewUrl} alt="شعار الشركة" className="h-full w-full object-contain" />
                    ) : (
                      <span className="text-[11px] text-muted">بدون شعار</span>
                    )}
                  </div>
                  <div className="flex flex-1 flex-wrap items-center gap-2">
                    <input
                      ref={fileInputRef}
                      type="file"
                      className="hidden"
                      accept={LOGO_ACCEPT}
                      onChange={onFilePick}
                    />
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      disabled={uploadLogoMutation.isPending}
                      onClick={() => fileInputRef.current?.click()}
                      className="gap-2"
                    >
                      {uploadLogoMutation.isPending ? <Spinner /> : <Upload className="h-4 w-4" />}
                      رفع شعار جديد
                    </Button>
                    {previewUrl && (
                      <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={removeLogoMutation.isPending}
                        onClick={() => removeLogoMutation.mutate()}
                        className="gap-2 border-danger/40 text-danger hover:bg-danger-soft"
                      >
                        {removeLogoMutation.isPending ? <Spinner /> : <Trash2 className="h-4 w-4" />}
                        إزالة
                      </Button>
                    )}
                  </div>
                </div>
                <p className="text-xs text-muted">
                  يُنصح باستخدام شعار مربّع بصيغة PNG أو WEBP، بحجم أقل من 2 ميجابايت.
                </p>
              </div>
            )}

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                إلغاء
              </Button>
              <Button
                type="submit"
                disabled={saveMutation.isPending}
                className="bg-brand text-white hover:bg-brand-hover"
              >
                {saveMutation.isPending && <Spinner className="text-white" />}
                {isEdit ? 'حفظ التغييرات' : 'إضافة الشركة'}
              </Button>
            </DialogFooter>
          </form>
        </Form>
      </DialogContent>
    </Dialog>
  );
}
