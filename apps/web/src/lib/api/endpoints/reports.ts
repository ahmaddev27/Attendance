import { apiClient } from '@/lib/api/client';
import type { LeaveStatus, RequestStatus } from '@/lib/api/types';

export type AttendanceReportRow = {
  employee_id: number;
  employee_number: number;
  full_name: string;
  department: string | null;
  present_days: number;
  late_days: number;
  absent_days: number;
  leave_days: number;
  total_minutes: number;
  overtime_minutes: number;
  late_minutes: number;
};

export type AttendanceMonthlyParams = {
  year: number;
  month: number;
  department_id?: number;
};

/** A leave belongs to `year` when any of its days fall inside it; the API defaults to the current year. */
export type LeaveReportExportParams = {
  year?: number;
  leave_type_id?: number;
  status?: LeaveStatus;
  department_id?: number;
};

/** `from`/`to` are Y-m-d and bound the submission date inclusively. */
export type RequestReportExportParams = {
  request_type_id?: number;
  status?: RequestStatus;
  from?: string;
  to?: string;
  department_id?: number;
};

/**
 * File-format response helpers share the same Blob transport — only the
 * `format` query param and the eventual Content-Type differ. Kept as
 * separate helpers so callers get an obvious shape (and a stable
 * queryKey slice) rather than passing the format around.
 */
export const reportsApi = {
  attendanceMonthly: (params: AttendanceMonthlyParams) =>
    apiClient.get<{ data: AttendanceReportRow[] }>('/admin/reports/attendance/monthly', {
      params,
    }),
  /**
   * Downloads the CSV directly — returns a Blob the caller pipes to a
   * temporary <a download>. Kept on apiClient so the session cookie +
   * X-XSRF-TOKEN pair rides on the request the same way as every other
   * authenticated call.
   */
  attendanceMonthlyCsv: (params: AttendanceMonthlyParams) =>
    apiClient.get<Blob>('/admin/reports/attendance/monthly', {
      params: { ...params, format: 'csv' },
      responseType: 'blob',
    }),
  attendanceMonthlyXlsx: (params: AttendanceMonthlyParams) =>
    apiClient.get<Blob>('/admin/reports/attendance/monthly', {
      params: { ...params, format: 'xlsx' },
      responseType: 'blob',
    }),
  attendanceMonthlyPdf: (params: AttendanceMonthlyParams) =>
    apiClient.get<Blob>('/admin/reports/attendance/monthly', {
      params: { ...params, format: 'pdf' },
      responseType: 'blob',
    }),
  leavesCsv: (params: LeaveReportExportParams) =>
    apiClient.get<Blob>('/admin/reports/leaves/export', { params, responseType: 'blob' }),
  requestsCsv: (params: RequestReportExportParams) =>
    apiClient.get<Blob>('/admin/reports/requests/export', { params, responseType: 'blob' }),
};
