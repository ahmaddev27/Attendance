# Recruitment Module — Phase 2 Execution Plan

> **الوثيقة رقم 6**
> النطاق: ATS Core — Candidate Database، CSV Import، Screening form، Shortlist، Interview Scheduling & Feedback، وتفعيل مراحل Pipeline المؤجَّلة من Phase 1.
> السابق: [`03-phase-1-plan.md`](03-phase-1-plan.md) · [`04-brightgaza-integration.md`](04-brightgaza-integration.md)
> المرجع المعماري: [`00-overview.md §4`](00-overview.md) (Phase 2 scope) · [`01-architecture.md §3`](01-architecture.md) (Pipeline Engine)

---

## 1. Executive Summary

Phase 2 يحوّل Recruitment من **CRM/Sales Pipeline** (المسلَّم في Phase 1) إلى **ATS فعلي** يُدار فيه المرشّحون داخل النظام من لحظة التقديم حتى قرار العميل. يبني على البنية التحتية الجاهزة (Pipeline Engine, Task Handoff, RBAC, OptionLists, AuditLogs) دون لمس أي من جداول Phase 1 المرحَّلة إلى الإنتاج.

**ما يُسلَّم في Phase 2:**

- **Candidate Database** — بنك مرشّحين مشترك عبر كل الوظائف، قابل للبحث وإعادة الاستخدام.
- **CSV/Excel Applicant Import** — رفع ملف دفعي لربط مرشّحين موجودين أو إنشاء جُدد وربطهم بوظيفة محدَّدة، مع dry-run و per-row error reporting.
- **Candidate Applications** — جدول ربط Candidate ↔ JobRequirement يحمل حالة المرشّح داخل Pipeline وظيفة بعينها.
- **Screening Form** — Scorecard قابل للتخصيص لكل Pipeline على مستوى الـ Stage، يملؤه Screening Officer ويُنتج `overall_score` + `passed` boolean.
- **Shortlist UI** — تأشير مرشّحين داخل الـ Application نفسها (flag + `shortlisted_at`) — بدون جدول جديد.
- **Interview Scheduling** — جدولة مقابلات داخلية (TAQAT) ومقابلات العميل، مع رابط الاجتماع والمكان والمدة.
- **Interview Feedback Capture** — Scorecard للمقابلة مع `recommendation` وملاحظات، يُقدّمه كل مُقابِل مستقلاً.
- **تفعيل مراحل Pipeline المؤجّلة** — المراحل `screening`, `shortlist`, `interviewing` من الـ Standard Pipeline تصبح قابلة للعمل الفعلي (`requires_fields` تتحقّق فعلاً، أدوار المالكين تستلم مهام حقيقية).

**ما يُؤجَّل:**

| المؤجَّل | إلى | السبب |
|---------|-----|-------|
| Contract preparation + signed contracts | Phase 3 | عملية حقوقية مستقلّة تحتاج قوالب PDF + e-signature |
| BrightGaza REST API (two-way) | Phase 4 | BrightGaza public-pull موجود في Phase 1؛ القياسي الآن لا API |
| AI CV Screening / Candidate Matching | Phase 4 | يحتاج vector store + prompt engineering منفصل |
| تسجيل مكالمات الفيديو داخل النظام | غير مخطَّط | خارج النطاق — نُسجّل الرابط فقط |
| Candidate Portal (مرشّح يسجّل بنفسه) | Phase 4 | يحتاج Auth منفصل للمرشّحين (ليس User) |

**الحجم المتوقع:** ~140 ساعة تطوير (3–3.5 أسابيع لمطوّر واحد بنفس وتيرة Phase 1).

---

## 2. Domain Model

### 2.1 الكيانات الجديدة

| الكيان | التعريف | العلاقات |
|-------|---------|---------|
| `Candidate` | شخص موجود في بنك المرشّحين — مشترك عبر كل الوظائف | `hasMany CandidateApplication` |
| `CandidateApplication` | تقديم مرشّح على وظيفة محدَّدة — pivot غني بالحقول | `belongsTo Candidate, JobRequirement, RecruitmentPipelineStage` |
| `CandidateScreening` | نموذج فرز واحد لكل Application | `belongsTo CandidateApplication, User` (scored_by) |
| `Interview` | مقابلة واحدة مجدولة لمرشّح على وظيفة | `belongsTo CandidateApplication, User` (created_by) · `hasMany InterviewFeedback` |
| `InterviewFeedback` | تقييم مُقابل واحد لمقابلة واحدة (N مُقابلين → N تقييمات) | `belongsTo Interview, User` (interviewer) |

**ملاحظة:** Shortlist **ليس جدولاً مستقلاً** — إنما حقلان على `candidate_applications` (`is_shortlisted`, `shortlisted_at`, `shortlisted_by_user_id`). راجع §3.4.

### 2.2 ERD Snapshot

```
┌──────────────────────┐
│  candidates          │        app-wide pool
│  (shared pool)       │
└──────────┬───────────┘
           │ 1:n
           ▼
┌──────────────────────────────────┐        ┌────────────────────────────┐
│  candidate_applications          │◄───1:n─│  job_requirements          │
│                                  │        │  (Phase 1 — unchanged)     │
│  current_stage_id ──► stages     │        └────────────────────────────┘
│  is_shortlisted (flag)           │
└──────────┬───────────┬───────────┘
           │ 1:1       │ 1:n
           ▼           ▼
┌──────────────────────┐  ┌──────────────────────┐
│ candidate_screenings │  │  interviews          │
│  (scorecard JSON)    │  │  kind: internal|client│
└──────────────────────┘  └──────────┬───────────┘
                                     │ 1:n
                                     ▼
                          ┌──────────────────────────┐
                          │  interview_feedbacks     │
                          │  (per interviewer)       │
                          └──────────────────────────┘
```

### 2.3 توضيح العلاقات الحرجة

