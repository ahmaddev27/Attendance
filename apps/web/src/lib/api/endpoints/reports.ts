import { apiClient } from '@/lib/api/client';

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
   * temporary <a download>. Kept in the api-client layer so the Bearer
   * token is attached the same way as every other request.
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
};
