'use client';

import * as React from 'react';
import { useMutation } from '@tanstack/react-query';
import { Check, Loader2, Sparkles, X } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
  applicationsApi,
  type AiRecommendation,
  type AiScreeningResult,
} from '@/lib/api/endpoints/candidates';
import { cn } from '@/lib/utils';

const RECOMMENDATION: Record<AiRecommendation, { label: string; className: string }> = {
  advance: { label: 'ترقية', className: 'bg-success/10 text-success' },
  maybe: { label: 'ربما', className: 'bg-amber-100 text-amber-800' },
  reject: { label: 'رفض', className: 'bg-danger/10 text-danger' },
};

/** Admin-only AI verdict; the result is cached server-side for 24h. */
export function AiScreeningCard({ applicationId }: { applicationId: number }) {
  const [result, setResult] = React.useState<AiScreeningResult | null>(null);

  const screen = useMutation({
    mutationFn: () => applicationsApi.aiScreen(applicationId),
    onSuccess: setResult,
    onError: () => toast.error('خدمة الذكاء الاصطناعي غير متوفرة الآن. حاول لاحقاً.'),
  });

  return (
    <Card className="border-hairline bg-surface shadow-none">
      <CardContent className="space-y-4 p-6">
        <Button
          onClick={() => screen.mutate()}
          disabled={screen.isPending}
          variant="outline"
          className="gap-2"
        >
          {screen.isPending ? (
            <Loader2 className="h-4 w-4 animate-spin" />
          ) : (
            <Sparkles className="h-4 w-4" />
          )}
          تقييم ذكي بالذكاء الاصطناعي
        </Button>
        {result && <AiScreeningResultView result={result} />}
      </CardContent>
    </Card>
  );
}

function AiScreeningResultView({ result }: { result: AiScreeningResult }) {
  const badge = RECOMMENDATION[result.recommendation];

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-4">
        <span className="num text-4xl font-bold text-ink" dir="ltr">
          {Math.round(result.overall_score)}%
        </span>
        <span className={cn('rounded-full px-3 py-1 text-xs font-semibold', badge.className)}>
          {badge.label}
        </span>
      </div>
      <p className="text-sm text-ink-2">{result.summary}</p>
      <PillList items={result.strengths} className="bg-success/10 text-success" />
      <PillList items={result.concerns} className="bg-amber-100 text-amber-800" />
      <ul className="grid grid-cols-1 gap-1 sm:grid-cols-2">
        {Object.entries(result.skill_match).map(([skill, matched]) => (
          <li key={skill} className="flex items-center gap-2 text-sm text-ink-2">
            {matched ? (
              <Check className="h-4 w-4 text-success" />
            ) : (
              <X className="h-4 w-4 text-danger" />
            )}
            {skill}
          </li>
        ))}
      </ul>
    </div>
  );
}

function PillList({ items, className }: { items: string[]; className: string }) {
  if (items.length === 0) return null;
  return (
    <div className="flex flex-wrap gap-2">
      {items.map((item) => (
        <span key={item} className={cn('rounded-full px-3 py-1 text-xs', className)}>
          {item}
        </span>
      ))}
    </div>
  );
}
