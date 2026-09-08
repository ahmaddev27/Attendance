'use client';

import * as React from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { AtSign, Bot, Loader2, MessageSquareText, Save, Settings } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import {
  settingsApi,
  type SettingField,
  type SettingsPayload,
  type SettingsUpdatePayload,
} from '@/lib/api/endpoints/settings';

/**
 * Runtime-editable system settings. Backend groups the fields for us
 * (mail / sms / ai) so this page is a straight render of whatever the
 * API returns — no hardcoded field list.
 *
 * Secrets (encrypted fields) never come back to the client as plaintext.
 * The form shows a "•••" placeholder + a "set new value" empty input;
 * leaving it blank keeps the stored value untouched.
 */
export default function SettingsPage() {
  const queryClient = useQueryClient();
  const [saving, setSaving] = React.useState(false);
  const [formState, setFormState] = React.useState<Record<string, Record<string, string>>>({});

  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['admin', 'settings'],
    queryFn: async () => (await settingsApi.get()).data.data,
  });

  // Seed form state whenever the server payload changes — but only for
  // plaintext fields. Encrypted fields stay blank so the admin has to
  // type them fresh to rotate.
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
      <div className="flex items-center justify-between">
        <div>
          <h1 className="flex items-center gap-2 text-2xl font-bold text-ink">
            <Settings className="h-6 w-6 text-brand" /> إعدادات النظام
          </h1>
          <p className="mt-1 text-sm text-muted">
            اربط خدمات البريد و SMS و AI. الحقول الحساسة مشفّرة قبل التخزين.
          </p>
        </div>
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
        </div>
      )}
    </div>
  );
}

// ---- Sub-components ----

function SettingsGroup({
  title,
  description,
  icon,
  fields,
  values,
  onChange,
}: {
  title: string;
  description: string;
  icon: React.ReactNode;
  fields: SettingField[];
  values: Record<string, string>;
  onChange: (key: string, value: string) => void;
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
    </Card>
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