- **Candidate ↔ JobRequirement = Many-to-Many عبر `candidate_applications`.** نفس المرشّح قد يتقدّم على عدة وظائف خلال السنة؛ قاعدة `UNIQUE(candidate_id, job_requirement_id)` تمنع التكرار داخل نفس الوظيفة.
- **CandidateApplication يحمل مرحلته الخاصة.** حقل `current_stage_id` على مستوى الـ Application، منفصلاً عن `job_requirements.current_stage_id` (الذي يبقى يمثّل مرحلة الوظيفة كـ aggregate — نشر، استقبال، إقفال). المرشّح قد يكون في `screening` بينما وظيفته في `interviewing` لأن مرشّحاً آخر وصل أبكر.
- **CandidateScreening = 1:1 مع Application.** صف واحد لكل Application. إعادة الفرز تُنتج version جديد عبر `UPDATE` نفس الصف وتسجيل `scored_at` الجديد (لا تاريخ versions في Phase 2).
- **Interview = N لكل Application.** مقابلة أولى داخلية، ثم مقابلة ثانية مع العميل، إلخ.
- **InterviewFeedback = N لكل Interview.** مقابلة لجنة فيها 3 مُقابلين تُنتج 3 صفوف. `UNIQUE(interview_id, interviewer_user_id)` يمنع تكرار نفس المُقابل.

---

## 3. Architectural Decisions

### 3.1 القرارات المحمولة من Phase 1 (لا نُعيد مناقشتها)

كل القرارات الـ 12 في [`00-overview.md §3`](00-overview.md) و [`01-architecture.md §2`](01-architecture.md) تنطبق كما هي:

1. Modular Monolith (نفس الـ API)
2. Pipeline Engine المستقل عن Workflow
3. Task Engine مع `entity_type/entity_id` polymorphic
4. Individual Permissions (لا Roles جديدة)
5. Configure, Don't Code
6. Services → Repositories → Models
7. Events بعد commit الـ transaction
8. OptionList pattern للقوائم القابلة للتعديل
9. Audit logs عبر `LogsActivity` على كل Model جديد
10. Soft delete على الكيانات التاريخية فقط
11. البيانات المرجعية تصل عبر migrations (لا seeders منفصلة في الإنتاج)
12. Signed URLs للمرفقات الخاصة (CVs)

### 3.2 القرارات الجديدة الخاصة بـ Phase 2

| # | القرار | المبرّر |
|---|-------|---------|
| D1 | **Candidate مشترك عبر كل الوظائف** (ليس per-job) | المرشّح الذي تقدّم لـ Senior Backend في Q1 قد يصلح لـ Full-stack في Q3؛ تكرار بياناته عبث. |
| D2 | **Shortlist = Flag على Application** (لا جدول منفصل) | Shortlist = view على `candidate_applications WHERE is_shortlisted = true`. جدول منفصل يضاعف الكتابة بدون فائدة. |
| D3 | **Screening Schema per Pipeline Stage** (ليس per JobRequirement) | قالب فرز "Backend Developer" ثابت عبر نفس النوع من الوظائف. Admin يعدّل القالب في `recruitment_pipeline_stages.screening_schema` (JSON). |
| D4 | **Interview = polymorphic kind** (`internal` \| `client`) بدل جدولين | البنية واحدة؛ الاختلاف فقط في من يحضر والـ visibility. enum على `kind` كافٍ. |
| D5 | **InterviewFeedback صف لكل مُقابل** (ليس JSON داخل Interview) | audit per-interviewer، permission check per-feedback، و calc لـ `average_score` بـ SQL بسيط. |
| D6 | **CandidateApplication تحمل `current_stage_id` خاصّتها** | راجع §2.3 — يسمح بتعدد المرشّحين في مراحل مختلفة داخل نفس الوظيفة. |
| D7 | **لا جدول `candidate_notes` منفصل في Phase 2** | ملاحظات على `candidates` و `candidate_applications` كـ TEXT. تاريخ منفصل يُؤجّل إلى Phase 3. |
| D8 | **CSV Import يستخدم Queue Job** (ليس inline) | ملف من 500 صف قد يستغرق 30s؛ Dry-run فقط inline. |
| D9 | **مرفقات CV على `local` disk + Signed URLs** | PII حرج، نفس نمط leave-attachments. |
| D10 | **Interview reminder = Scheduled Command** يومي 08:00 + 1h قبل | نفس نمط `recruitment:scan-sla` الحالي. |
| D11 | **تعديل واحد فقط على `recruitment_pipeline_stages`** — إضافة `screening_schema` JSON | باقي خصائص المرحلة تعمل كما هي من Phase 1. |
| D12 | **تفعيل `requires_fields` للمراحل المؤجّلة يتم بتعديل Seed قيم** (not new logic) | `PipelineTaskGenerator` و `JobRequirementService::advanceStage()` يفحصان `requires_fields` بالفعل من Phase 1. |

**ما لا يتغيّر من Phase 1:** جداول `leads`/`clients`/`cases`/`jobs`/`pipelines` بلا migrations جديدة؛ `pipeline_stages` migration واحدة لإضافة عمود؛ جميع endpoints Phase 1 تعمل كما هي.

---

## 4. ERD + DDL

### 4.1 `candidates` — بنك المرشّحين المشترك

