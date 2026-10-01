import { apiClient } from '@/lib/api/client';
import type { ApiResource } from '@/lib/api/types';

export type AdminDashboardKpis = {
  employees: {
    total: number;
    active: number;
    inactive: number;
  };
  today: {
    date: string;
    present: number;
    late: number;
    absent: number;
    on_leave: number;
  };
  pending: {
    leaves: number;
    requests: number;
    total: number;
  };
  tasks: {
    open: number;
    in_progress: number;
    overdue: number;
  };
};

export type AdminDashboardKpisParams = {
  // Soft Company Scoping — mirrors the admin header switcher's current
  // selection. Omitted (or undefined) means "all companies", matching the
  // server-side behavior added in c856558.
  company_id?: number;
};

export const adminDashboardApi = {
  kpis: (params?: AdminDashboardKpisParams) =>
    apiClient.get<ApiResource<AdminDashboardKpis>>('/admin/dashboard/kpis', {
      params,
    }),
};
