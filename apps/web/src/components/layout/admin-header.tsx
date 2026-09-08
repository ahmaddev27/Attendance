'use client';

import { useState } from 'react';
import Image from 'next/image';
import { Menu } from 'lucide-react';

import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { SidebarNav, UserFooter } from '@/components/layout/admin-sidebar';
import { NotificationBell } from '@/components/notifications/notification-bell';
import { cn } from '@/lib/utils';

/**
 * Header bar visible on every admin page.
 *
 * Below md: shows a menu button + logo (mobile-only strip), and the same
 *   bell — the sidebar itself is hidden and reachable through the menu.
 * At md+: shows only the bell aligned to the left edge of the content
 *   area (the sidebar owns the right side under RTL), so admin actions
 *   have a consistent spot to live regardless of viewport.
 */
export function AdminHeader() {
  const [open, setOpen] = useState(false);

  return (
    <header
      className={cn(
        'sticky top-0 z-20 flex items-center justify-between border-b border-hairline bg-surface px-4 py-3',
        // Keep the bar compact on desktop — only the bell needs to fit.
        'md:justify-start md:px-6'
      )}
    >
      {/* Mobile: menu button (left in RTL flow) */}
      <button
        type="button"
        onClick={() => setOpen(true)}
        aria-label="فتح القائمة"
        className={cn(
          'grid h-9 w-9 place-items-center rounded-md text-ink-2 transition-colors hover:bg-surface-2 hover:text-ink md:hidden'
        )}
      >
        <Menu className="h-5 w-5" />
      </button>

      {/* Mobile: logo (right in RTL flow) */}
      <Image
        src="/img/logo.png"
        alt="TAQAT"
        width={80}
        height={28}
        className="object-contain md:hidden"
        priority
      />

      {/* Bell — always visible; on desktop it's the only thing in the bar. */}
      <div className="md:ml-auto">
        <NotificationBell />
      </div>

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent className="flex max-h-[80vh] flex-col gap-4 p-4">
          <DialogTitle className="text-right text-sm font-semibold text-ink">القائمة</DialogTitle>
          <SidebarNav onNavigate={() => setOpen(false)} />
          <UserFooter />
        </DialogContent>
      </Dialog>
    </header>
  );
}
