import { apiClient } from '@/lib/api/client';

export type SettingField = {
  key: string;
  label: string;
  type: 'text' | 'email' | 'password' | 'url' | 'boolean';
  encrypted: boolean;
  has_value: boolean;
  value: string | null;
};

// SettingsPayload is intentionally an open Record so the admin UI renders
// whatever groups the API sends back — mail, sms, whatsapp, ai, and any
// group added server-side later. Nothing here needs updating when a new
// group (e.g. `whatsapp`) ships as long as the API returns SettingField[].
export type SettingsPayload = Record<string, SettingField[]>;

// Backend accepts an open bag of groups (mail/sms/whatsapp/ai/push/...) —
// each maps to a Record of that group's field keys. Kept open (index
// signature) so the UI can hand in any groups the API declares without
// having to update this type for every new group.
export type SettingsUpdatePayload = Record<string, Record<string, string>>;

// Test-endpoint result shape. On failure the API returns 422 with the same
// envelope + a `message` field — axios throws in that case, so the caller's
// error branch reads `err.response.data.message`.
export type SettingsTestResult = {
  ok: boolean;
  message?: string;
  provider_message_id?: string | null;
  raw_response?: Record<string, unknown> | null;
  error?: string | null;
};

export const settingsApi = {
  get: () => apiClient.get<{ data: SettingsPayload }>('/admin/settings'),
  update: (payload: SettingsUpdatePayload) =>
    apiClient.put<{ data: SettingsPayload }>('/admin/settings', payload),

  testMail: (to: string) =>
    apiClient.post<{ data: SettingsTestResult }>('/admin/settings/test/mail', { to }),
  testSms: (to: string) =>
    apiClient.post<{ data: SettingsTestResult }>('/admin/settings/test/sms', { to }),
  testWhatsapp: (to: string) =>
    apiClient.post<{ data: SettingsTestResult }>('/admin/settings/test/whatsapp', { to }),
};
