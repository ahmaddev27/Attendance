'use client';

import { useState } from 'react';
import Image from 'next/image';
import { Menu } from 'lucide-react';

import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { SidebarNav, UserFooter } from '@/components/layout/admin-sidebar';
import { NotificationBell } from '@/components/notifications/notification-bell';

/**
 * Header bar visible on every admin page.
 *
 * Below md: menu button + logo pack to the reading start (right in RTL),
 *   bell sits at the reading end (left in RTL) — sidebar is hidden and
 *   opens from the menu button.
 * At md+: menu + logo are hidden (the sidebar carries the brand), and
 *   the bell stays anchored at the reading end (left in RTL) — same spot
 *   at every breakpoint so muscle memory holds.
 *
 * `me-auto` on the leading group is what enforces the split: it pushes
 * the group toward the reading start and lets the bell claim the end,
 * with no reliance on `justify-*` (which broke on desktop when the
 * leading items were `md:hidden` and only the bell was left).
 */
export function AdminHeader() {
  const [open, setOpen] = useState(false);

  return (
    <header className="sticky top-0 z-20 flex items-center gap-3 border-b border-hairline bg-surface px-4 py-3 md:px-6">
      {/* Leading group — menu button + logo, mobile-only.
          `me-auto` on the wrapper eats the remaining space so the bell
          lands at the reading end (left in RTL) at every viewport. When
          the group is empty (desktop, both children md:hidden), the
          empty div still carries `me-auto` so the anchor point holds. */}
      <div className="flex items-center gap-3 me-auto">
        <button
          type="button"
          onClick={() => setOpen(true)}
          aria-label="فتح القائمة"
          className="grid h-9 w-9 place-items-center rounded-md text-ink-2 transition-colors hover:bg-surface-2 hover:text-ink md:hidden"
        >
          <Menu className="h-5 w-5" />
        </button>
        <Image
          src="/img/logo.png"
          alt="TAQAT"
          width={80}
          height={28}
          className="object-contain md:hidden"
          priority
        />
      </div>

      {/* Bell — reading-end anchor at every breakpoint. */}
      <NotificationBell />

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent className="flex max-h-[80vh] flex-col gap-4 p-4">
          <DialogTitle className="text-start text-sm font-semibold text-ink">القائمة</DialogTitle>
          <SidebarNav onNavigate={() => setOpen(false)} />
          <UserFooter />
        </DialogContent>
      </Dialog>
    </header>
  );
}
