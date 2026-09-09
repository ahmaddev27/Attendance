'use client';

import { useState } from 'react';
import Image from 'next/image';
import { Menu, Search } from 'lucide-react';

import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { SidebarNav, UserFooter } from '@/components/layout/admin-sidebar';
import { NotificationBell } from '@/components/notifications/notification-bell';
import { GlobalSearch, useGlobalSearchTrigger } from '@/components/search/global-search';

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
  // The trigger hook owns the palette's open state AND the Cmd/Ctrl+K
  // keyboard shortcut — mounting <GlobalSearch /> here is what makes
  // the shortcut actually surface anywhere in the admin shell.
  const { open: searchOpen, setOpen: setSearchOpen } = useGlobalSearchTrigger();

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

      {/* Global search trigger — sits just before the bell at the reading
          end. Cmd/Ctrl+K also opens it via the hook. */}
      <button
        type="button"
        onClick={() => setSearchOpen(true)}
        aria-label="بحث عام (⌘K)"
        title="بحث عام (⌘K)"
        className="grid h-9 w-9 place-items-center rounded-md text-ink-2 transition-colors hover:bg-surface-2 hover:text-ink"
      >
        <Search className="h-5 w-5" />
      </button>

      {/* Bell — reading-end anchor at every breakpoint. */}
      <NotificationBell />

      <GlobalSearch open={searchOpen} onOpenChange={setSearchOpen} />

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
