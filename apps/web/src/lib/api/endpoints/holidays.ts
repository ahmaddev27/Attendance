import { apiClient } from '../client';
import type { ApiResource, Holiday, HolidayPayload } from '../types';

export type HolidayListParams = {
  year?: number;
  type?: Holiday['type'];
};

export const holidaysApi = {
  list: (params: HolidayListParams = {}) =>
    apiClient.get<ApiResource<Holiday[]>>('/holidays', { params }).then((r) => r.data.data),

  get: (id: number) =>
    apiClient.get<ApiResource<Holiday>>(`/holidays/${id}`).then((r) => r.data.data),

  create: (payload: HolidayPayload) =>
    apiClient.post<ApiResource<Holiday>>('/holidays', payload).then((r) => r.data.data),

  update: (id: number, payload: HolidayPayload) =>
    apiClient.put<ApiResource<Holiday>>(`/holidays/${id}`, payload).then((r) => r.data.data),

  remove: (id: number) => apiClient.delete(`/holidays/${id}`).then(() => undefined),
};
