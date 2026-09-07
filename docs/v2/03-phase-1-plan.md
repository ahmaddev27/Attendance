# TAQAT v2 — Phase 1 Plan (MVP)

> **الوثيقة رقم 4 من حزمة تخطيط v2**
> النطاق: نطاق MVP، milestones M1-M8، مهام لكل milestone، Definition of Done، ما يُؤجَّل لـ Phase 2/3/4.
> الوثائق المرافقة: [`00-overview.md`](00-overview.md) · [`01-architecture.md`](01-architecture.md) · [`02-erd.md`](02-erd.md)

---

## 1. نطاق Phase 1 (MVP)

مستمدّ من SRS بند 72 مع تعديل مهم: **المشاريع والـ Sprints والـ Scrum ceremonies مؤجّلة إلى Phase 2**. الـ Tasks تبقى في Phase 1 لكن بلا project/sprint (task بلا مشروع = "personal task" أو "ad-hoc task").

### 1.1 القائمة النهائية للـ MVP (15 بند)

| #  | البند                                                    | Milestone | ملاحظة                                                            |
| -- | -------------------------------------------------------- | --------- | ----------------------------------------------------------------- |
| 1  | Authentication (Sanctum, RBAC 5 أدوار، employee login)   | M1        | employee_number أو email، password reset عبر SMS                   |
| 2  | Employee Management (بكل الحقول الجديدة)                  | M2        | CRUD + avatar + soft delete                                        |
| 3  | Organization Structure (Companies, Departments, Teams, Positions) | M2 | Companies صف واحد في Phase 1                                       |
| 4  | Direct Manager hierarchy                                  | M2        | تصفّح شجرة الفريق للـ manager/team_leader                          |
| 5  | QR Attendance ديناميكي + Geofence + IP whitelist         | M3        | Devices متعددة، token يتجدد كل 30 ثانية                            |
| 6  | Working Hours Engine (late/early/overtime/absence auto)  | M3        | Job ليلي 00:15 يعيد احتساب حضور اليوم السابق                       |
| 7  | Work Schedules + Holidays إدارياً                         | M3        | Configure Don't Code                                                |
| 8  | Multiple Leave Types + Balances + Entitlement            | M4        | Configure Don't Code + carry_over                                   |
| 9  | Generic Workflow Engine + Approver Resolver               | M5        | direct_manager / department_manager / role / specific / field_ref  |
| 10 | Request Builder + 3 request types (leave, business mission, advance) | M5 | form_schema JSON — إضافة نوع جديد من UI بدون deploy               |
| 11 | Simple Tasks (بلا project) + Comments + @Mentions + Attachments | M6 | subtasks + tags + history                                          |
| 12 | Notification Center (in-app + SMS via MTC) + Preferences | M7        | Reverb للـ in-app realtime                                          |
| 13 | Employee Personal Dashboard                              | M7        | KPIs الذاتية: حضوري، إجازتي، مهامي، طلباتي                          |
| 14 | Admin Dashboard + KPIs عامة                               | M8        | حضور اليوم، إجازات مفتوحة، طلبات معلّقة، توزيع الموظفين              |
| 15 | Basic Reports (CSV export فقط) + Audit Log + PWA         | M8        | Excel/PDF مؤجّل لـ Phase 2                                          |

### 1.2 ما هو صراحة **خارج** نطاق Phase 1

- Projects, Sprints, Scrum ceremonies (planning/review/retro), Kanban board، Burndown، Velocity → **Phase 2**
- Excel / PDF exports → **Phase 2** (CSV كافٍ لـ MVP)
- WhatsApp channel، Email notifications متقدّمة (templates) → **Phase 2**
- Global search (Meilisearch/Scout) → **Phase 2**
- Executive Dashboard → **Phase 3**
- AI (Daily Motivation، Manager Assistant) → **Phase 3**
- Performance reviews، KPIs محسوبة → **Phase 4**
- 2FA/SSO/SAML → **Phase 4**
- Multi-company (SaaS) → **Phase 4**
- Mobile app (React Native) → **Phase 4**

---

## 2. Milestone Breakdown

**الفريق المفترض:** 2-3 مطوّرين full-time (1 backend + 1 frontend + 0.5 fullstack/QA).
**المدّة الإجمالية:** 12 أسبوع (3 أشهر).

**قواعد عامة لكل milestone:**
- كل milestone ينتهي بـ **demo مسجّل + smoke test manual + merge إلى `main`**.
- كل ميزة في milestone تحتاج: DB migration + Model + Service + Controller + API Resource + Policy + FormRequest + Tests (Feature + Unit) + Next.js pages + i18n keys.
- المراجعة (`code-review` skill) قبل merge إلزامية.
- التحديث الفوري لـ `docs/runbooks/` بأي عملية تشغيلية جديدة.

---

### M1 — Monorepo + Docker + Auth Scaffold  (الأسبوع 1)

**الهدف:** بيئة تطوير كاملة قابلة للتشغيل + login/logout يعمل من Next.js إلى Laravel API عبر Docker.

