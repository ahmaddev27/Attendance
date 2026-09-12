import { apiClient } from '@/lib/api/client';
import type {
  ApiResource,
  PaginatedResponse,
  RequestActionPayload,
  RequestDetail,
  RequestForwardPayload,
  RequestListParams,
  RequestSummary,
  SubmitRequestPayload,
} from '@/lib/api/types';

/** Admin-facing view of every request in the system, plus workflow decisions. */
export const requestsApi = {
  list: (params?: RequestListParams) =>
    apiClient.get<PaginatedResponse<RequestSummary>>('/requests', { params }),
  get: (id: number) => apiClient.get<ApiResource<RequestDetail>>(`/requests/${id}`),
  approve: (id: number, payload?: RequestActionPayload) =>
    apiClient.post(`/requests/${id}/approve`, payload),
  reject: (id: number, payload: RequestActionPayload) =>
    apiClient.post(`/requests/${id}/reject`, payload),
  return: (id: number, payload: RequestActionPayload) =>
    apiClient.post(`/requests/${id}/return`, payload),
  forward: (id: number, payload: RequestForwardPayload) =>
    apiClient.post(`/requests/${id}/forward`, payload),
};

/** Logged-in employee's own requests — submit, track, cancel, resubmit. */
export const myRequestsApi = {
  list: () => apiClient.get<PaginatedResponse<RequestSummary>>('/me/requests'),
  get: (id: number) => apiClient.get<ApiResource<RequestDetail>>(`/me/requests/${id}`),
  submit: (payload: SubmitRequestPayload) => apiClient.post<ApiResource<RequestDetail>>('/me/requests', payload),
  cancel: (id: number) => apiClient.post(`/me/requests/${id}/cancel`),
  /**
   * Resubmit a Returned request with (typically edited) form_data.
   * The backend keeps the original request_number and routes the
   * request back through the workflow's first step.
   */
  resubmit: (id: number, formData: Record<string, unknown>) =>
    apiClient.post<ApiResource<RequestDetail>>(`/me/requests/${id}/resubmit`, { form_data: formData }),
};
