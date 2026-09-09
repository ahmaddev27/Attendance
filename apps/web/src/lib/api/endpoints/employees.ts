import { apiClient } from '@/lib/api/client';
import type {
  ApiResource,
  Employee,
  EmployeeInput,
  EmployeeListParams,
  PaginatedResponse,
} from '@/lib/api/types';

export const employeesApi = {
  list: (params?: EmployeeListParams) =>
    apiClient.get<PaginatedResponse<Employee>>('/employees', { params }),
  get: (id: number) => apiClient.get<ApiResource<Employee>>(`/employees/${id}`),
  create: (data: EmployeeInput) =>
    apiClient.post<ApiResource<Employee>>('/employees', data),
  update: (id: number, data: EmployeeInput) =>
    apiClient.put<ApiResource<Employee>>(`/employees/${id}`, data),
  delete: (id: number) => apiClient.delete(`/employees/${id}`),
  restore: (id: number) => apiClient.post(`/employees/${id}/restore`),
  /**
   * Any authenticated user — returns teammates the caller is allowed
   * to assign tasks to (same team_id, or just self if teamless).
   * Used by TaskFormDialog for non-admin creators; admins keep hitting
   * `list()` for the full org.
   */
  myTeam: (search?: string) =>
    apiClient.get<{ data: Employee[] }>('/me/team', { params: { search } }),
};