**التسليمات:**
- Monorepo تم إنشاؤه ودُفع إلى GitHub بحسب هيكل [`01-architecture.md#3`](01-architecture.md).
- `docker-compose.dev.yml` + `docker-compose.yml` (production) يعملان — 9 services تُقلع بلا أخطاء.
- Nginx يوجّه `/api/*` إلى Laravel و `/` إلى Next.js.
- Laravel skeleton مع modular structure جاهز (`app/Modules/Auth`, `app/Shared/*`).
- Next.js 15 App Router + Tailwind + shadcn/ui + Tajawal + RTL setup.
- Sanctum SPA auth شغّال: login endpoint + login page + `useAuth` hook.
- CI في GitHub Actions يشغّل lint + tests على كل push.
- Health endpoint `/api/health` يستجيب.

**Tasks:**
- [ ] إنشاء monorepo + إعداد `.gitignore` + README.md
- [ ] إنشاء `apps/api/` (Laravel 11 fresh install) + `deptrac` config
- [ ] إنشاء `apps/web/` (Next.js 15 fresh) + shadcn/ui setup + Tailwind config + Tajawal font
- [ ] كتابة Dockerfiles لـ api و web (multi-stage builds مع cache layers)
- [ ] كتابة `docker-compose.yml` كامل بحسب [`01-architecture.md#2.2`](01-architecture.md)
- [ ] إعداد Nginx conf + Let's Encrypt (staging cert أول)
- [ ] تثبيت Sanctum + إعداد CORS + `SANCTUM_STATEFUL_DOMAINS`
- [ ] إنشاء `App\Modules\Auth`: LoginController، LoginService، LoginRequest، routes
- [ ] Next.js: login page + `lib/api/client.ts` (axios with credentials) + `authStore` Zustand
- [ ] GitHub Actions workflows: `ci.yml` (Pest + ESLint) + `build-and-push.yml` (GHCR)
- [ ] Health endpoint + Docker healthchecks
- [ ] كتابة README.md مع خطوات first-time setup

**Definition of Done:**
- مطوّر جديد يستنسخ الـ repo → `docker compose up -d` → يفتح `http://localhost` → يسجّل الدخول بالحساب المزروع → يرى صفحة "Hello, {name}".
- CI أخضر.
- Sanity: `curl /api/health` يعيد `{status:"ok"}`.

---

### M2 — Employees + Org Structure  (الأسبوع 2)

**الهدف:** إدارة كاملة للموظفين وشجرة تنظيمية.

**التسليمات:**
- CRUD كامل لـ: Companies (view/edit فقط، صف واحد)، Departments (بشجرة parent_id)، Teams، Positions.
- Employees CRUD مع كل الحقول الجديدة (راجع [`02-erd.md#4`](02-erd.md)).
- Direct manager assignment + شجرة المرؤوسين.
- Avatar upload إلى MinIO bucket `avatars` (public visibility).
- Seeder migration من v1 (`V1MigrationSeeder`).
- Policies + Permissions (Spatie seeded).

**Tasks:**
- [ ] Migrations: companies, positions, departments, teams (بحسب Wave 1 من [`02-erd.md#15`](02-erd.md))
- [ ] Migration للـ `employees` (Wave 2)
- [ ] Migration للـ Spatie Permission + Seeder للأدوار الخمسة
- [ ] Migrations للـ circular FKs (Wave 3)
- [ ] Models + Relationships + Casts (`EncryptedString` لـ national_id)
- [ ] Repository: `EmployeeRepository` مع scopes للـ team/department
- [ ] Service: `EmployeeService::create/update/deactivate/terminate`
- [ ] FormRequests + Resources
- [ ] Controllers + routes:
  - `GET /api/v1/employees` (list, filter, paginate, search)
  - `POST /api/v1/employees` (admin only)
  - `GET /api/v1/employees/{id}`
  - `PATCH /api/v1/employees/{id}`
  - `DELETE /api/v1/employees/{id}` (soft)
  - `GET /api/v1/employees/{id}/subordinates`
  - `POST /api/v1/employees/{id}/avatar`
  - `GET /api/v1/org-tree` (nested tree للـ frontend)
  - `PATCH /api/v1/me/profile` (self-update للـ phone + avatar فقط)
- [ ] Policies: `EmployeePolicy` (view.any = admin/management، view.own = self، view.team = manager)
- [ ] Next.js pages:
  - `(admin)/employees/*`, `(admin)/departments/*`, `(admin)/teams/*`, `(admin)/positions/*`
  - `(employee)/profile/*`
  - `components/modules/employees/{EmployeeCard,OrgTree,EmployeeForm}.tsx`
- [ ] `V1MigrationSeeder` مع dry-run mode + rollback support
- [ ] Tests: Feature (CRUD + permissions matrix) + Unit (Service edge cases)

**Definition of Done:**
- Super Admin ينشئ department → team → position → employee → موظف الآن قادر على تسجيل الدخول.
- تغيير direct_manager لموظف يحدّث شجرة `/api/v1/org-tree` فوراً.
- V1 seeder يُشغَّل على snapshot من v1 → كل الموظفين ينقلون بدون خطأ (أو تقرير exceptions واضح).

---

### M3 — Attendance + Working Hours Engine  (الأسبوعان 3-4)

**الهدف:** حضور QR ديناميكي كامل مع احتساب ذكي للساعات.

**التسليمات:**
- Work Schedules + Holidays إدارياً (CRUD).
- Attendance Devices CRUD + عرض QR ديناميكي متجدّد.
- QR scan endpoint مع geofence + IP whitelist.
- Working Hours Engine (Job ليلي 00:15).
- History للموظف: عرض حضور آخر 30 يوماً.
- تعديل يدوي بواسطة admin مع audit trail.

