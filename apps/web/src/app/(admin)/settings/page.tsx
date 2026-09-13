'use client';

import * as React from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import {
  AtSign,
  Bell,
  Bot,
  ListChecks,
  Loader2,
  MessageCircle,
  MessageSquareText,
  PlugZap,
  Save,
  Send,
  Settings,
} from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { OptionListsPanel } from '@/components/option-lists/option-lists-panel';
import {
  settingsApi,
  type SettingField,
  type SettingsUpdatePayload,
} from '@/lib/api/endpoints/settings';

type TestKind = 'mail' | 'sms' | 'whatsapp';

type SettingsTab = 'integrations' | 'lists';

const SETTINGS_TABS: readonly string[] = ['integrations', 'lists'] satisfies SettingsTab[];

const isSettingsTab = (value: string): value is SettingsTab => SETTINGS_TABS.includes(value);

/**
 * Runtime-editable system settings. Backend groups the fields for us
 * (mail / sms / whatsapp / ai / push) so this page is a straight render
 * of whatever the API returns — no hardcoded field list.
 *
 * Secrets (encrypted fields) never come back to the client as plaintext.
 * The form shows a "•••" placeholder + a "set new value" empty input;
 * leaving it blank keeps the stored value untouched.
 *
 * Each communication group (mail / sms / whatsapp) also renders a
 * "test send" inline form so an admin can smoke-test the credentials
 * immediately after editing them — the endpoints run inline (not queued)
 * so the response reflects the real send outcome.
 */