```sql
CREATE TABLE candidates (
    id                   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    candidate_number     VARCHAR(20) NOT NULL UNIQUE,     -- 'CAN-YYYY-####'

    full_name            VARCHAR(200) NOT NULL,
    email                VARCHAR(150) NULL,
    phone                VARCHAR(30)  NULL,
    country              VARCHAR(100) NULL,
    city                 VARCHAR(100) NULL,
    linkedin_url         VARCHAR(255) NULL,
    portfolio_url        VARCHAR(255) NULL,

    resume_path          VARCHAR(500) NULL,              -- private disk
    resume_uploaded_at   TIMESTAMP NULL,

    status               VARCHAR(30) NOT NULL DEFAULT 'active',
                         -- 'active' | 'blacklisted' | 'placed' | 'inactive'
    source               VARCHAR(50)  NULL,              -- 'csv_import' | 'manual' | 'brightgaza' | 'referral'
    source_reference     VARCHAR(100) NULL,

    headline             VARCHAR(200) NULL,
    years_of_experience  SMALLINT UNSIGNED NULL,
    current_title        VARCHAR(150) NULL,
    current_company      VARCHAR(150) NULL,
    expected_salary_min  DECIMAL(10,2) NULL,
    expected_salary_max  DECIMAL(10,2) NULL,
    salary_currency      CHAR(3) NULL DEFAULT 'USD',
    availability         VARCHAR(50)  NULL,              -- 'immediate'|'2_weeks'|'1_month'|'negotiable'

    skills               JSON NULL,                      -- ['PHP','Laravel',...]
    languages            JSON NULL,

    notes                TEXT NULL,
    created_by_user_id   BIGINT UNSIGNED NOT NULL,

    created_at           TIMESTAMP NULL,
    updated_at           TIMESTAMP NULL,
    deleted_at           TIMESTAMP NULL,

    CONSTRAINT fk_candidates_created_by
        FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,

    INDEX idx_candidates_status (status),
    INDEX idx_candidates_email  (email),
    INDEX idx_candidates_phone  (phone),
    INDEX idx_candidates_source (source)
);
```

**قرارات:** `email`/`phone` ليسا UNIQUE قاعدياً (قد يفتقدهما المرشّح، أو يُدخَل مرتين عن قصد — dedup في CSV import soft). `source` كـ string (نفس نمط `leads.source`). cover letter منفصل يُؤجَّل إلى Phase 3 إن لزم.

### 4.2 `candidate_applications` — Pivot غني

```sql
CREATE TABLE candidate_applications (
    id                       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_number       VARCHAR(20) NOT NULL UNIQUE,    -- 'APP-YYYY-#####'

    candidate_id             BIGINT UNSIGNED NOT NULL,
    job_requirement_id       BIGINT UNSIGNED NOT NULL,
    current_stage_id         BIGINT UNSIGNED NOT NULL,       -- own stage pointer (D6)

    status                   VARCHAR(30) NOT NULL DEFAULT 'applied',
                             -- applied|in_screening|screened_in|screened_out|shortlisted
                             -- |interviewing|client_review|offered|rejected|withdrawn|hired

    source                   VARCHAR(50) NOT NULL,            -- 'manual'|'csv_import'|'brightgaza'
    applied_at               TIMESTAMP NOT NULL,

    is_shortlisted           BOOLEAN NOT NULL DEFAULT FALSE,  -- D2 (flag, no separate table)
    shortlisted_at           TIMESTAMP NULL,
    shortlisted_by_user_id   BIGINT UNSIGNED NULL,

    rejected_at              TIMESTAMP NULL,
    rejected_by_user_id      BIGINT UNSIGNED NULL,
    rejection_reason         VARCHAR(255) NULL,
    rejection_stage_code     VARCHAR(50) NULL,

    notes                    TEXT NULL,
    stage_entered_at         TIMESTAMP NOT NULL,

    created_at               TIMESTAMP NULL,
    updated_at               TIMESTAMP NULL,
    deleted_at               TIMESTAMP NULL,

    CONSTRAINT fk_applications_candidate       FOREIGN KEY (candidate_id)           REFERENCES candidates(id)                    ON DELETE RESTRICT,
    CONSTRAINT fk_applications_job             FOREIGN KEY (job_requirement_id)     REFERENCES job_requirements(id)              ON DELETE RESTRICT,
    CONSTRAINT fk_applications_stage           FOREIGN KEY (current_stage_id)       REFERENCES recruitment_pipeline_stages(id)   ON DELETE RESTRICT,
    CONSTRAINT fk_applications_shortlisted_by  FOREIGN KEY (shortlisted_by_user_id) REFERENCES users(id)                         ON DELETE SET NULL,
    CONSTRAINT fk_applications_rejected_by     FOREIGN KEY (rejected_by_user_id)    REFERENCES users(id)                         ON DELETE SET NULL,

    UNIQUE idx_applications_candidate_job (candidate_id, job_requirement_id),
    INDEX idx_applications_job_status     (job_requirement_id, status),
    INDEX idx_applications_shortlisted    (job_requirement_id, is_shortlisted),
    INDEX idx_applications_stage_entered  (stage_entered_at)
);
```

### 4.3 `candidate_screenings` — Scorecard لكل Application

```sql
CREATE TABLE candidate_screenings (
    id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id     BIGINT UNSIGNED NOT NULL UNIQUE,   -- 1:1 with Application
    scored_by_user_id  BIGINT UNSIGNED NOT NULL,

    scorecard          JSON NOT NULL,                    -- answers vs stage.screening_schema
    overall_score      DECIMAL(5,2) NULL,                -- 0.00–100.00
    passed             BOOLEAN NOT NULL DEFAULT FALSE,
    recommendation     VARCHAR(30) NULL,                 -- 'advance'|'reject'|'hold'
    notes              TEXT NULL,
    scored_at          TIMESTAMP NOT NULL,

    created_at         TIMESTAMP NULL,
    updated_at         TIMESTAMP NULL,

    CONSTRAINT fk_screenings_application FOREIGN KEY (application_id)    REFERENCES candidate_applications(id) ON DELETE CASCADE,
    CONSTRAINT fk_screenings_scored_by   FOREIGN KEY (scored_by_user_id) REFERENCES users(id)                  ON DELETE RESTRICT,

    INDEX idx_screenings_passed (passed)
);
```

### 4.4 `interviews`