**Tasks:**
- [ ] Migrations: work_schedules, holidays, attendance_devices, attendances (Wave 4)
- [ ] Seeder: work_schedule افتراضي (Sun-Thu, 8-16, grace 15min) + holidays 2026
- [ ] `QrTokenService`:
  - `generateForDevice(device)` → HMAC-SHA256 → يخزّن في Redis (`qr:device:{id}` TTL = rotate_seconds)
  - `verify(token, deviceCode)` → يفكّ الـ HMAC، يتحقّق من time drift ≤ 60s
- [ ] `GeofenceValidator::check(location, deviceId)` → Haversine distance
- [ ] `AttendanceService::checkIn(employee, deviceId, location, ip, userAgent)`:
  - transaction + `SELECT FOR UPDATE` على row (employee, today)
  - يتحقّق من IP whitelist لو موجود
  - يتحقّق من geofence
  - يلتقط `work_schedule_snapshot`
  - يُنشئ/يحدّث row
- [ ] `AttendanceService::checkOut(...)` مشابه
- [ ] `WorkingHoursEngine::recomputeForDate(date)`:
  - يجلب كل موظف نشط
  - لكل موظف: يجلب attendance اليوم، يحسب `total/late/early/overtime/status`
  - يعالج holidays و weekends
- [ ] Job: `RecomputeAttendanceForDate` (queue: default) — يُستدعى ليلاً
- [ ] Command: `taqat:recompute-attendance --from=YYYY-MM-DD --to=YYYY-MM-DD`
- [ ] Kernel scheduler: `$schedule->job(new RecomputeAttendanceForDate(today()->subDay()))->dailyAt('00:15')`
- [ ] API endpoints:
  - `POST /api/v1/attendance/scan` — public (لكن يتحقّق من session)
  - `GET /api/v1/attendance/today` — الحضور اليوم للموظف الحالي
  - `GET /api/v1/attendance/history?from=&to=` — تاريخ الحضور الشخصي
  - `GET /api/v1/attendance` — admin: كل الموظفين
  - `PATCH /api/v1/attendance/{id}` — admin: تعديل يدوي (audit)
  - `GET /api/v1/attendance-devices` + CRUD (admin)
  - `GET /api/v1/attendance-devices/{code}/qr` — يعرض token + expires_in (لعرض live)
  - `GET /api/v1/work-schedules` + CRUD (admin)
  - `GET /api/v1/holidays` + CRUD (admin)
- [ ] Next.js pages:
  - `(employee)/attendance/page.tsx` (QrScanner + button)
  - `(employee)/attendance/history/page.tsx`
  - `(admin)/work-schedules/*`, `(admin)/holidays/*`
  - `(public)/scan/[deviceCode]/page.tsx` — يعرض QR live rotation عبر polling كل 3 ثوان (أو Reverb)
- [ ] Tests:
  - QR verify يرفض token منتهي/مزيّف
  - checkIn خارج geofence يفشل
  - checkOut قبل checkIn يفشل
  - محاولتان متزامنتان → واحدة تنجح فقط (race lock)
  - WorkingHoursEngine يحسب صحيحاً: late = max(0, check_in - (start + grace))
  - Holidays تُتجاوز في status computation

**Definition of Done:**
- موظف يفتح `/attendance` من هاتفه → يمسح QR على شاشة المكتب → يظهر "تم تسجيل الحضور 08:03".
- QR على شاشة المكتب يتجدّد كل 30 ثانية بصرياً.
- محاولة scan من IP غير مسموح → 403 مع رسالة واضحة.
- ليلة الغد 00:20 → الأدمن يرى في التقرير أن late_minutes محسوبة صحيحاً.
- 106 tests من v1 → مستبدلة بـ ≥ 40 test جديد لـ attendance module فقط.

**مخاطر / اعتبارات:**
- **Race conditions:** لا تكرّر خطأ v1 الأصلي — استخدم `lockForUpdate()` داخل transaction.
- **Time drift:** VPS يجب أن يشغّل NTP.
- **PWA offline scan:** مؤجَّل لـ M8 مع Service Worker.

---

### M4 — Leaves + Balances  (الأسبوع 5)

**الهدف:** أنواع إجازات متعدّدة مع رصيد وطلبات موافقة (workflow مبسّط في هذه المرحلة، يُوسَّع في M5).

**التسليمات:**
- Leave Types إدارياً.
- Leave Balances تلقائية عند إضافة موظف / نوع جديد.
- Leave Request end-to-end (submit → pending → approved/rejected → balance update).
- Manager يوافق من dashboard.
- Attachments (medical certificate).

**Tasks:**
- [ ] Migrations: leave_types, leave_balances, leave_requests (Wave 6 جزئياً — بلا workflow_id بعد)
- [ ] Seeder: 4 leave_types (annual 21d, sick 14d, unpaid, emergency 3d)
- [ ] `LeaveBalanceService::ensureBalanceExists(employee, year)` — يُستدعى عند إضافة موظف
- [ ] `LeaveRequestService::submit(payload)`:
  - يحسب `days` مستبعداً weekends + holidays
  - يتحقّق من `min_notice_days`, `max_consecutive_days`, attachment required
  - يفتح transaction + `FOR UPDATE` على balance row
  - يتحقّق من `remaining >= days` (أو `allow_negative_balance`)
  - يزيد `pending += days`
  - يُنشئ leave_request بحالة `pending`
  - يطلق event `LeaveRequestSubmitted` → إشعار للـ direct_manager
