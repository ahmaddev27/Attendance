'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import Image from 'next/image';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card } from '@/components/ui/card';
import { toast } from 'sonner';
import { apiClient, primeCsrfCookie } from '@/lib/api/client';
import { useAuthStore, isAdminUser, emitAuthReset } from '@/lib/stores/auth-store';
import { Loader2 } from 'lucide-react';

export default function LoginPage() {
  const [identifier, setIdentifier] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const setAuth = useAuthStore((s) => s.setAuth);
  const router = useRouter();

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    try {
      // Sanctum SPA flow: prime the XSRF-TOKEN cookie FIRST, so axios
      // has it to mirror as X-XSRF-TOKEN on the login POST. Without
      // this step the CSRF middleware 419s the login itself.
      await primeCsrfCookie();

      // Backend accepts either an email or an employee_number under
      // the 'identifier' field. Auto-detects by looking for '@'. The
      // response now returns ONLY the user — the session id lives in
      // an httpOnly cookie the browser handles automatically.
      const { data } = await apiClient.post('/auth/login', {
        identifier: identifier.trim(),
        password,
      });
      setAuth(data.user);
      // Nuke any leftover React-Query cache from a previous session on the
      // same tab before we route into the new user's pages. See fix #6.
      emitAuthReset();
      toast.success('مرحباً بك مجدداً في TAQAT');
      // Role-based landing: administrative roles go to the admin dashboard,
      // regular employees go to their personal home page.
      router.push(isAdminUser(data.user) ? '/dashboard' : '/home');
    } catch (err: any) {
      const msg = err.response?.data?.message || 'فشل تسجيل الدخول';
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
            <h1 className="text-xl font-bold text-ink">أهلاً بعودتك</h1>
            <p className="text-xs text-muted mt-1">أدخل رقمك الوظيفي وكلمة السر</p>
          </div>
          <form onSubmit={submit} className="space-y-4">
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
            <div>
              <Label htmlFor="password" className="text-xs font-semibold text-ink-2">كلمة المرور</Label>
              <Input
                id="password"
                type="password"
                autoComplete="current-password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
                dir="ltr"
                className="mt-1.5 text-start"
              />
            </div>
            <Button type="submit" disabled={loading} className="w-full bg-brand hover:bg-brand-hover text-white">
              {loading && <Loader2 className="me-2 h-4 w-4 animate-spin" />}
              دخول
            </Button>
          </form>
        </Card>
      </div>
    </div>
  );
}
