'use client';

import * as React from 'react';
import { Download, X } from 'lucide-react';

/**
 * "Install TAQAT as an app" bar.
 *
 * Chromium browsers fire a `beforeinstallprompt` event once the PWA
 * criteria are met (manifest present, service worker registered, some
 * engagement); we intercept it and expose an in-app CTA instead of
 * the browser's default (silent) mini-info-bar.
 *
 * Dismissals are remembered in localStorage for 30 days so we don't
 * nag on every page load. iOS Safari does NOT fire the event — the
 * bar simply never shows there, which is the correct behaviour
 * (add-to-home-screen is done manually via the share sheet on iOS).
 */

type BeforeInstallPromptEvent = Event & {
  prompt: () => Promise<void>;
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

const DISMISS_KEY = 'taqat.install-prompt.dismissed-at';
const DISMISS_WINDOW_MS = 1000 * 60 * 60 * 24 * 30; // 30 days

function wasRecentlyDismissed(): boolean {
  try {
    const raw = localStorage.getItem(DISMISS_KEY);
    if (!raw) return false;
    return Date.now() - Number(raw) < DISMISS_WINDOW_MS;
  } catch {
    return false;
  }
}

export function InstallPrompt() {
  const [deferredEvent, setDeferredEvent] = React.useState<BeforeInstallPromptEvent | null>(null);

  React.useEffect(() => {
    if (wasRecentlyDismissed()) return;

    const handler = (event: Event) => {
      event.preventDefault();
      setDeferredEvent(event as BeforeInstallPromptEvent);
    };

    window.addEventListener('beforeinstallprompt', handler as EventListener);
    return () => window.removeEventListener('beforeinstallprompt', handler as EventListener);
  }, []);

  if (!deferredEvent) return null;

  const dismiss = () => {
    try {
      localStorage.setItem(DISMISS_KEY, String(Date.now()));
    } catch {
      // localStorage may be blocked — safe to ignore; the banner just
      // reappears next session.
    }
    setDeferredEvent(null);
  };

  const install = async () => {
    try {
      await deferredEvent.prompt();
      const choice = await deferredEvent.userChoice;
      if (choice.outcome === 'accepted' || choice.outcome === 'dismissed') {
        // Either way we're done with this event — it can only be used once.
        setDeferredEvent(null);
      }
    } catch {
      setDeferredEvent(null);
    }
  };

  return (
    <div className="fixed inset-x-0 bottom-4 z-50 mx-auto max-w-md px-4">
      <div className="flex items-center gap-3 rounded-2xl border border-hairline bg-surface p-4 shadow-lg">
        <div className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-brand-soft text-brand-ink">
          <Download className="h-5 w-5" />
        </div>
        <div className="flex-1 text-sm">
          <p className="font-semibold text-ink">ثبّت تطبيق TAQAT</p>
          <p className="text-xs text-muted">وصول أسرع + عمل بدون إنترنت</p>
        </div>
        <button
          type="button"
          onClick={install}
          className="rounded-lg bg-brand px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-brand-hover"
        >
          تثبيت
        </button>
        <button
          type="button"
          onClick={dismiss}
          aria-label="إغلاق"
          className="grid h-8 w-8 place-items-center rounded-md text-ink-2 transition-colors hover:bg-surface-2 hover:text-ink"
        >
          <X className="h-4 w-4" />
        </button>
      </div>
    </div>
  );
}
