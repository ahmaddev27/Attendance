# Recruitment Module — Architecture

> **الوثيقة رقم 2**
> النطاق: بنية الـ Module، الطبقات، Pipeline Engine، تكامل Task Engine، الأحداث والإشعارات.
> السابق: [`00-overview.md`](00-overview.md) · التالي: [`02-erd.md`](02-erd.md)

---

## 1. Module Structure

الموديول يتبع نفس النمط المعتمد في `Modules/Requests` و `Modules/Tasks`:

```
apps/api/app/Modules/Recruitment/
├── Controllers/
│   ├── LeadController.php                    # CRUD + Kanban + Convert
│   ├── LeadActivityController.php            # POST /leads/{id}/activities
│   ├── ClientController.php                  # CRUD + Profile
│   ├── ClientContactController.php           # nested under Client
│   ├── RecruitmentCaseController.php         # CRUD + Cases list under Client
│   ├── JobRequirementController.php          # CRUD + status transitions
│   ├── RecruitmentPipelineController.php     # Admin — configure pipelines
│   ├── RecruitmentPipelineStageController.php
│   └── RecruitmentDashboardController.php    # KPIs + funnel
│
├── Events/
│   ├── LeadCreated.php
│   ├── LeadConverted.php                     # → Client + Case + optional Jobs
│   ├── JobRequirementSubmitted.php
│   ├── JobRequirementStageAdvanced.php       # fires PipelineTaskGenerator
│   └── ClientCreated.php
│
├── Repositories/
│   ├── LeadRepository.php
│   ├── ClientRepository.php
│   ├── RecruitmentCaseRepository.php
│   ├── JobRequirementRepository.php
│   ├── RecruitmentPipelineRepository.php
│   └── LeadActivityRepository.php
│
├── Requests/                                 # Form Request classes
│   ├── StoreLeadRequest.php
│   ├── UpdateLeadRequest.php
│   ├── ConvertLeadRequest.php                # → Client + Case + Jobs bundle
│   ├── StoreClientRequest.php
│   ├── ...
│
├── Resources/                                # API Resources
│   ├── LeadResource.php
│   ├── LeadSummaryResource.php               # trimmed shape for lists
│   ├── ClientResource.php
│   ├── ClientProfileResource.php             # with active jobs + cases nested
│   ├── ...
│
└── Services/
    ├── LeadService.php
    ├── LeadConversionService.php             # الأثقل — Lead→Client+Case+Jobs في transaction
    ├── LeadActivityService.php               # نشاطات الاتصال/الاجتماع
    ├── ClientService.php
    ├── RecruitmentCaseService.php
    ├── JobRequirementService.php
    ├── PipelineTaskGeneratorService.php      # ⭐ محرك الـ handoff
    └── RecruitmentDashboardService.php
```

**قاعدة:** كل Controller لا يتجاوز ~120 سطر — كل المنطق في Service. كل Service يعتمد Repository حصراً لطلبات القاعدة (لا `Model::query()` في Services إلا للاستثناءات النادرة).

---

## 2. الطبقات (Layering)

```
┌─────────────────────────────────────────────────────────┐
│  Controllers (thin — validation via FormRequest + auth) │
└──────────────────────┬──────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────┐
│  Services (business logic + transactions + events)      │
│                                                          │
│  ┌───────────────────┐   ┌─────────────────────────┐   │
│  │ LeadService       │   │ PipelineTaskGenerator   │   │
│  │ ClientService     │   │  ↓ subscribes to        │   │
│  │ JobService        │   │  JobRequirementStage    │   │
│  │ ConversionService │   │  Advanced event         │   │
│  └────────┬──────────┘   └───────────┬─────────────┘   │
└───────────┼──────────────────────────┼──────────────────┘
            │                          │
            ▼                          ▼
┌───────────────────────┐  ┌────────────────────────────┐
│ Repositories          │  │ Cross-Module Dependencies  │
│  (queries only)       │  │  • Modules/Tasks/          │
└──────┬────────────────┘  │    TaskService::create()   │
       │                   │  • Modules/Notifications/  │
       ▼                   │    NotificationService     │
┌───────────────────────┐  │  • Models/User (Employees) │
│ Eloquent Models       │  │  • Spatie Permission       │
└───────────────────────┘  └────────────────────────────┘
```

