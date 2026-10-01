'use client';

import * as React from 'react';
import Link from 'next/link';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import { Bell, ChevronRight, Loader2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { Switch } from '@/components/ui/switch';
import {
  notificationPreferencesApi,
  type NotificationChannel,
  type NotificationPreferenceRow,
  type NotificationPreferenceUpdate,
} from '@/lib/api/endpoints/notifications';

/**
 * Arabic labels for every supported channel. Kept here (not in the API
 * resource) because the channel vocabulary is a UI concern — the API
 * returns stable snake_case keys so a future locale switcher can swap
 * these without a server redeploy.
 */
const CHANNEL_LABELS: Record<NotificationChannel, string> = {
  database: 'في التطبيق',
  broadcast: 'إشعار فوري',
  mail: 'البريد الإلكتروني',
  sms: 'رسالة SMS',
  whatsapp: 'واتساب',
  push: 'إشعار الجوّال',
};

const CHANNEL_HINT: Record<NotificationChannel, string> = {
  database:
    'الإشعارات الدائمة التي تظهر في جرس الإشعارات. لا يمكن إيقافها لضمان بقاء سجل التنبيهات متاحًا دائمًا.',
  broadcast: 'إشعار مباشر داخل التطبيق فور وقوع الحدث (قد يحتاج تحديث الصفحة).',
  mail: 'إرسال نسخة إلى بريدك الإلكتروني.',
  sms: 'رسالة قصيرة إلى رقم جوّالك المسجّل. تُستخدم فقط للإشعارات الحرجة.',
  whatsapp: 'رسالة واتساب على رقم جوّالك المسجّل.',
  push: 'إشعار على تطبيق الجوّال عند تسجيل الدخول.',
};

const CHANNELS_IN_ORDER: NotificationChannel[] = [
  'database',
  'broadcast',
  'mail',
  'sms',
  'whatsapp',
  'push',
];

export default function NotificationPreferencesPage() {
  const queryClient = useQueryClient();

  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['me', 'notification-preferences'],
    queryFn: async () => (await notificationPreferencesApi.list()).data.data,
  });

  // Local draft so toggling cells doesn't fire a PUT per flip — the user
  // reviews the matrix and clicks "Save" at the bottom. Hydrated from the
  // server copy on every refetch; a `dirty` flag unlocks the save button.
  const [draft, setDraft] = React.useState<NotificationPreferenceRow[] | null>(null);
  const [dirty, setDirty] = React.useState(false);
  React.useEffect(() => {
    if (data) {
      setDraft(data);
      setDirty(false);
    }
  }, [data]);

  const mutation = useMutation({
    mutationFn: (payload: NotificationPreferenceUpdate[]) =>
      notificationPreferencesApi.update(payload),
    onSuccess: (res) => {
      toast.success('تم حفظ تفضيلات الإشعارات');
      setDraft(res.data.data);
      setDirty(false);
      queryClient.invalidateQueries({ queryKey: ['me', 'notification-preferences'] });
    },
    onError: () => {
      toast.error('تعذّر حفظ التفضيلات — حاول مرة أخرى.');
    },
  });

  const toggle = (eventKey: string, channel: NotificationChannel, next: boolean): void => {
    // Database is deliberately locked ON in the UI: the backend never
    // suppresses it (durable inbox contract) so flipping the switch here
    // would be a lie.
    if (channel === 'database') return;

    setDraft((prev) => {
      if (!prev) return prev;
      return prev.map((row) =>
        row.event_key === eventKey
          ? { ...row, channels: { ...row.channels, [channel]: next } }
          : row,
      );
    });
    setDirty(true);
  };

  // Convert the matrix back into the flat list the API expects. We only
  // send cells that differ from the server copy — the matrix has ~60
  // cells, and sending the full set every time would waste bandwidth and
  // make the audit log noisier than it needs to be.
  const buildPayload = (): NotificationPreferenceUpdate[] => {
    if (!draft || !data) return [];
    const payload: NotificationPreferenceUpdate[] = [];
    const serverByKey = new Map(data.map((r) => [r.event_key, r]));
    for (const row of draft) {
      const serverRow = serverByKey.get(row.event_key);
      if (!serverRow) continue;
      for (const channel of CHANNELS_IN_ORDER) {
        if (channel === 'database') continue;
        if (serverRow.channels[channel] !== row.channels[channel]) {
          payload.push({
            event_key: row.event_key,
            channel,
            enabled: row.channels[channel],
          });
        }
      }
    }
    return payload;
  };

  const save = () => {
    const payload = buildPayload();
    if (payload.length === 0) {
      setDirty(false);
      return;
    }
    mutation.mutate(payload);
  };

  return (
    <div className="space-y-6">
      <nav className="flex items-center gap-1.5 text-xs text-muted">
        <Link href="/profile" className="hover:text-ink">
          الملف الشخصي
        </Link>
        <ChevronRight className="h-3 w-3 rtl:rotate-180" />
        <span className="text-ink">تفضيلات الإشعارات</span>
      </nav>

      <div className="flex flex-col gap-2">
        <h1 className="flex items-center gap-2 text-2xl font-bold text-ink">
          <Bell className="h-5 w-5 text-brand" />
          تفضيلات الإشعارات
        </h1>
        <p className="text-sm text-muted">
          تحكّم في القنوات التي يصلك عبرها كل نوع من الإشعارات. تبقى الإشعارات داخل التطبيق فعّالة
          دائمًا لضمان بقاء السجل متاحًا.
        </p>
      </div>

      {isError && (
        <Card className="border-danger-soft bg-danger-soft/40 p-4 text-sm text-danger">
          تعذّر تحميل تفضيلات الإشعارات.{' '}
          <button onClick={() => refetch()} className="font-semibold underline">
            إعادة المحاولة
          </button>
        </Card>
      )}

      <Card className="border-hairline bg-surface">
        {isLoading || !draft ? (
          <div className="space-y-3 p-5">
            <Skeleton className="h-6 w-40" />
            <Skeleton className="h-24 w-full" />
            <Skeleton className="h-24 w-full" />
          </div>
        ) : (
          <PreferencesMatrix rows={draft} onToggle={toggle} />
        )}
      </Card>

      <div className="sticky bottom-4 z-10 flex items-center justify-between gap-2 rounded-md border border-hairline bg-surface p-3 shadow-md">
        <p className="text-xs text-muted">
          {dirty
            ? 'لديك تغييرات غير محفوظة — اضغط حفظ لتأكيدها.'
            : 'جميع التغييرات محفوظة.'}
        </p>
        <Button
          type="button"
          onClick={save}
          disabled={!dirty || mutation.isPending}
          className="bg-brand text-white hover:bg-brand-hover"
        >
          {mutation.isPending && <Loader2 className="h-4 w-4 animate-spin" />}
          حفظ التفضيلات
        </Button>
      </div>
    </div>
  );
}

