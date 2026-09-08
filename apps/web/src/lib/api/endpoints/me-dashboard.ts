import { apiClient } from '@/lib/api/client';
import type { ApiResource } from '@/lib/api/types';

export type EmployeeDashboardKpis = {
  today: {
    status: string | null;
    checked_in_at: string | null;
    checked_out_at: string | null;
  };
  month: {
    present: number;
    late: number;
    absent: number;
    leave: number;
    working_days_elapsed: number;
  };
  leaves: {
    pending: number;
    upcoming: {
      start_date: string;
      end_date: string;
      type: string;
    } | null;
    balances: Array<{
      type: string;
      remaining: number;
      entitled: number;
    }>;
  };
  requests: { pending: number };
  tasks: {
    open: number;
    in_progress: number;
    overdue: number;
    completed_this_week: number;
  };
};

export const meDashboardApi = {
  kpis: () =>
    apiClient.get<ApiResource<EmployeeDashboardKpis>>('/me/dashboard/kpis'),
};
