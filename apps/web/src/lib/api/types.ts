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
 * Canonical shape returned by the backend `UserResource` — a login user
 * plus (when the user is linked to an Employee row) the employee id used
 * by workflow-step `approver_ref` values. Kept in sync with the auth
 * store's own `User` type; both include `employee_id` so the frontend
 * can compare a step's `approver_ref` against the current user without
 * having to join through a separate employees lookup.
 */
export type User = {
  id: number;
  employee_id: number | null;
  employee_number: number;
  name: string;
  email: string;
  roles: string[];
  permissions: string[];
};

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

/**
 * Trimmed embed the backend uses for cheap manager/creator lookups where
 * only the id and display name are needed (e.g. `direct_manager` on the
 * Employee resource — no employee_number or avatar_url sent). Kept
 * separate so consumers don't index into fields that will be undefined.
 */
export type EmployeeMini = Pick<EmployeeSummary, 'id' | 'full_name'>;

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
  /** 0 = QR never rotates (printed-poster mode). >0 = rotation window in seconds. */
  qr_rotates_every_seconds: number;
  last_token_rotated_at: string | null;
  allowed_lat: number | null;
  allowed_lng: number | null;
  allowed_radius_meters: number | null;
  ip_whitelist: string[] | null;
  /** When true, FraudGuard rejects scans outside allowed_lat/lng/radius. */
  enforce_geo: boolean;
  /** When true, FraudGuard rejects scans from IPs not in ip_whitelist. */
  enforce_ip: boolean;
  is_active: boolean;
};

export type AttendanceDevicePayload = {
  name: string;
  qr_rotates_every_seconds?: number | null;
  allowed_lat?: number | null;
  allowed_lng?: number | null;
  allowed_radius_meters?: number | null;
  ip_whitelist?: string[] | null;
  enforce_geo?: boolean;
  enforce_ip?: boolean;
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
  /**
   * Whether the device requires a location coordinate on every scan.
   * The kiosk uses this to decide whether to invoke the browser's
   * `navigator.geolocation` prompt — asking every time and discarding
   * the answer when the device doesn't enforce geo is a noisy UX.
   */
  enforce_geo?: boolean;
  enforce_ip?: boolean;
};

export type ScanResponse = {
  attendance: Attendance;
  /** Arabic, user-facing */
  message: string;
};

export type ScanStatus = {
  /**
   * `not_checked_in`: today's row is missing check_in_at → kiosk shows the
   * check-in button. `checked_in`: check_in_at set but no check_out yet →
   * kiosk shows check-out. `checked_out`: both stamped → the daily cycle
   * is done, kiosk shows a "see you tomorrow" state.
   */
  state: 'not_checked_in' | 'checked_in' | 'checked_out';
  // Deliberately no `full_name` — /scan/status is unauthenticated and
  // returning the name would let a QR-holder enumerate the directory by
  // walking employee_number. The greeting name comes back only after a
  // successful check-in POST (see ScanResponse.attendance.employee).
  employee: { id: number; employee_number: number };
  check_in_at: string | null;
  check_out_at: string | null;
  device_name: string;
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
  work_schedule_id: number | null;
  work_schedule: { id: number; name: string } | null;
  direct_manager: EmployeeMini | null;
  // Only ever set on the create response — the plaintext password the
  // server just minted for the new user. It's also enqueued as a welcome
  // SMS (see EmployeeService::sendWelcomeSms on the API), so this is a
  // fallback for out-of-band delivery when there's no phone on file.
  generated_password?: string;
};

