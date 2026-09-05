# Employee Attendance System — Product Requirements Document (PRD)

**Version:** 1.0
**Date:** 2026-09-05
**Author:** Development Team
**Status:** Approved for Implementation Planning

---

## 1. Executive Summary

A web-based attendance tracking system that allows employees to check in and out by scanning a physical QR code at the office entrance and entering their employee number. The system also supports leave requests with an admin approval workflow. Admins manage employees, monitor attendance, review leave requests, and configure anti-fraud rules through a dedicated dashboard.

**Primary Users:**
- **Employees** — check in/out and submit leave requests (no login required).
- **Admins** — manage employees, review leaves, monitor attendance, and configure system settings.

**Key Differentiators:**
- Zero-friction employee experience (scan → enter number → confirm).
- Configurable anti-fraud protection (GPS + IP validation, toggleable by admin).
- SMS notifications for onboarding and leave decisions via MTC SMS (Jordan).

---

## 2. Goals & Non-Goals

### 2.1 Goals

- Enable employees to record attendance quickly without individual logins.
- Provide a lightweight leave request workflow with admin approval.
- Give admins clear visibility into attendance and leave data through filterable dashboards.
- Prevent common fraud vectors (remote scans, buddy punching) with configurable rules.
- Notify employees via SMS on key events (account creation, leave decisions).

### 2.2 Non-Goals (Out of Scope for v1)

- Payroll integration or salary computation.
- Late arrival or overtime tracking.
- Leave balance/quota tracking or leave types (annual, sick, etc.).
- Employee-facing dashboards or history views.
- Multi-branch / multi-tenant support.
- Shift or schedule management.
- Biometric authentication.
- Mobile native app (web-based only).

---

## 3. Technology Stack

| Layer | Technology | Rationale |
|---|---|---|
| Backend Framework | Laravel 11 (PHP 8.3+) | Team standard, mature ecosystem |
| Database | MySQL 8 | Team standard, reliable |
| Frontend | Livewire 3 + Tailwind CSS + Alpine.js | Reactive dashboard without an SPA build step |
| Admin Auth | Laravel Breeze | Simple, secure, well-supported |
| SMS Gateway | MTC SMS (`api.mtcsms.com`) | Provided by client, local Jordanian carrier |
| Language | Arabic (RTL) primary | Target market |
| Testing | PHPUnit + Pest | Laravel standard |

---

## 4. Architecture Overview

The system follows a clean layered architecture with strict separation of concerns.

```
┌─────────────────────────────────────────────────────┐
│              Presentation Layer                      │
│  ┌──────────────────┐    ┌──────────────────────┐   │
│  │  Public QR Page  │    │   Admin Dashboard    │   │
│  │  (no auth)       │    │   (Livewire + auth)  │   │
│  └────────┬─────────┘    └──────────┬───────────┘   │
└───────────┼───────────────────────────┼─────────────┘
            │                           │
┌───────────▼───────────────────────────▼─────────────┐
│                 Controller Layer                     │
│              (thin, HTTP concerns only)              │
└───────────────────────┬─────────────────────────────┘
                        │
┌───────────────────────▼─────────────────────────────┐
│                  Service Layer                       │
│   EmployeeService   │  AttendanceService             │
│   LeaveService      │  SmsService                    │
│   FraudGuardService │  SettingsService               │
└───────────────────────┬─────────────────────────────┘
                        │
┌───────────────────────▼─────────────────────────────┐
│                Repository Layer                      │
│  (all Eloquent queries live here)                    │
└───────────────────────┬─────────────────────────────┘
                        │
┌───────────────────────▼─────────────────────────────┐
│                   Models Layer                       │
│           (Eloquent — relationships only)            │
└───────────────────────┬─────────────────────────────┘
                        │
                    MySQL 8
                        │
              ┌─────────▼─────────┐
              │   MTC SMS API     │
              └───────────────────┘
```

### 4.1 Layer Responsibilities

- **Controllers**: HTTP validation, dispatch to service, return response. No business logic.
- **Livewire Components**: Reactive UI state for admin dashboard (filters, tables, modals).
- **Services**: All business logic. Injected via constructor.
- **Repositories**: All DB queries. Return Eloquent collections/models to services.
- **Models**: Relationships, scopes, accessors. No business logic.

### 4.2 Services Catalog

