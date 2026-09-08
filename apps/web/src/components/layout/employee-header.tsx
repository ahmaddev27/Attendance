'use client';

import { useState } from 'react';
import Image from 'next/image';
import { Menu } from 'lucide-react';

import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { EmployeeSidebarNav, EmployeeUserFooter } from '@/components/layout/employee-sidebar';
import { NotificationBell } from '@/components/notifications/notification-bell';
import { cn } from '@/lib/utils';

/**
 * Header bar visible on every employee page — mirror of AdminHeader but
 * uses the smaller employee sidebar in the mobile drawer.
 */
export function EmployeeHeader() {
  const [open, setOpen] = useState(false);

  return (
    <header
      className={cn(
        'sticky top-0 z-20 flex items-center justify-between border-b border-hairline bg-surface px-4 py-3',
        'md:justify-start md:px-6'
      )}
    >
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

      <Image
        src="/img/logo.png"
        alt="TAQAT"
        width={80}
        height={28}
        className="object-contain md:hidden"
        priority
      />

      <div className="md:ml-auto">
        <NotificationBell />
      </div>

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent className="flex max-h-[80vh] flex-col gap-4 p-4">
          <DialogTitle className="text-right text-sm font-semibold text-ink">القائمة</DialogTitle>
          <EmployeeSidebarNav onNavigate={() => setOpen(false)} />
          <EmployeeUserFooter />
        </DialogContent>
      </Dialog>
    </header>
  );
}
