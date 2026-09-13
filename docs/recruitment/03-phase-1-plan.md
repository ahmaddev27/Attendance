# Recruitment Module — Phase 1 Execution Plan

> **الوثيقة رقم 4**
> النطاق: خطة تنفيذية بمهام مرقّمة، RBAC matrix، جدول API endpoints، خطة اختبار، wireframes.
> السابق: [`02-erd.md`](02-erd.md)

---

## 1. Phase 1 Scope Recap

**المدة المتوقعة:** أسبوعان إلى ثلاثة أسابيع من التطوير المتواصل.

**المخرجات:**
- CRUD كامل لـ Leads / Clients / Cases / Jobs
- Kanban view للـ Leads
- Lead → Client Conversion (bundle: Client + Case + Jobs)
- Pipeline Engine (بدون تفعيل مراحل Screening/Interview — تظهر لكن لا logic)
- Auto Task Handoff بين المراحل المفعّلة (Publish, Contracting)
- RBAC كامل (16 permission جديد)
- Dashboard أساسي (counts + funnel)
- CSV Export
- Audit logging

**غير مشمول:**
- BrightGaza integration
- Applicant/Candidate management
- Screening/Shortlist/Interview UI (يبقى للـ Phase 2)
- Contract management (يبقى للـ Phase 3)

---

## 2. Task Breakdown

### 2.1 Backend Tasks (الترتيب مهم)

| # | المهمة | الملفات | التقدير |
|---|--------|---------|---------|
| B1 | Migrations (10 ملفات) | `database/migrations/2026_10_01_*` | 4h |
| B2 | Models + Relationships | `app/Models/{Lead,LeadActivity,Client,ClientContact,RecruitmentCase,JobRequirement,RecruitmentPipeline,RecruitmentPipelineStage}.php` | 3h |
| B3 | Enums | `app/Shared/Enums/{LeadStatus,LeadSource,ClientStatus,CaseStatus,JobStatus,StageOwnerRule,TaskEntityType}.php` | 2h |
| B4 | Seeders (Permissions + Pipeline + Settings) | `database/seeders/Recruitment*Seeder.php` | 2h |
| B5 | Repositories (6 classes) | `app/Modules/Recruitment/Repositories/*` | 4h |
| B6 | Form Requests (validation, ~15 classes) | `app/Modules/Recruitment/Requests/*` | 4h |
| B7 | Resources (8 classes) | `app/Modules/Recruitment/Resources/*` | 3h |
| B8 | LeadService + LeadActivityService | `app/Modules/Recruitment/Services/*` | 4h |
| B9 | LeadConversionService (⭐ الأثقل) | نفسه | 5h |
| B10 | ClientService + ContactService + CaseService + JobRequirementService | نفسه | 6h |
| B11 | PipelineTaskGeneratorService + Event Listener | نفسه + `Events/*` | 5h |
| B12 | Controllers (9 controllers) | `app/Modules/Recruitment/Controllers/*` | 5h |
| B13 | Route registration + middleware | `routes/api.php` | 1h |
| B14 | NotificationService methods (6 new templates) | `app/Modules/Notifications/Services/NotificationService.php` | 3h |
| B15 | Audit Observers | `app/Modules/Recruitment/Observers/*` | 2h |
| B16 | Recruitment Dashboard Service | نفسه | 3h |
| B17 | CSV Export | `app/Modules/Recruitment/Services/RecruitmentExportService.php` | 2h |
| B18 | Feature Tests (~30 test) | `tests/Feature/Recruitment/*` | 8h |
| B19 | Scheduled Job: SLA breach + Stale lead follow-up | `app/Console/Commands/{ScanRecruitmentSla,ScanStaleLeads}.php` + `routes/console.php` | 2h |

**Backend Total:** ~68 ساعة

### 2.2 Frontend Tasks