| Service | Responsibility |
|---|---|
| `EmployeeService` | Create employees, generate sequential employee numbers, trigger onboarding SMS |
| `AttendanceService` | Record attendance, auto-detect check-in vs check-out |
| `LeaveService` | Create leave requests, approve/reject, trigger decision SMS |
| `SmsService` | Interface + MTC implementation, log all SMS attempts |
| `FraudGuardService` | Evaluate GPS/IP rules per current settings |
| `SettingsService` | Read/write system settings with caching |

---

## 5. Data Model

### 5.1 `employees`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `employee_number` | int UNIQUE | Auto-generated, starts at configurable value (default 1001) |
| `name` | string(150) | |
| `phone` | string(20) UNIQUE | E.164 format preferred |
| `email` | string(150) UNIQUE, nullable | |
| `is_active` | boolean | Default `true`; inactive employees cannot check in |
| `created_at`, `updated_at` | timestamp | |

### 5.2 `attendances`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `employee_id` | bigint FK → employees | Indexed |
| `type` | enum('check_in','check_out') | |
| `scanned_at` | datetime | Indexed |
| `ip_address` | string(45), nullable | For audit |
| `latitude` | decimal(10,7), nullable | |
| `longitude` | decimal(10,7), nullable | |
| `fraud_check_status` | enum('passed','gps_failed','ip_failed','skipped') | |
| `created_at` | timestamp | |

**Composite index:** `(employee_id, scanned_at DESC)` for fast last-record lookups.

### 5.3 `leave_requests`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `employee_id` | bigint FK → employees | |
| `start_date` | date | |
| `end_date` | date | Equal to `start_date` for single-day requests |
| `note` | text, nullable | |
| `status` | enum('pending','approved','rejected') | Default `pending` |
| `reviewed_by` | bigint FK → users, nullable | Admin who reviewed |
| `reviewed_at` | timestamp, nullable | |
| `rejection_reason` | text, nullable | |
| `created_at`, `updated_at` | timestamp | |

**Index:** `(status, start_date)`

### 5.4 `users` (Admins)
Standard Laravel Breeze `users` table (`id`, `name`, `email`, `password`, timestamps).

### 5.5 `settings`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `key` | string UNIQUE | e.g. `gps_enabled`, `office_lat` |
| `value` | text | Serialized based on `type` |
| `type` | enum('string','boolean','json','number') | For casting |

**Seeded keys:**
- `gps_enabled` (bool, default `false`)
- `office_lat`, `office_lng` (number, nullable)
- `geofence_radius_meters` (number, default 100)
- `ip_enabled` (bool, default `false`)
- `ip_whitelist` (json array of CIDRs/IPs)
- `sms_username`, `sms_password`, `sms_sender` (string)
- `employee_number_start` (number, default 1001)

### 5.6 `sms_logs`
| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `phone` | string(20) | |
| `message` | text | |
| `status` | enum('sent','failed') | |
| `provider_response` | text, nullable | Raw response from MTC |
| `error_code` | string, nullable | MTC error code if failed |
| `sent_at` | timestamp | |

---

## 6. Use Cases & User Flows

### UC-1: Employee Checks In / Out via QR

**Actor:** Employee
**Preconditions:** Physical QR code exists at office entrance; employee has been onboarded and knows their number.

**Flow:**
1. Employee scans the QR code with their phone camera.
2. Browser opens `/scan`.
3. Screen shows two options: **[Record Attendance]** or **[Request Leave]**.
4. Employee taps "Record Attendance".
5. Employee enters their employee number.
6. If browser geolocation is enabled in settings, the page requests location permission.
7. `FraudGuardService` evaluates:
   - If GPS enabled and location outside geofence → reject with clear message.
   - If IP restriction enabled and IP not whitelisted → reject.
   - If both enabled → passing either is sufficient (per client requirement).
   - If neither enabled → skip checks (`skipped` status logged).
8. `AttendanceService` determines type:
   - Look up last record for this employee **today**.
   - If none → `check_in`.
   - If last is `check_in` → `check_out`.
   - If last is `check_out` → `check_in` (allows multiple entries per day if needed).
9. Confirmation screen: *"Hello Ahmad — this will record your **check-in** at 8:12 AM. [Confirm]"*.
10. On confirm, record is saved. Success screen shows recorded time.

**Alternate flows:**
- Invalid employee number → inline error, allow retry (rate-limited).
- Fraud check fails → clear message: *"You appear to be outside the office. Please check in from the office premises."*
- Inactive employee → *"Your account is inactive. Please contact administration."*

### UC-2: Employee Submits Leave Request

