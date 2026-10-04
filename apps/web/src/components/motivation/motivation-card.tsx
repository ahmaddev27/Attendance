'use client';

import { Sparkles } from 'lucide-react';

import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { useMotivation } from '@/components/motivation/use-motivation';
import { formatDate } from '@/lib/attendance-format';

/**
 * Nice-to-have widget: any failure renders nothing so the home page
 * never breaks because of the AI line.
 */
export function MotivationCard() {
  const { data, isLoading, isError } = useMotivation();

  if (isLoading) {
    return <Skeleton className="h-24 w-full rounded-2xl" aria-label="جارٍ التحميل..." />;
  }
  if (isError || !data?.message) return null;

  return (
    <Card className="flex items-start gap-4 border-hairline bg-brand-soft/40 p-5 shadow-none">
      <Sparkles className="mt-1 h-5 w-5 shrink-0 text-brand" aria-hidden />
      <div className="min-w-0 flex-1">
        <p className="text-base leading-relaxed text-ink">{data.message}</p>
        <p className="mt-2 text-xs text-muted">
          رسالة اليوم · {formatDate(data.generated_at)}
        </p>
      </div>
    </Card>
  );
}
