import { apiClient } from '@/lib/api/client';

export type EmployeeTasksReportParams = { year: number; month?: number };

export type EmployeeTasksReportTask = {
  id: number;
  title: string;
  status: string | null;
  priority: string | null;
  assigned_role: 'assignee' | 'creator';
  created_at: string | null;
  due_date: string | null;
  completed_at: string | null;
  is_overdue: boolean;
};

export type EmployeeTasksReport = {
  employee: { id: number; employee_number: number; full_name: string; department: string | null };
  year: number;
  month: number | null;
  totals: {
    total: number;
    pending: number;
    in_progress: number;
    completed: number;
    cancelled: number;
    overdue: number;
  };
  by_month: { month: number; label: string; assigned: number; completed: number; overdue: number }[];
  by_status: { status_id: number; name: string; count: number; color: string | null }[];
  by_priority: { priority_id: number; name: string; count: number }[];
  tasks: EmployeeTasksReportTask[];
};

export const employeeTasksReportApi = {
  get: (employeeId: number, params: EmployeeTasksReportParams) =>
    apiClient.get<EmployeeTasksReport>(`/admin/employees/${employeeId}/tasks-report`, { params }),
  /** Blob download through apiClient so the session cookie and XSRF pairing travel with it. */
  pdf: (employeeId: number, params: EmployeeTasksReportParams) =>
    apiClient.get<Blob>(`/admin/employees/${employeeId}/tasks-report/pdf`, {
      params,
      responseType: 'blob',
    }),
};