**قواعد صارمة:**
- Controllers ⟶ Services فقط (لا Repository/Model مباشرة)
- Services ⟶ Repositories + Services أخرى (allowed cross-module)
- Repositories ⟶ Eloquent (المستوى الوحيد الذي يعرف SQL/Query Builder)
- Events تُطلق داخل الـ Service بعد commit للـ transaction (نفس نمط `LeaveRequestService::submit()` و `RequestService::submit()`)

---

## 3. Pipeline Engine — التصميم الأساسي

### 3.1 المفاهيم

| المفهوم | التعريف | مثال |
|---------|--------|------|
| `RecruitmentPipeline` | قالب مراحل قابل لإعادة الاستخدام | "Standard Job Pipeline" (7 مراحل) |
| `RecruitmentPipelineStage` | مرحلة واحدة داخل Pipeline | "Publish Job" (المرحلة 2) |
| `JobRequirement.pipeline_id` | ربط الوظيفة بـ Pipeline معين | كل وظيفة تختار قالبها |
| `JobRequirement.current_stage_id` | المرحلة الحالية للوظيفة | يتقدم عند "Complete & Handoff" |
| `stage_owner_rule` | كيف يُحدَّد المُسنَد إليه | `role:job_publisher` / `specific:user_id=42` / `case_owner` |
| `sla_hours` | كم ساعة قبل ما تُصبح المرحلة overdue | `48` |
| `auto_generate_task` | هل تُنشأ Task تلقائياً عند دخول المرحلة | `true` (افتراضي) |

### 3.2 دورة الحياة (Sequence Diagram)

```
Employee/System                 JobService              Event Bus              PipelineTaskGenerator          TaskService              NotificationService
      │                              │                       │                            │                          │                            │
      │ POST /jobs/{id}/advance      │                       │                            │                          │                            │
      ├─────────────────────────────►│                       │                            │                          │                            │
      │                              │ DB::transaction:      │                            │                          │                            │
      │                              │   validate transition │                            │                          │                            │
      │                              │   update job.stage_id │                            │                          │                            │
      │                              │   mark old task done  │                            │                          │                            │
      │                              │   commit              │                            │                          │                            │
      │                              │                       │                            │                          │                            │
      │                              │ dispatch(             │                            │                          │                            │
      │                              │   JobRequirement      │                            │                          │                            │
      │                              │   StageAdvanced)      │                            │                          │                            │
      │                              ├──────────────────────►│                            │                          │                            │
      │                              │                       │                            │                          │                            │
      │                              │                       │ resolve listener           │                          │                            │
      │                              │                       ├───────────────────────────►│                          │                            │
      │                              │                       │                            │                          │                            │
      │                              │                       │                            │ resolveStageOwner()      │                            │
      │                              │                       │                            │ TaskService::create(     │                          │
      │                              │                       │                            │   title, entity_type,    │                          │
      │                              │                       │                            │   entity_id, assigned_to)├─────────────────────────►│                            │
      │                              │                       │                            │                          │ dispatch TaskCreated       │
      │                              │                       │                            │                          ├───────────────────────────►│
      │                              │                       │                            │                          │                            │ push + email + broadcast
      │                              │                       │                            │                          │                            │
      │◄─── 200 { job, next_stage, task_id } ────────────────┴────────────────────────────┴──────────────────────────┴────────────────────────────┘
```

**الفكرة المفتاحية:**
1. `JobService::advanceStage()` يعمل update في transaction ثم ينشر Event
2. Listener منفصل ينشئ Task عبر `TaskService` الموجود مسبقاً — لا نكرر منطق التعيينات/الإشعارات
3. Task Engine يهتم بالباقي (deep links, push, broadcast) لأنه يعمل هيك بالفعل

### 3.3 Stage Owner Resolution

```php
// PipelineTaskGeneratorService::resolveStageOwner()

match ($stage->owner_rule_type) {
    'role'      => $this->firstUserWithPermission($stage->owner_rule_value),  // e.g. 'publish-jobs'
    'specific'  => User::find((int) $stage->owner_rule_value),
    'case_owner'=> $job->recruitmentCase->owner,
    'job_owner' => $job->owner,
    'previous_stage_owner' => $this->previousTaskAssignee($job),
    default     => throw new \InvalidArgumentException("Unknown owner rule: {$stage->owner_rule_type}"),
};
```