**Actor:** Employee
**Flow:**
1. From `/scan`, tap "Request Leave".
2. Form fields:
   - Employee number.
   - Date range (single date or start/end).
   - Optional note (textarea).
3. On submit, request is validated and saved with status `pending`.
4. Success screen: *"Your leave request has been submitted. You'll receive an SMS with the decision."*

### UC-3: Admin Creates Employee

**Actor:** Admin
**Flow:**
1. Dashboard → Employees → **[+ New Employee]**.
2. Form: name, phone, email (optional).
3. `EmployeeService.create()`:
   - Validates input.
   - Generates `employee_number = MAX(employee_number) + 1`, or `employee_number_start` if empty.
   - Persists record.
   - Dispatches SMS: *"Welcome to [Company]. Your employee number is 1042. Use it to check in via the QR at the entrance."*
4. Employee appears in the list.

### UC-4: Admin Reviews Leave Request

**Actor:** Admin
**Flow:**
1. Dashboard → Leaves → filter `status = pending`.
2. Open request → view employee info, date, note.
3. **[Approve]** or **[Reject]** (rejection requires reason).
4. `LeaveService.decide()`:
   - Updates status, `reviewed_by`, `reviewed_at`, `rejection_reason` (if applicable).
   - Sends SMS: *"Your leave for 2026-09-10 has been approved."* or *"Your leave for 2026-09-10 was rejected. Reason: …"*

### UC-5: Admin Configures Anti-Fraud Rules

**Actor:** Admin
**Flow:**
1. Dashboard → Settings.
2. Toggle **GPS Check** (on/off). If on, set office lat/lng and radius (meters).
3. Toggle **IP Check** (on/off). If on, edit IP whitelist (one per line, supports CIDR).
4. Toggles are independent — either, both, or neither may be enabled.
5. Save. Cache is invalidated.

### UC-6: Admin Views Dashboard Overview

**Actor:** Admin
**Displayed KPIs:**
- Employees present today / total active employees.
- Employees absent today (active but no check-in).
- Approved leaves for today.
- Pending leave requests count (with alert badge).
- Chart: attendance count last 7 days.
- Chart: leave distribution last 30 days.
- Feed: last 5 scans (real-time via Livewire polling).

---

## 7. Dashboard Pages & Filters

### 7.1 Pages

| Page | Route | Purpose |
|---|---|---|
| Overview | `/admin` | KPIs and charts |
| Employees | `/admin/employees` | List, create, edit, activate/deactivate |
| Attendance | `/admin/attendance` | Full attendance log |
| Leaves | `/admin/leaves` | Review and decide leave requests |
| Settings | `/admin/settings` | Anti-fraud, SMS, and system config |
| SMS Logs | `/admin/sms-logs` | Audit trail of sent messages |

### 7.2 Filters per Page

**Employees:**
- Search (name, employee number, phone).
- Status (active / inactive / all).
- Sort by number, name, creation date.

**Attendance:**
- Date range (from / to).
- Employee (searchable dropdown).
- Type (check-in / check-out / all).
- Fraud status (passed / failed / skipped).

**Leaves:**
- Date range (leave date).
- Employee.
- Status (pending / approved / rejected / all).

**SMS Logs:**
- Date range.
- Status (sent / failed).
- Phone search.

All tables support: pagination, CSV/Excel export, column sorting.

---

## 8. SMS Integration (MTC)

### 8.1 Endpoint
```
GET http://int.mtcsms.com/sendsms.aspx
  ?username=xxx
  &password=xxx
  &from=<sender_id>
  &to=<mobile>
  &msg=<text>
  &type=0     (0 = normal, 1 = hex/unicode for Arabic if needed)
```

### 8.2 Success/Error Codes
| Code | Meaning |
|---|---|
| `0` | Sent successfully |
| `10002` | Invalid username or password |
| `10003` | One or more fields empty |
| `10004` | Sender name not allowed |
| `10005` | Low balance |
| `10008` | Account suspended |

### 8.3 SMS Trigger Points
| Event | Recipient | Template (Arabic) |
|---|---|---|
| Employee created | New employee | `أهلاً بك في [الشركة]. رقمك الوظيفي: {number}. استخدمه لتسجيل الحضور عبر QR عند المدخل.` |
| Leave approved | Employee | `تمت الموافقة على طلب إجازتك بتاريخ {date}.` |
| Leave rejected | Employee | `تم رفض طلب إجازتك بتاريخ {date}. السبب: {reason}` |

**Note:** Check-in/check-out confirmations are **not** sent via SMS to control costs. On-screen confirmation is sufficient.

