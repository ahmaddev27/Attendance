'use client';

import * as React from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { KeyRound, Loader2, ShieldCheck, User as UserIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { myProfileApi } from '@/lib/api/endpoints/employees';
import { cn } from '@/lib/utils';

type FieldErrors = Record<string, string | undefined>;

/**
 * Narrow a thrown axios error to Laravel's { message, errors } envelope
 * and hand back either the field bag or a top-level message. Kept local
 * to the page — every other page has its own tiny helper for the same
 * pattern, no shared util yet.
 */
function extractApiError(err: unknown): {
  message: string | null;
  errors: Record<string, string[]> | null;
} {
  const response = (
    err as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }
  )?.response;
  return {
    message: response?.data?.message ?? null,
    errors: response?.data?.errors ?? null,
  };
}

/**
 * `/profile` — employee self-service.
 *
 * Section 1 is a read-only snapshot of the HR-owned identity fields (name,
 * employee number, email, department, team, position, direct manager, work
 * schedule) plus the ONE editable field: phone.
 *
 * Section 2 rotates the password. The backend verifies `current_password`
 * server-side and revokes every OTHER Sanctum token on success, so a
 * password change also signs the employee out of any other device.
 */
export default function EmployeeProfilePage() {
  const queryClient = useQueryClient();

  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['me', 'profile'],
    queryFn: async () => (await myProfileApi.show()).data.data,
  });

  return (
    <div className="space-y-6">
      <div className="flex flex-col gap-2">
        <h1 className="text-2xl font-bold text-ink">الملف الشخصي</h1>
        <p className="text-sm text-muted">
          تحقّق من بيانات حسابك وحدّث رقم الجوال أو كلمة السر عند الحاجة.
        </p>
      </div>

      {isError && (
        <Card className="border-danger-soft bg-danger-soft/40 p-4 text-sm text-danger">
          تعذّر تحميل بيانات ملفك الشخصي.{' '}
          <button onClick={() => refetch()} className="font-semibold underline">
            إعادة المحاولة
          </button>
        </Card>
      )}

      <ProfileSection
        loading={isLoading}
        data={data}
        onSaved={() => queryClient.invalidateQueries({ queryKey: ['me', 'profile'] })}
      />

      <PasswordSection />
    </div>
  );
}

// ---------------------------------------------------------------------------
// Section 1 — identity + phone
// ---------------------------------------------------------------------------

type ProfileData = Awaited<ReturnType<typeof myProfileApi.show>>['data']['data'];

