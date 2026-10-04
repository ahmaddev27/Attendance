import { apiClient } from '../client';
import { publicApiClient } from '../public-client';
import type {
  ApiResource,
  Attendance,
  AttendanceStatsSummary,
  AttendanceStatus,
  MonthlyAttendanceSummary,
  PaginatedResponse,
  ScanDeviceInfo,
  ScanPinIssueResult,
  ScanPinResetResult,
  ScanPinSummary,
  ScanResponse,
  ScanStatus,
  UpdateMyScanPinPayload,
  UpdateScanPinEnforcementPayload,
} from '../types';

/** Payload for the admin manual-correction PATCH. Every field is
 *  optional — the server only touches what you send. `check_out_at: null`
 *  explicitly reopens the day. */
export type UpdateAttendancePayload = {
  check_in_at?: string | null;
  check_out_at?: string | null;
  status?: Attendance['status'];
  notes?: string | null;
};

export type AttendanceListParams = {
  page?: number;
  per_page?: number;
  employee_id?: number;
  // Soft Company Scoping: optional admin filter. Normally supplied by
  // the Company Switcher via useScopedCompanyId().
  company_id?: number;
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
  company_id?: number;
  department_id?: number;
  employee_id?: number;
  format?: 'csv' | 'xlsx' | 'pdf';
};

export const attendanceApi = {
  list: (params: AttendanceListParams = {}) =>
    apiClient
      .get<PaginatedResponse<Attendance>>('/attendance', { params })
      .then((r) => r.data),

  /**
   * Date-range aggregates for the admin attendance table's stat tiles.
   * Mounted under /admin so the permission guard stays explicit; honours
   * the same filter set as list().
   */
  stats: (params: Omit<AttendanceListParams, 'page' | 'per_page'> = {}) =>
    apiClient
      .get<ApiResource<AttendanceStatsSummary>>('/admin/attendance/stats', { params })
      .then((r) => r.data.data),

  get: (id: number) =>
    apiClient.get<ApiResource<Attendance>>(`/attendance/${id}`).then((r) => r.data.data),

  /** Admin manual correction — patches any of check_in_at / check_out_at /
   *  status / notes. The server re-runs the hours engine on a timestamp
   *  change so derived minutes stay in sync with the admin edit. */
  update: (id: number, payload: UpdateAttendancePayload) =>
    apiClient
      .patch<ApiResource<Attendance>>(`/attendance/${id}`, payload)
      .then((r) => r.data.data),

  /** Hard-deletes an attendance row. Pair this with a confirm dialog on
   *  the UI — there is no soft-delete column, so this is irreversible. */
  remove: (id: number) =>
    apiClient.delete<void>(`/attendance/${id}`).then(() => undefined),

  /** One-click "undo clock-out" — clears check_out_at + derived minutes
   *  so the day is reopened without the admin having to assemble a PATCH. */
  clearCheckOut: (id: number) =>
    apiClient
      .post<ApiResource<Attendance>>(`/attendance/${id}/clear-check-out`)
      .then((r) => r.data.data),

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
  /** Omitted on PIN-only mode — the server resolves the employee from `pin`. */
  employee_number?: number;
  qr_token: string;
  /** Only sent while the device reports `pin_required`. */
  pin?: string;
  latitude?: number;
  longitude?: number;
};

export type ScanStatusPayload = {
  qr_token: string;
  /** Omitted on PIN-only mode — the server resolves from `pin`. */
  employee_number?: number;
  pin?: string;
};

/**
 * `POST /scan/record` response — the one-tap PIN-only endpoint picks the
 * action for the kiosk, so the FE just renders what the server did.
 * `action: 'done'` arrives with HTTP 409 (the day already closed) and no
 * `attendance` payload.
 */
export type ScanRecordResponse = {
  action: 'check-in' | 'check-out' | 'done';
  message: string;
  attendance?: Attendance;
  check_in_at?: string | null;
  check_out_at?: string | null;
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

  /**
   * PIN-only one-tap: send just the PIN (+ coords) and the server decides
   * whether to check-in or check-out, returning the taken action and the
   * resulting attendance row in a single roundtrip.
   */
  record: (payload: ScanCheckPayload) =>
    publicApiClient.post<ScanRecordResponse>('/scan/record', payload).then((r) => r.data),

  /**
   * Read-only probe: given (qr_token, employee_number), returns the
   * employee's current state so the kiosk can show ONE button
   * (check-in OR check-out) instead of two-and-a-guess. State is
   * derived from today's attendance row on the server.
   */
  status: (payload: ScanStatusPayload) =>
    publicApiClient
      .post<{ data: ScanStatus }>('/scan/status', payload)
      .then((r) => r.data.data),
};

/**
 * Attendance PIN rollout (admin, `manage-users`) plus the employee's own
 * PIN change. `reset` is the only call that ever returns a plaintext PIN.
 */
export const scanPinsApi = {
  summary: () =>
    apiClient
      .get<ApiResource<ScanPinSummary>>('/admin/attendance/scan-pins')
      .then((r) => r.data.data),

  issueMissing: () =>
    apiClient
      .post<ApiResource<ScanPinIssueResult>>('/admin/attendance/scan-pins/issue-missing')
      .then((r) => r.data.data),

  updateEnforcement: (payload: UpdateScanPinEnforcementPayload) =>
    apiClient
      .put<ApiResource<ScanPinSummary>>('/admin/attendance/scan-pins/enforcement', payload)
      .then((r) => r.data.data),

  reset: (employeeId: number) =>
    apiClient
      .post<ApiResource<ScanPinResetResult>>(`/employees/${employeeId}/scan-pin`)
      .then((r) => r.data.data),

  updateMine: (payload: UpdateMyScanPinPayload) => apiClient.put<void>('/me/scan-pin', payload),
};
