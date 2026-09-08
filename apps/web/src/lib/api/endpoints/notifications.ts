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
