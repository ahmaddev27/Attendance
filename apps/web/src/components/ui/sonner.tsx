'use client';

import { Toaster as Sonner } from 'sonner';

type ToasterProps = React.ComponentProps<typeof Sonner>;

/**
 * TAQAT-themed toast overlay.
 *
 * Sonner's `richColors` mode ships with an aggressive dark green/red
 * palette that clashed with our soft brand tokens (users flagged it as
 * ugly). We opt out of richColors and paint success/error/info/warning
 * ourselves via the design-token classes below, giving light-surface
 * toasts with a colored left border + matching icon tint — same visual
 * language as the cards and badges everywhere else in the app.
 */
const Toaster = ({ ...props }: ToasterProps) => {
  return (
    <Sonner
      className="toaster group"
      // Kept 'light' explicitly so the parent's `system` dark-mode media
      // query never flips the toast to a dark background — the underlying
      // pages are still light-only for now.
      theme="light"
      toastOptions={{
        // Slightly longer than sonner's 4s default: Arabic labels tend to
        // take a beat longer to scan than English single-word toasts.
        duration: 4500,
        unstyled: false,
        classNames: {
          // `!` on bg/text/border wins over sonner's own inline defaults,
          // which otherwise paint a dark background for the success/error
          // variants regardless of `richColors=false`. The start-edge
          // accent uses logical `border-s-4` + a per-type color so the
          // stripe stays on the reading start (right in RTL, left in LTR).
          toast:
            'group toast flex w-full items-start gap-3 rounded-xl !border !border-hairline !bg-surface p-4 text-sm !text-ink shadow-[0_8px_30px_rgb(15_23_42/0.08)] ' +
            'group-[.toaster]:!border-s-4 ' +
            'data-[type=success]:!border-s-success data-[type=error]:!border-s-danger data-[type=warning]:!border-s-warn data-[type=info]:!border-s-brand',
          title: '!text-sm !font-semibold !leading-6 !text-ink',
          description: '!text-xs !text-muted',
          actionButton:
            'group-[.toast]:!bg-brand group-[.toast]:!text-white group-[.toast]:hover:!bg-brand-hover group-[.toast]:!rounded-md group-[.toast]:!px-3 group-[.toast]:!py-1.5 group-[.toast]:!text-xs group-[.toast]:!font-semibold',
          cancelButton:
            'group-[.toast]:!bg-surface-2 group-[.toast]:!text-ink-2 group-[.toast]:hover:!bg-hairline group-[.toast]:!rounded-md group-[.toast]:!px-3 group-[.toast]:!py-1.5 group-[.toast]:!text-xs group-[.toast]:!font-semibold',
          closeButton:
            'group-[.toast]:!bg-surface-2 group-[.toast]:!text-ink-2 group-[.toast]:!border-hairline',
          success: '!bg-surface !text-ink [&_[data-icon]]:!text-success',
          error: '!bg-surface !text-ink [&_[data-icon]]:!text-danger',
          warning: '!bg-surface !text-ink [&_[data-icon]]:!text-warn-ink',
          info: '!bg-surface !text-ink [&_[data-icon]]:!text-brand',
        },
      }}
      {...props}
    />
  );
};

export { Toaster };