export type EmployeeInput = {
  first_name: string;
  last_name: string;
  email?: string | null;
  phone?: string | null;
  department_id: number | null;
  team_id?: number | null;
  position_id?: number | null;
  work_schedule_id?: number | null;
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

/**
 * `/me/profile` — the linked user + the employee row (or null when the
 * signed-in account is not bound to an employees row, e.g. bootstrap
 * super-admin).
 */
export type MyProfile = {
  user: User;
  employee: Employee | null;
};

/** `PATCH /me/profile` — the ONLY field the employee is allowed to change. */
export type MyProfileUpdatePayload = {
  phone?: string | null;
};

/** `POST /me/password` — Laravel's `confirmed` rule expects the `_confirmation` suffix. */
export type UpdateMyPasswordPayload = {
  current_password: string;
  password: string;
  password_confirmation: string;
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
  attachment_path?: string;
};

export type LeaveRequestListParams = {
  page?: number;
  per_page?: number;
  employee_id?: number;
  leave_type_id?: number;
  status?: LeaveStatus;
  start_date?: string;
  end_date?: string;
};

// ---------------------------------------------------------------------------
// Tasks (M6)
// ---------------------------------------------------------------------------

export type TaskAction =
  | 'created'
  | 'assigned'
  | 'unassigned'
  | 'status_changed'
  | 'priority_changed'
  | 'commented'
  | 'attached_file'
  | 'completed'
  | 'deleted'
  | 'restored';

export type TaskStatus = {
  id: number;
  name: string;
  code: string;
  color: string;
  sort_order: number;
  is_done_state: boolean;
  is_cancelled_state: boolean;
};

export type TaskStatusPayload = Omit<TaskStatus, 'id'>;

export type TaskPriority = {
  id: number;
  name: string;
  code: string;
  color: string;
  sort_order: number;
};

export type TaskPriorityPayload = Omit<TaskPriority, 'id'>;

export type TaskTag = {
  id: number;
  name: string;
  color: string;
};

export type TaskTagPayload = Omit<TaskTag, 'id'>;

export type TaskAttachment = {
  id: number;
  file_name: string;
  mime_type: string;
  size: number;
  uploaded_by: EmployeeSummary | null;
  download_url: string;
  created_at: string;
};

export type TaskComment = {
  id: number;
  user: { id: number; name: string; avatar_url: string | null };
  parent_id: number | null;
  body: string;
  mentions: number[];
  edited_at: string | null;
  created_at: string;
  can_edit: boolean;
  can_delete: boolean;
};

export type TaskCommentPayload = {
  body: string;
  mentions?: number[];
  parent_id?: number | null;
};

export type TaskHistoryEntry = {
  id: number;
  action: TaskAction;
  user: { id: number; name: string };
  old_value: unknown;
  new_value: unknown;
  created_at: string;
};

/** Business object a task is about — mirrors App\Shared\Enums\TaskEntityType. */
export type TaskEntityType = 'lead' | 'client' | 'recruitment_case' | 'job_requirement';

export type TaskEntity = {
  type: TaskEntityType;
  id: number;
  /** "J-2026-0045 — Senior Backend Dev"; null when the entity row is gone. */
  label: string | null;
  /** Frontend route to the entity, e.g. "/recruitment/jobs/45". */
  link: string;
};

export type Task = {
  id: number;
  parent_task_id: number | null;
  title: string;
  description: string | null;
  status: TaskStatus;
  priority: TaskPriority;
  creator: EmployeeSummary;
  assignee: EmployeeSummary | null;
  tags: TaskTag[];
  entity: TaskEntity | null;
  estimated_hours: number | null;
  actual_hours: number | null;
  progress_percent: number;
  start_date: string | null;
  due_date: string | null;
  completed_at: string | null;
  comments_count: number;
  attachments_count: number;
  subtasks_count: number;
  created_at: string;
};

export type TaskDetail = Task & {
  subtasks: Task[];
  comments: TaskComment[];
  history: TaskHistoryEntry[];
  attachments: TaskAttachment[];
};

export type TaskPayload = {
  title: string;
  description?: string;
  parent_task_id?: number | null;
  status_id: number;
  priority_id: number;
  assigned_to?: number | null;
  estimated_hours?: number | null;
  progress_percent?: number;
  start_date?: string | null;
  due_date?: string | null;
  tag_ids?: number[];
};

export type TaskListParams = {
  page?: number;
  per_page?: number;
  assigned_to?: number;
  created_by?: number;
  status_id?: number;
  priority_id?: number;
  tag_id?: number;
  parent_task_id?: number | null;
  due_date_from?: string;
  due_date_to?: string;
  search?: string;
};

/**
 * `/tasks/kanban` shape — keyed by status.code, each entry carries the
 * TaskStatus object, the (capped) tasks in that column, and the
 * unlimited total count. The backend (TaskService::kanban) caps every
 * column at 200 rows to keep the payload bounded; `count_total` is the
 * true row count so the board can render a "+ N more" hint whenever it
 * exceeds `tasks.length`.
 */
export type KanbanBoardEntry = { status: TaskStatus; tasks: Task[]; count_total: number };
export type KanbanBoard = Record<string, KanbanBoardEntry>; // keyed by status.code

// ---------------------------------------------------------------------------
// Workflow engine + request builder (M5)
// ---------------------------------------------------------------------------

export type RequestStatus =
  | 'draft'
  | 'submitted'
  | 'pending'
  | 'approved'
  | 'rejected'
  | 'returned'
  | 'cancelled'
  | 'completed';

export type ApproverType =
  | 'direct_manager'
  | 'department_manager'
  | 'specific_employee'
  | 'specific_role'
  | 'form_field';

export type ApprovalAction = 'approved' | 'rejected' | 'returned' | 'forwarded';

export type FormFieldType = 'text' | 'textarea' | 'number' | 'date' | 'select' | 'checkbox' | 'file' | 'employee';

export type FormField = {
  key: string;
  label: string;
  type: FormFieldType;
  required: boolean;
  options?: string[];
  min?: number;
  max?: number;
  placeholder?: string;
};

export type Workflow = {
  id: number;
  name: string;
  description: string | null;
  is_active: boolean;
  steps: WorkflowStep[];
};

export type WorkflowPayload = {
  name: string;
  description?: string | null;
  is_active: boolean;
};

export type WorkflowStep = {
  id: number;
  workflow_id: number;
  step_order: number;
  name: string;
  approver_type: ApproverType;
  approver_ref: string | null;
  can_reject: boolean;
  can_return: boolean;
  can_forward: boolean;
  sla_hours: number | null;
};

export type WorkflowStepPayload = {
  name: string;
  approver_type: ApproverType;
  approver_ref?: string | null;
  can_reject: boolean;
  can_return: boolean;
  can_forward: boolean;
  sla_hours?: number | null;
};

export type WorkflowStepReorderPayload = {
  steps: { id: number; step_order: number }[];
};

export type RequestType = {
  id: number;
  name: string;
  code: string;
  description: string | null;
  icon: string | null;
  color: string;
  workflow_id: number;
  workflow?: Workflow;
  form_schema: FormField[];
  is_active: boolean;
  sort_order: number;
};

export type RequestTypePayload = {
  name: string;
  code: string;
  description?: string | null;
  icon?: string | null;
  color: string;
  workflow_id: number;
  form_schema: FormField[];
  is_active: boolean;
  sort_order: number;
};

export type RequestSummary = {
  id: number;
  request_number: string;
  request_type: { id: number; name: string; color: string; icon: string | null };
  employee: EmployeeSummary;
  status: RequestStatus;
  submitted_at: string | null;
  completed_at: string | null;
  /**
   * Not part of the spec's base shape, but the admin/employee request list
   * tables both need a "current step" column — mirrors the
   * `WorkSchedule.employees_count` precedent above: optional because the
   * list endpoint may omit it, in which case the UI falls back to a dash.
   */
  current_step?: { id: number; name: string; step_order: number } | null;
};

export type RequestApproval = {
  id: number;
  workflow_step: { id: number; name: string; step_order: number };
  approver: EmployeeSummary;
  action: ApprovalAction;
  comment: string | null;
  forwarded_to: EmployeeSummary | null;
  decided_at: string;
};

export type RequestDetail = RequestSummary & {
  form_data: Record<string, unknown>;
  current_step: WorkflowStep | null;
  approvals: RequestApproval[];
};

/** Admin "all requests" list filters — mirrors the leave-requests list pattern. */
export type RequestListParams = {
  page?: number;
  per_page?: number;
  search?: string;
  status?: RequestStatus;
  request_type_id?: number;
  employee_id?: number;
  from?: string;
  to?: string;
};

/** Employee self-service submission payload — `request_type_id` + the dynamic form answers. */
export type SubmitRequestPayload = {
  request_type_id: number;
  form_data: Record<string, unknown>;
};

/** Body for approve/reject/return — a free-text comment, required for reject/return. */
export type RequestActionPayload = {
  comment?: string;
};

export type RequestForwardPayload = {
  forwarded_to_id: number;
  comment?: string;
};

// ---------------------------------------------------------------------------
// Recruitment (M8) — Leads, Clients, Cases, Jobs, Pipelines, Dashboard
// ---------------------------------------------------------------------------

/**
 * Trimmed User shape used by every Recruitment resource for owner /
 * account-manager / reviewer projections. Backend ships id + name +
 * email under `owner` / `account_manager` / etc. — keep as a shared
 * alias so consumers don't reinvent it per module.
 */
export type UserMini = {
  id: number;
  name: string;
  email?: string;
};

// -- enums -------------------------------------------------------------------

export type LeadStatus =
  | 'new'
  | 'contacted'
  | 'meeting_scheduled'
  | 'meeting_completed'
  | 'qualified'
  | 'proposal_sent'
  | 'negotiation'
  | 'converted'
  | 'lost'
  | 'on_hold';

/**
 * Code from the admin-editable `lead_sources` picker list. Deliberately
 * not a closed union: admins add and remove sources from /settings.
 */
export type LeadSource = string;

export type ClientStatus = 'active' | 'on_hold' | 'inactive' | 'terminated';

export type RecruitmentCaseStatus = 'draft' | 'active' | 'on_hold' | 'completed' | 'cancelled';

export type JobRequirementStatus = 'draft' | 'active' | 'on_hold' | 'filled' | 'cancelled';

export type CasePriority = 'low' | 'normal' | 'high' | 'urgent';

export type JobEmploymentType = 'full_time' | 'part_time' | 'contract' | 'intern' | 'temporary';

export type WorkMode = 'remote' | 'onsite' | 'hybrid';

export type LeadActivityType = 'call' | 'meeting' | 'email' | 'note';

export type StageOwnerRuleType =
  | 'role'
  | 'specific'
  | 'case_owner'
  | 'job_owner'
  | 'previous_stage_owner'
  | 'none';

// -- Lead --------------------------------------------------------------------

export type Lead = {
  id: number;
  lead_number: string;

  company_name: string;
  company_website: string | null;
  industry: string | null;
  company_size: string | null;
  country: string | null;
  city: string | null;

  contact_person: string | null;
  contact_position: string | null;
  contact_email: string | null;
  contact_phone: string | null;
  linkedin_url: string | null;

  source: LeadSource;
  status: LeadStatus;

  owner_id: number | null;
  owner?: UserMini | null;

  expected_hiring_volume: number | null;
  notes: string | null;

  last_contact_at: string | null;
  next_followup_at: string | null;

  converted_at: string | null;
  converted_client_id: number | null;
  converted_client?: { id: number; client_number: string; company_name: string } | null;

  lost_at: string | null;
  lost_reason: string | null;

  created_at: string;
  updated_at: string;
};

export type LeadSummary = {
  id: number;
  lead_number: string;
  company_name: string;
  country: string | null;
  status: LeadStatus;
  source: LeadSource;
  owner_id: number | null;
  expected_hiring_volume: number | null;
  next_followup_at: string | null;
};

export type LeadPayload = {
  company_name: string;
  company_website?: string | null;
  industry?: string | null;
  company_size?: string | null;
  country?: string | null;
  city?: string | null;
  contact_person?: string | null;
  contact_position?: string | null;
  contact_email?: string | null;
  contact_phone?: string | null;
  linkedin_url?: string | null;
  source: LeadSource;
  status?: LeadStatus;
  owner_id?: number;
  expected_hiring_volume?: number | null;
  notes?: string | null;
  last_contact_at?: string | null;
  next_followup_at?: string | null;
  lost_reason?: string | null;
  force?: boolean;
};

export type LeadListParams = {
  page?: number;
  per_page?: number;
  owner_id?: number;
  status?: LeadStatus;
  source?: LeadSource;
  country?: string;
  industry?: string;
  search?: string;
  followup_from?: string;
  followup_to?: string;
  active_only?: boolean;
};

/** GET /leads/kanban → `{ data: { [status]: LeadSummary[] } }`. */
export type LeadKanbanData = Partial<Record<LeadStatus, LeadSummary[]>>;

export type LeadActivity = {
  id: number;
  lead_id: number;
  type: LeadActivityType;
  subject: string | null;
  body: string | null;
  occurred_at: string | null;
  metadata: Record<string, unknown> | null;
  user_id: number | null;
  user?: { id: number; name: string } | null;
  created_at: string;
};

export type LeadActivityPayload = {
  type: LeadActivityType;
  subject?: string | null;
  body?: string | null;
  occurred_at: string;
};

// -- Client ------------------------------------------------------------------

export type ClientContact = {
  id: number;
  client_id: number;
  full_name: string;
  position: string | null;
  email: string | null;
  phone: string | null;
  linkedin_url: string | null;
  is_primary: boolean;
  notes: string | null;
  created_at: string;
};

export type ClientContactPayload = {
  full_name: string;
  position?: string | null;
  email?: string | null;
  phone?: string | null;
  linkedin_url?: string | null;
  is_primary?: boolean;
  notes?: string | null;
};

export type Client = {
  id: number;
  client_number: string;
  company_name: string;
  company_website: string | null;
  industry: string | null;
  company_size: string | null;
  country: string | null;
  city: string | null;
  address: string | null;
  tax_number: string | null;
  payment_terms: string | null;
  payment_terms_notes: string | null;
  status: ClientStatus;
  account_manager_id: number | null;
  account_manager?: UserMini | null;
  source_lead_id: number | null;
  source_lead?: { id: number; lead_number: string; company_name: string } | null;
  primary_contact?: ClientContact | null;
  notes: string | null;
  created_at: string;
  updated_at: string;
};

export type ClientSummary = {
  id: number;
  client_number: string;
  company_name: string;
  country: string | null;
  status: ClientStatus;
};

export type ClientPayload = {
  company_name: string;
  company_website?: string | null;
  industry?: string | null;
  company_size?: string | null;
  country?: string | null;
  city?: string | null;
  address?: string | null;
  tax_number?: string | null;
  payment_terms?: string | null;
  payment_terms_notes?: string | null;
  status?: ClientStatus;
  account_manager_id?: number | null;
  notes?: string | null;
  force?: boolean;
};

export type ClientListParams = {
  page?: number;
  per_page?: number;
  search?: string;
  status?: ClientStatus;
  account_manager_id?: number;
  country?: string;
  industry?: string;
};

export type ClientProfile = Client & {
  contacts?: ClientContact[];
  cases_summary?: {
    total: number;
    open_count: number;
    latest: Array<{
      id: number;
      case_number: string;
      title: string;
      status: RecruitmentCaseStatus;
      created_at: string;
    }>;
  };
};

// -- Recruitment Case --------------------------------------------------------

export type RecruitmentCase = {
  id: number;
  case_number: string;
  client_id: number;
  client?: ClientSummary | null;
  source_lead_id: number | null;
  source_lead?: LeadSummary | null;
  title: string;
  description: string | null;
  owner_id: number;
  owner?: UserMini | null;
  priority: CasePriority;
  status: RecruitmentCaseStatus;
  target_hires: number | null;
  started_at: string | null;
  deadline: string | null;
  completed_at: string | null;
  created_at: string;
  updated_at: string;
};

export type RecruitmentCasePayload = {
  client_id: number;
  source_lead_id?: number | null;
  title: string;
  description?: string | null;
  owner_id: number;
  priority?: CasePriority;
  status?: RecruitmentCaseStatus;
  target_hires?: number | null;
  started_at?: string | null;
  deadline?: string | null;
};

export type RecruitmentCaseListParams = {
  page?: number;
  per_page?: number;
  search?: string;
  status?: RecruitmentCaseStatus;
  priority?: CasePriority;
  client_id?: number;
  owner_id?: number;
  open_only?: boolean;
};

// -- Recruitment Pipelines / Stages -----------------------------------------

export type RecruitmentPipelineStage = {
  id: number;
  pipeline_id: number;
  display_order: number;
  code: string;
  name: string;
  description: string | null;
  owner_rule_type: StageOwnerRuleType;
  owner_rule_value: string | null;
  sla_hours: number | null;
  auto_generate_task: boolean;
  task_title_template: string | null;
  task_priority: CasePriority | null;
  requires_fields: string[];
  is_terminal: boolean;
};

export type RecruitmentPipelineStagePayload = {
  code: string;
  name: string;
  description?: string | null;
  display_order?: number;
  owner_rule_type: StageOwnerRuleType;
  owner_rule_value?: string | null;
  sla_hours?: number | null;
  auto_generate_task?: boolean;
  task_title_template?: string | null;
  task_priority?: CasePriority | null;
  requires_fields?: string[] | null;
  is_terminal?: boolean;
};

export type RecruitmentPipeline = {
  id: number;
  name: string;
  code: string;
  description: string | null;
  is_default: boolean;
  is_active: boolean;
  stages?: RecruitmentPipelineStage[];
  created_at: string;
  updated_at: string;
};

export type RecruitmentPipelinePayload = {
  name: string;
  code: string;
  description?: string | null;
  is_default?: boolean;
  is_active?: boolean;
};

// -- Job Requirement --------------------------------------------------------

export type JobRequirement = {
  id: number;
  job_number: string;
  recruitment_case_id: number;
  recruitment_case?: {
    id: number;
    case_number: string;
    title: string;
    client: ClientSummary | null;
  } | null;
  pipeline_id: number;
  pipeline?: { id: number; code: string; name: string } | null;
  current_stage_id: number | null;
  current_stage?: RecruitmentPipelineStage | null;
  owner_id: number;
  owner?: UserMini | null;
  title: string;
  department: string | null;
  openings: number;
  employment_type: JobEmploymentType;
  work_mode: WorkMode;
  location: string | null;
  salary_min: number | null;
  salary_max: number | null;
  salary_currency: string | null;
  required_experience_years: number | null;
  education_level: string | null;
  required_skills: string[] | null;
  nice_to_have_skills: string[] | null;
  required_languages: string[] | null;
  description: string | null;
  responsibilities: string | null;
  publication_url: string | null;
  published_at: string | null;
  application_deadline: string | null;
  target_start_date: string | null;
  status: JobRequirementStatus;
  stage_entered_at: string | null;
  completed_at: string | null;
  created_at: string;
  updated_at: string;
};

export type JobRequirementPayload = {
  recruitment_case_id: number;
  pipeline_id?: number | null;
  owner_id: number;
  title: string;
  department?: string | null;
  openings: number;
  employment_type: JobEmploymentType;
  work_mode: WorkMode;
  location?: string | null;
  salary_min?: number | null;
  salary_max?: number | null;
  salary_currency?: string | null;
  required_experience_years?: number | null;
  education_level?: string | null;
  required_skills?: string[] | null;
  nice_to_have_skills?: string[] | null;
  required_languages?: string[] | null;
  description?: string | null;
  responsibilities?: string | null;
  application_deadline?: string | null;
  target_start_date?: string | null;
  status?: JobRequirementStatus;
};

/** PATCH — every field optional, plus publication_url. */
export type JobRequirementUpdatePayload = Partial<Omit<JobRequirementPayload, 'recruitment_case_id' | 'pipeline_id'>> & {
  publication_url?: string | null;
};

export type JobRequirementListParams = {
  page?: number;
  per_page?: number;
  search?: string;
  status?: JobRequirementStatus;
  recruitment_case_id?: number;
  client_id?: number;
  pipeline_id?: number;
  current_stage_id?: number;
  owner_id?: number;
  employment_type?: JobEmploymentType;
  work_mode?: WorkMode;
  open_only?: boolean;
};

export type AdvanceJobStagePayload = {
  target_stage_id?: number;
  fields?: {
    publication_url?: string;
    shortlist_ids?: number[];
    contract_terms?: string;
  };
  handoff_note?: string | null;
};

// -- Lead conversion --------------------------------------------------------

export type ConvertLeadJobPayload = {
  title: string;
  department?: string | null;
  openings: number;
  employment_type: JobEmploymentType;
  work_mode: WorkMode;
  location?: string | null;
  salary_min?: number | null;
  salary_max?: number | null;
  salary_currency?: string | null;
  required_experience_years?: number | null;
  education_level?: string | null;
  required_skills?: string[] | null;
  nice_to_have_skills?: string[] | null;
  required_languages?: string[] | null;
  description?: string | null;
  responsibilities?: string | null;
  application_deadline?: string | null;
  target_start_date?: string | null;
  pipeline_id?: number | null;
  owner_id?: number | null;
};

export type ConvertLeadPayload = {
  reuse_client_id?: number | null;
  client?: {
    company_name: string;
    country?: string | null;
    city?: string | null;
    industry?: string | null;
    company_size?: string | null;
    company_website?: string | null;
    address?: string | null;
    tax_number?: string | null;
    payment_terms?: string | null;
    account_manager_id?: number | null;
    force?: boolean;
  };
  case: {
    title: string;
    description?: string | null;
    owner_id?: number | null;
    priority?: CasePriority;
    target_hires?: number | null;
    started_at?: string | null;
    deadline?: string | null;
  };
  jobs?: ConvertLeadJobPayload[];
};

export type ConvertLeadResult = {
  lead: Lead;
  client: Client;
  case: RecruitmentCase;
  jobs: JobRequirement[];
};

// -- Dashboard --------------------------------------------------------------

export type RecruitmentDashboardKpis = {
  leads_active: number;
  leads_converted: number;
  leads_lost: number;
  clients_active: number;
  cases_open: number;
  jobs_open: number;
  jobs_filled_this_month: number;
};

export type RecruitmentDashboardFunnel = {
  leads_by_status: Record<LeadStatus, number>;
  jobs_by_stage: Record<string, number>;
};

export type RecruitmentLeaderboardRow = {
  owner_id: number;
  owner_name: string | null;
  owner_email: string | null;
  conversions: number;
};

export type RecruitmentLeaderboard = {
  data: RecruitmentLeaderboardRow[];
  meta: { quarter_started_at: string };
};
