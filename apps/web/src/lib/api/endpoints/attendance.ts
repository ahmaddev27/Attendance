import { apiClient } from '../client';
import { publicApiClient } from '../public-client';
import type {
  ApiResource,
  Attendance,
  AttendanceStatus,
  MonthlyAttendanceSummary,
  PaginatedResponse,
  ScanDeviceInfo,
  ScanResponse,
} from '../types';

export type AttendanceListParams = {
  page?: number;
  per_page?: number;
  employee_id?: number;
  date_from?: string;
  date_to?: string;
  status?: AttendanceStatus;
};

/**
 * `/admin/reports/attendance/monthly` — the shared monthly-report endpoint
 * that streams CSV/xlsx/pdf. Kept separate from AttendanceListParams
 * because the report is aggregated by employee/month, not a filtered
 * copy of the raw attendance log.
 */
export type AttendanceExportParams = {
  year: number;
  month: number;
  department_id?: number;
  format?: 'csv' | 'xlsx' | 'pdf';
};

export const attendanceApi = {
  list: (params: AttendanceListParams = {}) =>
    apiClient
      .get<PaginatedResponse<Attendance>>('/attendance', { params })
      .then((r) => r.data),

  get: (id: number) =>
    apiClient.get<ApiResource<Attendance>>(`/attendance/${id}`).then((r) => r.data.data),

  monthlySummary: (employeeId: number, year: number, month: number) =>
    apiClient
      .get<ApiResource<MonthlyAttendanceSummary>>(
        `/attendance/employee/${employeeId}/monthly/${year}/${month}`
      )
      .then((r) => r.data.data),

  /**
   * Downloads the monthly attendance report as a CSV blob. Delegates to
   * `/admin/reports/attendance/monthly?format=csv`, which is the only
   * endpoint that actually knows how to stream a file — the raw
   * `/attendance` list has no format branch.
   */
  exportCsv: (params: AttendanceExportParams) =>
    apiClient
      .get('/admin/reports/attendance/monthly', {
        params: { ...params, format: 'csv' },
        responseType: 'blob',
      })
      .then((r) => r.data as Blob),
};

export type ScanCheckPayload = {
  employee_number: number;
  qr_token: string;
  latitude?: number;
  longitude?: number;
};

/**
 * Public, unauthenticated calls made from the kiosk scan page. Uses
 * publicApiClient (no auth header, no 401 -> /login redirect).
 */
export const scanApi = {
  deviceInfo: (qrToken: string) =>
    publicApiClient
      .get<ApiResource<ScanDeviceInfo> | ScanDeviceInfo>(`/scan/device/${qrToken}`)
      .then((r) => ('data' in r.data ? r.data.data : r.data)),

  checkIn: (payload: ScanCheckPayload) =>
    publicApiClient.post<ScanResponse>('/scan/check-in', payload).then((r) => r.data),

  checkOut: (payload: ScanCheckPayload) =>
    publicApiClient.post<ScanResponse>('/scan/check-out', payload).then((r) => r.data),
};
