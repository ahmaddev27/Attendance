import { apiClient } from '@/lib/api/client';
import type {
  ApiResource,
  Department,
  DepartmentInput,
  DepartmentListParams,
  PaginatedResponse,
} from '@/lib/api/types';

export const departmentsApi = {
  list: (params?: DepartmentListParams) =>
    apiClient.get<PaginatedResponse<Department>>('/org/departments', { params }),
  get: (id: number) =>
    apiClient.get<ApiResource<Department>>(`/org/departments/${id}`),
  create: (data: DepartmentInput) =>
    apiClient.post<ApiResource<Department>>('/org/departments', data),
  update: (id: number, data: DepartmentInput) =>
    apiClient.put<ApiResource<Department>>(`/org/departments/${id}`, data),
  delete: (id: number) => apiClient.delete(`/org/departments/${id}`),
  /**
   * There's no dedicated "assign manager" endpoint in the M2 API contract —
   * manager assignment is just a partial update of the department resource.
   * Kept as its own helper so callers (the row action + the assign dialog)
   * don't need to know the underlying HTTP shape.
   */
  assignManager: (id: number, managerId: number | null) =>
    apiClient.put<ApiResource<Department>>(`/org/departments/${id}`, {
      manager_id: managerId,
    }),
};