| # | المهمة | الملفات | التقدير |
|---|--------|---------|---------|
| F1 | API client layer | `apps/web/src/lib/api/recruitment.ts` + types | 3h |
| F2 | Leads List page + filters | `apps/web/src/app/(dashboard)/recruitment/leads/page.tsx` | 4h |
| F3 | Leads Kanban view (drag-drop) | `.../leads/kanban/page.tsx` | 5h |
| F4 | Lead Detail (contact, activities, timeline) | `.../leads/[id]/page.tsx` | 5h |
| F5 | Create/Edit Lead dialog | `.../leads/_components/lead-form-dialog.tsx` | 3h |
| F6 | Lead → Client Conversion wizard | `.../leads/[id]/convert/page.tsx` | 6h |
| F7 | Add Activity dialog | `.../leads/_components/add-activity-dialog.tsx` | 2h |
| F8 | Clients List + Profile page | `.../clients/page.tsx`, `.../clients/[id]/page.tsx` | 5h |
| F9 | Client Contacts management | `.../clients/[id]/_components/contacts-panel.tsx` | 3h |
| F10 | Cases list + Detail | `.../cases/page.tsx`, `.../cases/[id]/page.tsx` | 4h |
| F11 | Jobs list + Detail with pipeline visual | `.../jobs/page.tsx`, `.../jobs/[id]/page.tsx` | 6h |
| F12 | Job Stage Advance dialog | `.../jobs/[id]/_components/advance-stage-dialog.tsx` | 3h |
| F13 | Pipelines Admin (list + edit stages) | `.../pipelines/**` | 5h |
| F14 | Recruitment Dashboard | `.../dashboard/page.tsx` | 4h |
| F15 | Sidebar entries + RBAC gates | `src/components/admin-sidebar.tsx` | 1h |
| F16 | Arabic translations (~40 strings) | `src/lib/i18n/ar/recruitment.ts` | 2h |
| F17 | Mobile-responsive audit | كل الصفحات | 3h |

**Frontend Total:** ~64 ساعة

### 2.3 QA & Integration

| # | المهمة | التقدير |
|---|--------|---------|
| Q1 | Manual smoke test full journey (Lead→Convert→Job→Publish task) | 3h |
| Q2 | RBAC verification (5 sample users × 16 permissions) | 2h |
| Q3 | Notification delivery test (in-app + email) | 2h |
| Q4 | CSV export validation (edge cases: empty, filtered, large) | 1h |
| Q5 | Migration reversibility test (down + fresh) | 1h |

**QA Total:** ~9 ساعة

**الإجمالي: ~140 ساعة ≈ 3.5 أسابيع لمطور واحد.**

---

## 3. API Endpoints

### 3.1 Leads

| Method | Route | Controller | Permission | ملاحظات |
|--------|-------|-----------|------------|---------|
| GET    | `/api/leads` | `LeadController@index` | `view-leads` | filters: status, owner_id, source, search |
| POST   | `/api/leads` | `LeadController@store` | `manage-leads` | rate-limited 30/hour/user |
| GET    | `/api/leads/{lead}` | `LeadController@show` | `view-leads` | |
| PATCH  | `/api/leads/{lead}` | `LeadController@update` | `manage-leads` | |
| DELETE | `/api/leads/{lead}` | `LeadController@destroy` | `manage-leads` | soft delete |
| GET    | `/api/leads/kanban` | `LeadController@kanban` | `view-leads` | grouped by status |
| POST   | `/api/leads/{lead}/convert` | `LeadController@convert` | `convert-leads` | payload: client + case + jobs |
| POST   | `/api/leads/{lead}/activities` | `LeadActivityController@store` | `manage-leads` | |
| GET    | `/api/leads/{lead}/activities` | `LeadActivityController@index` | `view-leads` | paginated |
| GET    | `/api/leads/export` | `LeadController@export` | `export-recruitment-data` | CSV |

### 3.2 Clients

| Method | Route | Controller | Permission |
|--------|-------|-----------|------------|
| GET    | `/api/clients` | `ClientController@index` | `view-clients` |
| POST   | `/api/clients` | `ClientController@store` | `manage-clients` |
| GET    | `/api/clients/{client}` | `ClientController@show` | `view-clients` |
| PATCH  | `/api/clients/{client}` | `ClientController@update` | `manage-clients` |
| DELETE | `/api/clients/{client}` | `ClientController@destroy` | `manage-clients` |
| GET    | `/api/clients/{client}/profile` | `ClientController@profile` | `view-clients` |
| POST   | `/api/clients/{client}/contacts` | `ClientContactController@store` | `manage-clients` |
| PATCH  | `/api/clients/{client}/contacts/{contact}` | `ClientContactController@update` | `manage-clients` |
| DELETE | `/api/clients/{client}/contacts/{contact}` | `ClientContactController@destroy` | `manage-clients` |