- [ ] `LeaveRequestService::approve/reject/cancel` مع تحديث balance تلقائي
- [ ] API endpoints:
  - `GET /api/v1/leave-types`
  - `GET /api/v1/leaves/balances` (الموظف الحالي أو admin all)
  - `POST /api/v1/leaves` (submit)
  - `GET /api/v1/leaves?status=pending&scope=team|all`
  - `POST /api/v1/leaves/{id}/approve`
  - `POST /api/v1/leaves/{id}/reject` (with reason)
  - `POST /api/v1/leaves/{id}/cancel` (owner only, before decision)
  - `GET /api/v1/leaves/{id}` (detail + timeline)
  - Admin CRUD: `/api/v1/admin/leave-types`
- [ ] Kernel scheduler: `AnnualBalanceRollover` — 1 يناير 00:05
- [ ] Next.js pages:
  - `(employee)/leaves/page.tsx` — رصيدي + طلباتي
  - `(employee)/leaves/new/page.tsx` — form
  - `(admin)/leave-types/*`
  - `components/modules/leaves/{LeaveBalanceCard, LeaveRequestForm, LeaveTimeline}.tsx`
- [ ] Notification via SMS للموافقة/الرفض (يستخدم `SendMtcSms` job من M7 — placeholder الآن)
- [ ] Tests:
  - رصيد سلبي مرفوض ما لم يُسمح
  - الأيام تُحتسب بلا weekends/holidays
  - محاولتان لموافقة نفس الطلب → واحدة تنجح
  - AnnualBalanceRollover ينشئ صف السنة الجديدة بـ carry_over صحيح

**Definition of Done:**
- موظف يقدّم طلب إجازة، رصيده pending يقلّ فوراً.
- مديره يرى الطلب في dashboard خلال ثوان (بدون refresh — Reverb).
- Approve → balance.used ↑ + balance.pending ↓ + إشعار للموظف.
- تعديل leave_type من الأدمن يعكس فوراً في الـ form.

**Note:** Workflow في هذه المرحلة = خطوة واحدة (direct_manager). في M5 نستبدلها بـ Workflow Engine الكامل الذي يدعم خطوات متعدّدة.

---

### M5 — Workflow Engine + Requests  (الأسبوعان 6-7)

**الهدف:** محرّك موافقات عام قابل للتخصيص + Request Builder (form ديناميكي) + 3 request types جاهزة.

**التسليمات:**
- Workflows + Steps CRUD إدارياً مع UI drag-drop.
- ApproverResolver يعمل مع كل الأنواع الخمسة.
- Request Types CRUD مع Form Builder إدارياً.
- 3 request types مزروعة: `leave` (مربوطة بـ M4)، `business_mission`، `advance`.
- Generic Request submit/approve flow.
- SLA monitor.

**Tasks:**
- [ ] Migrations: workflows, workflow_steps (Wave 5)، request_types, requests, request_approvals (Wave 6)
- [ ] `ApproverResolver::resolve(step, context) → Employee|null`
  - `direct_manager`: context.employee.direct_manager
  - `department_manager`: context.employee.department.manager
  - `specific_employee`: step.approver_id
  - `role`: أول employee بذلك role (أو all?) — قرار: **all** يجب على الأقل واحد يوافق
  - `user_field_reference`: يقرأ من `context.form_data[field]`
- [ ] `WorkflowEngine`:
  - `start(subject: WorkflowSubject)` → يعيّن `current_step_id` = أول step + `current_approver_id`
  - `approve(subject, approver, comment)` → يسجّل approval → ينتقل للـ step التالي أو completes
  - `reject(subject, approver, comment)`
  - `return(subject, approver, comment)` → يعيد للطالب
  - `forward(subject, approver, newApprover)`
- [ ] `WorkflowSubject` interface — implements في `LeaveRequest` و `Request`
- [ ] `RequestFormValidator::validate(requestType, formData)` — يقرأ `form_schema` ويطبّق rules
- [ ] `RequestService::submit(employee, requestType, formData, attachments)`:
  - يتحقّق من form_schema
  - يُنشئ request بـ status=submitted
  - يستدعي WorkflowEngine::start
  - request_number يُولَّد: `REQ-{year}-{6-digit-seq}`
- [ ] SLA:
  - `SlaMonitor` job كل ساعة يفحص requests حيث `current_step_id` منذ > `sla_hours`
  - يضع `sla_breach_at` + يشعر `escalate_to_id`
- [ ] Seeder:
  - Workflow "Standard Single Manager" (step: direct_manager)
  - Workflow "Two-Step: Manager → HR" 
  - request_type `business_mission` (fields: destination, start_date, end_date, purpose, budget, attachment)
  - request_type `advance` (fields: amount, reason, repayment_months)
  - ربط leave_types بـ workflow "Standard Single Manager"
- [ ] API endpoints:
  - `GET /api/v1/workflows` + CRUD (admin)
  - `GET /api/v1/request-types` (public list of active types)
  - `GET /api/v1/request-types/{code}` — يعيد form_schema للـ frontend
  - Admin: `/api/v1/admin/request-types` CRUD
  - `POST /api/v1/requests` — submit
  - `GET /api/v1/requests?scope=me|to_approve|all&status=...`
  - `GET /api/v1/requests/{id}` (detail + approvals timeline)
  - `POST /api/v1/requests/{id}/approve|reject|return|forward|cancel`