```sql
CREATE TABLE interviews (
    id                     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    interview_number       VARCHAR(20) NOT NULL UNIQUE,       -- 'INT-YYYY-#####'

    application_id         BIGINT UNSIGNED NOT NULL,
    kind                   VARCHAR(20) NOT NULL,              -- 'internal' | 'client' (D4)

    scheduled_at           TIMESTAMP NOT NULL,
    duration_minutes       SMALLINT UNSIGNED NOT NULL DEFAULT 60,
    timezone               VARCHAR(50) NOT NULL DEFAULT 'Asia/Gaza',

    location               VARCHAR(255) NULL,
    meeting_url            VARCHAR(500) NULL,
    meeting_notes          TEXT NULL,

    status                 VARCHAR(30) NOT NULL DEFAULT 'scheduled',
                           -- 'scheduled'|'completed'|'cancelled'|'no_show'|'rescheduled'
    cancelled_reason       VARCHAR(255) NULL,
    rescheduled_from_id    BIGINT UNSIGNED NULL,              -- self-ref on reschedule

    created_by_user_id     BIGINT UNSIGNED NOT NULL,

    created_at             TIMESTAMP NULL,
    updated_at             TIMESTAMP NULL,
    deleted_at             TIMESTAMP NULL,

    CONSTRAINT fk_interviews_application        FOREIGN KEY (application_id)      REFERENCES candidate_applications(id) ON DELETE RESTRICT,
    CONSTRAINT fk_interviews_created_by         FOREIGN KEY (created_by_user_id)  REFERENCES users(id)                  ON DELETE RESTRICT,
    CONSTRAINT fk_interviews_rescheduled_from   FOREIGN KEY (rescheduled_from_id) REFERENCES interviews(id)             ON DELETE SET NULL,

    INDEX idx_interviews_application (application_id),
    INDEX idx_interviews_scheduled   (scheduled_at),
    INDEX idx_interviews_kind_status (kind, status)
);
```

### 4.5 `interview_feedbacks` — N لكل Interview

```sql
CREATE TABLE interview_feedbacks (
    id                     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    interview_id           BIGINT UNSIGNED NOT NULL,
    interviewer_user_id    BIGINT UNSIGNED NOT NULL,

    scorecard              JSON NOT NULL,
    overall_score          DECIMAL(5,2) NULL,
    recommendation         VARCHAR(20) NOT NULL,             -- 'strong_hire'|'hire'|'maybe'|'no_hire'
    strengths              TEXT NULL,
    weaknesses             TEXT NULL,
    notes                  TEXT NULL,

    submitted_at           TIMESTAMP NOT NULL,
    created_at             TIMESTAMP NULL,
    updated_at             TIMESTAMP NULL,

    CONSTRAINT fk_feedbacks_interview    FOREIGN KEY (interview_id)        REFERENCES interviews(id) ON DELETE CASCADE,
    CONSTRAINT fk_feedbacks_interviewer  FOREIGN KEY (interviewer_user_id) REFERENCES users(id)      ON DELETE RESTRICT,

    UNIQUE idx_feedback_interview_interviewer (interview_id, interviewer_user_id),
    INDEX idx_feedbacks_recommendation (recommendation)
);
```

### 4.6 توسعة `recruitment_pipeline_stages`

```sql
ALTER TABLE recruitment_pipeline_stages
    ADD COLUMN screening_schema JSON NULL AFTER requires_fields;
```

يحمل JSON schema لنموذج الفرز لتلك المرحلة (أسماء الحقول، أنواعها، وأوزانها). مثال:

```json
{
  "fields": [
    { "key": "technical_fit",   "label": "Technical Fit",   "type": "rating_1_5", "weight": 0.4 },
    { "key": "communication",   "label": "Communication",   "type": "rating_1_5", "weight": 0.2 },
    { "key": "experience_years","label": "Years Experience","type": "number",     "weight": 0.2 },
    { "key": "english_level",   "label": "English Level",   "type": "select", "options": ["basic","good","fluent"], "weight": 0.2 }
  ],
  "pass_threshold": 3.5
}
```

### 4.7 توسعة `TaskEntityType` enum

إضافة ثلاث حالات جديدة إلى `App\Shared\Enums\TaskEntityType`:

```
case Candidate              = 'candidate';
case CandidateApplication   = 'candidate_application';
case Interview              = 'interview';
```

لا migration — Enum PHP فقط. `tasks.entity_type` يقبلها من Phase 1 بالفعل.

### 4.8 ترتيب الـ Migrations

```
2026_11_01_100001_create_candidates_table.php
2026_11_01_100002_create_candidate_applications_table.php         (FK → candidates, jobs, stages)
2026_11_01_100003_create_candidate_screenings_table.php           (FK → applications)
2026_11_01_100004_create_interviews_table.php                     (FK → applications)
2026_11_01_100005_create_interview_feedbacks_table.php            (FK → interviews)
2026_11_01_100006_add_screening_schema_to_pipeline_stages.php
2026_11_01_100007_recruitment_phase_2_permissions.php             (seeds new permissions in up())
2026_11_01_100008_update_default_pipeline_phase_2_fields.php      (updates requires_fields + screening_schema)
```

---

## 5. Module Structure

نضيف الملفات إلى نفس `App\Modules\Recruitment\*` القائم — **لا موديول جديد**. المبرر: Candidate/Interview جزء عضوي من نفس الـ domain؛ تقسيمها إلى موديول مستقل يُضاعف الـ cross-module calls بلا فائدة.

```
apps/api/app/Modules/Recruitment/
├── Controllers/            (existing + new)
│   ├── CandidateController.php                     ← جديد
│   ├── CandidateApplicationController.php          ← جديد
│   ├── CandidateImportController.php               ← جديد (CSV)
│   ├── CandidateScreeningController.php            ← جديد
│   ├── CandidateShortlistController.php            ← جديد (toggle only)
│   ├── InterviewController.php                     ← جديد
│   └── InterviewFeedbackController.php             ← جديد
│
├── Services/
│   ├── CandidateService.php                        ← جديد
│   ├── CandidateApplicationService.php             ← جديد (⭐ core)
│   ├── CandidateImportService.php                  ← جديد (⭐ heavy)
│   ├── CandidateScreeningService.php               ← جديد
│   ├── CandidateShortlistService.php               ← جديد
│   ├── InterviewService.php                        ← جديد
│   ├── InterviewFeedbackService.php                ← جديد
│   └── PipelineTaskGeneratorService.php            ← مُوسَّع (يعرف الكيانات الجديدة)
│
├── Repositories/
│   ├── CandidateRepository.php                     ← جديد
│   ├── CandidateApplicationRepository.php          ← جديد
│   ├── CandidateScreeningRepository.php            ← جديد
│   ├── InterviewRepository.php                     ← جديد
│   └── InterviewFeedbackRepository.php             ← جديد
│
├── Requests/               (~20 Form Requests جديد)
├── Resources/              (10 API Resources جديد)
├── Events/
│   ├── CandidateApplied.php
│   ├── ScreeningCompleted.php
│   ├── InterviewScheduled.php
│   ├── InterviewCompleted.php
│   └── FeedbackSubmitted.php
│
├── Jobs/                   ← مجلّد جديد
│   ├── ProcessCandidateCsvImport.php               ← Queue Job (D8)
│   └── SendInterviewReminder.php
│
└── Listeners/              ← يستلمون الأحداث الجديدة
    ├── GenerateScreeningTask.php
    ├── GenerateInterviewSchedulingTask.php
    └── RouteInterviewFeedbackRequest.php
```

