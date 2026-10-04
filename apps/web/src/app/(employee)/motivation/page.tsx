'use client';

import { useState } from 'react';
import { Check, Copy, RefreshCw, Sparkles } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { useMotivation } from '@/components/motivation/use-motivation';
import { formatDate } from '@/lib/attendance-format';

export default function MotivationPage() {
  const { data, isLoading, isError, isFetching, refetch } = useMotivation();
  const [saved, setSaved] = useState(false);

  const copyMessage = async () => {
    if (!data?.message) return;
    try {
      await navigator.clipboard.writeText(data.message);
      setSaved(true);
      setTimeout(() => setSaved(false), 2000);
    } catch {
      setSaved(false);
    }
  };

  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <h1 className="flex items-center gap-2 text-2xl font-bold text-ink">
        <Sparkles className="h-6 w-6 text-brand" /> رسالة اليوم
      </h1>

      <Card className="border-hairline bg-brand-soft/40 p-6 shadow-none">
        {isLoading ? (
          <div className="space-y-3" aria-label="جارٍ التحميل...">
            <Skeleton className="h-5 w-full" />
            <Skeleton className="h-5 w-4/5" />
          </div>
        ) : isError || !data?.message ? (
          <p className="text-sm text-muted">تعذّر تحميل رسالة اليوم حالياً.</p>
        ) : (
          <>
            <p className="text-lg leading-relaxed text-ink">{data.message}</p>
            <p className="mt-3 text-xs text-muted">
              رسالة اليوم · {formatDate(data.generated_at)}
            </p>
          </>
        )}
      </Card>

      <div className="flex flex-wrap gap-3">
        <Button onClick={copyMessage} disabled={!data?.message}>
          {saved ? <Check className="me-2 h-4 w-4" /> : <Copy className="me-2 h-4 w-4" />}
          {saved ? 'تم الحفظ' : 'نسخ الرسالة'}
        </Button>
        <Button variant="outline" onClick={() => refetch()} disabled={isFetching}>
          <RefreshCw className={`me-2 h-4 w-4 ${isFetching ? 'animate-spin' : ''}`} />
          تحديث
        </Button>
      </div>
    </div>
  );
}
