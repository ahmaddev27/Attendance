/**
 * Shared API types for the TAQAT web app.
 *
 * Laravel's default API Resource conventions are assumed for envelope
 * shapes: a single resource is wrapped as `{ data: T }`, and a paginated
 * resource collection as `{ data: T[], links, meta }`.
 */

// ---------------------------------------------------------------------------
// Generic envelopes
// ---------------------------------------------------------------------------

export type ApiResource<T> = {
  data: T;
};

export type PaginatedResponse<T> = {
  data: T[];
  links: {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
  };
  meta: {
    current_page: number;
    from: number | null;
    last_page: number;
    path: string;
    per_page: number;
    to: number | null;
    total: number;
  };
};

// ---------------------------------------------------------------------------
// Shared/cross-module types
// ---------------------------------------------------------------------------

/**
 * Minimal employee shape embedded in other resources (attendance rows,
 * monthly summaries, department/team managers, direct managers, …). This is
 * the canonical shape from the Employees module — matches the `full_name`
 * accessor every Employee-related API resource exposes.
 */
export type EmployeeSummary = {
  id: number;
  employee_number: number;
  full_name: string;
  avatar_url: string | null;
};

// ---------------------------------------------------------------------------
// Attendance
// ---------------------------------------------------------------------------

export type AttendanceStatus =
  | 'present'
  | 'late'
  | 'early_leave'
  | 'absent'
  | 'on_leave'
  | 'holiday'
  | 'weekend'
  | 'remote'
  | 'business_mission';

export type Attendance = {
  id: number;
  employee_id: number;
  employee: EmployeeSummary;
  date: string;
  check_in_at: string | null;
  check_out_at: string | null;
  total_minutes: number | null;
  late_minutes: number | null;
  early_leave_minutes: number | null;
  overtime_minutes: number;
  status: AttendanceStatus;
  check_in_ip: string | null;
  check_out_ip: string | null;
};

export type MonthlyAttendanceSummary = {
  employee: EmployeeSummary;
  year: number;
  month: number;
  total_working_days: number;
  present_days: number;
  absent_days: number;
  leave_days: number;
  holiday_days: number;
  weekend_days: number;
  total_minutes: number;
  expected_minutes: number;
  difference_minutes: number;
  overtime_minutes: number;
  late_minutes: number;
  early_leave_minutes: number;
  attendance_percentage: number;
};

// ---------------------------------------------------------------------------
// Attendance devices (QR kiosks)
// ---------------------------------------------------------------------------

export type AttendanceDevice = {
  id: number;
  name: string;
  qr_token: string;
  qr_rotates_every_seconds: number;
  last_token_rotated_at: string | null;
  allowed_lat: number | null;
  allowed_lng: number | null;
  allowed_radius_meters: number | null;
  ip_whitelist: string[] | null;
  is_active: boolean;
};

export type AttendanceDevicePayload = {
  name: string;
  allowed_lat?: number | null;
  allowed_lng?: number | null;
  allowed_radius_meters?: number | null;
  ip_whitelist?: string[] | null;
  is_active: boolean;
};

// ---------------------------------------------------------------------------
// Holidays
// ---------------------------------------------------------------------------

export type HolidayType = 'official' | 'company' | 'special';

export type Holiday = {
  id: number;
  date: string;
  name: string;
  type: HolidayType;
  is_recurring: boolean;
  description: string | null;
};

export type HolidayPayload = {
  date: string;
  name: string;
  type: HolidayType;
  is_recurring: boolean;
  description?: string | null;
};

// ---------------------------------------------------------------------------
// Work schedules
// ---------------------------------------------------------------------------

export type WorkSchedule = {
  id: number;
  name: string;
  timezone: string;
  check_in_time: string | null;
  check_out_time: string | null;
  min_hours_per_day: number;
  grace_late_minutes: number;
  grace_early_leave_minutes: number;
  workdays: number[];
  is_flexible: boolean;
  is_active: boolean;
  /**
   * Not part of the spec's base shape, but useful for the schedules card
   * grid ("employees count"). Optional because the backend may not send it
   * yet — the UI falls back to a dash when absent.
   */
  employees_count?: number;
};

export type WorkSchedulePayload = {
  name: string;
  timezone?: string;
  check_in_time?: string | null;
  check_out_time?: string | null;
  min_hours_per_day: number;
  grace_late_minutes: number;
  grace_early_leave_minutes: number;
  workdays: number[];
  is_flexible: boolean;
  is_active: boolean;
};

// ---------------------------------------------------------------------------
// Public kiosk scan flow
// ---------------------------------------------------------------------------

export type ScanDeviceInfo = {
  device_name: string;
  server_time: string;
  qr_token: string;
};

