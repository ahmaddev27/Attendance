import { apiClient } from '@/lib/api/client';
import type { ApiResource } from '@/lib/api/types';

export type EmployeeDashboardKpis = {
  today: {
    status: string | null;
    checked_in_at: string | null;
    checked_out_at: string | null;
    // ISO datetime of the still-open session's check-in, or null when
    // the session is closed / no attendance row exists yet. The home
    // banner keys off this single field so it doesn't have to
    // reconcile the status enum with checked_in_at / checked_out_at.
    open_session_since: string | null;
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