### 8.4 Implementation Notes
- `SmsService` is defined as an interface (`SmsGatewayInterface`) with `MtcSmsGateway` as the concrete implementation, allowing future swaps.
- All SMS attempts logged to `sms_logs` (success and failure).
- Arabic messages use `type=1` (hex) if MTC requires it for Unicode; otherwise `type=0`. To be confirmed during implementation.
- SMS sending is dispatched via Laravel Queue to avoid blocking user requests.

---

## 9. Security & Privacy

- **Admin Authentication:** Laravel Breeze (session-based, hashed passwords, password reset via email).
- **Public Route Protection:**
  - `/scan` and related POST endpoints are rate-limited (30 req/min per IP).
  - CSRF tokens enforced on all state-changing requests.
- **Input Validation:** All request data validated via `FormRequest` classes. Employee numbers checked for existence and active status.
- **Anti-Fraud (configurable per admin):**
  - GPS geofence enforcement.
  - IP whitelist enforcement.
  - Both can be independently toggled.
- **Audit Trail:** All attendance records store IP and (if provided) geolocation. All SMS logged.
- **Sensitive Data:** SMS credentials stored in `settings` table, encrypted at rest via Laravel's `Crypt` facade.
- **RTL & Localization:** All UI in Arabic with `dir="rtl"`; English fallback strings available.

---

## 10. Non-Functional Requirements

| Category | Requirement |
|---|---|
| Performance | Scan-to-confirmation < 2 seconds under normal load |
| Availability | 99% uptime target |
| Scalability | Support up to 500 employees and 1,000 scans/day without optimization |
| Browser Support | Modern mobile browsers (iOS Safari 15+, Chrome 100+) |
| Accessibility | WCAG 2.1 AA minimum on admin dashboard |
| Backup | Daily MySQL backup |

---

## 11. Testing Strategy

- **Feature Tests (PHPUnit):**
  - `AttendanceService::record()` — check-in/out auto-detection, fraud guard integration.
  - `LeaveService::decide()` — status transitions, SMS dispatch.
  - `EmployeeService::create()` — number generation, SMS dispatch, duplicate handling.
  - `FraudGuardService` — all permutations of GPS/IP toggles.
- **HTTP Tests:** Scan flow end-to-end, admin CRUD, auth middleware.
- **Unit Tests:** `SmsService` with mocked HTTP client, `SettingsService` cache behavior.

---

## 12. Deliverables & Milestones (High-Level)

*(Detailed sprint plan to be produced in the Implementation Plan phase.)*

1. **M1 — Foundation:** Laravel setup, DB migrations, admin auth, settings framework.
2. **M2 — Employee Management:** CRUD, employee number generator, onboarding SMS.
3. **M3 — Attendance Flow:** QR scan page, auto-detect logic, fraud guard.
4. **M4 — Leave Workflow:** Request form, admin review, decision SMS.
5. **M5 — Dashboard & Filters:** Overview KPIs, filtered tables, exports.
6. **M6 — Hardening:** Full test coverage, security review, deployment.

---

## 13. Open Questions

*(To be resolved before or during implementation.)*

1. Does the SMS sender ID need to be pre-registered with MTC before go-live? (Client to confirm)
2. Should employees have a way to check their own attendance history (e.g., an SMS with today's status on demand)? — deferred to v2.
3. Multi-language admin dashboard (Arabic + English) or Arabic only? — currently Arabic only.
4. Timezone: system-wide Asia/Amman assumed. Confirm.

---

## 14. Appendix

### A. Glossary
- **QR Scan Endpoint:** Public page opened after scanning the office QR code.
- **Fraud Guard:** Configurable set of rules (GPS + IP) that validates a scan's legitimacy.
- **Employee Number:** Public identifier used at scan time (e.g., `1042`), sequential and unique.

### B. Sample Fraud Guard Decision Table

| `gps_enabled` | `ip_enabled` | GPS in range | IP whitelisted | Result |
|---|---|---|---|---|
| off | off | — | — | ✅ passed (skipped) |
| on | off | ✅ | — | ✅ passed |
| on | off | ❌ | — | ❌ gps_failed |
| off | on | — | ✅ | ✅ passed |
| off | on | — | ❌ | ❌ ip_failed |
| on | on | ✅ | ❌ | ✅ passed |
| on | on | ❌ | ✅ | ✅ passed |
| on | on | ❌ | ❌ | ❌ (both failed) |

---

**End of Document**