- [ ] Refactor leave_requests to use WorkflowEngine (استبدال الـ single-step من M4)
- [ ] Next.js:
  - `components/modules/requests/DynamicForm.tsx` — يبني من form_schema
  - `components/modules/requests/ApprovalTimeline.tsx`
  - `components/modules/workflow/WorkflowBuilder.tsx` — drag-drop steps
  - `(employee)/requests/*`, `(employee)/requests/new/[typeCode]/page.tsx`
  - `(admin)/workflows/*`, `(admin)/request-types/*` (form builder)
- [ ] Tests:
  - ApproverResolver لكل نوع
  - Workflow متعدّد الخطوات: approval يمرّ صحيحاً
  - Reject في أي خطوة يوقف الـ flow
  - Return يعيد للطالب مع status=returned
  - SLA breach يُطلق تنبيه

**Definition of Done:**
- Admin ينشئ نوع طلب جديد "استقالة" من الـ Form Builder + workflow ثلاثي (manager → HR → CEO) بدون كتابة كود.
- الموظف يرى النوع الجديد فوراً في `/requests/new`.
- يقدّم الطلب → يظهر بحالة pending عند المدير → المدير يوافق → ينتقل لـ HR → HR يوافق → ينتقل لـ CEO → CEO يوافق → status=approved.
- كل خطوة تُشعر الشخص المعنيّ.
- SLA: لو HR لم يقرّر خلال 24 ساعة → إشعار escalation إلى بديله.

---

### M6 — Simple Tasks + Comments + Files  (الأسبوعان 8-9)

**الهدف:** إدارة مهام أساسية بلا project/sprint (لكن الجداول موجودة استعداداً لـ Phase 2).

**التسليمات:**
- Task CRUD مع assignee, priority, status, due_date, description.
- Subtasks (parent_task_id).
- Comments مع @mentions.
- File attachments عبر MinIO.
- Task history (insert-only).
- Tags.
- Filters + search على قائمة المهام.

**Tasks:**
- [ ] Migrations: projects, project_members, sprints, task_priorities, task_statuses, tasks, task_comments, task_history, task_tags, taggables (Wave 7)
- [ ] Seeder: task_priorities (low/medium/high/urgent) + task_statuses global (todo/in_progress/review/done/cancelled)
- [ ] `TaskService::create/update/assign/changeStatus/delete`
  - `changeStatus` → لو `is_done_state` → `completed_at = now()`
  - `assign` → إشعار للـ assignee
  - Observer: `TaskObserver` يسجّل في `task_history` عند كل تغيير
- [ ] `TaskCommentService::create(task, user, body)`:
  - يستخرج @mentions من body عبر regex على `@EMP-\d+`
  - يخزّن `mentions[]`
  - لكل mention → إشعار
- [ ] Spatie Media Library integration للـ Task attachments
- [ ] API endpoints:
  - `GET /api/v1/tasks?scope=me|assigned_to_me|created_by_me|all&status=&priority=&search=`
  - `POST /api/v1/tasks`
  - `GET /api/v1/tasks/{id}` (with comments, history, attachments)
  - `PATCH /api/v1/tasks/{id}`
  - `DELETE /api/v1/tasks/{id}` (soft)
  - `POST /api/v1/tasks/{id}/comments`
  - `PATCH /api/v1/tasks/{id}/comments/{cid}`
  - `DELETE /api/v1/tasks/{id}/comments/{cid}`
  - `POST /api/v1/tasks/{id}/attachments`
  - `DELETE /api/v1/tasks/{id}/attachments/{mid}`
  - `POST /api/v1/tasks/{id}/status/{statusCode}` (shortcut)
  - `GET /api/v1/task-tags` + admin CRUD
- [ ] Realtime broadcast: `TaskUpdated` event → channel `App.Models.User.{assignedTo}` + `App.Models.User.{createdBy}`
- [ ] Next.js:
  - `(employee)/tasks/page.tsx` — قائمة + filters + search
  - `(employee)/tasks/[id]/page.tsx` — detail + comments + attachments
  - `components/modules/tasks/{TaskCard,TaskList,TaskDetail,CommentThread,MentionInput,AttachmentUploader}.tsx`
- [ ] Tests:
  - إنشاء task + assignment → task_history سجّل الحدث
  - Comment مع @mention → notification أُنشئت للشخص المذكور
  - Attachment upload → media table + signed URL يعمل
  - Delete task (soft) → لا يظهر في list لكن يظهر في trash view (admin)

**Definition of Done:**
- موظف ينشئ مهمّة، يعيّنها لزميل، يذكره في تعليق "@EMP-102 يرجى المراجعة" → الزميل يستلم إشعار in-app فوراً (Reverb) + SMS.
- ملف PDF (< 5MB) يُرفَع بنجاح ويُنزَّل عبر signed URL.
- تغيير status إلى "done" → completed_at يُملأ + task_history يسجّل.
- 25+ tests لـ tasks module.

---

### M7 — Notifications + Employee Dashboard  (الأسبوع 10)

**الهدف:** مركز إشعارات كامل + لوحة موظف شاملة.

**التسليمات:**
- Notification Center (in-app + SMS + email fallback log).
- Preferences per event لكل موظف.
- Real-time via Reverb.
- Employee Dashboard مع 6 كروت.

