import type { AxiosProgressEvent } from 'axios';

import { apiClient } from '@/lib/api/client';
import type {
  ApiResource,
  LeaveBalance,
  LeaveRequest,
  LeaveRequestListParams,
  LeaveRequestPayload,
  PaginatedResponse,
} from '@/lib/api/types';

export type LeaveAttachmentUpload = {
  attachment_path: string;
  download_url: string;
};

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
  /**
   * Uploads a supporting document before submit — the returned
   * `attachment_path` is then passed on the submit payload so the
   * eventual LeaveRequest row references the stored file. Streams via
   * multipart/form-data with `onUploadProgress` so the dialog can show
   * a progress indicator for large PDFs.
   */
  uploadAttachment: (file: File, onUploadProgress?: (event: AxiosProgressEvent) => void) => {
    const formData = new FormData();
    formData.append('file', file);
    return apiClient.post<ApiResource<LeaveAttachmentUpload>>('/me/leaves/attachment', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      onUploadProgress,
    });
  },
};
