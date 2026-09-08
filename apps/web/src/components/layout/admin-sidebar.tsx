'use client';

import Image from 'next/image';
import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import {
  Bell,
  Briefcase,
  Building,
  Calendar,
  CalendarOff,
  Check,
  ClipboardCheck,
  Clock,
  BarChart3,
  FileCog,
  FileText,
  GitBranch,
  History,
  Home,
  Inbox,
  LogOut,
  Palette,
  QrCode,
  Settings,
  Settings2,
  TrendingUp,
  Users,
  UsersRound,
} from 'lucide-react';

import { cn } from '@/lib/utils';
import { hasAnyPermission, useAuthStore } from '@/lib/stores/auth-store';

type NavItem = {
  href: string;
  label: string;
  icon: React.ComponentType<{ className?: string }>;
  /**
   * Permissions gating this item — any-of semantics. Empty array = visible
   * to every authenticated admin (like the dashboard summary + notifications
   * inbox everyone should see).
   */
  permissions: string[];
};

const NAV_ITEMS: NavItem[] = [
  { href: '/dashboard', label: 'الرئيسية', icon: Home, permissions: [] },
  { href: '/employees', label: 'الموظفون', icon: Users, permissions: ['manage-users'] },
  { href: '/organization/departments', label: 'الأقسام', icon: Building, permissions: ['manage-departments'] },
  { href: '/organization/teams', label: 'الفرق', icon: UsersRound, permissions: ['manage-departments'] },
  { href: '/organization/positions', label: 'المسميات الوظيفية', icon: Briefcase, permissions: ['manage-departments'] },
  { href: '/attendance', label: 'الحضور', icon: Calendar, permissions: ['view-all-attendance'] },
  { href: '/organization/schedules', label: 'الجداول', icon: Clock, permissions: ['manage-departments'] },
  { href: '/organization/holidays', label: 'العطل', icon: CalendarOff, permissions: ['manage-departments'] },
  { href: '/organization/devices', label: 'أجهزة QR', icon: QrCode, permissions: ['manage-departments'] },
  { href: '/organization/leave-types', label: 'أنواع الإجازات', icon: Palette, permissions: ['approve-leaves'] },
  { href: '/leaves', label: 'الإجازات', icon: Check, permissions: ['approve-leaves'] },
  { href: '/requests', label: 'الطلبات', icon: FileText, permissions: ['manage-workflows'] },
  { href: '/approvals', label: 'صندوق الموافقات', icon: Inbox, permissions: [] },
  { href: '/workflows', label: 'مسارات العمل', icon: GitBranch, permissions: ['manage-workflows'] },
  { href: '/request-types', label: 'أنواع الطلبات', icon: FileCog, permissions: ['manage-workflows'] },
  { href: '/tasks', label: 'المهام', icon: ClipboardCheck, permissions: ['create-tasks'] },
  { href: '/tasks-config/statuses', label: 'إعدادات المهام', icon: Settings2, permissions: ['manage-workflows'] },
  { href: '/reports/attendance', label: 'تقارير الحضور', icon: BarChart3, permissions: ['view-reports'] },
  { href: '/analytics', label: 'التحليلات', icon: TrendingUp, permissions: ['view-reports'] },
  { href: '/audit', label: 'سجل النشاط', icon: History, permissions: ['view-audit-logs'] },
  { href: '/notifications', label: 'الإشعارات', icon: Bell, permissions: [] },
  { href: '/settings', label: 'الإعدادات', icon: Settings, permissions: ['manage-users'] },
];

function isActivePath(pathname: string | null, href: string) {
  if (!pathname) return false;
  return pathname === href || pathname.startsWith(`${href}/`);
}

export function SidebarNav({ onNavigate }: { onNavigate?: () => void }) {
  const pathname = usePathname();
  const user = useAuthStore((s) => s.user);

  // Filter items by the current user's permissions — items with an empty
  // permissions array (dashboard, notifications) are visible to everyone.
  const visibleItems = NAV_ITEMS.filter((item) => hasAnyPermission(user, item.permissions));

  return (
    <nav className="flex flex-1 flex-col gap-1 overflow-y-auto">
      {visibleItems.map(({ href, label, icon: Icon }) => {
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
    <aside className="fixed inset-y-0 start-0 z-30 hidden w-60 flex-col gap-6 border-e border-hairline bg-surface p-4 md:flex">
      <SidebarLogo />
      <SidebarNav />
      <UserFooter />
    </aside>
  );
}