function ProfileSection({
  loading,
  data,
  onSaved,
}: {
  loading: boolean;
  data: ProfileData | undefined;
  onSaved: () => void;
}) {
  const [phone, setPhone] = React.useState('');
  const [phoneError, setPhoneError] = React.useState<string | null>(null);
  const [apiError, setApiError] = React.useState<string | null>(null);

  // Sync the input value whenever the query resolves (or the linked
  // employee row changes). Guarded by `data?.employee?.id` so an unlinked
  // super-admin account doesn't wipe an in-progress edit.
  const employeePhone = data?.employee?.phone ?? '';
  const employeeId = data?.employee?.id;
  React.useEffect(() => {
    if (employeeId != null) {
      setPhone(employeePhone ?? '');
    }
  }, [employeeId, employeePhone]);

  const mutation = useMutation({
    mutationFn: () =>
      myProfileApi.update({ phone: phone.trim() === '' ? null : phone.trim() }),
    onSuccess: () => {
      toast.success('تم حفظ رقم الجوال');
      setApiError(null);
      setPhoneError(null);
      onSaved();
    },
    onError: (err: unknown) => {
      const { message, errors } = extractApiError(err);
      setPhoneError(errors?.phone?.[0] ?? null);
      setApiError(message ?? 'تعذّر حفظ التغييرات، حاول مرة أخرى.');
    },
  });

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    setApiError(null);
    setPhoneError(null);
    mutation.mutate();
  };

  const employee = data?.employee ?? null;
  const user = data?.user ?? null;
  const disabled = loading || !employee;
  const dirty = (employee?.phone ?? '') !== phone;

  return (
    <Card className="border-hairline bg-surface p-5">
      <SectionHeader
        icon={<UserIcon className="h-4 w-4 text-brand" />}
        title="بيانات الحساب"
        description="الحقول أدناه للقراءة فقط. راجع الموارد البشرية لتعديلها."
      />

      <form onSubmit={submit} className="space-y-6">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <ReadOnlyField
            label="الاسم الكامل"
            value={employee?.full_name ?? user?.name ?? '—'}
            loading={loading}
          />
          <ReadOnlyField
            label="الرقم الوظيفي"
            value={employee?.employee_number ?? user?.employee_number ?? '—'}
            loading={loading}
            dir="ltr"
            numeric
          />
          <ReadOnlyField
            label="البريد الإلكتروني"
            value={employee?.email ?? user?.email ?? '—'}
            loading={loading}
            dir="ltr"
          />
          <ReadOnlyField
            label="القسم"
            value={employee?.department?.name ?? '—'}
            loading={loading}
          />
          <ReadOnlyField
            label="الفريق"
            value={employee?.team?.name ?? '—'}
            loading={loading}
          />
          <ReadOnlyField
            label="المسمى الوظيفي"
            value={employee?.position?.title ?? '—'}
            loading={loading}
          />
          <ReadOnlyField
            label="المدير المباشر"
            value={employee?.direct_manager?.full_name ?? '—'}
            loading={loading}
          />
          <ReadOnlyField
            label="جدول العمل"
            value={employee?.work_schedule?.name ?? '—'}
            loading={loading}
          />
        </div>

        <div className="border-t border-hairline pt-5">
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div className="space-y-1.5">
              <Label htmlFor="phone">رقم الجوال</Label>
              <Input
                id="phone"
                type="tel"
                dir="ltr"
                inputMode="tel"
                autoComplete="tel"
                value={phone}
                onChange={(e) => setPhone(e.target.value)}
                disabled={disabled}
                placeholder="+9705XXXXXXXX"
                aria-invalid={phoneError ? true : undefined}
                aria-describedby={phoneError ? 'phone-error' : undefined}
              />
              {phoneError && (
                <p id="phone-error" className="text-xs font-medium text-danger">
                  {phoneError}
                </p>
              )}
              <p className="text-[11px] text-muted">
                يُستخدم رقم الجوال لإرسال إشعارات الحساب فقط.
              </p>
            </div>
          </div>
        </div>

        {apiError && (
          <p className="rounded-md border border-danger-soft bg-danger-soft/40 px-3 py-2 text-sm text-danger">
            {apiError}
          </p>
        )}

        <div className="flex items-center justify-end gap-2">
          <Button
            type="submit"
            disabled={disabled || !dirty || mutation.isPending}
            className="bg-brand text-white hover:bg-brand-hover"
          >
            {mutation.isPending && <Loader2 className="h-4 w-4 animate-spin" />}
            حفظ التغييرات
          </Button>
        </div>
      </form>
    </Card>
  );
}

// ---------------------------------------------------------------------------
// Section 2 — password rotation
// ---------------------------------------------------------------------------

