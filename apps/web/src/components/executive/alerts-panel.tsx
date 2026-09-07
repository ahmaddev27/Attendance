import Link from 'next/link';
import { ChevronLeft } from 'lucide-react';

import { cn } from '@/lib/utils';

export type AlertItem = {
  label: string;
  count: number;
  href: string;
  tone?: 'warn' | 'danger' | 'default';
};

const BADGE_TONE_CLASSNAME: Record<NonNullable<AlertItem['tone']>, string> = {
  default: 'bg-surface-2 text-ink-2',
  warn: 'bg-warn-soft text-warn-ink',
  danger: 'bg-danger-soft text-danger',
};

/** Row-3 alerts list — each row links to the relevant filtered admin page. */
export function AlertsPanel({ items }: { items: AlertItem[] }) {
  return (
    <div className="divide-y divide-hairline overflow-hidden rounded-xl border border-hairline bg-surface">
      {items.map((item) => (
        <Link
          key={item.href}
          href={item.href}
          className="flex items-center justify-between gap-3 px-5 py-4 transition-colors hover:bg-surface-2"
        >
          <span className="text-sm font-medium text-ink">{item.label}</span>
          <span className="flex items-center gap-2">
            <span
              className={cn(
                'num rounded-full px-2.5 py-0.5 text-xs font-semibold',
                BADGE_TONE_CLASSNAME[item.tone ?? 'default']
              )}
            >
              {item.count}
            </span>
            <ChevronLeft className="h-4 w-4 text-muted" />
          </span>
        </Link>
      ))}
    </div>
  );
}
