# TAQAT Recruitment Module — Overview & Phase 1 Design

> **الوثيقة رقم 1 من حزمة تخطيط Recruitment Module**
> النطاق: نظرة عامة على الموديول، القرارات المعمارية، تعريف نطاق Phase 1، الفصل بين المراحل.
> الوثائق المرافقة: [`01-architecture.md`](01-architecture.md) · [`02-erd.md`](02-erd.md) · [`03-phase-1-plan.md`](03-phase-1-plan.md)

---

## 1. Executive Summary

موديول **Recruitment** هو امتداد لنظام TAQAT الداخلي بهدف إدارة دورة التوظيف الكاملة من **اكتشاف العميل المحتمل (Lead)** حتى **توقيع العقد (Hired)**، بحيث يصبح النظام قادراً على تشغيل قسم التوظيف بالكامل بدلاً من الاعتماد على Excel والرسائل الشخصية.

الموديول يُبنى كـ **Module جديد داخل نفس Modular Monolith** (`apps/api/app/Modules/Recruitment/`) دون فصل خدمة، ويستفيد من البنية التحتية القائمة (Task Engine, Notification Service, RBAC, Attachments, Audit Logs). القرار المعماري الحرج هو **بناء Pipeline Engine مستقل** عن الـ Workflow Engine الحالي، لأن الأخير مصمم لقرارات Approve/Reject بينما مراحل التوظيف هي **Operational Handoff بين مالكين مختلفين**.

الطرح يتم على **4 مراحل** تُبنى بالتتابع. هذه الوثيقة تركز على **Phase 1 (MVP)** — Leads + Clients + Job Requirements + Task Handoff — الذي يعطي قيمة تجارية فورية كـ Sales/CRM Pipeline بدون التزام بـ ATS الكامل.

---

## 2. الفصل بين الكيانات الأساسية

هذه أهم نقطة في التصميم قبل أي جدول:

| الكيان | التعريف | مثال |
|--------|---------|------|
| **Lead** | شركة محتملة لم تُحوَّل بعد إلى عميل | "شركة ABC Technology مهتمة بتوظيف 5 مطورين" |
| **Client** | شركة أصبحت عميلاً فعلياً وقّعت اتفاق تعامل | "ABC Technology — Active Client since 2026-01-15" |
| **Recruitment Case** | حملة/طلب توظيف واحد من العميل (يجمع عدة وظائف) | "Kerten Hospitality — Q1 2026 Hiring Campaign (20 remote roles)" |
| **Job Requirement** | وظيفة محددة داخل الحملة | "Senior Backend Developer × 2 — Remote — $1,500–$2,000" |
| **Candidate** | شخص متقدم لوظيفة معينة | مؤجَّل لـ Phase 2 |

**العلاقات:**

```
Lead (converted 1:1) ──► Client (1:n) ──► Recruitment Case (1:n) ──► Job Requirement (1:n) ──► [Candidates - Phase 2]
```

**لماذا Recruitment Case وليس Client → Jobs مباشرة؟**

Client قد يطلب عدة حملات توظيف على مدى سنوات. تجميع الوظائف تحت "Case" يعطي:
- لوحة تحكم لكل حملة (KPIs, timeline, owner)
- سياق للـ SLA والـ Deadline (الحملة كلها لها تاريخ استحقاق واحد غالباً)
- سهولة إغلاق حملة كاملة دون التأثير على العميل نفسه

---

## 3. القرارات المعمارية

### 3.1 Module vs Separate Service — **Modular Monolith**

Recruitment يُبنى كـ Module جديد داخل نفس الـ Laravel API الحالي. الأسباب:
- كل الأدوات المشتركة موجودة (Sanctum, RBAC, Notifications, Tasks)
- ما فيه سبب تشغيلي لفصل الخدمة (نفس فريق التطوير، نفس قاعدة البيانات، نفس السياسة الأمنية)
- الفصل المنطقي عبر namespace يكفي: `App\Modules\Recruitment\*`

### 3.2 Pipeline Engine — **جديد ومستقل عن Workflow**

**لماذا لا نستخدم WorkflowStep الحالي؟**

`WorkflowStep` مصمم لسيناريو Approval — كل خطوة تُنتج قرار (approved/rejected/returned) ولها Approver يُحدَّد بـ (`DirectManager`, `DepartmentManager`, `SpecificRole`, ...). Recruitment Pipeline مختلف جوهرياً:

| البُعد | WorkflowStep الحالي | Recruitment PipelineStage الجديد |
|-------|---------------------|-----------------------------------|
| الهدف | قرار (Approve/Reject) | تسليم عمل (Handoff) |
| المالك | Approver يُحدَّد بقاعدة | Operational Owner يتغير كل مرحلة |
| الإخراج | قرار على السجل نفسه | Task مولَّدة تلقائياً للمالك التالي |
| الرجوع | يعود لصاحب الطلب للتعديل | نادر — عادة يستمر أو يُلغى |
| SLA | مبني على `days_to_respond` للـ Approval | مبني على مدة العمل الفعلي داخل المرحلة |

