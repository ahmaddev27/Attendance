# Recruitment Module — ERD & DDL

> **الوثيقة رقم 3**
> النطاق: ERD كامل، DDL لكل جدول، فهارس، مبررات الـ FK, seed data.
> السابق: [`01-architecture.md`](01-architecture.md) · التالي: [`03-phase-1-plan.md`](03-phase-1-plan.md)

---

## 1. ERD — نظرة شاملة (Phase 1)

```
┌────────────────────┐            ┌──────────────────┐
│  users             │            │  employees       │
│  (existing)        │◄───────────│  (existing)      │
└────────────────────┘  1:1       └────────┬─────────┘
         ▲                                 │ owner
         │                                 │
         │ owner                           │
         │                                 ▼
┌────────┴───────────┐             ┌────────────────────┐
│  leads             │────────────►│  lead_activities   │
│                    │  1:n        │  (call/meeting/    │
│  status FK          │             │   email/note)     │
│  source FK          │             └────────────────────┘
└───────┬────────────┘
        │  converted_client_id (1:1, nullable)
        ▼
┌────────────────────┐  1:n        ┌────────────────────┐
│  clients           │────────────►│  client_contacts   │
│                    │             └────────────────────┘
└───────┬────────────┘
        │  1:n
        ▼
┌────────────────────┐  1:n        ┌────────────────────┐
│  recruitment_cases │────────────►│  job_requirements  │
│                    │             │                    │
│  source_lead_id    │             │  pipeline_id FK    │
│  owner_id (User)   │             │  current_stage_id  │
└────────────────────┘             │  owner_id (User)   │
                                   └─────────┬──────────┘
                                             │  entity_type = 'job_requirement'
                                             │  entity_id   = job.id
                                             ▼
                    ┌──────────────────────────────────────────┐
                    │  tasks (existing + polymorphic columns)  │
                    │  entity_type / entity_id                 │
                    └──────────────────────────────────────────┘

┌───────────────────────────┐     ┌──────────────────────────────┐
│  recruitment_pipelines    │◄────│  recruitment_pipeline_stages │
│  (templates)              │ 1:n │                              │
└───────────────────────────┘     └──────────────────────────────┘
```

---

## 2. الجداول الجديدة (Phase 1)

### 2.1 `leads`

كل شركة محتملة تدخل هنا. لا تُحذف بعد التحويل — بل تُعلَّم `converted`.

```sql
CREATE TABLE leads (
    id                       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lead_number              VARCHAR(20) NOT NULL UNIQUE,

    -- Company basics
    company_name             VARCHAR(200) NOT NULL,
    company_website          VARCHAR(255) NULL,
    industry                 VARCHAR(100) NULL,
    company_size             VARCHAR(50)  NULL,   -- '1-10', '11-50', '51-200', ...
    country                  VARCHAR(100) NULL,
    city                     VARCHAR(100) NULL,

    -- Contact
    contact_person           VARCHAR(150) NULL,
    contact_position         VARCHAR(150) NULL,
    contact_email            VARCHAR(150) NULL,
    contact_phone            VARCHAR(30)  NULL,
    linkedin_url             VARCHAR(255) NULL,

    -- Classification
    source                   VARCHAR(50)  NOT NULL,  -- 'linkedin' | 'referral' | 'website' | ...
    status                   VARCHAR(30)  NOT NULL DEFAULT 'new',
                             -- 'new' | 'contacted' | 'meeting_scheduled' | 'meeting_completed'
                             -- | 'qualified' | 'proposal_sent' | 'negotiation'
                             -- | 'converted' | 'lost' | 'on_hold'

    owner_id                 BIGINT UNSIGNED NOT NULL,  -- FK → users.id
    expected_hiring_volume   SMALLINT UNSIGNED NULL,
    notes                    TEXT NULL,

    -- Follow-up tracking
    last_contact_at          TIMESTAMP NULL,
    next_followup_at         TIMESTAMP NULL,

    -- Conversion tracking (kept even if lead deleted logically)
    converted_at             TIMESTAMP NULL,
    converted_client_id      BIGINT UNSIGNED NULL,

    -- Loss tracking
    lost_at                  TIMESTAMP NULL,
    lost_reason              VARCHAR(255) NULL,

    created_at               TIMESTAMP NULL,
    updated_at               TIMESTAMP NULL,
    deleted_at               TIMESTAMP NULL,

    CONSTRAINT fk_leads_owner
        FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_leads_converted_client
        FOREIGN KEY (converted_client_id) REFERENCES clients(id) ON DELETE SET NULL,

    INDEX idx_leads_status (status),
    INDEX idx_leads_owner_status (owner_id, status),
    INDEX idx_leads_next_followup (next_followup_at),
    INDEX idx_leads_source (source)
);
```

