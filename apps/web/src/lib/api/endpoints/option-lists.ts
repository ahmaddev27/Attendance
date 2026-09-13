import { apiClient } from '@/lib/api/client';

/** Mirrors App\Shared\Enums\OptionList on the API. */
export type OptionListKey = 'currencies' | 'lead_sources' | 'industries' | 'company_sizes' | 'education_levels';

export type OptionItem = {
  /** Stable code stored on records, e.g. "USD" or "direct_outreach". */
  value: string;
  /** What users read, e.g. "دولار أمريكي". */
  label: string;
};

export type OptionLists = Record<OptionListKey, OptionItem[]>;

/** One list as the settings editor needs it. */
export type AdminOptionList = {
  key: OptionListKey;
  group: 'general' | 'recruitment';
  label: string;
  description: string;
  /** Arabic rule the API enforces on codes for this list. */
  value_hint: string;
  items: OptionItem[];
};

export const optionListsApi = {
  all: () => apiClient.get<{ data: OptionLists }>('/option-lists'),

  adminList: () => apiClient.get<{ data: AdminOptionList[] }>('/admin/option-lists'),
  update: (key: OptionListKey, items: OptionItem[]) =>
    apiClient.put<{ data: AdminOptionList }>(`/admin/option-lists/${key}`, { items }),
  /** Drops the admin override; the response carries the code defaults. */
  reset: (key: OptionListKey) => apiClient.delete<{ data: AdminOptionList }>(`/admin/option-lists/${key}`),
};
