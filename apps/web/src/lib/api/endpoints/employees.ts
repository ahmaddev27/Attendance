import { apiClient } from '@/lib/api/client';
import type {
  ApiResource,
  BulkEmailPayload,
  BulkEmailResult,
  BulkSmsPayload,
  BulkSmsResult,
  Employee,
  EmployeeFileUploadResponse,
  EmployeeInput,
  EmployeeListParams,
  MyProfile,
  MyProfileUpdatePayload,
  PaginatedResponse,
  UpdateMyPasswordPayload,
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
  /** Replaces the role of the employee's login account (manage-users). */
  assignRole: (id: number, role: string) =>
    apiClient.put<{ data: { employee_id: number; user_id: number; role: string | null } }>(`/employees/${id}/role`, { role }),
  /**
   * Any authenticated user — returns teammates the caller is allowed
   * to assign tasks to (same team_id, or just self if teamless).
   * Used by TaskFormDialog for non-admin creators; admins keep hitting
   * `list()` for the full org.
   */
  myTeam: (search?: string) =>
    apiClient.get<{ data: Employee[] }>('/me/team', { params: { search } }),

  /**
   * Upload the national ID scan for an employee. The server stores the file
   * on the PRIVATE disk and returns the storage path, which is persisted on
   * the employee row inside the same request — the FE needs the path only
   * if it wants to echo it back (e.g. a two-step wizard); the main form
   * refetches the employee after upload to pick up the new signed URL.
   */
  uploadNationalIdImage: (id: number, file: File) => {
    const formData = new FormData();
    formData.append('file', file);
    return apiClient.post<ApiResource<EmployeeFileUploadResponse>>(
      `/employees/${id}/national-id-image`,
      formData,
      { headers: { 'Content-Type': 'multipart/form-data' } },
    );
  },
  deleteNationalIdImage: (id: number) =>
    apiClient.delete(`/employees/${id}/national-id-image`),

  uploadEmploymentContract: (id: number, file: File) => {
    const formData = new FormData();
    formData.append('file', file);
    return apiClient.post<ApiResource<EmployeeFileUploadResponse>>(
      `/employees/${id}/employment-contract`,
      formData,
      { headers: { 'Content-Type': 'multipart/form-data' } },
    );
  },
  deleteEmploymentContract: (id: number) =>
    apiClient.delete(`/employees/${id}/employment-contract`),

  /**
   * Admin bulk comms — fan one email to a selected set of employees. The
   * server returns {queued, skipped_no_email} so the FE can show both
   * numbers in the toast ("sent to 42 out of 50; 8 had no email").
   */
  bulkEmail: (payload: BulkEmailPayload) =>
    apiClient.post<ApiResource<BulkEmailResult>>('/admin/employees/bulk-email', payload),
  bulkSms: (payload: BulkSmsPayload) =>
    apiClient.post<ApiResource<BulkSmsResult>>('/admin/employees/bulk-sms', payload),
};

/**
 * Employee self-service profile — read the linked user + employee for the
 * /profile page, update the phone number, and rotate the password. Every
 * write is scoped server-side to the caller's own row, so no id is ever
 * shipped from the client.
 */
export const myProfileApi = {
  show: () => apiClient.get<ApiResource<MyProfile>>('/me/profile'),
  update: (payload: MyProfileUpdatePayload) =>
    apiClient.patch<ApiResource<MyProfile>>('/me/profile', payload),
  updatePassword: (payload: UpdateMyPasswordPayload) =>
    apiClient.post<{ data: { ok: true }; message?: string }>('/me/password', payload),
};