**قرارات:**
- `lead_number` بصيغة `L-YYYY-####` (مثل `L-2026-0042`) — قابل للطباعة، مستقل عن `id`.
- `status` كـ string (ليس Enum SQL) لأن Admin يعدّلها.
- `owner_id → users.id` وليس `employees.id` لأن Lead Owner قد يكون Sales User لم يُنشأ له Employee بعد (خاصة للـ Bootstrap Admin).
- `RESTRICT` على owner — لا نسمح بحذف مستخدم يملك Leads نشطة.
- Soft delete — نحافظ على الـ history للتحويلات.

### 2.2 `lead_activities`

كل تفاعل مع Lead (اتصال، اجتماع، إيميل، ملاحظة).

```sql
CREATE TABLE lead_activities (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lead_id         BIGINT UNSIGNED NOT NULL,
    user_id         BIGINT UNSIGNED NOT NULL,        -- who logged it
    type            VARCHAR(30) NOT NULL,            -- 'call' | 'meeting' | 'email' | 'note' | 'status_change'
    subject         VARCHAR(200) NULL,
    body            TEXT NULL,
    occurred_at     TIMESTAMP NOT NULL,              -- when the activity happened
    metadata        JSON NULL,                       -- e.g. { from_status: 'new', to_status: 'contacted' }
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,

    CONSTRAINT fk_activities_lead
        FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
    CONSTRAINT fk_activities_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,

    INDEX idx_activities_lead_time (lead_id, occurred_at DESC),
    INDEX idx_activities_user (user_id)
);
```

**قرارات:**
- Cascade على `lead_id` — حذف Lead يحذف نشاطاته.
- Restrict على `user_id` — الاحتفاظ بمن سجّل النشاط.
- `metadata` JSON للتغييرات التلقائية (status change, owner reassignment).

### 2.3 `clients`

الشركة كعميل فعلي.

```sql
CREATE TABLE clients (
    id                   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_number        VARCHAR(20) NOT NULL UNIQUE,

    company_name         VARCHAR(200) NOT NULL,
    company_website      VARCHAR(255) NULL,
    industry             VARCHAR(100) NULL,
    company_size         VARCHAR(50)  NULL,
    country              VARCHAR(100) NULL,
    city                 VARCHAR(100) NULL,
    address              TEXT NULL,

    -- Business
    tax_number           VARCHAR(50)  NULL,
    payment_terms        VARCHAR(50)  NULL,          -- 'net_30' | 'net_60' | 'upfront' | 'custom'
    payment_terms_notes  TEXT NULL,

    status               VARCHAR(30) NOT NULL DEFAULT 'active',
                         -- 'active' | 'inactive' | 'on_hold' | 'terminated'

    account_manager_id   BIGINT UNSIGNED NULL,       -- FK → users.id
    source_lead_id       BIGINT UNSIGNED NULL,       -- provenance if converted

    notes                TEXT NULL,

    created_at           TIMESTAMP NULL,
    updated_at           TIMESTAMP NULL,
    deleted_at           TIMESTAMP NULL,

    CONSTRAINT fk_clients_account_manager
        FOREIGN KEY (account_manager_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_clients_source_lead
        FOREIGN KEY (source_lead_id) REFERENCES leads(id) ON DELETE SET NULL,

    INDEX idx_clients_status (status),
    INDEX idx_clients_account_manager (account_manager_id),
    INDEX idx_clients_source_lead (source_lead_id),
    UNIQUE idx_clients_company_country (company_name, country)  -- soft-unique for dedup UX
);
```

**قرارات:**
- `client_number` بصيغة `C-YYYY-####`.
- `UNIQUE (company_name, country)` تفعّل الـ dedup على مستوى القاعدة (مع تحذير في الـ Service قبل الوصول للـ constraint).
- Soft delete — بيانات تاريخية عن التعاقدات مهمة.

### 2.4 `client_contacts`

جهات اتصال متعددة لكل عميل (HR Director, CTO, CFO, ...).