جدول `recruitment_pipeline_stages`:

```
id | pipeline_id | order | code           | name              | owner_rule_type | owner_rule_value | sla_hours | requires_fields  | auto_task
---+-------------+-------+----------------+-------------------+-----------------+------------------+-----------+------------------+----------
 1 | 1           | 1     | new            | New Job           | job_owner       | NULL             | NULL      | []               | false
 2 | 1           | 2     | publish        | Publish Job       | role            | publish-jobs     | 24        | []               | true
 3 | 1           | 3     | receive_apps   | Receiving Applic. | role            | screen-candidates| 168       | []               | false
 4 | 1           | 4     | screening      | Screening         | role            | screen-candidates| 72        | ['screening_notes']| true
 5 | 1           | 5     | shortlist      | Shortlist         | previous_stage_owner | NULL        | 24        | ['shortlist_ids']| true
 6 | 1           | 6     | interviewing   | Interviewing      | role            | schedule-interviews | 168    | []               | true
 7 | 1           | 7     | client_decision| Client Decision   | job_owner       | NULL             | 72        | []               | false
 8 | 1           | 8     | contracting    | Contracting       | role            | prepare-contracts | 48       | ['contract_terms']| true
 9 | 1           | 9     | hired          | Hired             | NULL            | NULL             | NULL      | []               | false
```

في Phase 1 نُسجّل كل هذه المراحل في seed لكن نُفعّل التحكم الفعلي عبرها فقط للمراحل 1–2 (المتبقي يبقى للـ Phases التالية).

---

## 4. تكامل Task Engine — التوسعة المطلوبة

### 4.1 Migration واحدة صغيرة

```php
// 2026_10_xx_xxxxxx_add_entity_polymorphic_to_tasks.php
public function up(): void
{
    Schema::table('tasks', function (Blueprint $table) {
        $table->string('entity_type', 50)->nullable()->after('assigned_to');
        $table->unsignedBigInteger('entity_id')->nullable()->after('entity_type');
        $table->index(['entity_type', 'entity_id'], 'tasks_entity_idx');
    });
}
```

### 4.2 Enum مركزي للأنواع

```php
// apps/api/app/Shared/Enums/TaskEntityType.php
enum TaskEntityType: string
{
    case Lead           = 'lead';
    case Client         = 'client';
    case RecruitmentCase= 'recruitment_case';
    case JobRequirement = 'job_requirement';
    // Phase 2:
    // case Candidate      = 'candidate';
    // case Interview      = 'interview';
    // case Contract       = 'contract';
}
```

**لماذا Enum مركزي بدل morph map؟**
morph map يفرض أن يكون `entity_type` اسم كلاس ModelBase. الـ Enum يفصل الاسم الظاهر في القاعدة عن الـ Model، وهذا يجعل حذف/تسمية Model لا يكسر البيانات التاريخية. نفس النمط المستخدم في `Enums/RequestStatus.php`.

### 4.3 توسعة `TaskResource`

`TaskResource` يبعث اليوم `entity` = null. نضيف:

```php
'entity' => $this->when(
    $this->entity_type !== null,
    fn () => [
        'type' => $this->entity_type,
        'id'   => $this->entity_id,
        'label'=> $this->resolveEntityLabel(),  // "Job #2026-0045 — Senior Backend Dev"
        'link' => $this->resolveEntityLink(),   // "/recruitment/jobs/45"
    ]
),
```

يتيح لواجهة My Tasks عرض "Job #2026-0045" كـ chip بجانب عنوان المهمة.

---

## 5. LeadConversionService — العملية الأثقل

عملية `Lead → Client + Case + Jobs` هي المكان الوحيد الذي يعمل create متعدد الجداول في transaction واحدة.