export type ScanResponse = {
  attendance: Attendance;
  /** Arabic, user-facing */
  message: string;
};

// ---------------------------------------------------------------------------
// Employees
// ---------------------------------------------------------------------------

export type EmploymentType = 'full_time' | 'part_time' | 'contractor' | 'intern';
export type Gender = 'male' | 'female';
export type EmployeeStatus = 'active' | 'inactive' | 'on_leave' | 'terminated';

export type Employee = {
  id: number;
  employee_number: number;
  first_name: string;
  last_name: string;
  full_name: string;
  email: string | null;
  phone: string | null;
  employment_type: EmploymentType;
  gender: Gender | null;
  status: EmployeeStatus;
  joining_date: string;
  birth_date: string | null;
  avatar_url: string | null;
  position: Position | null;
  department: Department | null;
  team: Team | null;
  direct_manager: EmployeeSummary | null;
};

export type EmployeeInput = {
  first_name: string;
  last_name: string;
  email?: string | null;
  phone?: string | null;
  department_id: number | null;
  team_id?: number | null;
  position_id?: number | null;
  employment_type: EmploymentType;
  joining_date: string;
  gender?: Gender | null;
  direct_manager_id?: number | null;
};

export type EmployeeListParams = {
  page?: number;
  per_page?: number;
  search?: string;
  department_id?: number;
  team_id?: number;
  position_id?: number;
  status?: EmployeeStatus;
  employment_type?: EmploymentType;
};

// ---------------------------------------------------------------------------
// Organization structure — departments, teams, positions
// ---------------------------------------------------------------------------

export type Department = {
  id: number;
  name: string;
  code: string | null;
  parent_id: number | null;
  parent?: Department | null;
  description?: string | null;
  manager: EmployeeSummary | null;
  is_active: boolean;
  employees_count?: number;
  teams_count?: number;
};

export type DepartmentInput = {
  name: string;
  code?: string | null;
  parent_id?: number | null;
  description?: string | null;
  is_active?: boolean;
};

export type DepartmentListParams = {
  page?: number;
  per_page?: number;
  search?: string;
  is_active?: boolean;
};

export type Team = {
  id: number;
  name: string;
  department_id: number;
  department?: Department;
  description?: string | null;
  leader: EmployeeSummary | null;
  is_active: boolean;
  employees_count?: number;
};

export type TeamInput = {
  name: string;
  department_id: number;
  description?: string | null;
  is_active?: boolean;
};

export type TeamListParams = {
  page?: number;
  per_page?: number;
  search?: string;
  department_id?: number;
  is_active?: boolean;
};

export type Position = {
  id: number;
  title: string;
  code: string | null;
  department_id: number | null;
  department?: Department | null;
  is_active: boolean;
  employees_count?: number;
};

export type PositionInput = {
  title: string;
  code?: string | null;
  department_id?: number | null;
  is_active?: boolean;
};

export type PositionListParams = {
  page?: number;
  per_page?: number;
  search?: string;
  department_id?: number;
  is_active?: boolean;
};

// ---------------------------------------------------------------------------
// Leaves (M4)
// ---------------------------------------------------------------------------

export type LeaveStatus = 'draft' | 'pending' | 'approved' | 'rejected' | 'cancelled';

export type LeaveType = {
  id: number;
  name: string;
  code: string;
  is_paid: boolean;
  is_balance_based: boolean;
  default_annual_entitlement: number;
  allow_negative_balance: boolean;
  requires_attachment: boolean;
  max_consecutive_days: number | null;
  min_notice_days: number;
  color: string;
  is_active: boolean;
  sort_order: number;
};

export type LeaveTypePayload = Omit<LeaveType, 'id'>;

export type LeaveBalance = {
  id: number;
  employee_id: number;
  leave_type_id: number;
  leave_type?: LeaveType;
  year: number;
  entitlement: number;
  used: number;
  pending: number;
  carry_over_from_previous: number;
  remaining: number;
  available: number;
};

export type LeaveRequest = {
  id: number;
  employee_id: number;
  employee: EmployeeSummary;
  leave_type_id: number;
  leave_type: LeaveType;
  start_date: string;
  end_date: string;
  days: number;
  reason: string | null;
  attachment_url: string | null;
  status: LeaveStatus;
  reviewed_by: number | null;
  reviewer: { id: number; name: string } | null;
  reviewed_at: string | null;
  rejection_reason: string | null;
  created_at: string;
};

export type LeaveRequestPayload = {
  employee_id?: number;
  leave_type_id: number;
  start_date: string;
  end_date: string;
  reason?: string;
};

export type LeaveRequestListParams = {
  page?: number;
  per_page?: number;
  employee_id?: number;
  leave_type_id?: number;
  status?: LeaveStatus | 'all';
  from?: string;
  to?: string;
};
