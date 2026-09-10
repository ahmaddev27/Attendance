'use client';

import { useEffect, useState } from 'react';
import { QRCodeSVG } from 'qrcode.react';

/**
 * Renders a kiosk device's QR code plus a live countdown to its next token
 * rotation. Purely presentational — callers own re-fetching the device
 * (e.g. after `rotate`) and passing the new `value`/`lastRotatedAt` down,
 * which naturally restarts the countdown.
 */
export function QrDisplay({
  value,
  rotatesEverySeconds,
  lastRotatedAt,
  size = 220,
}: {
  value: string;
  rotatesEverySeconds: number;
  lastRotatedAt: string | null;
  size?: number;
}) {
  const [secondsLeft, setSecondsLeft] = useState<number | null>(null);
  const neverRotates = rotatesEverySeconds <= 0;

  useEffect(() => {
    if (neverRotates) {
      setSecondsLeft(null);
      return;
    }

    // No last-rotated timestamp yet (freshly created device) — start the
    // countdown from now rather than showing a meaningless value.
    const baseline = lastRotatedAt ? new Date(lastRotatedAt).getTime() : Date.now();

    const tick = () => {
      const elapsedSeconds = Math.floor((Date.now() - baseline) / 1000);
      const remaining = rotatesEverySeconds - (elapsedSeconds % rotatesEverySeconds);
      setSecondsLeft(remaining);
    };

    tick();
    const timer = setInterval(tick, 1000);
    return () => clearInterval(timer);
  }, [lastRotatedAt, rotatesEverySeconds, neverRotates]);

  const minutes = secondsLeft !== null ? Math.floor(secondsLeft / 60) : 0;
  const seconds = secondsLeft !== null ? secondsLeft % 60 : 0;

  return (
    <div className="flex flex-col items-center gap-3">
      <div className="rounded-xl border border-hairline bg-white p-4">
        <QRCodeSVG value={value} size={size} level="M" />
      </div>
      <p className="num max-w-[260px] break-all text-center text-xs text-muted" dir="ltr">
        {value}
      </p>
      {neverRotates ? (
        <p className="text-sm font-medium text-success">
          رمز دائم — لا ينتهي
        </p>
      ) : (
        <p className="text-sm text-ink-2">
          التدوير التالي خلال{' '}
          <span className="num font-semibold text-ink">
            {String(minutes).padStart(2, '0')}:{String(seconds).padStart(2, '0')}
          </span>
        </p>
      )}
    </div>
  );
}