function PasswordSection() {
  const [values, setValues] = React.useState({
    current_password: '',
    password: '',
    password_confirmation: '',
  });
  const [errors, setErrors] = React.useState<FieldErrors>({});
  const [apiError, setApiError] = React.useState<string | null>(null);

  const reset = () => {
    setValues({ current_password: '', password: '', password_confirmation: '' });
    setErrors({});
    setApiError(null);
  };

  const mutation = useMutation({
    mutationFn: () => myProfileApi.updatePassword(values),
    onSuccess: () => {
      toast.success('تم تحديث كلمة السر بنجاح');
      reset();
    },
    onError: (err: unknown) => {
      const { message, errors: bag } = extractApiError(err);
      const next: FieldErrors = {};
      if (bag) {
        for (const [key, msgs] of Object.entries(bag)) {
          if (msgs[0]) next[key] = msgs[0];
        }
      }
      setErrors(next);
      setApiError(message ?? 'تعذّر تحديث كلمة السر.');
    },
  });

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    setApiError(null);
    setErrors({});

    // Client-side pre-flight — mirror the backend rules so the user sees
    // an inline error before the round-trip. Backend still enforces the
    // same checks; this is UX polish, not a security boundary.
    const next: FieldErrors = {};
    if (!values.current_password) next.current_password = 'الرجاء إدخال كلمة السر الحالية.';
    if (!values.password) next.password = 'الرجاء إدخال كلمة السر الجديدة.';
    else if (values.password.length < 8) next.password = 'يجب ألّا تقل كلمة السر عن 8 أحرف.';
    else if (values.password === values.current_password)
      next.password = 'يجب أن تختلف كلمة السر الجديدة عن الحالية.';
    if (values.password !== values.password_confirmation)
      next.password_confirmation = 'تأكيد كلمة السر لا يطابق كلمة السر الجديدة.';

    if (Object.keys(next).length > 0) {
      setErrors(next);
      return;
    }

    mutation.mutate();
  };

  return (
    <Card className="border-hairline bg-surface p-5">
      <SectionHeader
        icon={<KeyRound className="h-4 w-4 text-brand" />}
        title="تغيير كلمة السر"
        description="سيتم تسجيل خروجك من الأجهزة الأخرى بعد التحديث."
      />

      <form onSubmit={submit} className="space-y-4">
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div className="space-y-1.5 sm:col-span-2">
            <Label htmlFor="current_password">كلمة السر الحالية</Label>
            <Input
              id="current_password"
              type="password"
              autoComplete="current-password"
              value={values.current_password}
              onChange={(e) => setValues((v) => ({ ...v, current_password: e.target.value }))}
              aria-invalid={errors.current_password ? true : undefined}
              aria-describedby={errors.current_password ? 'current-password-error' : undefined}
            />
            {errors.current_password && (
              <p id="current-password-error" className="text-xs font-medium text-danger">
                {errors.current_password}
              </p>
            )}
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="password">كلمة السر الجديدة</Label>
            <Input
              id="password"
              type="password"
              autoComplete="new-password"
              value={values.password}
              onChange={(e) => setValues((v) => ({ ...v, password: e.target.value }))}
              aria-invalid={errors.password ? true : undefined}
              aria-describedby={errors.password ? 'password-error' : undefined}
            />
            {errors.password && (
              <p id="password-error" className="text-xs font-medium text-danger">
                {errors.password}
              </p>
            )}
            <p className="text-[11px] text-muted">8 أحرف على الأقل.</p>
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="password_confirmation">تأكيد كلمة السر</Label>
            <Input
              id="password_confirmation"
              type="password"
              autoComplete="new-password"
              value={values.password_confirmation}
              onChange={(e) =>
                setValues((v) => ({ ...v, password_confirmation: e.target.value }))
              }
              aria-invalid={errors.password_confirmation ? true : undefined}
              aria-describedby={
                errors.password_confirmation ? 'password-confirmation-error' : undefined
              }
            />
            {errors.password_confirmation && (
              <p id="password-confirmation-error" className="text-xs font-medium text-danger">
                {errors.password_confirmation}
              </p>
            )}
          </div>
        </div>

        {apiError && (
          <p className="rounded-md border border-danger-soft bg-danger-soft/40 px-3 py-2 text-sm text-danger">
            {apiError}
          </p>
        )}

        <div className="flex items-center justify-between gap-2">
          <p className="flex items-center gap-1.5 text-xs text-muted">
            <ShieldCheck className="h-3.5 w-3.5" />
            نتحقّق من كلمة السر الحالية قبل الحفظ.
          </p>
          <Button
            type="submit"
            disabled={mutation.isPending}
            className="bg-brand text-white hover:bg-brand-hover"
          >
            {mutation.isPending && <Loader2 className="h-4 w-4 animate-spin" />}
            تحديث كلمة السر
          </Button>
        </div>
      </form>
    </Card>
  );
}

// ---------------------------------------------------------------------------
// Local building blocks — kept private to the page (single consumer).
// ---------------------------------------------------------------------------

function SectionHeader({
  icon,
  title,
  description,
}: {
  icon: React.ReactNode;
  title: string;
  description?: string;
}) {
  return (
    <div className="mb-5 flex items-start justify-between gap-4">
      <div>
        <h2 className="flex items-center gap-2 text-base font-semibold text-ink">
          {icon}
          {title}
        </h2>
        {description && <p className="mt-1 text-xs text-muted">{description}</p>}
      </div>
    </div>
  );
}

function ReadOnlyField({
  label,
  value,
  loading,
  dir,
  numeric,
}: {
  label: string;
  value: string | number | null | undefined;
  loading?: boolean;
  dir?: 'ltr' | 'rtl';
  numeric?: boolean;
}) {
  return (
    <div className="space-y-1.5">
      <Label className="text-muted">{label}</Label>
      {loading ? (
        <Skeleton className="h-9 w-full" />
      ) : (
        <div
          className={cn(
            'flex h-9 items-center rounded-md border border-hairline bg-surface-2 px-3 text-sm text-ink',
            numeric && 'num'
          )}
          dir={dir}
        >
          {value === null || value === undefined || value === '' ? '—' : value}
        </div>
      )}
    </div>
  );
}
