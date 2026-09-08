'use client';

import * as React from 'react';
import Link from 'next/link';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Bell, CheckCheck } from 'lucide-react';

import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { notificationsApi, type TaqatNotification } from '@/lib/api/endpoints/notifications';
import { cn } from '@/lib/utils';

/**
 * The bell + dropdown in the admin header. Polls unread-count every 30s
 * so a fresh notification shows up without a full page reload; the list
 * itself is fetched only when the user opens the popover, so the constant
 * poll stays cheap.
 */
export function NotificationBell() {
  const [open, setOpen] = React.useState(false);
  const queryClient = useQueryClient();

  const { data: countRes } = useQuery({
    queryKey: ['notifications', 'unread-count'],
    queryFn: async () => (await notificationsApi.unreadCount()).data.data,
    refetchInterval: 30_000,
  });
  const unread = countRes?.count ?? 0;

  const { data: listRes, isLoading } = useQuery({
    queryKey: ['notifications', 'list', 'preview'],
    queryFn: async () => (await notificationsApi.list({ per_page: 10 })).data,
    enabled: open,
  });

  const notifications: TaqatNotification[] = listRes?.data ?? [];

  const handleMarkAllRead = async () => {
    await notificationsApi.markAllRead();
    queryClient.invalidateQueries({ queryKey: ['notifications'] });
  };

  const handleItemClick = async (n: TaqatNotification) => {
    if (!n.read_at) {
      await notificationsApi.markRead(n.id);
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
    }
    setOpen(false);
  };

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <button
          type="button"
          aria-label={unread > 0 ? `الإشعارات (${unread} غير مقروء)` : 'الإشعارات'}
          className={cn(
            'relative grid h-9 w-9 place-items-center rounded-md text-ink-2 transition-colors',
            'hover:bg-surface-2 hover:text-ink',
            open && 'bg-surface-2 text-ink'
          )}
        >
          <Bell className="h-5 w-5" />
          {unread > 0 && (
            <span
              className="num absolute -top-1 -right-1 grid min-h-4 min-w-4 place-items-center rounded-full bg-danger px-1 text-[10px] font-bold text-white ring-2 ring-surface"
              dir="ltr"
            >
              {unread > 99 ? '99+' : unread}
            </span>
          )}
        </button>
      </PopoverTrigger>

      <PopoverContent
        align="end"
        sideOffset={8}
        className="w-80 p-0 md:w-96"
        onOpenAutoFocus={(e) => e.preventDefault()}
      >
        <div className="flex items-center justify-between border-b border-hairline px-4 py-3">
          <h3 className="text-sm font-semibold text-ink">الإشعارات</h3>
          {unread > 0 && (
            <button
              type="button"
              onClick={handleMarkAllRead}
              className="inline-flex items-center gap-1 text-xs font-semibold text-brand transition-colors hover:text-brand-hover"
            >
              <CheckCheck className="h-3.5 w-3.5" />
              تعليم الكل كمقروء
            </button>
          )}
        </div>

        <div className="max-h-96 overflow-y-auto">
          {isLoading ? (
            <div className="p-8 text-center text-sm text-muted">جارِ التحميل…</div>
          ) : notifications.length === 0 ? (
            <div className="p-8 text-center text-sm text-muted">لا توجد إشعارات</div>
          ) : (
            <ul className="divide-y divide-hairline">
              {notifications.map((n) => (
                <li key={n.id}>
                  <NotificationRow n={n} onClick={() => handleItemClick(n)} />
                </li>
              ))}
            </ul>
          )}
        </div>

        <div className="border-t border-hairline px-4 py-2 text-center">
          <Link
            href="/notifications"
            onClick={() => setOpen(false)}
            className="text-xs font-semibold text-brand hover:text-brand-hover"
          >
            عرض كل الإشعارات ←
          </Link>
        </div>
      </PopoverContent>
    </Popover>
  );
}

function NotificationRow({
  n,
  onClick,
}: {
  n: TaqatNotification;
  onClick: () => void | Promise<void>;
}) {
  const inner = (
    <div
      className={cn(
        'flex items-start gap-3 px-4 py-3 text-right transition-colors hover:bg-surface-2',
        !n.read_at && 'bg-brand-soft/30'
      )}
    >
      <div className="flex-1 space-y-0.5">
        <p className={cn('text-sm text-ink', !n.read_at && 'font-semibold')}>{n.title}</p>
        {n.body && <p className="line-clamp-2 text-xs text-muted">{n.body}</p>}
        {n.created_at && (
          <p className="num pt-1 text-[10px] uppercase tracking-wider text-muted" dir="ltr">
            {formatRelativeArabic(n.created_at)}
          </p>
        )}
      </div>
      {!n.read_at && <span className="mt-1 h-2 w-2 shrink-0 rounded-full bg-brand" />}
    </div>
  );

  if (n.url) {
    return (
      <Link href={n.url} onClick={onClick} className="block w-full">
        {inner}
      </Link>
    );
  }
  return (
    <button type="button" onClick={onClick} className="block w-full">
      {inner}
    </button>
  );
}

function formatRelativeArabic(iso: string): string {
  const now = Date.now();
  const then = new Date(iso).getTime();
  const diff = Math.max(0, Math.floor((now - then) / 1000));

  if (diff < 60) return 'الآن';
  if (diff < 3600) return `منذ ${Math.floor(diff / 60)} د`;
  if (diff < 86400) return `منذ ${Math.floor(diff / 3600)} س`;
  if (diff < 604800) return `منذ ${Math.floor(diff / 86400)} ي`;
  return new Date(iso).toLocaleDateString('ar-EG');
}
