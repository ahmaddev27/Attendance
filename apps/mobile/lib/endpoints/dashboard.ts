/**
 * Employee dashboard fetch helpers. Kept tiny — one shape per endpoint,
 * one method per call. The `api` instance in ../api already carries the
 * bearer token, so screens just `useQuery(['me-dashboard'], fetchMyDashboard)`
 * and forget about auth.
 */

import { api } from '../api';

export interface MyDashboardKpis {
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
    upcoming: { start_date: string; end_date: string; type: string } | null;
    balances: Array<{ type: string; remaining: number; entitled: number }>;
  };
  requests: { pending: number };
  tasks: {
    open: number;
    in_progress: number;
    overdue: number;
    completed_this_week: number;
  };
}

export async function fetchMyDashboard(): Promise<MyDashboardKpis> {
  const { data } = await api.get<{ data: MyDashboardKpis }>('/me/dashboard/kpis');
  return data.data;
}
