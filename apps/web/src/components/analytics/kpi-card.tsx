'use client';

import * as React from 'react';
import { cn } from '@/lib/utils';

type Tone = 'brand' | 'success' | 'warn' | 'danger' | 'neutral';

const TONE_STYLES: Record<Tone, { chip: string; value: string }> = {
  brand: { chip: 'bg-brand-soft text-brand-ink', value: 'text-brand-ink' },
  success: { chip: 'bg-success-soft text-success', value: 'text-success' },
  warn: { chip: 'bg-warn-soft text-warn-ink', value: 'text-warn-ink' },
  danger: { chip: 'bg-danger-soft text-danger', value: 'text-danger' },
  neutral: { chip: 'bg-surface-2 text-ink-2', value: 'text-ink' },
};

type Props = {
  label: string;
  value: React.ReactNode;
  hint?: React.ReactNode;
  icon?: React.ComponentType<{ className?: string }>;
  tone?: Tone;
  /**
   * Compact variant fits three cards in a row on smaller screens without
   * shrinking the value text. The full variant is the default and takes
   * more vertical room for the label + hint pair.
   */
  compact?: boolean;
};

/**
 * Single-metric card used across every analytics panel. Keeps the shape
 * consistent — icon chip on the reading start, big number, one-line
 * label + optional hint — so a row of KPIs reads as one system.
 */
export function KpiCard({ label, value, hint, icon: Icon, tone = 'neutral', compact = false }: Props) {
  const tones = TONE_STYLES[tone];

  return (
    <div className="rounded-xl border border-hairline bg-surface p-4">
      <div className="flex items-start gap-3">
        {Icon && (
          <div className={cn('grid h-10 w-10 shrink-0 place-items-center rounded-lg', tones.chip)}>
            <Icon className="h-5 w-5" />
          </div>
        )}
        <div className="min-w-0 flex-1">
          <p className={cn('text-xs font-medium text-muted', compact && 'text-[11px]')}>{label}</p>
          <p className={cn('num mt-1 font-bold', tones.value, compact ? 'text-xl' : 'text-2xl')} dir="ltr">
            {value}
          </p>
          {hint && <p className="mt-1 text-[11px] text-muted">{hint}</p>}
        </div>
      </div>
    </div>
  );
}