---

## 6. API Contracts

~32 endpoint جديد.

### 6.1 Candidates (global pool)

| Method | Route | Controller | Permission |
|--------|-------|-----------|------------|
| GET    | `/api/candidates` | `CandidateController@index` | `view-candidates` |
| POST   | `/api/candidates` | `CandidateController@store` | `manage-candidates` |
| GET    | `/api/candidates/{candidate}` | `CandidateController@show` | `view-candidates` |
| PATCH  | `/api/candidates/{candidate}` | `CandidateController@update` | `manage-candidates` |
| DELETE | `/api/candidates/{candidate}` | `CandidateController@destroy` | `manage-candidates` |
| POST   | `/api/candidates/{candidate}/resume` | `CandidateController@uploadResume` | `manage-candidates` |
| GET    | `/api/candidates/{candidate}/resume` | `CandidateController@downloadResume` | `view-candidates` (signed URL) |
| GET    | `/api/candidates/{candidate}/applications` | `CandidateController@applications` | `view-candidates` |
| GET    | `/api/candidates/export` | `CandidateController@export` | `export-recruitment-data` |

### 6.2 Candidate Applications (per job)

| Method | Route | Controller | Permission |
|--------|-------|-----------|------------|
| GET    | `/api/jobs/{job}/applications` | `CandidateApplicationController@indexForJob` | `view-jobs` |
| POST   | `/api/jobs/{job}/applications` | `CandidateApplicationController@attach` | `manage-candidates` |
| GET    | `/api/applications/{application}` | `CandidateApplicationController@show` | `view-candidates` |
| PATCH  | `/api/applications/{application}` | `CandidateApplicationController@update` | `manage-candidates` |
| POST   | `/api/applications/{application}/advance-stage` | `CandidateApplicationController@advanceStage` | `advance-job-stage` |
| POST   | `/api/applications/{application}/reject` | `CandidateApplicationController@reject` | `manage-candidates` |
| POST   | `/api/applications/{application}/withdraw` | `CandidateApplicationController@withdraw` | `manage-candidates` |

### 6.3 CSV Import

| Method | Route | Controller | Permission |
|--------|-------|-----------|------------|
| GET    | `/api/jobs/{job}/applications/import/template` | `CandidateImportController@template` | `manage-candidates` |
| POST   | `/api/jobs/{job}/applications/import/dry-run` | `CandidateImportController@dryRun` | `manage-candidates` |
| POST   | `/api/jobs/{job}/applications/import` | `CandidateImportController@store` | `manage-candidates` |
| GET    | `/api/jobs/{job}/applications/import/{jobRun}` | `CandidateImportController@status` | `manage-candidates` |

### 6.4 Screening

| Method | Route | Controller | Permission |
|--------|-------|-----------|------------|
| GET    | `/api/applications/{application}/screening` | `CandidateScreeningController@show` | `view-candidates` |
| POST   | `/api/applications/{application}/screening` | `CandidateScreeningController@store` | `screen-candidates` |
| PATCH  | `/api/applications/{application}/screening` | `CandidateScreeningController@update` | `screen-candidates` |
| GET    | `/api/pipelines/{pipeline}/stages/{stage}/screening-schema` | `CandidateScreeningController@schema` | `screen-candidates` |

### 6.5 Shortlist

| Method | Route | Controller | Permission |
|--------|-------|-----------|------------|
| POST   | `/api/applications/{application}/shortlist` | `CandidateShortlistController@add` | `shortlist-candidates` |
| DELETE | `/api/applications/{application}/shortlist` | `CandidateShortlistController@remove` | `shortlist-candidates` |
| GET    | `/api/jobs/{job}/shortlist` | `CandidateShortlistController@indexForJob` | `view-candidates` |

### 6.6 Interviews

| Method | Route | Controller | Permission |
|--------|-------|-----------|------------|
| GET    | `/api/interviews` | `InterviewController@index` | `view-interviews` |
| POST   | `/api/applications/{application}/interviews` | `InterviewController@store` | `schedule-interviews` |
| GET    | `/api/interviews/{interview}` | `InterviewController@show` | `view-interviews` |
| PATCH  | `/api/interviews/{interview}` | `InterviewController@update` | `schedule-interviews` |
| POST   | `/api/interviews/{interview}/cancel` | `InterviewController@cancel` | `schedule-interviews` |
| POST   | `/api/interviews/{interview}/reschedule` | `InterviewController@reschedule` | `schedule-interviews` |
| POST   | `/api/interviews/{interview}/complete` | `InterviewController@complete` | `schedule-interviews` |

### 6.7 Interview Feedback

| Method | Route | Controller | Permission |
|--------|-------|-----------|------------|
| GET    | `/api/interviews/{interview}/feedback` | `InterviewFeedbackController@index` | `view-interviews` |
| POST   | `/api/interviews/{interview}/feedback` | `InterviewFeedbackController@store` | `submit-interview-feedback` |
| PATCH  | `/api/interviews/{interview}/feedback/{feedback}` | `InterviewFeedbackController@update` | `submit-interview-feedback` (self only) |

**إجمالي:** 32 endpoint جديد.

---

