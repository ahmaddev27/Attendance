import { apiClient } from '../client';
import type { ApiResource, WorkSchedule, WorkSchedulePayload } from '../types';

export const schedulesApi = {
  list: () =>
    apiClient.get<ApiResource<WorkSchedule[]>>('/work-schedules').then((r) => r.data.data),

  get: (id: number) =>
    apiClient.get<ApiResource<WorkSchedule>>(`/work-schedules/${id}`).then((r) => r.data.data),

  create: (payload: WorkSchedulePayload) =>
    apiClient
      .post<ApiResource<WorkSchedule>>('/work-schedules', payload)
      .then((r) => r.data.data),

  update: (id: number, payload: WorkSchedulePayload) =>
    apiClient
      .put<ApiResource<WorkSchedule>>(`/work-schedules/${id}`, payload)
      .then((r) => r.data.data),

  remove: (id: number) => apiClient.delete(`/work-schedules/${id}`).then(() => undefined),
};