**القرار:** ننشئ `RecruitmentPipeline` + `RecruitmentPipelineStage` كأنماط قائمة بذاتها، ويستفيد Task Engine الحالي كطبقة تنفيذ.

### 3.3 Task Engine — **إعادة استخدام مع توسعة صغيرة**

جدول `tasks` الحالي لا يعرف بأي كيان "تخص هذه المهمة". نضيف **حقلين polymorphic** بسيطين:

```sql
ALTER TABLE tasks
  ADD COLUMN entity_type VARCHAR(50) NULL AFTER assigned_to,
  ADD COLUMN entity_id  BIGINT UNSIGNED NULL AFTER entity_type,
  ADD INDEX tasks_entity_idx (entity_type, entity_id);
```

هذا يسمح لـ Task واحدة أن ترتبط بـ `Lead` / `JobRequirement` / `Candidate` / ... دون تعديل جدول tasks لكل نوع جديد. المهام العامة (بدون كيان) تبقى تعمل كما هي (`entity_type IS NULL`).

### 3.4 لا Roles جديدة — Individual Permissions فقط

بدل إنشاء 6 أدوار (Recruitment Manager, Recruitment Officer, Publisher, Screening Officer, Interview Coordinator, Contracting Officer)، نستخدم **صلاحيات دقيقة** يُركبها Admin على الأدوار الموجودة أو مباشرة على المستخدمين. الأدوار قابلة للتغيير بمرور الوقت — الصلاحيات ثابتة.