## 7. Task Handoff Integration

`PipelineTaskGeneratorService` من Phase 1 يعمل بالفعل — يستمع لـ `JobRequirementStageAdvanced` ويولّد مهمة للمالك التالي. في Phase 2 نُدمج ثلاثة أحداث جديدة في نفس الـ engine:

### 7.1 أحداث → مولِّد المهام

| Event | Listener | المهمة المولَّدة | المالك | entity_type |
|-------|---------|------------------|-------|-------------|
| `CandidateApplied` | `GenerateScreeningTask` | "Screen candidate: {candidate.name} for {job.title}" | `role:screen-candidates` | `candidate_application` |
| `ScreeningCompleted` (passed=true) | `GenerateShortlistReviewTask` | "Review for shortlist: {candidate.name}" | `job_owner` | `candidate_application` |
| `CandidateApplication` → stage=`interviewing` | `GenerateInterviewSchedulingTask` | "Schedule interview: {candidate.name} / {job.title}" | `role:schedule-interviews` | `candidate_application` |
| `InterviewScheduled` | `RouteInterviewFeedbackRequest` | "Submit feedback: {candidate.name} ({interview.kind})" | each `interviewer_user_id` | `interview` |
| `InterviewCompleted` (all feedback in) | `AdvanceApplicationToClientReview` (optional) | "Client decision: {candidate.name}" | `job_owner` | `candidate_application` |

### 7.2 إعادة استخدام `PipelineTaskGeneratorService`

التوسعة الوحيدة: الدالة تقبل الآن `?CandidateApplication $application` بالإضافة إلى `JobRequirement $job`. عند تمرير application، تستخدم `application.current_stage_id` بدلاً من `job.current_stage_id` للتحقّق من `requires_fields` و `screening_schema`.

### 7.3 تفعيل المراحل المؤجّلة من Seed

`RecruitmentPipelineSeeder` من Phase 1 يُحدَّث عبر migration `2026_11_01_100008`:

| Stage | قبل Phase 2 | بعد Phase 2 |
|-------|------------|-------------|
| `screening` | `requires_fields: []`, بلا `screening_schema` | `requires_fields: ['screening_passed_count']` + `screening_schema` (قالب افتراضي 4 حقول) |
| `shortlist` | `requires_fields: []` | `requires_fields: ['shortlisted_count']` (≥ 1) |
| `interviewing` | `requires_fields: []` | `requires_fields: ['completed_interviews_count']` (≥ 1) |

`screening_passed_count` / `shortlisted_count` / `completed_interviews_count` حقول محسوبة في `JobRequirementService::advanceStage()` من جداول `candidate_applications` و `interviews` — ليست أعمدة على `job_requirements`.

---

## 8. Frontend Module Structure

```
apps/web/src/app/(dashboard)/recruitment/
├── candidates/
│   ├── page.tsx                               # Global candidate database (search/filter/import)
│   ├── new/page.tsx                           # Create candidate
│   └── [id]/
│       ├── page.tsx                           # Profile: identity, resume, skills, timeline
│       └── applications/page.tsx              # All applications this candidate has
│
├── jobs/[id]/                                 # existing — new tabs added
│   ├── candidates/page.tsx                    ← جديد — applicants for THIS job
│   ├── shortlist/page.tsx                     ← جديد — shortlisted-only view
│   └── applications/import/page.tsx           ← جديد — CSV upload wizard
│
├── applications/
│   └── [id]/
│       ├── page.tsx                           # Single application: tabs
│       ├── screening/page.tsx                 # Screening form (dynamic from schema)
│       ├── interviews/page.tsx                # Interview list
│       └── timeline/page.tsx                  # Stage history
│
└── interviews/
    ├── page.tsx                               # Calendar view of upcoming interviews
    └── [id]/
        ├── page.tsx                           # Interview details + feedback summary
        └── feedback/new/page.tsx              # Interviewer submits scorecard
```

### 8.1 Dialogs / Dialog Pages

| Dialog | Trigger | Page |
|--------|---------|------|
| Upload CSV | زر "Import CSV" في `/jobs/{id}/candidates` | `/jobs/{id}/applications/import` |
| Record Screening | زر "Score" على application | `/applications/{id}/screening` |
| Schedule Interview | زر "Schedule" على shortlisted application | dialog داخل `/applications/{id}` |
| Submit Feedback | من مهمة الـ My Tasks أو من `/interviews/{id}` | `/interviews/{id}/feedback/new` |
| Reject Candidate | زر على application | inline dialog مع `rejection_reason` |

### 8.2 إعادة استخدام من Phase 1

- `AdvanceStageDialog` نفسه يُعاد استخدامه للـ `CandidateApplication` بتمرير `entityType="application"`.
- Pipeline visual على Job Detail يُظهر الآن counts لكل مرحلة بناء على `candidate_applications.current_stage_id` (بدل تمثيل مرحلة الوظيفة فقط).

---

## 9. CSV Import — المواصفات التفصيلية

### 9.1 تنسيق الملف

- **File types:** `.csv`, `.xlsx` (via Laravel Excel)
- **Encoding:** UTF-8 with BOM (نحن نعرض أسماء عربية في Headers)
- **Max rows:** 2,000 لكل upload (أكبر من ذلك يُقسَّم)
- **Max file size:** 5 MB

### 9.2 الأعمدة المدعومة

| Column | Required | Validation | ملاحظات |
|--------|---------|-----------|---------|
| `full_name` | ✅ | string, 2–200 chars | |
| `email` | ⚠️ عند عدم وجود phone | valid email | used for dedup |
| `phone` | ⚠️ عند عدم وجود email | E.164-ish, ≥ 7 digits | used for dedup |
| `country` | ❌ | string, 2–100 | option_list check |
| `city` | ❌ | string | |
| `linkedin_url` | ❌ | url | |
| `headline` | ❌ | string, max 200 | |
| `years_of_experience` | ❌ | int 0–60 | |
| `current_title` | ❌ | string | |
| `current_company` | ❌ | string | |
| `expected_salary_min` | ❌ | numeric ≥ 0 | |
| `expected_salary_max` | ❌ | numeric ≥ min | |
| `salary_currency` | ❌ | ISO-4217 (3 chars) | defaults USD |
| `availability` | ❌ | one of `immediate\|2_weeks\|1_month\|negotiable` | |
| `skills` | ❌ | pipe-separated: `PHP\|Laravel\|MySQL` | split on upload |
| `languages` | ❌ | pipe-separated | |
| `notes` | ❌ | text, max 2000 | |

