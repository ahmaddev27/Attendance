import { apiClient } from '@/lib/api/client';
import type { PaginatedResponse } from '@/lib/api/types';

export type AuditActivity = {
  id: number;
  description: string;
  log_name: string | null;
  event: string | null;
  subject_type: string | null;
  subject_id: number | null;
  causer: {
    id: number;
    name: string;
    employee_number: number;
  } | null;
  properties: Record<string, unknown>;
  created_at: string | null;
};

export type AuditFilters = {
  page?: number;
  per_page?: number;
  subject_type?: string;
  log_name?: string;
  causer_id?: number;
  from?: string;
  to?: string;
};

export const auditLogApi = {
  list: (params?: AuditFilters) =>
    apiClient.get<PaginatedResponse<AuditActivity>>('/admin/audit-log', { params }),
};
