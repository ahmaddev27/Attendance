import { apiClient } from '@/lib/api/client';
import type { ApiResource, PaginatedResponse } from '@/lib/api/types';

export type TaqatNotification = {
  id: string;
  title: string;
  body: string | null;
  url: string | null;
  icon: string | null;
  meta: Record<string, unknown>;
  read_at: string | null;
  created_at: string | null;
};

export const notificationsApi = {
  list: (params?: { unread?: boolean; per_page?: number; page?: number }) =>
    apiClient.get<PaginatedResponse<TaqatNotification>>('/me/notifications', { params }),
  unreadCount: () =>
    apiClient.get<ApiResource<{ count: number }>>('/me/notifications/unread-count'),
  markRead: (id: string) =>
    apiClient.post<ApiResource<TaqatNotification>>(`/me/notifications/${id}/read`),
  markAllRead: () => apiClient.post<ApiResource<{ ok: boolean }>>('/me/notifications/read-all'),
};

export type NotificationChannel =
  | 'database'
  | 'broadcast'
  | 'mail'
  | 'sms'
  | 'whatsapp'
  | 'push';

export type NotificationPreferenceRow = {
  event_key: string;
  label: string;
  default_channels: NotificationChannel[];
  channels: Record<NotificationChannel, boolean>;
};

export type NotificationPreferenceUpdate = {
  event_key: string;
  channel: NotificationChannel;
  enabled: boolean;
};

export const notificationPreferencesApi = {
  list: () =>
    apiClient.get<ApiResource<NotificationPreferenceRow[]>>('/me/notification-preferences'),
  update: (preferences: NotificationPreferenceUpdate[]) =>
    apiClient.put<ApiResource<NotificationPreferenceRow[]>>('/me/notification-preferences', {
      preferences,
    }),
};
