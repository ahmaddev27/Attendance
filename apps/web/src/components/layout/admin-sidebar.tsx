'use client';

import Image from 'next/image';
import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import {
  Briefcase,
  Building,
  Calendar,
  CalendarOff,
  Check,
  ClipboardCheck,
  Clock,
  Home,
  LogOut,
  Palette,
  QrCode,
  Users,
  UsersRound,
} from 'lucide-react';

import { cn } from '@/lib/utils';
import { useAuthStore } from '@/lib/stores/auth-store';

type NavItem = {
  href: string;
  label: string;
  icon: React.ComponentType<{ className?: string }>;
};

const NAV_ITEMS: NavItem[] = [
  { href: '/dashboard', label: 'الرئيسية', icon: Home },
  { href: '/employees', label: 'الموظفون', icon: Users },
  { href: '/organization/departments', label: 'الأقسام', icon: Building },
  { href: '/organization/teams', label: 'الفرق', icon: UsersRound },
  { href: '/organization/positions', label: 'المسميات الوظيفية', icon: Briefcase },
  { href: '/attendance', label: 'الحضور', icon: Calendar },
  { href: '/organization/schedules', label: 'الجداول', icon: Clock },
  { href: '/organization/holidays', label: 'العطل', icon: CalendarOff },
  { href: '/organization/devices', label: 'أجهزة QR', icon: QrCode },
  { href: '/organization/leave-types', label: 'أنواع الإجازات', icon: Palette },
  { href: '/leaves', label: 'الإجازات', icon: Check },
  { href: '/tasks', label: 'المهام', icon: ClipboardCheck },
];

function isActivePath(pathname: string | null, href: string) {
  if (!pathname) return false;
  return pathname === href || pathname.startsWith(`${href}/`);
}

export function SidebarNav({ onNavigate }: { onNavigate?: () => void }) {
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

export function UserFooter() {
  const user = useAuthStore((s) => s.user);
  const logout = useAuthStore((s) => s.logout);
  const router = useRouter();

  const initial = user?.name?.trim()?.charAt(0)?.toUpperCase() || '؟';

  const handleLogout = () => {
    logout();
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

function SidebarLogo() {
  return (
    <div className="flex justify-center pt-2">
      <Image src="/img/logo.png" alt="TAQAT" width={100} height={36} className="object-contain" priority />
    </div>
  );
}

/** Desktop, right-side (RTL) fixed sidebar — hidden below the md breakpoint. */
export function AdminSidebar() {
  return (
    <aside className="fixed inset-y-0 right-0 z-30 hidden w-60 flex-col gap-6 border-l border-hairline bg-surface p-4 md:flex">
      <SidebarLogo />
      <SidebarNav />
      <UserFooter />
    </aside>
  );
}
