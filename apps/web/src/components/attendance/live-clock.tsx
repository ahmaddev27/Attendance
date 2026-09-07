'use client';

import { useEffect, useState } from 'react';

/**
 * Self-updating clock used on the kiosk scan screen. Renders nothing
 * server-side meaningful (the very first tick) to avoid a hydration
 * mismatch — the real time is set client-side on mount, then ticks every
 * second.
 */
export function LiveClock({ className }: { className?: string }) {
  const [now, setNow] = useState<Date | null>(null);

  useEffect(() => {
    setNow(new Date());
    const timer = setInterval(() => setNow(new Date()), 1000);
    return () => clearInterval(timer);
  }, []);

  const time = now?.toLocaleTimeString('ar-SA', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  });
  const date = now?.toLocaleDateString('ar-SA', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });

  return (
    <div className={className}>
      <p className="num text-center text-5xl font-bold tabular-nums text-ink">{time ?? '--:--:--'}</p>
      <p className="mt-1 text-center text-sm text-muted">{date ?? ' '}</p>
    </div>
  );
}