```php
public function convert(Lead $lead, ConvertLeadPayload $payload): array
{
    return DB::transaction(function () use ($lead, $payload) {
        // 1. Guard: is the lead already converted?
        if ($lead->status === LeadStatus::Converted) {
            throw ValidationException::withMessages([
                'status' => 'This lead has already been converted.',
            ]);
        }

        // 2. Create Client (or reuse if payload.reuse_client_id is set)
        $client = $payload->reuseClientId
            ? $this->clients->findOrFail($payload->reuseClientId)
            : $this->clients->create($this->clientAttributesFromLead($lead, $payload));

        // 3. Copy primary contact from Lead → ClientContact
        $this->clientContacts->create([
            'client_id' => $client->id,
            'full_name' => $lead->contact_person,
            'position'  => $lead->contact_position,
            'email'     => $lead->contact_email,
            'phone'     => $lead->contact_phone,
            'is_primary'=> true,
        ]);

        // 4. Create Recruitment Case
        $case = $this->cases->create([
            'client_id'  => $client->id,
            'title'      => $payload->caseTitle,
            'owner_id'   => $payload->caseOwnerId ?? $lead->owner_id,
            'source_lead_id' => $lead->id,
            'status'     => RecruitmentCaseStatus::Active,
        ]);

        // 5. Create initial Jobs (optional — can be added later)
        $jobs = collect($payload->jobs)->map(
            fn (array $jobData) => $this->jobs->create([
                'recruitment_case_id' => $case->id,
                'pipeline_id'  => $this->defaultPipelineId(),
                'current_stage_id' => $this->firstStageId(),
                ...$jobData,
            ])
        );

        // 6. Mark lead converted (never delete — audit trail)
        $lead->update([
            'status'          => LeadStatus::Converted,
            'converted_at'    => now(),
            'converted_client_id' => $client->id,
        ]);

        return compact('client', 'case', 'jobs');
    });

    // Post-commit fan-out (outside transaction):
    LeadConverted::dispatch($lead, $client, $case);
}
```

بعد الـ commit، `LeadConverted` event يُطلق `PipelineTaskGeneratorService` لكل Job جديد → مهمة Publish تصل لموظف النشر تلقائياً.

---

## 6. الأحداث والإشعارات

| Event | Fires when | Notification |
|-------|-----------|--------------|
| `LeadCreated` | Lead جديد أُدخل | in-app للـ Lead Owner |
| `LeadConverted` | تحويل Lead إلى Client | in-app للـ Case Owner + email للـ Sales Manager |
| `JobRequirementSubmitted` | Job جديد | in-app للـ Case Owner |
| `JobRequirementStageAdvanced` | تقدم مرحلة | `PipelineTaskGenerator` ينشئ Task → Task Notification |
| `LeadStale` (Scheduled Job) | يومياً — leads بدون activity منذ N يوم | in-app للـ Lead Owner |
| `StageSlaBreached` (Scheduled Job) | كل ساعة — مراحل تجاوزت `sla_hours` | in-app + email للمُسنَد إليه + للـ Manager |

Notification templates تُضاف كـ methods جديدة على `NotificationService` الحالي — نفس النمط المستخدم لـ `leaveSubmitted`, `requestPendingApproval`.

---

## 7. Frontend Module Structure

```
apps/web/src/app/(dashboard)/recruitment/
├── leads/
│   ├── page.tsx                    # List + filters + create button
│   ├── kanban/page.tsx             # Kanban view (drag-drop status)
│   ├── [id]/
│   │   ├── page.tsx                # Lead detail (contact, activities, notes)
│   │   ├── convert/page.tsx        # Convert dialog (client + case + jobs)
│   │   └── activities/new/page.tsx # Add activity dialog
│   └── new/page.tsx                # Create dialog
├── clients/
│   ├── page.tsx                    # List
│   └── [id]/
│       ├── page.tsx                # Profile tab
│       ├── cases/page.tsx          # Cases tab
│       ├── jobs/page.tsx           # Jobs tab
│       └── activity/page.tsx       # Timeline
├── cases/
│   └── [id]/page.tsx               # Case detail (jobs list + timeline)
├── jobs/
│   ├── page.tsx                    # Global jobs list (across cases)
│   └── [id]/page.tsx               # Job detail (stage pipeline visual)
├── pipelines/                       # Admin only
│   ├── page.tsx                    # List pipelines
│   └── [id]/page.tsx               # Edit stages
└── dashboard/
    └── page.tsx                    # KPIs + funnel + leaderboard
```