انظر [`03-phase-1-plan.md#rbac-matrix`](03-phase-1-plan.md#7-rbac--الصلاحيات).

### 3.5 Configure, Don't Code — تأكيد على المبدأ

كل شيء قابل للتخصيص من Admin Panel:
- **Lead Sources** — قائمة قابلة للإضافة (LinkedIn / Referral / Website / ...)
- **Lead Statuses** — pipeline كامل (New / Contacted / Qualified / ...)
- **Job Pipelines** — كل نوع وظيفة قد يكون له pipeline مختلف
- **Stage Owners** — إعدادات الافتراضي لكل مرحلة (مثلاً "Publish → أول موظف بصلاحية `publish-jobs`")
- **SLA per Stage** — بالساعات/الأيام
- **Required Fields per Stage** — قائمة حقول واجب ملؤها للانتقال

---

## 4. الفصل بين المراحل

### Phase 1 — MVP (النطاق الحالي) 🎯

**Sales/CRM Pipeline فقط.** يعطي قيمة تشغيلية فورية لقسم البيع والحسابات دون التزام بـ ATS كامل.

المُسلَّم:
- Lead Management (CRUD + Pipeline + Kanban view)
- Lead → Client Conversion
- Client Management (CRUD + Profile page)
- Recruitment Case (لتجميع الوظائف)
- Job Requirement (CRUD + الحقول الأساسية)
- Task Handoff Engine (Pipeline Stages + Auto-assign + Notifications)
- Basic Dashboard (Leads/Clients/Jobs counts + Pipeline funnel)
- RBAC + Audit Logs
- CSV Export

**غير مشمول في Phase 1:**
- نشر الإعلان (BrightGaza integration أو UI)
- استيراد Applicants
- Screening / Shortlist / Interviews
- Contracts

### Phase 2 — Recruitment (ATS Core)
Job Publishing UI + Applicant Import (Excel/CSV) + Candidate Database + Screening + Shortlist + Interview Scheduling & Feedback.

### Phase 3 — Hiring
Client Decision → Selection → Contract Preparation → Signed Contract → Hired + Recruitment KPIs + SLA Reports.

### Phase 4 — Automation & Integration
BrightGaza API + Automatic applicant sync + AI CV Screening + AI Candidate Matching + Forecasting.

---

## 5. الجدوى والتكلفة

### 5.1 حجم Phase 1

قياساً على أحدث موجات التطوير في المشروع (wave-m + wave-n):

| المكوّن | تقدير الوحدات | تفصيل |
|--------|----------------|--------|
| Migrations | 8 | leads, lead_activities, clients, client_contacts, recruitment_cases, job_requirements, recruitment_pipelines, recruitment_pipeline_stages + توسعة `tasks` |
| Models | 8 | مقابل الجداول أعلاه |
| Repositories | 6 | Lead, Client, Case, Job, Pipeline, PipelineStage |
| Services | 5 | LeadService, LeadConversionService, ClientService, JobRequirementService, PipelineTaskGeneratorService |
| Controllers | 8 | Admin + Self-service حيث يلزم |
| Resources | 8 | مقابل كل كيان + Summary variants |
| Requests (Validation) | ~15 | Create/Update/Convert لكل كيان |
| Frontend Pages (Next.js) | ~10 | leads list + kanban + detail + convert dialog / clients list + detail / cases + jobs + pipeline builder |
| Tests | ~30 Feature test | تغطية الـ endpoints الأساسية |

**تقدير الجهد:** أسبوعان إلى ثلاثة أسابيع من التطوير المتواصل لمطوّر واحد بنفس وتيرة waves السابقة.

### 5.2 المخاطر المعمارية

| المخاطرة | التأثير | التخفيف |
|---------|--------|---------|
| بناء Pipeline Engine ثم اكتشاف حاجة لإعادة تصميم في Phase 2 | إعادة كتابة الـ Handoff logic | تصميم `RecruitmentPipeline` كـ abstraction عامة من البداية — قادرة على استيعاب مراحل Candidate/Interview لاحقاً بدون تعديل الجداول |
| الـ polymorphic entity على tasks يفتح باب لأي كيان | تشتُّت المهام | تعريف constant `TASK_ENTITY_TYPES` في enum مركزي |
| BrightGaza integration مؤجَّل — قد يفرض تصميم مختلف لاحقاً | إعادة نمذجة Candidate | جدول `candidates` (Phase 2) يُصمَّم بحقل `external_source_id` قابل لأي مصدر خارجي |
| بيانات Lead/Client حساسة (تجارياً) | تسريب معلومات عملاء محتملين | تطبيق نفس نمط الـ Employee data — Signed URLs للمرفقات + private disk + audit logs |

---

## 6. مقارنة مع النظام الحالي

| المجال | حالياً | بعد Phase 1 |
|--------|-------|-------------|
| **Leads / CRM** | لا يوجد | Module كامل مع Kanban + Conversion |
| **Clients** | لا يوجد | Profile مركزي مع Cases + Jobs + Activity |
| **Recruitment Workflow** | Tasks عامة يدوية | Pipeline Engine + Auto Task Generation |
| **Task Handoff** | تخصيص يدوي عبر `assigned_to` | Stage transition → auto Task creation for next owner |
| **Task ↔ Business Entity** | Task منفصلة عن أي كيان | `entity_type/entity_id` polymorphic على tasks |
| **Audit** | audit_logs عام | نفسه، مع أنواع كيانات جديدة (Lead/Client/...) |

---

## 7. مخرجات هذه الحزمة الوثائقية

| الوثيقة | المحتوى |
|---------|---------|
| **00-overview.md** (هذه) | Executive summary + Architectural decisions + Phasing |
| **01-architecture.md** | Module structure + Layering + Pipeline Engine design + Class diagrams |
| **02-erd.md** | ERD كامل + Full DDL لكل جدول + Indexes + Foreign key rationale |
| **03-phase-1-plan.md** | خطة تنفيذية بمهام مرقّمة + RBAC Matrix + API Endpoints Table + Test Plan |

---

## 8. القرارات المفتوحة (تحتاج إجابة قبل بدء التنفيذ)

هذه أسئلة تصميمية لا يمكن الإجابة عليها من قراءة الـ SRS وحده — تحتاج قرار من صاحب المنتج:

1. **Multi-tenancy?** — هل TAQAT و BrightGaza كيانان تجاريان منفصلان يستخدمان نفس النظام (يتطلب `tenant_id` على كل جدول)، أم أن BrightGaza مجرد قناة تسويقية لـ TAQAT (لا يحتاج tenancy)؟
2. **Deals/Contracts on Client?** — هل مطلوب في Phase 1 تسجيل قيمة تعاقد إجمالية للـ Client (يفيد Sales KPIs)، أم نؤجل لـ Phase 3؟
3. **Lead ownership transfer** — عند تغيير Lead Owner، هل يبقى الأول في السجل التاريخي أم يُستبدل كلياً؟
4. **Deduplication policy** — لو موظفان أدخلا نفس Lead (نفس اسم الشركة/الإيميل)، ما الإجراء؟ Auto-merge / Warn / Block؟
5. **Recruitment Case necessity** — هل نبقيه في Phase 1 (طبقة إضافية) أم نبدأ بـ Client → Jobs مباشرة ونضيفه لاحقاً (يوفّر أسبوع تطوير لكن يفرض migration معقّد بعدين)؟

**توصيتنا:**
- (1) بدون tenancy الآن — BrightGaza قناة تسويقية.
- (2) تأجيل قيمة التعاقد لـ Phase 3 (مع الـ Contract).
- (3) تسجيل تاريخي كامل في `lead_activities`.
- (4) Warn مع خيار Force-create.
- (5) الإبقاء عليه — التكلفة الإضافية ساعتان تطوير، والتوفير في Migration المستقبلي كبير.

---

**التالي:** انظر [`01-architecture.md`](01-architecture.md) لتفاصيل بنية الـ Module والـ Pipeline Engine.