### 3.3 Recruitment Cases

| Method | Route | Controller | Permission |
|--------|-------|-----------|------------|
| GET    | `/api/recruitment-cases` | `RecruitmentCaseController@index` | `view-recruitment-cases` |
| POST   | `/api/recruitment-cases` | `RecruitmentCaseController@store` | `manage-recruitment-cases` |
| GET    | `/api/recruitment-cases/{case}` | `RecruitmentCaseController@show` | `view-recruitment-cases` |
| PATCH  | `/api/recruitment-cases/{case}` | `RecruitmentCaseController@update` | `manage-recruitment-cases` |
| DELETE | `/api/recruitment-cases/{case}` | `RecruitmentCaseController@destroy` | `manage-recruitment-cases` |
| GET    | `/api/clients/{client}/cases` | `RecruitmentCaseController@indexForClient` | `view-recruitment-cases` |

### 3.4 Job Requirements

| Method | Route | Controller | Permission |
|--------|-------|-----------|------------|
| GET    | `/api/jobs` | `JobRequirementController@index` | `view-jobs` |
| POST   | `/api/jobs` | `JobRequirementController@store` | `manage-jobs` |
| GET    | `/api/jobs/{job}` | `JobRequirementController@show` | `view-jobs` |
| PATCH  | `/api/jobs/{job}` | `JobRequirementController@update` | `manage-jobs` |
| DELETE | `/api/jobs/{job}` | `JobRequirementController@destroy` | `manage-jobs` |
| POST   | `/api/jobs/{job}/advance-stage` | `JobRequirementController@advanceStage` | `advance-job-stage` |
| POST   | `/api/jobs/{job}/cancel` | `JobRequirementController@cancel` | `manage-jobs` |
| GET    | `/api/recruitment-cases/{case}/jobs` | `JobRequirementController@indexForCase` | `view-jobs` |

### 3.5 Pipelines (Admin)

| Method | Route | Controller | Permission |
|--------|-------|-----------|------------|
| GET    | `/api/recruitment-pipelines` | `RecruitmentPipelineController@index` | `manage-recruitment-pipelines` |
| POST   | `/api/recruitment-pipelines` | `RecruitmentPipelineController@store` | `manage-recruitment-pipelines` |
| GET    | `/api/recruitment-pipelines/{pipeline}` | `RecruitmentPipelineController@show` | `manage-recruitment-pipelines` |
| PATCH  | `/api/recruitment-pipelines/{pipeline}` | `RecruitmentPipelineController@update` | `manage-recruitment-pipelines` |
| DELETE | `/api/recruitment-pipelines/{pipeline}` | `RecruitmentPipelineController@destroy` | `manage-recruitment-pipelines` |
| POST   | `/api/recruitment-pipelines/{pipeline}/stages` | `RecruitmentPipelineStageController@store` | `manage-recruitment-pipelines` |
| PATCH  | `/api/recruitment-pipelines/{pipeline}/stages/{stage}` | `RecruitmentPipelineStageController@update` | `manage-recruitment-pipelines` |
| DELETE | `/api/recruitment-pipelines/{pipeline}/stages/{stage}` | `RecruitmentPipelineStageController@destroy` | `manage-recruitment-pipelines` |
| POST   | `/api/recruitment-pipelines/{pipeline}/stages/reorder` | `RecruitmentPipelineStageController@reorder` | `manage-recruitment-pipelines` |

### 3.6 Dashboard

| Method | Route | Controller | Permission |
|--------|-------|-----------|------------|
| GET    | `/api/recruitment/dashboard/kpis` | `RecruitmentDashboardController@kpis` | `view-leads`\|`view-jobs` |
| GET    | `/api/recruitment/dashboard/funnel` | `RecruitmentDashboardController@funnel` | نفسه |
| GET    | `/api/recruitment/dashboard/leaderboard` | `RecruitmentDashboardController@leaderboard` | نفسه |

**إجمالي: ~40 endpoint جديد.**

---

## 4. RBAC Matrix — الصلاحيات الجديدة

