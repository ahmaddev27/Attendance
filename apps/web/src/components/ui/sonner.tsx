'use client';

import { Toaster as Sonner } from 'sonner';

type ToasterProps = React.ComponentProps<typeof Sonner>;

/**
 * TAQAT-themed toast overlay.
 *
 * Design notes:
 *  - Softer, smaller footprint than sonner's default. A 320px max-width
 *    keeps the toast from spanning a full desktop viewport at top-left.
 *  - Compact vertical rhythm (py-2.5 not p-4) so a stack of two or three
 *    toasts doesn't dominate the screen.
 *  - Colored left-edge accent (start-edge in RTL) + a matching pastel
 *    icon chip — the "chip on left, text on right" convention every user
 *    already reads from Gmail / iOS notifications.
 *  - Softer shadow (mostly y-offset, low blur) so the toast reads as a
 *    lifted card rather than a heavy popup.
 *  - Every color override carries `!` because sonner's own inline
 *    defaults out-specificity plain Tailwind classes.
 */
const Toaster = ({ ...props }: ToasterProps) => {
  return (
    <Sonner
      className="toaster group"
      // Kept 'light' explicitly so the parent's `system` dark-mode media
      // query never flips the toast to a dark background — the underlying
      // pages are still light-only for now.
      theme="light"
      // 5s is enough for Arabic labels (which take a beat longer to scan
      // than English) without over-lingering when the user's already
      // moved on.
      duration={5000}
      // Larger gap between stacked toasts so each reads as its own card.
      gap={10}
      // Slightly nudged in from the viewport edge so the corner doesn't
      // feel pinched — sonner's default is 32px, we go a touch wider.
      offset={20}
      toastOptions={{
        unstyled: false,
        classNames: {
          // Tight, refined card. `!` on bg/text/border wins over
          // sonner's own inline defaults, which otherwise paint a dark
          // background for the success/error variants regardless of
          // richColors=false.
          toast:
            'group toast pointer-events-auto relative flex w-full max-w-[340px] items-start gap-3 rounded-xl !border !border-hairline !bg-surface px-4 py-3 text-sm !text-ink shadow-[0_10px_25px_-12px_rgb(15_23_42/0.15),0_4px_8px_-4px_rgb(15_23_42/0.06)] ' +
            // Colored start-edge accent (right in RTL, left in LTR).
            'group-[.toaster]:!border-s-[3px] ' +
            'data-[type=success]:!border-s-success data-[type=error]:!border-s-danger data-[type=warning]:!border-s-warn data-[type=info]:!border-s-brand',
          title: '!text-[13px] !font-semibold !leading-5 !text-ink',
          description: '!text-[12px] !leading-5 !text-muted mt-0.5',
          actionButton:
            'group-[.toast]:!bg-brand group-[.toast]:!text-white group-[.toast]:hover:!bg-brand-hover group-[.toast]:!rounded-md group-[.toast]:!px-2.5 group-[.toast]:!py-1 group-[.toast]:!text-[11px] group-[.toast]:!font-semibold',
          cancelButton:
            'group-[.toast]:!bg-surface-2 group-[.toast]:!text-ink-2 group-[.toast]:hover:!bg-hairline group-[.toast]:!rounded-md group-[.toast]:!px-2.5 group-[.toast]:!py-1 group-[.toast]:!text-[11px] group-[.toast]:!font-semibold',
          closeButton:
            'group-[.toast]:!bg-surface group-[.toast]:!text-muted hover:group-[.toast]:!text-ink group-[.toast]:!border-hairline group-[.toast]:!h-5 group-[.toast]:!w-5',
          // Per-severity: keep the surface white and only tint the icon
          // so the toast stays quiet even for `error` — the start-edge
          // stripe carries the semantic weight.
          success: '!bg-surface !text-ink [&_[data-icon]]:!text-success [&_[data-icon]]:!bg-success-soft [&_[data-icon]]:!rounded-full [&_[data-icon]]:!p-1',
          error: '!bg-surface !text-ink [&_[data-icon]]:!text-danger [&_[data-icon]]:!bg-danger-soft [&_[data-icon]]:!rounded-full [&_[data-icon]]:!p-1',
          warning: '!bg-surface !text-ink [&_[data-icon]]:!text-warn-ink [&_[data-icon]]:!bg-warn-soft [&_[data-icon]]:!rounded-full [&_[data-icon]]:!p-1',
          info: '!bg-surface !text-ink [&_[data-icon]]:!text-brand [&_[data-icon]]:!bg-brand-soft [&_[data-icon]]:!rounded-full [&_[data-icon]]:!p-1',
        },
      }}
      {...props}
    />
  );
};

export { Toaster };
