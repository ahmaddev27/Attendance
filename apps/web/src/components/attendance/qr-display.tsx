'use client';

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
  // Rotation was removed as a product feature: QR tokens are now
  // permanent for the life of the device. `rotatesEverySeconds` and
  // `lastRotatedAt` are still accepted so old callers don't break,
  // but they're intentionally unused — the display always reads as
  // permanent regardless of any stale DB value on a legacy device row.
  void rotatesEverySeconds;
  void lastRotatedAt;

  return (
    <div className="flex flex-col items-center gap-3">
      <div className="rounded-xl border border-hairline bg-white p-4">
        <QRCodeSVG value={value} size={size} level="M" />
      </div>
      <p className="num max-w-[260px] break-all text-center text-xs text-muted" dir="ltr">
        {value}
      </p>
    </div>
  );
}
