import { apiClient } from '@/lib/api/client';
import type {
  ApiResource,
  LeaveBalance,
  LeaveRequest,
  LeaveRequestListParams,
  LeaveRequestPayload,
  PaginatedResponse,
} from '@/lib/api/types';

/** Admin-facing leave request CRUD + approval workflow. */
export const leaveRequestsApi = {
  list: (params?: LeaveRequestListParams) =>
    apiClient.get<PaginatedResponse<LeaveRequest>>('/leave-requests', { params }),
  get: (id: number) => apiClient.get<ApiResource<LeaveRequest>>(`/leave-requests/${id}`),
  create: (payload: LeaveRequestPayload) =>
    apiClient.post<ApiResource<LeaveRequest>>('/leave-requests', payload),
  approve: (id: number) => apiClient.post(`/leave-requests/${id}/approve`),
  reject: (id: number, reason: string) =>
    apiClient.post(`/leave-requests/${id}/reject`, { rejection_reason: reason }),
  cancel: (id: number) => apiClient.post(`/leave-requests/${id}/cancel`),
  delete: (id: number) => apiClient.delete(`/leave-requests/${id}`),
};

/** Logged-in employee's own leave requests + balances. */
export const myLeavesApi = {
  list: () => apiClient.get<PaginatedResponse<LeaveRequest>>('/me/leaves'),
  balances: () => apiClient.get<ApiResource<LeaveBalance[]>>('/me/leaves/balances'),
  submit: (payload: Omit<LeaveRequestPayload, 'employee_id'>) =>
    apiClient.post<ApiResource<LeaveRequest>>('/me/leaves', payload),
  cancel: (id: number) => apiClient.post(`/me/leaves/${id}/cancel`),
};