**Tasks:**
- [ ] Migrations: notifications (Laravel default) + notification_preferences
- [ ] Seeder: default preferences لكل موظف عبر Observer عند إنشائه
- [ ] Notification classes:
  - `LeaveApprovedNotification`, `LeaveRejectedNotification`
  - `RequestPendingYourApprovalNotification`
  - `TaskAssignedNotification`, `MentionedInCommentNotification`
  - `AttendanceMissedNotification` (cron يومي إذا لم يسجّل موظف نشط)
- [ ] Channels:
  - `database` (in-app) — default
  - `broadcast` (Reverb) — always للـ in-app
  - `MtcSmsChannel` — يستخدم MTC integration
- [ ] `NotificationDispatcher::send(notification, user)`:
  - يقرأ preferences
  - يوجّه إلى channels المفعّلة
- [ ] `PreferencesService` — الموظف يعدّل من settings
- [ ] SMS integration: migrate كود MTC من v1 (encrypted credentials في settings, `SendMtcSms` job)
- [ ] API endpoints:
  - `GET /api/v1/notifications?filter=unread`
  - `POST /api/v1/notifications/{id}/read`
  - `POST /api/v1/notifications/read-all`
  - `GET /api/v1/me/notification-preferences`
  - `PATCH /api/v1/me/notification-preferences`
  - `GET /api/v1/me/dashboard` — يعيد الـ payload الموحّد للـ dashboard
- [ ] Employee Dashboard endpoint payload:
  ```
  {
    attendance_today: { status, check_in_at, check_out_at, late_minutes },
    leave_balance_summary: [{ type, remaining }, ...],
    pending_leave_requests: count + preview 3,
    pending_generic_requests: count + preview 3,
    my_tasks_summary: { todo, in_progress, done_this_week, overdue },
    recent_notifications: 5 latest,
    quick_actions: [ scan_qr, request_leave, new_request, new_task ]
  }
  ```
- [ ] Next.js:
  - `(employee)/dashboard/page.tsx` (grid من 6 كروت)
  - `components/layout/NotificationsBell.tsx` (badge + dropdown + realtime)
  - `(employee)/notifications/page.tsx` (كامل بـ filters)
  - `(employee)/profile/preferences/page.tsx`
  - `lib/hooks/useRealtime.ts` + `useNotifications.ts`
- [ ] Reverb setup: `BroadcastServiceProvider` + `routes/channels.php`
- [ ] Tests:
  - Notification تُرسَل عبر القنوات الصحيحة حسب preferences
  - SMS يُرسَل بنجاح (mock MTC)
  - Realtime: private channel authorization

**Definition of Done:**
- موظف يفتح Dashboard → يرى كل ملخّصاته + الجرس يعرض 3 إشعارات جديدة.
- عندما يوافق مديره على إجازته من جهاز آخر → الجرس يومض في لوحة الموظف خلال ثانية.
- SMS تصل خلال 30 ثانية (حسب MTC).
- الموظف يعطّل SMS للـ `mentioned_in_comment` → لا يستلم SMS لاحقاً لكن in-app نعم.

---

### M8 — Admin Dashboard + Reports + Audit + PWA  (الأسبوعان 11-12)

**الهدف:** إتمام تجربة الأدمن + جودة الإنتاج + PWA.

**التسليمات:**
- Admin Dashboard مع KPIs + charts.
- Reports: attendance, leaves, requests → CSV export.
- Activity log عرض إداري.
- PWA manifest + service worker + install prompt.
- Runbooks كاملة.
- V1 migration seeder مختبَر على snapshot حقيقي.
- Load testing (k6) + security scan.

**Tasks:**
- [ ] Admin Dashboard endpoint `/api/v1/admin/dashboard`:
  ```
  {
    kpi: { active_employees, on_leave_today, present_today, late_today, pending_requests, overdue_tasks },
    charts: {
      attendance_last_30_days: [...],
      leaves_by_type_this_month: [...],
      requests_by_type_this_month: [...],
      top_absentees: [...],
    }
  }
  ```
- [ ] Reports module:
  - `AttendanceReportService::generate(from, to, filters)` → returns DTO
  - `CsvExporter::stream(dto, columns)` → returns download
  - `POST /api/v1/reports/attendance/csv?from=&to=&department_id=`
  - `POST /api/v1/reports/leaves/csv?year=&type=`
  - `POST /api/v1/reports/requests/csv?type=&status=`
  - Job async لتقارير كبيرة (> 1000 rows) → email لينك signed
- [ ] Audit:
  - Install `spatie/laravel-activitylog`
  - Add `LogsActivity` trait لـ: Employee, LeaveRequest, Request, User (login/logout), WorkflowStep changes, Attendance manual adjustment, Settings changes
  - Admin page `/audit-logs` مع filters (causer, subject_type, event, date range)
  - `GET /api/v1/admin/audit-logs?...`
- [ ] PWA:
  - `manifest.webmanifest` مع icons (192, 512)
  - Service Worker (via `next-pwa` أو Workbox)
  - Cache strategy: shell (App shell)، API responses no-cache
  - Install prompt + splash
  - Offline banner
- [ ] Runbooks:
  - `docs/runbooks/backup-and-restore.md`
  - `docs/runbooks/deploy.md`
  - `docs/runbooks/add-new-employee.md`
  - `docs/runbooks/recover-from-outage.md`
  - `docs/runbooks/v1-migration.md`
