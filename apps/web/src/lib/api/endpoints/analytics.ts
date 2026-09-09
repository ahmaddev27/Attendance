import { apiClient } from '@/lib/api/client';

/**
 * Every analytics endpoint accepts the same slice-and-dice params, so we
 * type them once here. Undefined-valued keys are stripped before serialisation
 * by axios's default paramsSerializer — safe to spread partial params.
 */
export type AnalyticsFilters = {
  from?: string; // YYYY-MM-DD
  to?: string;   // YYYY-MM-DD
  department_id?: number;
  team_id?: number;
  employee_id?: number;
};

/** Envelope every /admin/analytics/* endpoint returns. */
export type AnalyticsEnvelope<T> = {
  data: T;
  meta: {
    from: string;
    to: string;
    department_id: number | null;
    team_id: number | null;
    employee_id: number | null;
    cached_at: string;
  };
};

// ---- Attendance ---------------------------------------------------------

export type AttendanceKpis = {
  totals: { present: number; late: number; absent: number; on_leave: number; other: number };
  attendance_rate: number;
  avg_check_in: string | null;   // "HH:MM"
  avg_check_out: string | null;  // "HH:MM"
  working_days: number;
};

/** 7×24 matrix, `matrix[dow][hour]` gives the check-in count. `max` powers the color scale. */
export type AttendanceHeatmap = {
  matrix: number[][];
  max: number;
};

// ---- Leaves -------------------------------------------------------------

export type LeavePatterns = {
  by_month: Array<{ month: string; days: number; requests: number }>;
  by_type: Array<{ name: string; days: number; requests: number }>;
  by_department: Array<{ name: string; days: number; requests: number }>;
  totals: { days: number; requests: number };
};

// ---- Tasks --------------------------------------------------------------

export type TaskPerformance = {
  totals: {
    total: number;
    open: number;
    in_progress: number;
    done: number;
    cancelled: number;
    overdue: number;
  };
  avg_completion_hours: number | null;
  by_status: Array<{ name: string; count: number }>;
  top_assignees: Array<{ name: string; done: number; avg_hours: number | null }>;
};

// ---- Employees ----------------------------------------------------------

export type EmployeeSummary = {
  headcount: {
    total: number;
    active: number;
    inactive: number;
    on_leave: number;
    terminated: number;
  };
  by_department: Array<{ name: string; count: number }>;
  by_team: Array<{ name: string; count: number }>;
  recent_hires: Array<{
    id: number;
    name: string;
    employee_number: number;
    // Backend column is `joining_date` (no `hire_date` in the schema).
    joining_date: string | null;
    department: string | null;
  }>;
};

// ---- Client -------------------------------------------------------------

export const analyticsApi = {
  attendanceKpis: (params: AnalyticsFilters) =>
    apiClient.get<AnalyticsEnvelope<AttendanceKpis>>('/admin/analytics/attendance/kpis', { params }),

  attendanceHeatmap: (params: AnalyticsFilters) =>
    apiClient.get<AnalyticsEnvelope<AttendanceHeatmap>>('/admin/analytics/attendance/heatmap', { params }),

  leavePatterns: (params: AnalyticsFilters) =>
    apiClient.get<AnalyticsEnvelope<LeavePatterns>>('/admin/analytics/leaves/patterns', { params }),

  taskPerformance: (params: AnalyticsFilters) =>
    apiClient.get<AnalyticsEnvelope<TaskPerformance>>('/admin/analytics/tasks/performance', { params }),

  employeeSummary: (params: AnalyticsFilters) =>
    apiClient.get<AnalyticsEnvelope<EmployeeSummary>>('/admin/analytics/employees/summary', { params }),
};
