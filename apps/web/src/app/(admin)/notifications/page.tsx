'use client';

import * as React from 'react';
import Link from 'next/link';
import { keepPreviousData, useQuery, useQueryClient } from '@tanstack/react-query';
import { Bell, CheckCheck, Filter } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { notificationsApi, type TaqatNotification } from '@/lib/api/endpoints/notifications';
import { cn } from '@/lib/utils';

export default function NotificationsPage() {
  const [page, setPage] = React.useState(1);
  const [unreadOnly, setUnreadOnly] = React.useState(false);
  const queryClient = useQueryClient();

  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['notifications', 'list', { page, unreadOnly }],
    queryFn: async () =>
      (await notificationsApi.list({ page, per_page: 20, unread: unreadOnly || undefined })).data,
    placeholderData: keepPreviousData,
  });

  const list: TaqatNotification[] = data?.data ?? [];
  const pageInfo = data?.meta;

  const markAllRead = async () => {
    await notificationsApi.markAllRead();
    queryClient.invalidateQueries({ queryKey: ['notifications'] });
  };

  const markOne = async (n: TaqatNotification) => {
    if (n.read_at) return;
    await notificationsApi.markRead(n.id);
    queryClient.invalidateQueries({ queryKey: ['notifications'] });
  };

  return (
    <div className="space-y-4">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="flex items-center gap-2 text-2xl font-bold text-ink">
            <Bell className="h-6 w-6 text-brand" /> الإشعارات
          </h1>
          <p className="mt-1 text-sm text-muted">آخر التنبيهات على حسابك.</p>
        </div>
        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={() => setUnreadOnly((v) => !v)}
            className={cn(
              'inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold transition-colors',
              unreadOnly
                ? 'border-brand bg-brand-soft text-brand-ink'
                : 'border-hairline bg-surface text-ink-2 hover:bg-surface-2'
            )}
          >
            <Filter className="h-3.5 w-3.5" />
            {unreadOnly ? 'غير مقروء فقط' : 'الكل'}
          </button>
          <Button variant="outline" size="sm" onClick={markAllRead}>
            <CheckCheck className="ml-1 h-4 w-4" />
            تعليم الكل كمقروء
          </Button>
        </div>
      </div>

      {isError && (
        <Card className="border-danger-soft bg-danger-soft/40 p-4 text-sm text-danger">
          تعذّر تحميل الإشعارات.{' '}
          <button onClick={() => refetch()} className="font-semibold underline">
            إعادة المحاولة
          </button>
        </Card>
      )}

      <Card className="border-hairline bg-surface p-0">
        {isLoading ? (
          <div className="space-y-3 p-6">
            {[1, 2, 3, 4].map((i) => (
              <Skeleton key={i} className="h-16 w-full" />
            ))}
          </div>
        ) : list.length === 0 ? (
          <div className="grid place-items-center gap-2 py-16 text-center text-muted">
            <Bell className="h-8 w-8 opacity-40" />
            <p className="text-sm">
              {unreadOnly ? 'لا توجد إشعارات غير مقروءة' : 'لا توجد إشعارات بعد'}
            </p>
          </div>
        ) : (
          <ul className="divide-y divide-hairline">
            {list.map((n) => (
              <li key={n.id}>
                <NotificationRow n={n} onOpen={() => markOne(n)} />
              </li>
            ))}
          </ul>
        )}
      </Card>

      {pageInfo && pageInfo.last_page > 1 && (
        <div className="flex items-center justify-between text-sm text-ink-2">
          <span>
            صفحة <span className="num" dir="ltr">{pageInfo.current_page}</span> من{' '}
            <span className="num" dir="ltr">{pageInfo.last_page}</span>
          </span>
          <div className="flex gap-2">
            <Button
              variant="outline"
              size="sm"
              disabled={page <= 1}
              onClick={() => setPage((p) => Math.max(1, p - 1))}
            >
              السابق
            </Button>
            <Button
              variant="outline"
              size="sm"
              disabled={page >= (pageInfo.last_page ?? 1)}
              onClick={() => setPage((p) => p + 1)}
            >
              التالي
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}

function NotificationRow({ n, onOpen }: { n: TaqatNotification; onOpen: () => void }) {
  const body = (
    <div
      className={cn(
        'flex items-start gap-3 px-6 py-4 text-right transition-colors hover:bg-surface-2',
        !n.read_at && 'bg-brand-soft/30'
      )}
    >
      <div className="flex-1 space-y-1">
        <p className={cn('text-sm text-ink', !n.read_at && 'font-semibold')}>{n.title}</p>
        {n.body && <p className="text-xs text-muted">{n.body}</p>}
        {n.created_at && (
          <p className="num pt-1 text-[10px] uppercase tracking-wider text-muted" dir="ltr">
            {new Date(n.created_at).toLocaleString('ar-EG')}
          </p>
        )}
      </div>
      {!n.read_at && <span className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-brand" />}
    </div>
  );

  if (n.url) {
    return (
      <Link href={n.url} onClick={onOpen} className="block">
        {body}
      </Link>
    );
  }
  return (
    <button type="button" onClick={onOpen} className="block w-full">
      {body}
    </button>
  );
}