**عمود مُشتق تلقائياً:**
- `source` = `'csv_import'`
- `candidate_number` = مولَّد عبر `RecruitmentNumberGenerator`
- `created_by_user_id` = المستخدم الحالي

### 9.3 Dedup Policy

قبل الـ insert لكل صف:
1. ابحث عن candidate مطابق بـ `email` (case-insensitive) أو `phone` (normalized).
2. **Match found → attach existing candidate** للـ Job (دون تعديل حقوله). سجّل `reused_candidate` في النتيجة.
3. **No match → create new candidate** وربطه بالـ Job.
4. **Candidate موجود + مرتبط بنفس الـ Job سابقاً → skip مع تحذير** (duplicate application).

### 9.4 Error Reporting

Response payload بعد الـ import (sync أو async):

```json
{
  "import_job_id": 123,
  "total_rows": 150,
  "created_candidates": 102,
  "reused_candidates": 44,
  "created_applications": 146,
  "skipped_duplicates": 3,
  "errors": [
    { "row": 7,  "field": "email",  "message": "Invalid email format: 'ahmad[at]x.com'" },
    { "row": 23, "field": "years_of_experience", "message": "Must be between 0 and 60" },
    { "row": 41, "field": "__row__", "message": "At least one of email/phone is required" }
  ],
  "status": "completed_with_errors"
}
```

### 9.5 Dry-Run Mode

`POST /applications/import/dry-run` يُنفّذ **كامل الـ validation** بدون كتابة أي صف. يرجع نفس شكل الـ response أعلاه لكن مع `created_candidates: 0` و `preview: [...first 10 rows parsed...]`. يُستخدم في الـ wizard لعرض ملخص قبل الـ confirm النهائي.

### 9.6 Queue Job

```
ProcessCandidateCsvImport ──► reads uploaded file from private disk
                              ──► parses in chunks of 100
                              ──► for each row: dedup + create/attach
                              ──► updates import_job progress (via cache or table)
                              ──► fires CandidateApplied event per new application
                              ──► notifies uploader on completion (in-app + email)
```

> **ملاحظة:** جدول متابعة الـ import jobs (`candidate_import_jobs`) اختياري. إن اختار صاحب المنتج تتبّعاً دائماً، نضيفه في Rollout. البديل هو حفظ الحالة في Redis مع TTL = 7 أيام.

---

## 10. RBAC Matrix

### 10.1 صلاحيات مُعاد استخدامها من Phase 1

| Permission | يُستخدم في Phase 2 لـ |
|-----------|---------------------|
| `view-jobs` | listing applications per job |
| `advance-job-stage` | تقدّم Application عبر stages |
| `screen-candidates` | تعبئة Screening form |
| `schedule-interviews` | جدولة Interviews |
| `export-recruitment-data` | تصدير candidates CSV |

### 10.2 صلاحيات جديدة في Phase 2

| Permission | معنى | Recommended assignment |
|-----------|------|------------------------|
| `view-candidates` | قراءة بنك المرشّحين و applications | Recruitment, Screening Officer, Interview Coordinator |
| `manage-candidates` | CRUD + CSV import + attach to jobs | Recruitment Officer |
| `shortlist-candidates` | إضافة/إزالة من shortlist | Recruitment Manager, Job Owner |
| `view-interviews` | قراءة جدول المقابلات + feedback | Recruitment, Interviewer |
| `submit-interview-feedback` | تعبئة feedback لمقابلة شُركت فيها | أي مستخدم مُعيَّن كـ interviewer على مقابلة |

**الإجمالي:** 5 صلاحيات جديدة فقط. الـ 16 القديمة كافية للباقي.

### 10.3 عينة أدوار بعد Phase 2

| Role | Permissions |
|------|-------------|
| `screening_officer` | view-candidates, view-jobs, screen-candidates, view-interviews |
| `interview_coordinator` | view-candidates, view-jobs, schedule-interviews, view-interviews |
| `interviewer` | view-interviews, submit-interview-feedback (+ view-candidates على المقابلات التي يحضرها فقط — يُفحص في الـ Service) |
| `recruitment_officer` (مُحدَّث) | + view-candidates, manage-candidates, shortlist-candidates |

**قاعدة خاصة بـ `submit-interview-feedback`:** الصلاحية لا تكفي وحدها — Service يُلزم أن يكون المستخدم مُسجَّلاً كـ interviewer على المقابلة (أو مُنشِئها) قبل قبول الـ feedback. هذا نمط Phase 1 نفسه (الصلاحية بوابة، الـ Service يفحص الملكية).

---

## 11. Notifications

6 إشعارات جديدة تُضاف كـ methods على `NotificationService` القائم. كل method تفتح in-app + email حسب تفضيل المستخدم (نفس نمط `jobStageAdvanced`).

```php
public function candidateApplied(CandidateApplication $application): void;
public function screeningCompleted(CandidateScreening $screening): void;
public function interviewScheduled(Interview $interview, User $interviewer): void;
public function interviewReminder(Interview $interview, User $interviewer, int $hoursUntil): void;
public function interviewFeedbackSubmitted(InterviewFeedback $feedback): void;
public function csvImportCompleted(User $uploader, array $summary): void;
```

### 11.1 Routing per Event

| Event | يصل إلى | قناة |
|-------|---------|------|
| `CandidateApplied` | Job Owner + Screening Officer (المعيَّن) | in-app |
| `ScreeningCompleted` | Job Owner | in-app + email |
| `InterviewScheduled` | المرشّح (SMS/email — لاحقاً) + كل interviewer + Job Owner | in-app + email |
| `InterviewReminder` | كل interviewer | in-app + email (1h قبل) + email (يومياً 08:00) |
| `FeedbackSubmitted` | Job Owner + Interview Coordinator | in-app |
| `CsvImportCompleted` | المستخدم الذي رفع الملف | in-app + email |

