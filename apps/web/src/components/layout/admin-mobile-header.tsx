'use client';

import { useState } from 'react';
import Image from 'next/image';
import { Menu } from 'lucide-react';

import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { SidebarNav, UserFooter } from '@/components/layout/admin-sidebar';
import { cn } from '@/lib/utils';

/**
 * The main sidebar is desktop-only (`hidden md:flex`, per the design spec).
 * Below md we still need a way to reach the nav, so this renders a slim top
 * bar with a menu button that opens the same SidebarNav in a dialog — no new
 * animation pattern introduced, just the shared Dialog used everywhere else.
 */
export function AdminMobileHeader() {
  const [open, setOpen] = useState(false);

  return (
    <header className="sticky top-0 z-20 flex items-center justify-between border-b border-hairline bg-surface px-4 py-3 md:hidden">
      <Image src="/img/logo.png" alt="TAQAT" width={80} height={28} className="object-contain" priority />
      <button
        type="button"
        onClick={() => setOpen(true)}
        aria-label="فتح القائمة"
        className={cn(
          'grid h-9 w-9 place-items-center rounded-md text-ink-2 transition-colors hover:bg-surface-2 hover:text-ink'
        )}
      >
        <Menu className="h-5 w-5" />
      </button>

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
