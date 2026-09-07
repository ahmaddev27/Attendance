import { apiClient } from '@/lib/api/client';
import type { ApiResource, AuditLogEntry, AuditLogListParams, PaginatedResponse } from '@/lib/api/types';

export const auditLogsApi = {
  list: (params: AuditLogListParams = {}) =>
    apiClient.get<PaginatedResponse<AuditLogEntry>>('/audit-logs', { params }).then((r) => r.data),

  get: (id: number) =>
    apiClient.get<ApiResource<AuditLogEntry>>(`/audit-logs/${id}`).then((r) => r.data.data),
};
