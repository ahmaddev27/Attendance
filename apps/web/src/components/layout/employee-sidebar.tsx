'use client';

import Image from 'next/image';
import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import {
  Bell,
  CalendarCheck,
  ClipboardList,
  FileText,
  Home,
  LogOut,
  User,
} from 'lucide-react';

import { cn } from '@/lib/utils';
import { useAuthStore } from '@/lib/stores/auth-store';

type NavItem = {
  href: string;
  label: string;
  icon: React.ComponentType<{ className?: string }>;
};

/**
 * Employee sidebar — deliberately much smaller than the admin one.
 * A regular staff member should only see the entries they can actually
 * use: their own home, their tasks, their leaves, their requests, their
 * notifications.
 */
const NAV_ITEMS: NavItem[] = [
  { href: '/home', label: 'الرئيسية', icon: Home },
  { href: '/my-tasks', label: 'مهامي', icon: ClipboardList },
  { href: '/my-leaves', label: 'إجازاتي', icon: CalendarCheck },
  { href: '/my-requests', label: 'طلباتي', icon: FileText },
  { href: '/my-notifications', label: 'الإشعارات', icon: Bell },
  { href: '/profile', label: 'الملف الشخصي', icon: User },
];

function isActivePath(pathname: string | null, href: string) {
  if (!pathname) return false;
  return pathname === href || pathname.startsWith(`${href}/`);
}

export function EmployeeSidebarNav({ onNavigate }: { onNavigate?: () => void }) {
  const pathname = usePathname();

  return (
    <nav className="flex flex-1 flex-col gap-1 overflow-y-auto">
      {NAV_ITEMS.map(({ href, label, icon: Icon }) => {
        const active = isActivePath(pathname, href);
        return (
          <Link
            key={href}
            href={href}
            onClick={onNavigate}
            className={cn(
              'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors',
              active
                ? 'bg-brand-soft font-semibold text-brand-ink'
                : 'text-ink-2 hover:bg-surface-2 hover:text-ink'
            )}
            aria-current={active ? 'page' : undefined}
          >
            <Icon className="h-[18px] w-[18px] shrink-0" />
            <span>{label}</span>
          </Link>
        );
      })}
    </nav>
  );
}

export function EmployeeUserFooter() {
  const user = useAuthStore((s) => s.user);
  const logout = useAuthStore((s) => s.logout);
  const router = useRouter();

  const initial = user?.name?.trim()?.charAt(0)?.toUpperCase() || '؟';

  const handleLogout = async () => {
    // `logout()` now revokes the Sanctum token on the server before
    // clearing local state — await it so we don't race a still-in-flight
    // POST /auth/logout against the /login navigation.
    await logout();
    router.replace('/login');
  };

  return (
    <div className="flex items-center gap-3 rounded-lg border border-hairline bg-surface-2 p-3">
      <div className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-brand text-sm font-semibold text-white">
        {initial}
      </div>
      <div className="min-w-0 flex-1">
        <p className="truncate text-sm font-semibold text-ink">{user?.name ?? '—'}</p>
        {user?.employee_number != null && (
          <p className="num truncate text-xs text-muted" dir="ltr">
            {user.employee_number}
          </p>
        )}
      </div>
      <button
        type="button"
        onClick={handleLogout}
        aria-label="تسجيل الخروج"
        title="تسجيل الخروج"
        className="grid h-8 w-8 shrink-0 place-items-center rounded-md text-ink-2 transition-colors hover:bg-danger-soft hover:text-danger"
      >
        <LogOut className="h-4 w-4" />
      </button>
    </div>
  );
}

function EmployeeSidebarLogo() {
  return (
    <div className="flex justify-center pt-2">
      <Image src="/img/logo.png" alt="TAQAT" width={100} height={36} className="object-contain" priority />
    </div>
  );
}

/** Desktop, right-side (RTL) fixed sidebar — hidden below the md breakpoint. */
export function EmployeeSidebar() {
  return (
    <aside className="fixed inset-y-0 start-0 z-30 hidden w-60 flex-col gap-6 border-e border-hairline bg-surface p-4 md:flex">
      <EmployeeSidebarLogo />
      <EmployeeSidebarNav />
      <EmployeeUserFooter />
    </aside>
  );
}
