import { apiClient } from '@/lib/api/client';
import type {
  ApiResource,
  PaginatedResponse,
  Team,
  TeamInput,
  TeamListParams,
} from '@/lib/api/types';

export const teamsApi = {
  list: (params?: TeamListParams) =>
    apiClient.get<PaginatedResponse<Team>>('/org/teams', { params }),
  get: (id: number) => apiClient.get<ApiResource<Team>>(`/org/teams/${id}`),
  create: (data: TeamInput) =>
    apiClient.post<ApiResource<Team>>('/org/teams', data),
  update: (id: number, data: TeamInput) =>
    apiClient.put<ApiResource<Team>>(`/org/teams/${id}`, data),
  delete: (id: number) => apiClient.delete(`/org/teams/${id}`),
  /** See departmentsApi.assignManager — same partial-update pattern. */
  assignLeader: (id: number, leaderId: number | null) =>
    apiClient.put<ApiResource<Team>>(`/org/teams/${id}`, {
      leader_id: leaderId,
    }),
};
