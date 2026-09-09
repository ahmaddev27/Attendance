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

type NavSection = {
  /** Section heading shown above the group. `null` = ungrouped header row. */
  title: string | null;
  items: NavItem[];
};

/**
 * Grouped sidebar navigation. Each section is a logical cluster (people,
 * attendance, leaves, workflow, tasks, reports, system). A section is
 * rendered only when at least one of its items is visible to the current
 * user — keeps the sidebar tight for viewers with narrow permissions
 * (e.g. an approve-leaves-only manager shouldn't see empty "الأشخاص" or
 * "التقارير" headers).
 */
const NAV_SECTIONS: NavSection[] = [
  {
    title: null,
    items: [
      { href: '/dashboard', label: 'الرئيسية', icon: Home, permissions: [] },
      { href: '/notifications', label: 'الإشعارات', icon: Bell, permissions: [] },
      { href: '/approvals', label: 'صندوق الموافقات', icon: Inbox, permissions: [] },
    ],
  },
  {
    title: 'الأشخاص',
    items: [
      { href: '/employees', label: 'الموظفون', icon: Users, permissions: ['manage-users'] },
      { href: '/organization/departments', label: 'الأقسام', icon: Building, permissions: ['manage-departments'] },
      { href: '/organization/teams', label: 'الفرق', icon: UsersRound, permissions: ['manage-departments'] },
      { href: '/organization/positions', label: 'المسميات الوظيفية', icon: Briefcase, permissions: ['manage-departments'] },
    ],
  },
  {
    title: 'الحضور',
    items: [
      { href: '/attendance', label: 'سجل الحضور', icon: Calendar, permissions: ['view-all-attendance'] },
      { href: '/organization/schedules', label: 'جداول الدوام', icon: Clock, permissions: ['manage-departments'] },
      { href: '/organization/holidays', label: 'العطل', icon: CalendarOff, permissions: ['manage-departments'] },
      { href: '/organization/devices', label: 'أجهزة QR', icon: QrCode, permissions: ['manage-departments'] },
    ],
  },
  {
    title: 'الإجازات',
    items: [
      { href: '/leaves', label: 'طلبات الإجازة', icon: Check, permissions: ['approve-leaves'] },
      { href: '/organization/leave-types', label: 'أنواع الإجازات', icon: Palette, permissions: ['approve-leaves'] },
    ],
  },
  {
    title: 'الطلبات والموافقات',
    items: [
      { href: '/requests', label: 'كل الطلبات', icon: FileText, permissions: ['manage-workflows'] },
      { href: '/request-types', label: 'أنواع الطلبات', icon: FileCog, permissions: ['manage-workflows'] },
      { href: '/workflows', label: 'مسارات العمل', icon: GitBranch, permissions: ['manage-workflows'] },
    ],
  },
  {
    title: 'المهام',
    items: [
      { href: '/tasks', label: 'كل المهام', icon: ClipboardCheck, permissions: ['create-tasks'] },
      { href: '/tasks-config/statuses', label: 'إعدادات المهام', icon: Settings2, permissions: ['manage-workflows'] },
    ],
  },
  {
    title: 'التقارير',
    items: [
      { href: '/reports/attendance', label: 'تقارير الحضور', icon: BarChart3, permissions: ['view-reports'] },
      { href: '/analytics', label: 'التحليلات', icon: TrendingUp, permissions: ['view-reports'] },
    ],
  },
  {
    title: 'النظام',
    items: [
      { href: '/audit', label: 'سجل النشاط', icon: History, permissions: ['view-audit-logs'] },
      { href: '/settings', label: 'الإعدادات', icon: Settings, permissions: ['manage-users'] },
    ],
  },
];

function isActivePath(pathname: string | null, href: string) {
  if (!pathname) return false;
  return pathname === href || pathname.startsWith(`${href}/`);
}

export function SidebarNav({ onNavigate }: { onNavigate?: () => void }) {
  const pathname = usePathname();
  const user = useAuthStore((s) => s.user);

  // Filter each section's items by the user's permissions, then drop
  // sections that ended up empty — an approve-leaves-only manager
  // shouldn't see an empty "التقارير" header taking vertical space.
  const visibleSections = NAV_SECTIONS
    .map((section) => ({
      ...section,
      items: section.items.filter((item) => hasAnyPermission(user, item.permissions)),
    }))
    .filter((section) => section.items.length > 0);

  return (
    <nav className="flex flex-1 flex-col gap-4 overflow-y-auto pb-2">
      {visibleSections.map((section, sectionIndex) => (
        <div key={section.title ?? `top-${sectionIndex}`} className="flex flex-col gap-0.5">
          {section.title && (
            <div className="mb-1 px-3 pt-2 text-[10px] font-bold uppercase tracking-wider text-muted">
              {section.title}
            </div>
          )}
          {section.items.map(({ href, label, icon: Icon }) => {
            const active = isActivePath(pathname, href);
            return (
              <Link
                key={href}
                href={href}
                onClick={onNavigate}
                className={cn(
                  'flex items-center gap-3 rounded-lg px-3 py-2 text-[13.5px] transition-colors',
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
        </div>
      ))}
    </nav>
  );
}

export function UserFooter() {
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
