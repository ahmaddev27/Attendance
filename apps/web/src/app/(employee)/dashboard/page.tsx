'use client';

import { Construction } from 'lucide-react';

import { Card } from '@/components/ui/card';
import { useAuthStore } from '@/lib/stores/auth-store';

export default function DashboardPage() {
  const user = useAuthStore((s) => s.user);
  const firstName = user?.name?.trim()?.split(' ')[0];

  return (
    <div className="min-h-screen bg-ground p-6 md:p-10">
      <div className="mx-auto max-w-2xl space-y-6">
        <Card className="border-hairline bg-surface p-8">
          <p className="text-sm text-muted">أهلاً بعودتك</p>
          <h1 className="mt-1 text-2xl font-bold text-ink">
            {firstName ? `مرحباً، ${firstName}` : 'مرحباً بك في TAQAT'}
          </h1>
          {user?.employee_number != null && (
            <p className="num mt-2 text-xs text-muted" dir="ltr">
              الرقم الوظيفي: {user.employee_number}
            </p>
          )}
        </Card>

        <Card className="flex flex-col items-center gap-3 border-hairline bg-surface p-10 text-center">
          <div className="grid h-12 w-12 place-items-center rounded-full bg-brand-soft text-brand-ink">
            <Construction className="h-6 w-6" />
          </div>
          <h2 className="text-lg font-semibold text-ink">لوحة الموظف قيد التطوير</h2>
          <p className="max-w-sm text-sm text-muted">
            نعمل حالياً على بناء هذه الصفحة. سيتم عرض ملخص الحضور والإجازات والمهام هنا قريباً.
          </p>
        </Card>
      </div>
    </div>
  );
}
