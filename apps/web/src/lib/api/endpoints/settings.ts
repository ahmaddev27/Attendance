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

export type SettingsUpdatePayload = {
  mail?: Record<string, string>;
  sms?: Record<string, string>;
  whatsapp?: Record<string, string>;
};

export const settingsApi = {
  get: () => apiClient.get<{ data: SettingsPayload }>('/admin/settings'),
  update: (payload: SettingsUpdatePayload) =>
    apiClient.put<{ data: SettingsPayload }>('/admin/settings', payload),
};