```sql
CREATE TABLE client_contacts (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id      BIGINT UNSIGNED NOT NULL,
    full_name      VARCHAR(150) NOT NULL,
    position       VARCHAR(150) NULL,
    email          VARCHAR(150) NULL,
    phone          VARCHAR(30)  NULL,
    linkedin_url   VARCHAR(255) NULL,
    is_primary     BOOLEAN NOT NULL DEFAULT FALSE,
    notes          TEXT NULL,
    created_at     TIMESTAMP NULL,
    updated_at     TIMESTAMP NULL,

    CONSTRAINT fk_contacts_client
        FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,

    INDEX idx_contacts_client (client_id),
    INDEX idx_contacts_client_primary (client_id, is_primary)
);
```

**قرار:** لا نحصر Primary بواحد على مستوى القاعدة (partial unique index معقّد MySQL) — نحصره في `ClientContactService::setPrimary()` عبر transaction.

### 2.5 `recruitment_cases`

حملة توظيف تجمع عدة وظائف من نفس العميل.

```sql
CREATE TABLE recruitment_cases (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_number      VARCHAR(20) NOT NULL UNIQUE,
    client_id        BIGINT UNSIGNED NOT NULL,
    source_lead_id   BIGINT UNSIGNED NULL,

    title            VARCHAR(200) NOT NULL,        -- "Q1 2026 Remote Hiring"
    description      TEXT NULL,

    owner_id         BIGINT UNSIGNED NOT NULL,     -- users.id — Case Recruiter
    priority         VARCHAR(20) NOT NULL DEFAULT 'normal',  -- 'low'|'normal'|'high'|'urgent'

    status           VARCHAR(30) NOT NULL DEFAULT 'active',
                     -- 'draft'|'active'|'on_hold'|'completed'|'cancelled'

    target_hires     SMALLINT UNSIGNED NULL,       -- total roles wanted
    started_at       DATE NULL,
    deadline         DATE NULL,
    completed_at     TIMESTAMP NULL,

    created_at       TIMESTAMP NULL,
    updated_at       TIMESTAMP NULL,
    deleted_at       TIMESTAMP NULL,

    CONSTRAINT fk_cases_client
        FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE RESTRICT,
    CONSTRAINT fk_cases_source_lead
        FOREIGN KEY (source_lead_id) REFERENCES leads(id) ON DELETE SET NULL,
    CONSTRAINT fk_cases_owner
        FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE RESTRICT,

    INDEX idx_cases_client_status (client_id, status),
    INDEX idx_cases_owner (owner_id),
    INDEX idx_cases_deadline (deadline)
);
```

### 2.6 `recruitment_pipelines`

قوالب المراحل. نبدأ بواحد افتراضي "Standard Job Pipeline" ونسمح بإضافة قوالب متخصصة.

```sql
CREATE TABLE recruitment_pipelines (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(100) NOT NULL,
    code         VARCHAR(50)  NOT NULL UNIQUE,
    description  TEXT NULL,
    is_default   BOOLEAN NOT NULL DEFAULT FALSE,
    is_active    BOOLEAN NOT NULL DEFAULT TRUE,
    created_at   TIMESTAMP NULL,
    updated_at   TIMESTAMP NULL,

    INDEX idx_pipelines_active (is_active)
);
```

### 2.7 `recruitment_pipeline_stages`