| Permission | معنى | Recommended assignment |
|-----------|------|------------------------|
| `view-leads` | قراءة قوائم Leads | Sales, Recruitment Manager |
| `manage-leads` | CRUD Leads | Sales |
| `convert-leads` | تحويل Lead → Client | Sales Manager (خطوة تجارية حرجة) |
| `view-clients` | قراءة قوائم Clients | Sales, Recruitment, Account Management |
| `manage-clients` | CRUD Clients | Sales Manager, Account Manager |
| `view-recruitment-cases` | قراءة الحملات | Recruitment |
| `manage-recruitment-cases` | CRUD الحملات | Recruitment Manager |
| `view-jobs` | قراءة الوظائف | كل فريق التوظيف |
| `manage-jobs` | CRUD الوظائف | Recruitment |
| `advance-job-stage` | نقل الوظيفة بين المراحل | مالك المرحلة الحالية فقط (يُفحص إضافياً في الـ Service) |
| `publish-jobs` | تنفيذ مرحلة النشر | Publisher |
| `screen-candidates` | تنفيذ مرحلة الفرز (Phase 2) | Screening Officer |
| `schedule-interviews` | جدولة المقابلات (Phase 2) | Interview Coordinator |
| `prepare-contracts` | تحضير العقود (Phase 3) | Contracting Officer |
| `manage-recruitment-pipelines` | تعديل قوالب المراحل | Admin |
| `export-recruitment-data` | تصدير CSV مع PII | Manager (بأذن خاص) |

### 4.1 عينة أدوار مقترحة (Admin يبنيها من الصلاحيات)

| Role | Permissions |
|------|-------------|
| `sales_rep` | view-leads, manage-leads, view-clients |
| `sales_manager` | + convert-leads, manage-clients, export-recruitment-data |
| `recruitment_officer` | view-jobs, manage-jobs, view-clients, view-recruitment-cases, publish-jobs |
| `recruitment_manager` | + manage-recruitment-cases, advance-job-stage, export-recruitment-data |
| `account_manager` | view-clients, manage-clients, view-recruitment-cases, view-jobs |

**قاعدة:** هذه أمثلة توجيهية — الـ Admin ينشئها من `/admin/roles` ولا نصلّبها في seeder.

---

## 5. Notifications الجديدة

`NotificationService` يحصل على 6 methods جديدة، كل واحدة تفتح in-app + email حسب تفضيل المستخدم:

```php
public function leadCreated(Lead $lead, User $owner): void;
public function leadConverted(Lead $lead, Client $client, User $caseOwner): void;
public function leadStale(Lead $lead, int $daysSinceLastContact): void;
public function jobStageAdvanced(JobRequirement $job, PipelineStage $newStage, User $newOwner): void;
public function stageSlaBreached(JobRequirement $job, PipelineStage $stage, int $hoursOverdue): void;
public function taskAssignedForRecruitment(Task $task, User $assignee): void;  // wrapper — reuses existing taskAssigned template
```

**Deep-link pattern:** كل إشعار يحمل `link` يفتح مباشرة صفحة الكيان (`/recruitment/jobs/45`, `/recruitment/leads/12`).

---

## 6. Scheduled Jobs

في `routes/console.php`:

```php
// كل ساعة — يفحص الوظائف التي تجاوزت SLA للمرحلة الحالية
Schedule::command('recruitment:scan-sla')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// كل يوم 09:00 — Leads بدون activity منذ 7 أيام في حالة نشطة (contacted/qualified/negotiation)
Schedule::command('recruitment:scan-stale-leads')
    ->dailyAt('09:00')
    ->withoutOverlapping();
```

الأمرين ينفذان استعلامات محدودة (`stage_entered_at < NOW() - INTERVAL sla_hours HOUR` و `last_contact_at < NOW() - INTERVAL 7 DAY`) ثم ينشئون Notifications عبر `NotificationService`.

---

## 7. Test Plan

### 7.1 Feature Tests (المطلوب في Phase 1)

كل ملف تحت `tests/Feature/Recruitment/`:

| ملف | عدد الاختبارات | التغطية |
|-----|---------------|---------|
| `LeadCrudTest.php` | 6 | create/update/delete/list/filter/search |
| `LeadKanbanTest.php` | 2 | grouped counts / status transition via kanban |
| `LeadConversionTest.php` | 5 | successful convert / duplicate convert rejection / with reuse client / with N jobs / permission gate |
| `LeadActivityTest.php` | 3 | create call/meeting/note / auto-log on status change / list chronological |
| `ClientCrudTest.php` | 4 | create/update/soft delete/profile |
| `ClientContactsTest.php` | 3 | multiple contacts / primary uniqueness / cascade delete |
| `CaseCrudTest.php` | 3 | create under client / list per client / soft delete |
| `JobRequirementCrudTest.php` | 4 | create/update/delete/list |
| `JobStageAdvanceTest.php` | 6 | happy path / requires_fields validation / terminal stage rejection / auto-task creation / owner resolution / permission gate |
| `PipelineAdminTest.php` | 3 | create pipeline / reorder stages / prevent delete stage in use |
| `RbacRecruitmentTest.php` | 8 | permission gates on each critical endpoint |
| `SlaScanTest.php` | 2 | breach detection / notification fanout |
| `DashboardTest.php` | 2 | KPI counts / funnel breakdown |
| `ExportTest.php` | 2 | CSV shape / permission gate |

**Total:** ~53 test cases.

### 7.2 Smoke Test Journey (Manual)

1. تسجيل دخول كـ `sales_rep`
2. إضافة Lead جديد (LinkedIn) → يُخصَّص لي كـ owner
3. إضافة نشاط "meeting" → status ينتقل إلى `meeting_completed`
4. تسجيل دخول كـ `sales_manager` → تحويل Lead إلى Client + Case + وظيفة واحدة
5. التحقق من ظهور مهمة "Publish job: X" في `/my-tasks` لموظف بصلاحية `publish-jobs`
6. تسجيل دخول كـ `publisher` → تنفيذ المهمة → advance-stage مع `publication_url`
7. التحقق من انتقال Job إلى `receiving_apps` وإكمال Task تلقائياً
8. تسجيل دخول كـ `admin` → تصدير CSV للـ leads

---

## 8. Non-Functional Requirements

| البُعد | المتطلب |
|-------|---------|
| Performance | List endpoints تُرجع ≤ 500ms على 10K leads / 5K jobs |
| Pagination | افتراضي 25/صفحة، أقصى 100 |
| Caching | `settings` مسبقاً مكاش-مقنن (Redis) — لا نضيف طبقات جديدة الآن |
| Broadcasting | `JobStageAdvanced` يبث على قناة خاصة `recruitment.job.{job_id}` — Kanban يحدّث فوراً |
| Localization | كل الرسائل الجديدة مترجمة عربي |
| Timezone | كل التواريخ تُخزَّن UTC وتُعرَض بتوقيت المستخدم من `users.timezone` |
| RTL | كل صفحات الفرونت تدعم RTL (نفس النمط) |

---

## 9. UI Wireframes (Textual)

### 9.1 Leads Kanban

```
┌────────────────────────────────────────────────────────────────────────────┐
│  Leads                     [+ New Lead]     🔎 Search   ⚙️ Filters         │
├─────────┬─────────┬──────────────┬──────────────┬─────────────┬────────────┤
│ NEW (12)│CONTACTED│MEETING SCHED │  QUALIFIED   │  PROPOSAL   │ NEGOTIATION│
│         │  (8)    │    (5)       │    (7)       │    (3)      │    (2)     │
├─────────┼─────────┼──────────────┼──────────────┼─────────────┼────────────┤
│┌───────┐│┌───────┐│              │┌────────────┐│             │            │
││ ABC Tec│││ Kerten │              ││ Zain      ││             │            │
││ 5 devs │││ 20 hqs│              ││ 3 senior  ││             │            │
││ 📞Ali  │││📅TmrwG│              ││📞Manager Q││             │            │
│└───────┘│└───────┘│              │└────────────┘│             │            │
│┌───────┐│         │              │              │             │            │
││ Beta LL│         │              │              │             │            │
│└───────┘│         │              │              │             │            │
└─────────┴─────────┴──────────────┴──────────────┴─────────────┴────────────┘
```

- Drag card بين الأعمدة → PATCH `/leads/{id}` مع status الجديد
- Card يعرض: company_name, expected_hiring, owner initials, next_followup icon
- Click card → Lead Detail page

### 9.2 Job Detail — Pipeline Visual