### 11.2 Deep links

كل إشعار يحمل `link` يفتح الصفحة المعنية مباشرة (`/recruitment/applications/{id}`, `/recruitment/interviews/{id}`). نفس نمط Phase 1.

### 11.3 Scheduled Commands الجديدة

```
Schedule::command('recruitment:interview-reminders-daily')->dailyAt('08:00');
Schedule::command('recruitment:interview-reminders-hourly')->hourly();   // sends 1h-before notice
Schedule::command('recruitment:application-sla-scan')->hourly();         // uses stage_entered_at
```

الـ `application-sla-scan` يعيد استخدام نفس المنطق في `ScanRecruitmentSla` القائم مع إضافة loop على `candidate_applications` ذات `stage_entered_at` تجاوز `sla_hours` للمرحلة.

---

## 12. Non-Goals for Phase 2

قائمة صريحة لما **لا** يُسلَّم — لحسم أي سؤال "هل هذا مشمول؟":

| ليس في Phase 2 | أين |
|---------------|-----|
| Contract preparation + e-sign + hired-date tracking | Phase 3 |
| BrightGaza REST API (two-way sync) | Phase 4 |
| AI CV parsing / auto-populate candidate fields | Phase 4 |
| AI candidate ↔ job matching score | Phase 4 |
| Candidate self-service portal (مرشّح يسجّل بنفسه) | Phase 4 |
| Video interview recording / live transcription | **خارج النطاق نهائياً** |
| SMS notifications للمرشّحين | Phase 3 (مع contract flow) |
| Candidate re-engagement campaigns (nurture) | Phase 4 |
| Advanced search / Meilisearch على candidates | Phase 4 |
| Candidate pipeline analytics (time-to-hire, drop-off) | Phase 3 (مع التقارير) |
| Multi-stage approval workflow للـ offers | Phase 3 |

---

## 13. Rollout Steps

**المدة المتوقعة:** 4 أسابيع لمطوّر واحد.

| Week | Backend | Frontend / QA | Deliverable |
|------|---------|---------------|-------------|
| 1 — Candidate Core | Migrations الـ 8 + Models + Enums + Permissions (via migration) + `CandidateRepository`/`CandidateApplicationRepository` + `CandidateService`/`CandidateApplicationService` + Controllers/Requests/Resources + ~15 Feature test | — | إمكانية إضافة مرشّح يدوياً وربطه بوظيفة من API |
| 2 — Import + Screening + Shortlist | `CandidateImportService` + `ProcessCandidateCsvImport` Queue Job + Dry-run + Dedup + `ScreeningService` + `ShortlistService` + ~15 Feature test | — | CSV يُنتج applications، Screening + Shortlist يعمل من API |
| 3 — Interviews + Feedback + Tasks | `InterviewService` + `InterviewFeedbackService` + توسعة `PipelineTaskGeneratorService` + 5 event listeners + 3 scheduled commands + 6 notification methods + ~12 Feature test | — | جدولة مقابلة تُنتج مهام، feedback يصل للـ Job Owner |
| 4 — Frontend + Polish | — | صفحات `/candidates`, `/jobs/{id}/candidates`, `/applications/{id}`, `/interviews` + Dynamic Screening form + ~60 Arabic string + Mobile responsive + QA (3 journeys) | جاهز للـ staging |

**Deployment:** نفس GitHub Actions الحالي. البيانات المرجعية عبر migrations. كل migration لها `down()` نظيف.

---

## 14. Open Questions — تحتاج قرار صاحب المنتج قبل البدء

هذه قرارات تصميمية لا يُمكن حسمها من الـ Phase 1 docs وحدها:

| # | السؤال | البدائل | توصيتنا |
|---|-------|---------|---------|
| Q1 | Interview كجدول مستقل أم إعادة استخدام Workflow Engine؟ | أ) جدول مستقل (§4.4). ب) WorkflowStep. | (أ) — Workflow مصمَّم لقرار موظف-على-طلب، ليس لتجميع N تقييمات. |
| Q2 | Screening schema per Pipeline Stage أم per JobRequirement؟ | أ) Per Stage (الحالي). ب) override على Job. ج) `screening_templates` مُسمّى. | (أ) في Phase 2؛ (ب) migration صغيرة في Phase 3 عند الحاجة. |
| Q3 | جدول `candidate_import_jobs` دائم أم Redis TTL؟ | أ) جدول. ب) Redis. | جدول خفيف `(uploader_id, job_id, filename, totals, errors_json, status)` — ساعة تطوير، قيمة audit واضحة. |
| Q4 | Candidate dedup: strict أم warn؟ | أ) silent reuse على email/phone. ب) تحذير في dry-run. ج) fuzzy على name. | (أ) + إظهار `reused_candidate_ids` للمراجعة؛ (ج) إلى Phase 4 مع AI. |
| Q5 | Interview Feedback: averaging policy؟ | أ) عرض الكل بدون قرار. ب) majority rule. ج) weighted بـ seniority. | (أ) في Phase 2 — حساب `average_score` فقط، قرار الـ Owner يدوي. |
| Q6 | إعادة تقديم نفس المرشّح على نفس الوظيفة بعد الرفض؟ | أ) UNIQUE صارم. ب) partial unique على `deleted_at IS NULL`. | (أ) في Phase 2؛ تحويل إلى (ب) عند طلب لاحق. |
| Q7 | SMS للمرشّح عند جدولة المقابلة؟ | أ) الآن. ب) تأجيل. | (ب) — Phase 2 يبقى داخل TAQAT؛ SMS للمرشّح يحتاج consent policy في Phase 3. |

---

**نهاية خطة تنفيذ Phase 2.**
المراجعة التالية: بعد قرار الـ owner على Q1–Q7 أعلاه ↑