- [ ] Load test: k6 script بـ 100 concurrent users على `/dashboard` + `/tasks`
- [ ] Security scan: OWASP ZAP baseline + npm audit + composer audit
- [ ] Final V1 migration dry-run على snapshot + validation checklist
- [ ] UAT session (2 ساعات مع صاحب المشروع) على 5 سيناريوهات

**Definition of Done:**
- Admin dashboard يفتح في < 1.5s ويعرض 6 KPIs محدّثة.
- CSV export لـ attendance شهر كامل يُنزَّل خلال 5 ثوان.
- تعديل بيانات موظف يظهر في audit log خلال ثوان.
- تثبيت PWA على iPhone/Android يعمل، الأيقونة تظهر في home screen.
- k6: 95th percentile response time < 300ms عند 100 users.
- كل checklist [`00-overview.md#7`](00-overview.md) ✅.

---

## 3. Definition of Done for Phase 1 (Master Checklist)

الـ MVP جاهز للـ production عند تحقيق كل ما يلي (مستمدّ من SRS 80 + قرارات هذا الحزمة):

### 3.1 Functional (مطابقة قسم 7.1 من [`00-overview.md`](00-overview.md))
- [ ] Auth: employee_number/email login + password reset + logout + session invalidation
- [ ] RBAC: 5 أدوار seeded + permissions matrix مختبرة
- [ ] Employees: CRUD كامل + بحث + فلترة + avatar
- [ ] Org: Companies, Departments (tree), Teams, Positions CRUD
- [ ] Direct manager hierarchy + subordinates endpoint
- [ ] Work Schedules + Holidays CRUD
- [ ] Attendance Devices CRUD + QR live rotation
- [ ] QR scan مع geofence + IP whitelist + race lock
- [ ] Working Hours Engine ليلي يحتسب: total/late/early/overtime/absent/holiday/weekend
- [ ] Leave Types CRUD + Balances تلقائية + Annual rollover
- [ ] Leave Request submit → workflow → approve/reject → balance update
- [ ] Workflow Engine: 5 approver types يعملون + can_return/can_forward/SLA
- [ ] Request Types + Form Builder + 3 seeded types
- [ ] Request submit → workflow → approve/reject/return/forward → complete
- [ ] Tasks: CRUD + assignee + subtasks + status/priority + due dates
- [ ] Comments + @mentions + notifications
- [ ] Attachments (MinIO signed URLs)
- [ ] Task history (insert-only)
- [ ] Notification Center: in-app + SMS + preferences
- [ ] Realtime via Reverb (fallback polling)
- [ ] Employee Dashboard مع 6 كروت
- [ ] Admin Dashboard مع KPIs + charts
- [ ] Reports: attendance/leaves/requests → CSV
- [ ] Activity log عرض إداري مع filters
- [ ] PWA installable

### 3.2 Non-Functional
- [ ] Response time p95 < 300ms على reads، < 500ms على writes
- [ ] Login → dashboard TTFB < 1.5s على 3G
- [ ] Test coverage: ≥ 70% خطوط، ≥ 85% للـ Services
- [ ] Playwright E2E: 10 flows حرجة تمرّ
- [ ] Zero secrets في git — كل الـ credentials في env + Docker secrets
- [ ] SSL A+ على SSL Labs
- [ ] Rate limiting: 60 req/min عام، 5/min login/reset
- [ ] Security scan: OWASP ZAP baseline بدون high issues
- [ ] `composer audit` + `npm audit` نظيفة
- [ ] Backups يومية → BackBlaze B2 (أو مماثل خارج VPS)
- [ ] Health endpoint يفحص كل الخدمات
- [ ] Structured logs (JSON) على stdout
- [ ] Audit log insert-only (لا UPDATE/DELETE من التطبيق)

### 3.3 Operational
- [ ] GitHub Actions: lint + tests + build + push + deploy
- [ ] `docker compose up -d` من صفر يشغّل الكل
- [ ] Zero-downtime redeploy
- [ ] Runbooks كاملة (backup, deploy, restore, migrate, add employee)
- [ ] Onboarding videos (2-3 دقائق) لأهم 5 عمليات
- [ ] Uptime monitor مضبوط على `/api/health`
- [ ] V1 migration script مختبر بنجاح على snapshot حقيقي

### 3.4 Go-live Gate
- [ ] 100% من 3.1 ✅
- [ ] ≥ 95% من 3.2 ✅
- [ ] 100% من 3.3 ✅
- [ ] UAT signoff من صاحب المشروع على 5 سيناريوهات كاملة (attendance/leave approval/leave rejection/task create+comment/CSV export/new employee)

---

## 4. What Phase 2 / 3 / 4 Add

### Phase 2 — Projects & Scrum + Polish (8 أسابيع)
- Projects CRUD + Members + role-based access داخل مشروع
- Sprints + Product Backlog + Sprint Backlog
- Kanban board realtime (drag-drop عبر Reverb)
- Story Points + Estimated hours + Actual hours
- Burndown chart + Velocity chart
- Sprint Planning meeting UI (choose stories → commit)
- Sprint Review + Retrospective notes
- Task advanced: subtasks unlimited depth، dependencies (blocks/blocked_by)
- Custom task_statuses per project
- Excel export (`maatwebsite/excel`)
- PDF export (`spatie/browsershot`)
- WhatsApp channel للـ notifications
- Email templates (transactional)
- Global search (Meilisearch + Laravel Scout)
- Horizon dashboard

