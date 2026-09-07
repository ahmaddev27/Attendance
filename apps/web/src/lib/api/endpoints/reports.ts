import { apiClient } from '@/lib/api/client';
import type {
  ApiResource,
  AttendanceReportParams,
  DepartmentPerformanceParams,
  DepartmentPerformanceRow,
  LeaveReportParams,
  MonthlyEmployeeReport,
  PaginatedResponse,
  ReportRow,
} from '@/lib/api/types';

/**
 * Downloads a report endpoint as a CSV blob and triggers a browser save —
 * shared by every report page's "Export CSV" button so the blob-handling
 * and link-click boilerplate only lives in one place.
 */
export async function downloadCsv(
  url: string,
  params: Record<string, unknown>,
  filename: string
): Promise<void> {
  const response = await apiClient.get(url, {
    params: { ...params, format: 'csv' },
    responseType: 'blob',
  });
  const blob = new Blob([response.data], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = filename;
  link.click();
  URL.revokeObjectURL(link.href);
}

export const reportsApi = {
  attendance: (params: AttendanceReportParams = {}) =>
    apiClient
      .get<PaginatedResponse<ReportRow>>('/reports/attendance', { params })
      .then((r) => r.data),

  exportAttendanceCsv: (params: AttendanceReportParams = {}, filename = 'attendance-report.csv') =>
    downloadCsv('/reports/attendance', { ...params, page: undefined, per_page: undefined }, filename),

  leaves: (params: LeaveReportParams = {}) =>
    apiClient.get<PaginatedResponse<ReportRow>>('/reports/leaves', { params }).then((r) => r.data),

  exportLeavesCsv: (params: LeaveReportParams = {}, filename = 'leaves-report.csv') =>
    downloadCsv('/reports/leaves', { ...params, page: undefined, per_page: undefined }, filename),

  monthlyEmployee: (employeeId: number, year: number, month: number) =>
    apiClient
      .get<ApiResource<MonthlyEmployeeReport>>(
        `/reports/monthly-employee/${employeeId}/${year}/${month}`
      )
      .then((r) => r.data.data),

  exportMonthlyEmployeeCsv: (employeeId: number, year: number, month: number, filename: string) =>
    downloadCsv(`/reports/monthly-employee/${employeeId}/${year}/${month}`, {}, filename),

  departmentPerformance: (params: DepartmentPerformanceParams = {}) =>
    apiClient
      .get<ApiResource<DepartmentPerformanceRow[]>>('/reports/department-performance', { params })
      .then((r) => r.data.data),

  exportDepartmentPerformanceCsv: (
    params: DepartmentPerformanceParams = {},
    filename = 'department-performance.csv'
  ) => downloadCsv('/reports/department-performance', params, filename),
};