// ---------------------------------------------------------------------------
// Matrix table — one row per event, one column per channel.
// ---------------------------------------------------------------------------

function PreferencesMatrix({
  rows,
  onToggle,
}: {
  rows: NotificationPreferenceRow[];
  onToggle: (eventKey: string, channel: NotificationChannel, next: boolean) => void;
}) {
  return (
    <div className="overflow-x-auto">
      <table className="min-w-full border-collapse text-sm">
        <thead>
          <tr className="border-b border-hairline bg-surface-2 text-start text-xs font-medium text-muted">
            <th scope="col" className="min-w-[220px] px-4 py-3 text-start">
              نوع الإشعار
            </th>
            {CHANNELS_IN_ORDER.map((channel) => (
              <th
                key={channel}
                scope="col"
                className="px-3 py-3 text-center"
                title={CHANNEL_HINT[channel]}
              >
                {CHANNEL_LABELS[channel]}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={row.event_key} className="border-b border-hairline last:border-0">
              <th scope="row" className="px-4 py-3 text-start text-sm font-medium text-ink">
                {row.label}
              </th>
              {CHANNELS_IN_ORDER.map((channel) => {
                const isLocked = channel === 'database';
                return (
                  <td key={channel} className="px-3 py-3 text-center">
                    <div className="flex items-center justify-center">
                      <Switch
                        checked={row.channels[channel]}
                        disabled={isLocked}
                        onCheckedChange={(next) => onToggle(row.event_key, channel, next)}
                        aria-label={`${row.label} — ${CHANNEL_LABELS[channel]}`}
                      />
                    </div>
                  </td>
                );
              })}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
