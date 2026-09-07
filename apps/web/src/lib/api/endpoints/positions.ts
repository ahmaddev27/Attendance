import { apiClient } from '@/lib/api/client';
import type {
  ApiResource,
  PaginatedResponse,
  Position,
  PositionInput,
  PositionListParams,
} from '@/lib/api/types';

export const positionsApi = {
  list: (params?: PositionListParams) =>
    apiClient.get<PaginatedResponse<Position>>('/org/positions', { params }),
  get: (id: number) =>
    apiClient.get<ApiResource<Position>>(`/org/positions/${id}`),
  create: (data: PositionInput) =>
    apiClient.post<ApiResource<Position>>('/org/positions', data),
  update: (id: number, data: PositionInput) =>
    apiClient.put<ApiResource<Position>>(`/org/positions/${id}`, data),
  delete: (id: number) => apiClient.delete(`/org/positions/${id}`),
};
