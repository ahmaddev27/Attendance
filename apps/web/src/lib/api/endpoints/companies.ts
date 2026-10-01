import { apiClient } from '@/lib/api/client';
import type {
  ApiResource,
  Company,
  CompanyInput,
  CompanyListParams,
  CompanyTreeNode,
  PaginatedResponse,
} from '@/lib/api/types';

export const companiesApi = {
  list: (params?: CompanyListParams) =>
    apiClient.get<PaginatedResponse<Company>>('/companies', { params }),
  get: (id: number) => apiClient.get<ApiResource<Company>>(`/companies/${id}`),
  create: (data: CompanyInput) => apiClient.post<ApiResource<Company>>('/companies', data),
  update: (id: number, data: CompanyInput) =>
    apiClient.put<ApiResource<Company>>(`/companies/${id}`, data),
  delete: (id: number) => apiClient.delete(`/companies/${id}`),
  /**
   * Multipart upload — axios needs the FormData explicitly, otherwise the
   * default `Content-Type: application/json` header would make Laravel
   * reject the request before it reached UploadCompanyLogoRequest.
   */
  uploadLogo: (id: number, file: File) => {
    const form = new FormData();
    form.append('logo', file);
    return apiClient.post<ApiResource<Company>>(`/companies/${id}/logo`, form);
  },
  removeLogo: (id: number) => apiClient.delete<ApiResource<Company>>(`/companies/${id}/logo`),
  tree: () => apiClient.get<{ data: CompanyTreeNode[] }>('/companies/tree'),
};
