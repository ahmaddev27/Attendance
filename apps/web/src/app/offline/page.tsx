import { WifiOff } from 'lucide-react';

/**
 * Rendered by the service worker's document fallback when a navigation
 * request has nothing to serve (first-visit-plus-offline case). Keep it
 * intentionally small — no data hooks, no auth — so it works even
 * without any bundled JS beyond the shell.
 */
export default function OfflinePage() {
  return (
    <div className="min-h-screen bg-ground grid place-items-center px-6">
      <div className="max-w-sm text-center">
        <div className="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-warn-soft text-warn-ink">
          <WifiOff className="h-6 w-6" />
        </div>
        <h1 className="mt-4 text-xl font-bold text-ink">لا يوجد اتصال بالإنترنت</h1>
        <p className="mt-2 text-sm text-muted">
          أنت الآن دون اتصال. تسجيل الحضور المُخزَّن سيُرسَل تلقائياً بمجرد عودة الاتصال.
        </p>
      </div>
    </div>
  );
}