export default function SettingsPage() {
  const queryClient = useQueryClient();
  const [saving, setSaving] = React.useState(false);
  const [formState, setFormState] = React.useState<Record<string, Record<string, string>>>({});

  // The active tab lives in the URL hash so /settings#lists deep-links
  // straight to the picker lists.
  const [tab, setTab] = React.useState<SettingsTab>('integrations');

  React.useEffect(() => {
    const fromHash = window.location.hash.slice(1);
    if (isSettingsTab(fromHash)) setTab(fromHash);
  }, []);

  const changeTab = (value: string) => {
    if (!isSettingsTab(value)) return;
    setTab(value);
    window.history.replaceState(null, '', `#${value}`);
  };

  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['admin', 'settings'],
    queryFn: async () => (await settingsApi.get()).data.data,
  });

  React.useEffect(() => {
    if (!data) return;
    const initial: Record<string, Record<string, string>> = {};
    for (const [group, fields] of Object.entries(data)) {
      initial[group] = {};
      for (const field of fields) {
        initial[group][field.key] = field.encrypted ? '' : (field.value ?? '');
      }
    }
    setFormState(initial);
  }, [data]);

  const setValue = (group: string, key: string, value: string) => {
    setFormState((prev) => ({
      ...prev,
      [group]: { ...(prev[group] ?? {}), [key]: value },
    }));
  };

  const handleSave = async () => {
    setSaving(true);
    try {
      const payload: SettingsUpdatePayload = {};
      for (const [group, values] of Object.entries(formState)) {
        payload[group as keyof SettingsUpdatePayload] = values;
      }
      await settingsApi.update(payload);
      toast.success('تم حفظ الإعدادات');
      queryClient.invalidateQueries({ queryKey: ['admin', 'settings'] });
    } catch (e: unknown) {
      const message =
        (e as { response?: { data?: { message?: string } } })?.response?.data?.message
        ?? 'تعذّر حفظ الإعدادات';
      toast.error(message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="space-y-4">
      <div>
        <h1 className="flex items-center gap-2 text-2xl font-bold text-ink">
          <Settings className="h-6 w-6 text-brand" /> إعدادات النظام
        </h1>
        <p className="mt-1 text-sm text-muted">
          اربط الخدمات الخارجية وأدِر قوائم الاختيار التي تظهر في النماذج.
        </p>
      </div>

      <Tabs value={tab} onValueChange={changeTab}>
        <TabsList>
          <TabsTrigger value="integrations" className="gap-1.5">
            <PlugZap className="h-4 w-4" /> الخدمات والتكاملات
          </TabsTrigger>
          <TabsTrigger value="lists" className="gap-1.5">
            <ListChecks className="h-4 w-4" /> قوائم الاختيار
          </TabsTrigger>
        </TabsList>

        <TabsContent value="integrations" className="space-y-4">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <p className="text-sm text-muted">
              اربط خدمات البريد و SMS و واتساب و AI. الحقول الحساسة مشفّرة قبل التخزين.
            </p>
            <Button
              type="button"
              onClick={handleSave}
              disabled={saving || isLoading || !data}
              className="bg-brand hover:bg-brand-hover text-white"
            >
              {saving ? <Loader2 className="me-1 h-4 w-4 animate-spin" /> : <Save className="me-1 h-4 w-4" />}
              حفظ التغييرات
            </Button>
          </div>

          {isError && (
            <Card className="border-danger-soft bg-danger-soft/40 p-4 text-sm text-danger">
              تعذّر تحميل الإعدادات.{' '}
              <button onClick={() => refetch()} className="font-semibold underline">
                إعادة المحاولة
              </button>
            </Card>
          )}

          {isLoading || !data ? (
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
              {[1, 2, 3].map((i) => (
                <Skeleton key={i} className="h-64 w-full" />
              ))}
            </div>
          ) : (
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
              {'mail' in data && (
                <SettingsGroup
                  title="البريد الإلكتروني (Resend)"
                  description="إعدادات إرسال الإيميلات عبر خدمة Resend."
                  icon={<AtSign className="h-4 w-4 text-brand" />}
                  fields={data.mail}
                  values={formState.mail ?? {}}
                  onChange={(k, v) => setValue('mail', k, v)}
                  test={{
                    kind: 'mail',
                    label: 'أرسل بريد اختبار',
                    inputLabel: 'عنوان البريد المستقبِل',
                    inputPlaceholder: 'name@example.com',
                    inputType: 'email',
                  }}
                />
              )}
              {'sms' in data && (
                <SettingsGroup
                  title="الرسائل النصية (MTC SMS)"
                  description="بيانات الاعتماد لبوابة MTC Jordan."
                  icon={<MessageSquareText className="h-4 w-4 text-brand" />}
                  fields={data.sms}
                  values={formState.sms ?? {}}
                  onChange={(k, v) => setValue('sms', k, v)}
                  test={{
                    kind: 'sms',
                    label: 'أرسل SMS اختبار',
                    inputLabel: 'رقم الجوّال (مثال: 962791234567)',
                    inputPlaceholder: '9627XXXXXXXX',
                    inputType: 'tel',
                  }}
                />
              )}
              {'whatsapp' in data && (
                <SettingsGroup
                  title="واتساب (Meta Cloud API)"
                  description="إعدادات إرسال رسائل واتساب من الحساب التجاري."
                  icon={<MessageCircle className="h-4 w-4 text-brand" />}
                  fields={data.whatsapp}
                  values={formState.whatsapp ?? {}}
                  onChange={(k, v) => setValue('whatsapp', k, v)}
                  test={{
                    kind: 'whatsapp',
                    label: 'أرسل رسالة واتساب اختبار',
                    inputLabel: 'رقم الواتساب (مثال: 962791234567)',
                    inputPlaceholder: '9627XXXXXXXX',
                    inputType: 'tel',
                  }}
                />
              )}
              {'ai' in data && (
                <SettingsGroup
                  title="الذكاء الاصطناعي (Claude)"
                  description="مفتاح Anthropic لتوليد الرسائل التحفيزية."
                  icon={<Bot className="h-4 w-4 text-brand" />}
                  fields={data.ai}
                  values={formState.ai ?? {}}
                  onChange={(k, v) => setValue('ai', k, v)}
                />
              )}
              {'push' in data && (
                <SettingsGroup
                  title="إشعارات Push (Expo)"
                  description="Access token اختياري لإشعارات تطبيق الجوّال."
                  icon={<Bell className="h-4 w-4 text-brand" />}
                  fields={data.push}
                  values={formState.push ?? {}}
                  onChange={(k, v) => setValue('push', k, v)}
                />
              )}
            </div>
          )}
        </TabsContent>

        <TabsContent value="lists">
          <OptionListsPanel />
        </TabsContent>
      </Tabs>
    </div>
  );
}

// ---- Sub-components ----

type TestConfig = {
  kind: TestKind;
  label: string;
  inputLabel: string;
  inputPlaceholder: string;
  inputType: 'email' | 'tel';
};

function SettingsGroup({
  title,
  description,
  icon,
  fields,
  values,
  onChange,
  test,
}: {
  title: string;
  description: string;
  icon: React.ReactNode;
  fields: SettingField[];
  values: Record<string, string>;
  onChange: (key: string, value: string) => void;
  test?: TestConfig;
}) {
  return (
    <Card className="border-hairline bg-surface p-5">
      <div className="mb-4 flex items-start gap-2">
        <div className="grid h-8 w-8 place-items-center rounded-lg bg-brand-soft">{icon}</div>
        <div className="flex-1">
          <h2 className="text-base font-semibold text-ink">{title}</h2>
          <p className="text-xs text-muted">{description}</p>
        </div>
      </div>
      <div className="space-y-3">
        {fields.map((field) => (
          <FieldInput
            key={field.key}
            field={field}
            value={values[field.key] ?? ''}
            onChange={(v) => onChange(field.key, v)}
          />
        ))}
      </div>
      {test && <TestSender test={test} />}
    </Card>
  );
}

/**
 * Inline test-send form rendered under each communications group.
 * Calls the sync `/admin/settings/test/{kind}` endpoint so an admin
 * gets immediate success/failure feedback for a live send.
 *
 * Uses the values currently stored on the server, NOT the unsaved
 * form draft — this is intentional: admins should save first, then
 * test what will actually go out in production.
 */
function TestSender({ test }: { test: TestConfig }) {
  const [to, setTo] = React.useState('');
  const [busy, setBusy] = React.useState(false);

  const handleSend = async () => {
    if (!to.trim()) {
      toast.error(test.inputLabel + ' مطلوب');
      return;
    }
    setBusy(true);
    try {
      const api =
        test.kind === 'mail'
          ? settingsApi.testMail
          : test.kind === 'sms'
            ? settingsApi.testSms
            : settingsApi.testWhatsapp;
      const res = await api(to.trim());
      toast.success(res.data.data.message ?? 'تم إرسال الاختبار');
    } catch (e: unknown) {
      const message =
        (e as { response?: { data?: { message?: string; data?: { error?: string } } } })
          ?.response?.data?.message
        ?? (e as { response?: { data?: { data?: { error?: string } } } })
          ?.response?.data?.data?.error
        ?? 'تعذّر إرسال الاختبار';
      toast.error(message);
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="mt-5 rounded-lg border border-hairline bg-surface-2 p-3">
      <Label className="mb-1.5 block text-xs font-semibold text-ink-2">
        {test.inputLabel}
      </Label>
      <div className="flex flex-wrap items-stretch gap-2">
        <Input
          type={test.inputType === 'email' ? 'email' : 'tel'}
          inputMode={test.inputType === 'email' ? 'email' : 'tel'}
          value={to}
          onChange={(e) => setTo(e.target.value)}
          placeholder={test.inputPlaceholder}
          dir="ltr"
          className="flex-1 min-w-[180px] text-start"
        />
        <Button
          type="button"
          onClick={handleSend}
          disabled={busy}
          variant="outline"
          className="gap-1"
        >
          {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
          {test.label}
        </Button>
      </div>
      <p className="mt-2 text-[11px] text-muted">
        الاختبار يستخدم الإعدادات المحفوظة حالياً — احفظ التغييرات أوّلاً ثم اختبر.
      </p>
    </div>
  );
}

function FieldInput({
  field,
  value,
  onChange,
}: {
  field: SettingField;
  value: string;
  onChange: (v: string) => void;
}) {
  if (field.type === 'boolean') {
    const bool = value === '1' || value === 'true';
    return (
      <div>
        <Label className="text-xs font-semibold text-ink-2">{field.label}</Label>
        <label className="mt-1.5 flex cursor-pointer items-center gap-2 rounded-lg border border-hairline bg-surface-2 p-3">
          <input
            type="checkbox"
            checked={bool}
            onChange={(e) => onChange(e.target.checked ? '1' : '0')}
            className="h-4 w-4 accent-brand"
          />
          <span className="text-sm text-ink">{bool ? 'مُفعّل' : 'غير مفعّل'}</span>
        </label>
      </div>
    );
  }

  return (
    <div>
      <Label htmlFor={field.key} className="text-xs font-semibold text-ink-2">
        {field.label}
        {field.encrypted && field.has_value && (
          <span className="ms-2 rounded bg-success-soft px-1.5 py-0.5 text-[10px] font-medium text-success">
            مُخزَّن ✓
          </span>
        )}
      </Label>
      <Input
        id={field.key}
        type={field.type === 'password' ? 'password' : 'text'}
        inputMode={field.type === 'email' ? 'email' : field.type === 'url' ? 'url' : undefined}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        placeholder={
          field.encrypted && field.has_value
            ? '•••••••• (اتركه فارغاً للإبقاء على القيمة الحالية)'
            : undefined
        }
        dir="ltr"
        className="mt-1.5 text-start"
      />
    </div>
  );
}
