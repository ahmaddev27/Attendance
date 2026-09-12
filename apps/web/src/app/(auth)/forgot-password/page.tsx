'use client';

import { useState } from 'react';
import Link from 'next/link';
import Image from 'next/image';
import { useRouter } from 'next/navigation';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card } from '@/components/ui/card';
import { toast } from 'sonner';
import { authApi } from '@/lib/api/endpoints/auth';
import { Loader2 } from 'lucide-react';

/**
 * Two-step self-service password reset.
 *
 * Step 1: user submits an identifier (email or employee_number). The
 *         backend always answers with the same generic message — never
 *         confirms whether the account exists — so we advance the UI
 *         to step 2 on any 2xx response.
 *
 * Step 2: user submits the 6-digit SMS code + new password. On success
 *         we toast and route to /login; the backend has already
 *         revoked every Sanctum token for that user, so any other
 *         signed-in devices will 401 on their next request.
 */
export default function ForgotPasswordPage() {
  const router = useRouter();
  const [step, setStep] = useState<'request' | 'verify'>('request');
  const [identifier, setIdentifier] = useState('');
  const [code, setCode] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirm, setPasswordConfirm] = useState('');
  const [loading, setLoading] = useState(false);

  const submitRequest = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    try {
      await authApi.forgotPassword(identifier.trim());
      toast.success('إن كان الحساب موجوداً، ستصلك رسالة SMS.');
      setStep('verify');
    } catch (err: any) {
      // The backend intentionally does not fail this endpoint for an
      // unknown identifier — a non-2xx here almost always means a
      // rate-limit (429) or a validation problem, both safe to surface.
      const msg = err?.response?.data?.message || 'تعذر إرسال الطلب. حاول لاحقاً.';
      toast.error(msg);
    } finally {
      setLoading(false);
    }
  };

  const submitReset = async (e: React.FormEvent) => {
    e.preventDefault();
    if (password !== passwordConfirm) {
      toast.error('كلمتا السر غير متطابقتين.');
      return;
    }
    setLoading(true);
    try {
      await authApi.resetPassword({
        identifier: identifier.trim(),
        code: code.trim(),
        password,
        password_confirmation: passwordConfirm,
      });
      toast.success('تم تحديث كلمة السر. سجّل الدخول من جديد.');
      router.push('/login');
    } catch (err: any) {
      // Uniform generic error on unknown identifier / bad code / expired
      // code (see PasswordResetService::throwInvalidCode). Surface
      // Laravel's validation-bag message when present.
      const bag = err?.response?.data?.errors;
      const first = bag && Object.values(bag)[0];
      const msg =
        (Array.isArray(first) && first[0]) ||
        err?.response?.data?.message ||
        'رمز غير صالح أو منتهي الصلاحية.';
      toast.error(msg);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen grid place-items-center bg-ground px-4">
      <div className="w-full max-w-md">
        <div className="flex justify-center mb-6">
          <Image src="/img/logo.png" alt="TAQAT" width={140} height={50} priority className="object-contain" />
        </div>
        <Card className="p-8 bg-surface border-hairline">
          <div className="mb-6">
            <h1 className="text-xl font-bold text-ink">
              {step === 'request' ? 'استعادة كلمة السر' : 'أدخل الرمز'}
            </h1>
            <p className="text-xs text-muted mt-1">
              {step === 'request'
                ? 'أدخل بريدك أو رقمك الوظيفي وسنرسل رمز التحقق برسالة نصية.'
                : 'أدخل الرمز الذي وصلك عبر SMS مع كلمة السر الجديدة.'}
            </p>
          </div>

          {step === 'request' ? (
            <form onSubmit={submitRequest} className="space-y-4">
              <div>
                <Label htmlFor="identifier" className="text-xs font-semibold text-ink-2">
                  البريد الإلكتروني أو الرقم الوظيفي
                </Label>
                <Input
                  id="identifier"
                  type="text"
                  inputMode="email"
                  autoComplete="username"
                  value={identifier}
                  onChange={(e) => setIdentifier(e.target.value)}
                  required
                  autoFocus
                  dir="ltr"
                  placeholder="admin@taqat.local  أو  1000"
                  className="mt-1.5 text-start"
                />
              </div>
              <Button
                type="submit"
                disabled={loading}
                className="w-full bg-brand hover:bg-brand-hover text-white"
              >
                {loading && <Loader2 className="me-2 h-4 w-4 animate-spin" />}
                إرسال الرمز
              </Button>
            </form>
          ) : (
            <form onSubmit={submitReset} className="space-y-4">
              <div>
                <Label htmlFor="code" className="text-xs font-semibold text-ink-2">
                  رمز التحقق
                </Label>
                <Input
                  id="code"
                  type="text"
                  inputMode="numeric"
                  autoComplete="one-time-code"
                  maxLength={6}
                  value={code}
                  onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))}
                  required
                  autoFocus
                  dir="ltr"
                  placeholder="123456"
                  className="mt-1.5 text-start tracking-widest"
                />
              </div>
              <div>
                <Label htmlFor="password" className="text-xs font-semibold text-ink-2">
                  كلمة السر الجديدة
                </Label>
                <Input
                  id="password"
                  type="password"
                  autoComplete="new-password"
                  minLength={8}
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  required
                  dir="ltr"
                  className="mt-1.5 text-start"
                />
              </div>
              <div>
                <Label htmlFor="password_confirmation" className="text-xs font-semibold text-ink-2">
                  تأكيد كلمة السر
                </Label>
                <Input
                  id="password_confirmation"
                  type="password"
                  autoComplete="new-password"
                  minLength={8}
                  value={passwordConfirm}
                  onChange={(e) => setPasswordConfirm(e.target.value)}
                  required
                  dir="ltr"
                  className="mt-1.5 text-start"
                />
              </div>
              <Button
                type="submit"
                disabled={loading}
                className="w-full bg-brand hover:bg-brand-hover text-white"
              >
                {loading && <Loader2 className="me-2 h-4 w-4 animate-spin" />}
                تحديث كلمة السر
              </Button>
              <button
                type="button"
                onClick={() => setStep('request')}
                className="w-full text-xs text-muted hover:text-ink text-center"
              >
                لم يصلك الرمز؟ ارجع وأعد الطلب.
              </button>
            </form>
          )}

          <div className="mt-6 text-center">
            <Link href="/login" className="text-xs text-brand hover:underline">
              العودة لتسجيل الدخول
            </Link>
          </div>
        </Card>
      </div>
    </div>
  );
}
