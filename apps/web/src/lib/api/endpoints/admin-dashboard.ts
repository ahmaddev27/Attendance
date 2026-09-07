import { apiClient } from '@/lib/api/client';
import type {
  ApiResource,
  AttendanceTrendPoint,
  DashboardDepartmentBreakdown,
  ExecutiveDashboard,
} from '@/lib/api/types';

/**
 * Read-only aggregates that back the executive dashboard (`/executive`).
 * Every call returns a single resource object — there's no pagination or
 * mutation here, just management-facing rollups computed server-side.
 */
export const adminDashboardApi = {
  get: () =>
    apiClient.get<ApiResource<ExecutiveDashboard>>('/admin/dashboard').then((r) => r.data.data),

  attendanceTrend: (days = 7) =>
    apiClient
      .get<ApiResource<AttendanceTrendPoint[]>>('/admin/dashboard/attendance-trend', {
        params: { days },
      })
      .then((r) => r.data.data),

  departments: () =>
    apiClient
      .get<ApiResource<DashboardDepartmentBreakdown[]>>('/admin/dashboard/departments')
      .then((r) => r.data.data),
};
