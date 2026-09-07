import { apiClient } from '@/lib/api/client';
import type { ApiResource, LeaveType, LeaveTypePayload } from '@/lib/api/types';

export const leaveTypesApi = {
  list: () => apiClient.get<ApiResource<LeaveType[]>>('/leave-types'),
  get: (id: number) => apiClient.get<ApiResource<LeaveType>>(`/leave-types/${id}`),
  create: (payload: LeaveTypePayload) => apiClient.post<ApiResource<LeaveType>>('/leave-types', payload),
  update: (id: number, payload: LeaveTypePayload) =>
    apiClient.put<ApiResource<LeaveType>>(`/leave-types/${id}`, payload),
  delete: (id: number) => apiClient.delete(`/leave-types/${id}`),
};