### Phase 3 — AI + Executive (6 أسابيع)
- AI abstraction + Anthropic Claude integration
- `AIProvider` interface + `AnthropicProvider`
- Token/cost tracking + monthly budget circuit breaker
- Daily Motivation (scheduled 08:00 لكل موظف بحسب preferences)
- Manager Assistant (ملخّص فريق + توصيات قرار)
- Task Helper (اقتراح breakdown، إعادة صياغة)
- Executive Dashboard (KPIs متعمّقة، heatmaps، مقارنات team-vs-team)
- Anomaly detection (موظف بحضور منخفض مفاجئ، فريق بإنتاجية متراجعة)

### Phase 4 — Scale + Polish + Mobile (4+ أسابيع)
- Performance reviews + KPIs محسوبة (attendance rate, task completion, sprint delivery)
- Employee goals + OKRs
- 2FA (TOTP)
- SSO (SAML) عبر SimpleSAMLphp أو Auth0
- Multi-company (SaaS) — tenant scoping middleware + billing hooks
- React Native mobile app (Auth, Attendance, Tasks, Notifications, Requests view)
- i18n كامل (English + Arabic)
- Advanced audit dashboard (queryable)
- Data export لأغراض GDPR-like
- Data anonymization tool

---

## 5. Cross-Reference (خرائط الاعتماد)

- **M1** يوفّر: bootstrapping الكامل + Auth → مطلوب لكل ما بعده
- **M2** يوفّر: Employees + Org → مطلوب لـ M3, M4, M5, M6, M7, M8
- **M3** يستخدم: Employees, WorkSchedules → مطلوب لـ M7 (Dashboard)
- **M4** يستخدم: Employees، initial workflow (بسيط) → يُعاد فيه في M5
- **M5** يستخدم: Employees + LeaveTypes (يعيد ربطها بـ Workflow) → مطلوب لـ M8 (Admin Dashboard)
- **M6** يستخدم: Employees + Users → مطلوب لـ M7 (Dashboard)
- **M7** يستخدم: كل ما سبق + MTC SMS → مطلوب لـ M8 (تكامل)
- **M8** يستهلك كل الوحدات + يضيف polish

**تعديل الجدول ممكن لكن حذر:**
- M3 و M4 قد يتوازيان لو الفريق ≥ 3.
- M5 لا يبدأ قبل M2 + M4 (Workflow يحتاج Employees و Leave use case).
- M6 يمكن أن يبدأ متوازياً مع M5 لو مطوّر مختلف.

---

## 6. Risk Register لـ Phase 1

| # | خطر                                                                 | احتمال | أثر  | تخفيف                                                                       |
| - | ------------------------------------------------------------------- | ------ | ---- | --------------------------------------------------------------------------- |
| 1 | Scope creep (SRS طويل، المستخدم قد يطلب "just add X")                 | عالي   | عالي | تجميد نهائي بعد M1 + كل إضافة → Phase 2 backlog                              |
| 2 | Docker+Windows dev environment بطيء                                  | متوسط  | متوسط | استخدام WSL2 + توثيق واضح + خيار Sail                                       |
| 3 | Reverb يتوقّف بلا مراقبة → لا realtime                                | متوسط  | متوسط 3 | health check + auto-restart + fallback polling                              |
| 4 | Sanctum SPA + Next.js SSR cookies معقّد                              | متوسط  | عالي | Playwright test لـ auth flow في M1 + توثيق SESSION_DOMAIN                    |
| 5 | Race conditions في attendance/leave balance                          | متوسط  | عالي | `SELECT FOR UPDATE` في كل نقاط الحرج + integration test                     |
| 6 | V1 data migration يكشف بيانات فاسدة                                  | عالي   | متوسط | seeder dry-run + exception report + fallback يدوي                            |
| 7 | Deptrac يُبطئ التطوير (false positives)                              | منخفض  | منخفض | ضبط قواعد deptrac بحسب الاستخدام الفعلي؛ فحص فقط في CI                       |
| 8 | Cron جدول العمل الليلي يفشل بصمت                                     | متوسط  | عالي | Sentinel: check `attendances_recomputed_at` كل صباح؛ لو غائب → تنبيه         |
| 9 | Manager يريد تعديل approval عبر البدائل أثناء إجازته                 | متوسط  | متوسط | `can_forward` في workflow + concept "delegate" في M5                          |
| 10 | 12 أسابيع ضيّق لفريق 2                                              | عالي   | عالي | مؤشّرات أسبوعية + إعادة تقييم بعد M4؛ إمكانية تأجيل PWA/audit polish لبعد الإطلاق |

---

## 7. مؤشّرات النجاح الأسبوعية

كل أسبوع يجب أن يُقاس بـ:
- **Velocity**: story points/tasks مكتملة vs. مخطّطة
- **CI health**: نسبة الـ builds الخضراء
- **Test coverage delta**: هل ارتفعت أم انخفضت؟
- **Bug count**: مفتوحة vs. مغلقة
- **Runbook coverage**: كم عملية موثّقة

تُراجَع أسبوعياً في stand-up قصير مع صاحب المشروع (30 دقيقة).

---

## 8. تاريخ التعديلات
| الإصدار | التاريخ    | التغيير              |
| ------- | ---------- | -------------------- |
| 1.0     | 2026-09-07 | الإصدار الأول        |
