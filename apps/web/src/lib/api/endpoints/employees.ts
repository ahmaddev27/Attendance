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
};
