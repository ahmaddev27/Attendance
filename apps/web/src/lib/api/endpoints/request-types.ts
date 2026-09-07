import { apiClient } from '@/lib/api/client';
import type { ApiResource, RequestType, RequestTypePayload } from '@/lib/api/types';

/** Admin request-type CRUD — same "small config list" shape as leave types/workflows. */
export const requestTypesApi = {
  list: () => apiClient.get<ApiResource<RequestType[]>>('/request-types'),
  get: (id: number) => apiClient.get<ApiResource<RequestType>>(`/request-types/${id}`),
  create: (payload: RequestTypePayload) => apiClient.post<ApiResource<RequestType>>('/request-types', payload),
  update: (id: number, payload: RequestTypePayload) =>
    apiClient.put<ApiResource<RequestType>>(`/request-types/${id}`, payload),
  delete: (id: number) => apiClient.delete(`/request-types/${id}`),
};