```
┌─────────────────────────────────────────────────────────────────────────┐
│  Job #2026-0045 — Senior Backend Developer          [Advance Stage ▶] │
│  Client: ABC Technology · Case: Q1 Remote Hiring · Openings: 2         │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ✓ New ──── ✓ Publish ──── ● Receiving Apps ──── ○ Screening ──── ...  │
│                             (5 days remaining)                          │
│                                                                         │
├─────────────────────────────────────────────────────────────────────────┤
│  Description                                     │  Owner: Ahmad       │
│  Location: Remote                                │  Publication URL:   │
│  Salary: $1,500 – $2,000                         │  brightgaza.jo/... │
│  Skills: PHP, Laravel, PostgreSQL                │  Deadline: 30 Sep   │
│  ...                                             │                     │
├─────────────────────────────────────────────────────────────────────────┤
│  Activity Timeline                                                       │
│  · 2026-10-03 08:15  Ahmad created job                                  │
│  · 2026-10-03 09:00  Sara advanced to Publish                           │
│  · 2026-10-03 14:30  Sara advanced to Receiving Apps (URL added)        │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 10. Migration & Rollback Plan

### 10.1 Forward

الـ deploy (`.github/workflows/deploy.yml`) يشغّل `php artisan migrate --force` فقط ولا يشغّل أي seeder، ولا أحد يدخل السيرفر يدوياً. لذلك البيانات المرجعية تصل للإنتاج عبر migration:

```text
2026_10_01_100001 … 100010   → الجداول + tasks.entity_type/entity_id
2026_10_01_100011            → RecruitmentPermissionSeeder (16 صلاحية + منحها لـ super-admin)
                               + RecruitmentPipelineSeeder (فقط إن لم يوجد مسار بالرمز standard)
```

- المسار الافتراضي لا يأخذ `is_default` إن كان هناك مسار افتراضي آخر اختاره الـ Admin.
- قوائم الاختيار (العملات، المصادر، القطاعات، …) لا تحتاج seeding: قيمها الافتراضية في `App\Shared\Enums\OptionList`. `RecruitmentSettingsSeeder` حُذف.

### 10.2 Rollback

كل migration فيها `down()` — رجوع نظيف للحالة السابقة. `100011` له `down()` فارغ عمداً: حذف الصلاحيات يُسقط منحها لأدوار عدّلها الـ Admin.

اختبار Q5 مُنفّذ في `tests/Feature/Recruitment/RecruitmentMigrationsReversibleTest.php`: يعمل `migrate:rollback --path` للـ 11 migration، يتحقق من حذف الجداول وأعمدة `tasks`، ثم `migrate --path` ويتحقق من رجوعها ومن إعادة زرع المسار والصلاحيات. يمر على SQLite و MySQL 8.4.

**تحذير:** لا يمكن rollback بعد إنشاء بيانات production. الـ rollback فقط للـ CI/staging.

---

## 11. Metrics of Success

بعد أول شهر من الإطلاق:

| KPI | Target | Measurement |
|-----|--------|-------------|
| Leads captured | 50+ | `COUNT(*) FROM leads` |
| Lead → Client conversion | ≥ 15% | ratio |
| Jobs opened | 20+ | filter status='active' |
| Time-in-stage average | ≤ SLA | via `stage_entered_at` |
| SLA breach rate | ≤ 10% | count of breached / total transitions |
| Auto-tasks created | 40+ | join `tasks.entity_type = 'job_requirement'` |
| Users trained | 100% of sales+recruitment | training log |

---

## 12. Rollout Steps

1. **Week 1**: Backend Migrations + Models + Repositories + LeadService + LeadConversionService + Feature tests للـ Leads
2. **Week 2**: Client/Case/Job services + Pipeline engine + Auto Task generation + Notifications
3. **Week 3**: Frontend Leads (List + Kanban + Detail + Convert) + Clients + Cases + Jobs
4. **Week 4** (buffer): Dashboard + Pipeline Admin + CSV Export + QA + Docs + Training

**Deployment:** عبر نفس GitHub Actions المستخدمة الآن. لا تغيير في infra.

---

## 13. Post-Launch — تحضيرات Phase 2

بمجرد استقرار Phase 1 (~2-3 أسابيع بعد الإطلاق)، نبدأ Phase 2:

- جدول `candidates` + `candidate_applications`
- CSV Import للـ applicants
- Screening form (configurable) + Shortlist logic
- Interview scheduling + feedback capture
- تفعيل مراحل Pipeline المؤجّلة (Screening, Interviewing)

---

**نهاية حزمة تخطيط Recruitment Module — Phase 1.**