React Query hooks في `apps/web/src/lib/api/recruitment.ts` (نفس نمط `leaves.ts`, `requests.ts`).

---

## 8. Configure, Don't Code — نقاط التحكم الإدارية

كل هذه في Admin Panel:

| Setting | جدول | UI Location |
|---------|------|-------------|
| Currencies | `settings` key `general.currencies` | /settings#lists (قسم عام) |
| Lead Sources | `settings` key `recruitment.lead_sources` | /settings#lists (قسم التوظيف) |
| Industries / Company Sizes / Education Levels | `settings` keys `recruitment.*` | /settings#lists (قسم التوظيف) |
| Job Pipelines | `recruitment_pipelines` | /recruitment/pipelines |
| Pipeline Stages | `recruitment_pipeline_stages` | /recruitment/pipelines/{id} |
| Stage Owners (default) | حقول على stage | نفس المكان |
| SLA per stage (hours) | `sla_hours` على stage | نفس المكان |
| Required fields per stage | `requires_fields` json على stage | نفس المكان |

**قاعدة:** أي قائمة يُتوقع أن يعدّلها Admin أكثر من مرة في السنة → لا تكون Enum ثابت في PHP.

**كيف تعمل القوائم (مُنفّذ):**
- `App\Shared\Enums\OptionList` يحمل لكل قائمة: مفتاح الإعداد، القيم الافتراضية بتسميات عربية، ونمط الرمز المسموح.
- كل عنصر `{value, label}`. الرمز ثابت ويُخزَّن على السجلات، والاسم الظاهر للعرض فقط.
- إن لم يوجد صف في `settings` (أو كان تالفاً) تُستخدم القيم الافتراضية من الكود، فلا تحتاج القوائم أي seeder.
- `GET /api/option-lists` للنماذج (أي مستخدم مسجّل)، و `GET|PUT|DELETE /api/admin/option-lists/{list}` للتعديل (`manage-settings`).
- حذف رمز من القائمة لا يكسر السجلات القديمة: الواجهة تعرض القيمة كما هي، وتعديل الـ lead يقبل مصدره الحالي.

**قرار: قوائم تبقى في الكود وليست قابلة للتعديل:** Lead Statuses و Employment Types و Work Modes. الكانبان وتدفق التحويل والـ validation تعتمد على قيمها الحرفية (`converted`، `lost`، ...)، فإعادة تسميتها أو حذفها من الإعدادات تكسر السلوك وليس العرض فقط.

---

## 9. الاعتبارات الأمنية

| السطر | القرار |
|-------|--------|
| بيانات الـ Lead/Client حساسة تجارياً | كل الـ endpoints خلف `auth:sanctum` + permission check |
| مرفقات الـ Lead/Client (proposals, NDAs) | private disk (`local`) + signed URLs (30 min) — نفس نمط leave-attachments |
| Rate limiting على `POST /leads` | 30/hour/user (يمنع مضخات إدخال آلية) |
| Audit logging على convert/delete/reassign | مسجل في `audit_logs` عبر Observer |
| Cross-tenant leakage prevention (مستقبلاً) | تصميم Repository ينوي منذ الآن قبول `?tenant_id` param لسهولة إضافة multi-tenancy لاحقاً |
| PII في Lead/Client (`contact_email`, `contact_phone`) | تُصدَّر في CSV فقط بأذون خاصة (`export-recruitment-data`) |

---

## 10. ما يُعاد استخدامه vs ما هو جديد — ملخص

| البُعد | جديد | مُعاد استخدامه من الموجود |
|--------|------|-------------------------------|
| Models | 8 | User, Employee |
| Migrations | 8 + 1 توسعة | جدول tasks الحالي |
| Services | 8 | TaskService, NotificationService, PermissionService |
| Events | 5 | نفس الـ Event Bus |
| Notifications | 6 templates جديدة | NotificationService كامل |
| Frontend | ~10 صفحات | React Query, layout, RTL, auth guards |
| Auth | 12 permission جديد | Spatie Permission + Guards |
| Broadcast | 3 channels جديدة | Reverb + Echo |

---

**التالي:** انظر [`02-erd.md`](02-erd.md) لـ ERD كامل و DDL لكل جدول.