مراحل داخل Pipeline. القرار المعماري الأهم — راجع [`01-architecture.md#3-pipeline-engine`](01-architecture.md#3-pipeline-engine).

```sql
CREATE TABLE recruitment_pipeline_stages (
    id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pipeline_id       BIGINT UNSIGNED NOT NULL,
    display_order     SMALLINT UNSIGNED NOT NULL,
    code              VARCHAR(50)  NOT NULL,        -- 'new'|'publish'|'screening'|...
    name              VARCHAR(100) NOT NULL,
    description       TEXT NULL,

    -- Ownership rules
    owner_rule_type   VARCHAR(30) NOT NULL,
                      -- 'role' | 'specific' | 'case_owner' | 'job_owner'
                      -- | 'previous_stage_owner' | 'none'
    owner_rule_value  VARCHAR(100) NULL,            -- permission name or user_id

    -- SLA & task generation
    sla_hours         SMALLINT UNSIGNED NULL,
    auto_generate_task BOOLEAN NOT NULL DEFAULT TRUE,
    task_title_template VARCHAR(200) NULL,          -- e.g. "Publish job: {job.title}"
    task_priority     VARCHAR(20)  NULL,            -- 'low'|'normal'|'high'|'urgent'

    -- Requirements for transition
    requires_fields   JSON NULL,                    -- ['publication_url', 'shortlist_ids']
    is_terminal       BOOLEAN NOT NULL DEFAULT FALSE, -- Hired/Cancelled

    created_at        TIMESTAMP NULL,
    updated_at        TIMESTAMP NULL,

    CONSTRAINT fk_stages_pipeline
        FOREIGN KEY (pipeline_id) REFERENCES recruitment_pipelines(id) ON DELETE CASCADE,

    UNIQUE idx_stages_pipeline_code (pipeline_id, code),
    UNIQUE idx_stages_pipeline_order (pipeline_id, display_order),
    INDEX idx_stages_pipeline (pipeline_id)
);
```

**قرارات:**
- `owner_rule_type` كـ string وليس Enum SQL — يسمح بإضافة أنواع لاحقاً بدون migration.
- `requires_fields` JSON — قائمة أسماء الحقول التي يجب أن تكون non-null على `job_requirements` قبل الانتقال. الفحص في `JobRequirementService::advanceStage()`.
- `is_terminal` — Hired / Cancelled يجب أن لا يقبل انتقال بعدها.

### 2.8 `job_requirements`

الوظيفة نفسها.

```sql
CREATE TABLE job_requirements (
    id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_number            VARCHAR(20) NOT NULL UNIQUE,          -- J-YYYY-####

    recruitment_case_id   BIGINT UNSIGNED NOT NULL,
    pipeline_id           BIGINT UNSIGNED NOT NULL,
    current_stage_id      BIGINT UNSIGNED NOT NULL,

    owner_id              BIGINT UNSIGNED NOT NULL,             -- users.id — recruiter

    -- Position basics
    title                 VARCHAR(200) NOT NULL,
    department            VARCHAR(100) NULL,
    openings              SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    employment_type       VARCHAR(30)  NOT NULL,               -- 'full_time'|'part_time'|'contract'|'intern'
    work_mode             VARCHAR(20)  NOT NULL,               -- 'remote'|'onsite'|'hybrid'
    location              VARCHAR(200) NULL,

    -- Compensation
    salary_min            DECIMAL(10,2) NULL,
    salary_max            DECIMAL(10,2) NULL,
    salary_currency       CHAR(3) NULL DEFAULT 'USD',

    -- Requirements
    required_experience_years SMALLINT UNSIGNED NULL,
    education_level       VARCHAR(50) NULL,
    required_skills       JSON NULL,                            -- ['PHP', 'Laravel', 'PostgreSQL']
    nice_to_have_skills   JSON NULL,
    required_languages    JSON NULL,
    description           TEXT NULL,
    responsibilities      TEXT NULL,

    -- Publication metadata (populated when moved to 'published' stage in Phase 2)
    publication_url       VARCHAR(500) NULL,
    published_at          TIMESTAMP NULL,

    -- Timing
    application_deadline  DATE NULL,
    target_start_date     DATE NULL,

    -- Status
    status                VARCHAR(30) NOT NULL DEFAULT 'draft',
                          -- 'draft'|'active'|'on_hold'|'filled'|'cancelled'

    stage_entered_at      TIMESTAMP NOT NULL,                   -- when the current stage started (for SLA)
    completed_at          TIMESTAMP NULL,

    created_at            TIMESTAMP NULL,
    updated_at            TIMESTAMP NULL,
    deleted_at            TIMESTAMP NULL,

    CONSTRAINT fk_jobs_case
        FOREIGN KEY (recruitment_case_id) REFERENCES recruitment_cases(id) ON DELETE RESTRICT,
    CONSTRAINT fk_jobs_pipeline
        FOREIGN KEY (pipeline_id) REFERENCES recruitment_pipelines(id) ON DELETE RESTRICT,
    CONSTRAINT fk_jobs_current_stage
        FOREIGN KEY (current_stage_id) REFERENCES recruitment_pipeline_stages(id) ON DELETE RESTRICT,
    CONSTRAINT fk_jobs_owner
        FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE RESTRICT,

    INDEX idx_jobs_case_status (recruitment_case_id, status),
    INDEX idx_jobs_owner (owner_id),
    INDEX idx_jobs_current_stage (current_stage_id),
    INDEX idx_jobs_status (status),
    INDEX idx_jobs_stage_entered (stage_entered_at)             -- SLA scan uses this
);
```

**قرارات:**
- كل الـ FKs الحرجة `RESTRICT` — لا نسمح بحذف Pipeline/Stage/Client إذا فيه Jobs نشطة.
- `stage_entered_at` منفصل عن `updated_at` لأنه لا يتغير إلا عند تقدّم مرحلة — أساس حساب SLA breach.
- `required_skills` JSON بدل جدول many-to-many — بسيط، والبحث عليها نادر في Phase 1 (سيتحول لجدول عند تفعيل Meilisearch على Jobs في Phase 2).

### 2.9 توسعة `tasks`

Migration منفصلة لأنها تلمس جدول موجود.

```sql
ALTER TABLE tasks
    ADD COLUMN entity_type VARCHAR(50) NULL AFTER assigned_to,
    ADD COLUMN entity_id   BIGINT UNSIGNED NULL AFTER entity_type,
    ADD INDEX idx_tasks_entity (entity_type, entity_id);
```

`tasks` مع Soft Delete على مستوى القاعدة موجود مسبقاً، لا نلمسه.

---

## 3. Seed Data

### 3.1 Default Recruitment Pipeline

```php
// database/seeders/RecruitmentPipelineSeeder.php
$pipeline = RecruitmentPipeline::create([
    'name'       => 'Standard Job Pipeline',
    'code'       => 'standard',
    'is_default' => true,
    'is_active'  => true,
]);

$stages = [
    ['new',             'New Job',            'job_owner',           null,                    null,  false, false],
    ['publish',         'Publish Job',        'role',                'publish-jobs',          24,    true,  false],
    ['receiving_apps',  'Receiving Applic.',  'role',                'screen-candidates',     168,   false, false],
    ['screening',       'Screening',          'role',                'screen-candidates',     72,    true,  false],
    ['shortlist',       'Shortlist',          'previous_stage_owner',null,                    24,    true,  false],
    ['interviewing',    'Interviewing',       'role',                'schedule-interviews',   168,   true,  false],
    ['client_decision', 'Client Decision',    'job_owner',           null,                    72,    false, false],
    ['contracting',     'Contracting',        'role',                'prepare-contracts',     48,    true,  false],
    ['hired',           'Hired',              'none',                null,                    null,  false, true],
    ['cancelled',       'Cancelled',          'none',                null,                    null,  false, true],
];

foreach ($stages as $i => [$code, $name, $ruleType, $ruleValue, $sla, $autoTask, $terminal]) {
    RecruitmentPipelineStage::create([
        'pipeline_id'       => $pipeline->id,
        'display_order'     => $i + 1,
        'code'              => $code,
        'name'              => $name,
        'owner_rule_type'   => $ruleType,
        'owner_rule_value'  => $ruleValue,
        'sla_hours'         => $sla,
        'auto_generate_task'=> $autoTask,
        'is_terminal'       => $terminal,
        'requires_fields'   => match ($code) {
            'publish'   => ['publication_url'],
            'shortlist' => ['shortlist_ids'],
            'contracting'=> ['contract_terms'],
            default => [],
        },
    ]);
}
```

### 3.2 Permissions

```php
// database/seeders/RecruitmentPermissionSeeder.php
$permissions = [
    'view-leads',
    'manage-leads',
    'convert-leads',
    'view-clients',
    'manage-clients',
    'view-recruitment-cases',
    'manage-recruitment-cases',
    'view-jobs',
    'manage-jobs',
    'advance-job-stage',
    'publish-jobs',
    'screen-candidates',
    'schedule-interviews',
    'prepare-contracts',
    'manage-recruitment-pipelines',
    'export-recruitment-data',
];

foreach ($permissions as $name) {
    Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
}

// Assign to super_admin (bootstrap)
Role::where('name', 'super_admin')->first()?->givePermissionTo($permissions);
```

### 3.3 Settings

```php
// database/seeders/RecruitmentSettingsSeeder.php
Setting::firstOrCreate(['key' => 'recruitment.lead_sources'], ['value' => json_encode([
    'linkedin', 'referral', 'website', 'existing_client', 'partner',
    'email', 'direct_outreach', 'event', 'other',
])]);

Setting::firstOrCreate(['key' => 'recruitment.lead_statuses'], ['value' => json_encode([
    'new', 'contacted', 'meeting_scheduled', 'meeting_completed',
    'qualified', 'proposal_sent', 'negotiation', 'converted', 'lost', 'on_hold',
])]);

Setting::firstOrCreate(['key' => 'recruitment.industries'], ['value' => json_encode([
    'technology', 'finance', 'healthcare', 'education', 'retail',
    'manufacturing', 'consulting', 'hospitality', 'other',
])]);

Setting::firstOrCreate(['key' => 'recruitment.company_sizes'], ['value' => json_encode([
    '1-10', '11-50', '51-200', '201-500', '501-1000', '1000+',
])]);
```

---

## 4. Migration Order

الترتيب مهم للـ FKs:

```
2026_10_01_100001_create_recruitment_pipelines_table.php
2026_10_01_100002_create_recruitment_pipeline_stages_table.php     (FK → pipelines)
2026_10_01_100003_create_leads_table.php                            (FK → users; NO FK to clients yet)
2026_10_01_100004_create_lead_activities_table.php                  (FK → leads, users)
2026_10_01_100005_create_clients_table.php                          (FK → users, leads)
2026_10_01_100006_add_converted_client_fk_to_leads_table.php        (FK: leads.converted_client_id → clients.id)
2026_10_01_100007_create_client_contacts_table.php                  (FK → clients)
2026_10_01_100008_create_recruitment_cases_table.php                (FK → clients, users, leads)
2026_10_01_100009_create_job_requirements_table.php                 (FK → cases, pipelines, stages, users)
2026_10_01_100010_add_entity_polymorphic_to_tasks_table.php         (existing tasks table)
```

**لماذا 100006 منفصلة؟**
`leads` تُنشأ قبل `clients` (لأن `clients.source_lead_id → leads.id`). ثم نُضيف الـ FK العكسي `leads.converted_client_id → clients.id` بعد إنشاء `clients`. هذه دائرة FK صحيحة لكن تحتاج migration واحدة لكل اتجاه.

---

## 5. القرارات المعمارية على مستوى القاعدة

| القرار | المبرر |
|--------|--------|
| كل PK هو `BIGINT UNSIGNED` (Laravel `id()`) | نفس النمط في كل الجداول الحالية |
| Soft deletes على `leads`, `clients`, `cases`, `jobs` — لا على `activities`/`contacts`/`stages` | الأولى بيانات تاريخية، الثانية تفاصيل يجب حذفها فعلياً |
| `deleted_at` بدل `is_deleted` | Laravel Eloquent convention |
| `RESTRICT` على FKs المتعلقة بـ owner/client/pipeline | نمنع الحذف الصامت لبيانات ذات مراجع نشطة |
| `SET NULL` على `source_lead_id` و `converted_client_id` | history العلاقة قابلة للفقد بأمان |
| `CASCADE` على `lead_activities.lead_id` و `client_contacts.client_id` | ذيول العنصر الأب — لا معنى لبقائها |
| فهارس مركّبة على `(status, owner_id)` بدل مفردة | كل الاستعلامات الشائعة تفلتر على الاثنين معاً |
| Enum SQL محرَّم | كل status/type كـ VARCHAR + validation في الـ Service — تسهيل التعديل |
| JSON بدل جداول many-to-many حيث الاستعلامات نادرة | `required_skills`, `requires_fields`, `metadata` |

---

## 6. تقدير حجم البيانات

قياس تقريبي على مدى 12 شهر:

| الجدول | الصفوف المتوقعة | الحجم |
|--------|----------------|-------|
| leads | 2,000 | ~1 MB |
| lead_activities | 20,000 (10 لكل lead) | ~5 MB |
| clients | 400 (20% conversion) | ~200 KB |
| client_contacts | 1,200 (3 لكل client) | ~400 KB |
| recruitment_cases | 600 (1.5 لكل client) | ~300 KB |
| job_requirements | 2,400 (4 لكل case) | ~2 MB |
| tasks (with entity) | 15,000 (المهام المولّدة تلقائياً) | ~5 MB |

الحجم الكلي المتوقع ~15 MB في السنة الأولى — لا يحتاج قرارات partitioning أو archiving في Phase 1. راجع مرة أخرى عند نهاية السنة الثانية.

---

**التالي:** انظر [`03-phase-1-plan.md`](03-phase-1-plan.md) للخطة التنفيذية التفصيلية مع مهام مرقّمة و RBAC matrix.
