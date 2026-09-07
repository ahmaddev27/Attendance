# TAQAT v2 — Overview

> **الوثيقة رقم 1 من حزمة تخطيط v2**
> النطاق: نظرة عامة، مقارنة v1↔v2، القرارات المعمارية، المراحل، معايير النجاح، المخاطر.
> الوثائق المرافقة: [`01-architecture.md`](01-architecture.md) · [`02-erd.md`](02-erd.md) · [`03-phase-1-plan.md`](03-phase-1-plan.md)

---

## 1. Executive Summary

**TAQAT v2** هو إعادة بناء كاملة لتطبيق حضور موظفين بسيط (v1) ليتحول إلى **منصة عمل رقمية متكاملة (Digital Workplace)** تجمع بين إدارة الموارد البشرية (HRMS)، إدارة المشاريع، منهجية Scrum، بناء الطلبات والموافقات (Workflow Engine)، ومساعد ذكاء اصطناعي مبني على Anthropic Claude.

النظام يُبنى كـ **Modular Monolith** على Laravel 11 (Backend/API) مع **Next.js 15** كواجهة SPA منفصلة، ويستضاف على **VPS بواسطة Docker Compose**. يبقى الهوية البصرية (شعار TAQAT + الأزرق `#2678C4` والبرتقالي `#F5A623` + خط Tajawal RTL) موحّدة مع v1، لكن كل الطبقات التقنية تحتها جديدة بالكامل.

المرحلة الأولى (MVP، ~12 أسبوع) تُطلق: مصادقة كاملة بأدوار (RBAC)، إدارة موظفين + هيكل تنظيمي، حضور QR ديناميكي بجيولوكيشن، محرّك ساعات عمل، إجازات متعددة الأنواع برصيد، محرّك Workflow عام + طلبات (إجازة/مأمورية/سلفة)، مهام مبسّطة مع تعليقات ومرفقات، مركز إشعارات، ولوحة إدارة + تقارير أساسية. تُؤجَّل: المشاريع/Sprints/Scrum الكامل، الذكاء الاصطناعي، PDF export، Executive Dashboard.

---

## 2. الفجوة بين v1 و v2

الجدول التالي يقارن **ما هو موجود اليوم** مقابل **ما يجب أن يصبح عليه v2**، مع تصنيف حجم التغيير (Δ):

| المجال                | v1 (الحالي)                                                                | v2 (المستهدف)                                                                                             | Δ (حجم التغيير) |
| --------------------- | -------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------- | ---------------- |
| **الاستضافة**         | cPanel (PHP shared hosting)                                                | VPS + Docker Compose                                                                                      | 🔴 كامل           |
| **الواجهة الأمامية**   | Livewire 3 + Blade + Tailwind (SSR)                                        | Next.js 15 (App Router, React) SPA منفصلة                                                                 | 🔴 كامل           |
| **الـ Backend**       | Laravel 11 monolith بمكوّنات Livewire متداخلة                                | Laravel 11 API-only، Modular Monolith، Sanctum tokens                                                     | 🔴 كامل           |
| **قاعدة البيانات**    | MySQL — 6 جداول (users, employees, attendances, leave_requests, settings, sms_logs) | MySQL — ~40 جدولاً موزّعة على 12 وحدة (Auth, Org, Employees, Attendance, Leaves, Tasks, Projects, Sprints, Workflow, Requests, Notifications, AI, Audit) | 🔴 كامل            |
| **المصادقة**          | تسجيل دخول أدمن فقط (Breeze email/password)                                 | تسجيل دخول لكل الموظفين بـ `employee_number` + password، Sanctum API tokens، تجديد Token، 2FA اختيارية      | 🔴 كامل           |
| **الأدوار والصلاحيات** | حقل `is_admin` بسيط                                                        | Spatie Permission — 5 أدوار (Super Admin / Management / Manager / Team Leader / Employee) + Permissions دقيقة | 🔴 كامل           |
| **الهيكل التنظيمي**   | لا يوجد                                                                    | Companies + Departments (شجرية) + Teams + Positions + Direct Manager hierarchy                            | 🟢 جديد           |
| **الموظفون**          | الحقول: `name, employee_number, email, phone, is_active`                    | + `first_name, last_name, position_id, department_id, team_id, direct_manager_id, employment_type, joining_date, birth_date, gender, avatar_path, status, work_schedule_id, soft_delete` | 🟠 توسيع كبير     |
| **الحضور**            | QR ثابت + تسجيل دخول/خروج مع GPS/IP fraud check أساسي                      | QR ديناميكي متجدد (rotates every N seconds) + Geofencing (POINT + allowed_radius) + IP whitelist + Devices متعددة + Working Schedules قابلة للتخصيص + Holidays + Working Hours Engine (late/early/overtime/absence auto-computed) | 🟠 توسيع كبير     |
| **الإجازات**          | نوع واحد فقط (leave_requests): approve/reject                              | Leave Types (متعددة، مدفوعة/غير مدفوعة، برصيد/بدون) + Leave Balances سنوية + Entitlement + Workflow متعدد المستويات + مرفقات + قواعد (min_notice, max_consecutive) | 🟠 توسيع كبير     |
| **المهام**            | لا يوجد                                                                    | Tasks + Subtasks + Priorities + Statuses (custom per project) + Assignment + Comments + Mentions + Attachments + History log + Tags | 🟢 جديد           |
| **المشاريع / Scrum**   | لا يوجد                                                                    | Projects + Members (roles) + Sprints + Burndown + Velocity + Story Points + Sprint Planning/Review/Retro | 🟢 جديد (مؤجَّل لـ Phase 2) |
| **Workflow Engine**   | لا يوجد                                                                    | محرّك عام: Workflows → Steps (approver types: direct_manager / department_manager / role / specific / field_reference) + SLA + can_return/can_forward | 🟢 جديد           |
| **Request Builder**   | لا يوجد                                                                    | Request Types بـ `form_schema` JSON ديناميكي (Configure Don't Code) — يمرّ عبر Workflow Engine             | 🟢 جديد           |
| **الإشعارات**         | لا يوجد مركز (SMS فقط عند تسجيل دخول)                                        | Notification Center: in-app + email + SMS (MTC) + WhatsApp (Phase 2) + read/unread + preferences per-user | 🟠 توسيع كبير     |
| **الذكاء الاصطناعي**   | لا يوجد                                                                    | AI Engine (Anthropic Claude) عبر abstraction layer — Daily Motivation + Manager Assistant + Task Helper + cost/token tracking | 🟢 جديد (Phase 3) |
| **الـ Real-time**     | لا يوجد                                                                    | Laravel Reverb (WebSockets) — إشعارات فورية، تحديث Kanban، حضور live                                      | 🟢 جديد           |
| **تخزين الملفات**     | Local disk                                                                 | MinIO (S3-compatible) داخل Docker — buckets: `avatars`, `attachments`, `exports` + signed URLs             | 🟠 كامل           |
| **الطوابير**          | Sync (default = sync per commit `e64ad05`)                                 | Redis + 3 queues (`notifications`, `ai`, `reports`) — Horizon اختيارياً في Phase 2                        | 🟠 كامل           |
| **التقارير**          | لوحة إدارة KPIs بسيطة                                                       | Attendance / Leaves / Requests / Tasks reports + CSV export (Phase 1) + Excel + PDF (Phase 2) + Executive Dashboard (Phase 3) | 🟠 توسيع كبير     |
| **البحث الشامل**      | لا يوجد                                                                    | Global search بـ Meilisearch/Scout (Phase 2)                                                              | 🟢 جديد           |
| **سجل التدقيق**       | لا يوجد                                                                    | Spatie ActivityLog على كل العمليات الحساسة                                                                | 🟢 جديد           |
| **PWA**               | لا يوجد                                                                    | Next.js PWA (manifest + service worker + offline shell للحضور)                                            | 🟢 جديد           |
| **CI/CD**             | git push + rsync يدوي                                                      | GitHub Actions → GHCR → `docker compose pull && up -d` على VPS (zero-downtime)                            | 🟢 جديد           |
| **الاختبارات**        | 106 tests (Pest) — feature/unit للـ Livewire                                | Pest للـ API (contract + integration) + Playwright للـ E2E على Next.js                                    | 🟠 كامل           |

**الخلاصة:** v2 هو **مشروع جديد كلياً** يستفيد من الدروس المكتسبة في v1 ويحافظ على الأصول التالية فقط:
1. شعار TAQAT (`public/img/logo.png`) والألوان (`#2678C4` / `#F5A623`) وخط Tajawal.
2. أنماط MTC SMS integration (طريقة الاستدعاء + تشفير الاعتماديات في `settings`).
3. القيم المشتقة من قاعدة بيانات v1: قائمة الموظفين الحاليين، سجل الحضور، الطلبات المفتوحة → تُهجَّر عبر seeder مخصّص.

---

## 3. القرارات المعمارية الجوهرية

الجدول التالي يوثّق **الثمانية قرارات** التي اتُّفق عليها مع صاحب المشروع، ولماذا:

### 3.1 بناء جديد (Fresh Start) — لا تمديد لـ v1
**القرار:** إعادة بناء كاملة داخل مستودع جديد (monorepo) بدل تعديل v1 تدريجياً.
**السبب:**
- v1 مبني على Livewire (SSR)، بينما v2 يحتاج SPA — لا يمكن التعايش.
- بنية v1 flat (كل شيء في `app/Models`، `app/Livewire`) لا تحتمل ~40 جدولاً و12 وحدة.
- قرار الاستضافة (VPS + Docker) يتطلب migration كاملة على أي حال.
- الـ RBAC والـ multi-tenancy الجزئي (Companies) يحتاج مخطط جداول جديد للجذور.

**النتيجة:** يُنقَل من v1 فقط: الشعار + tokens التصميم + منطق MTC SMS + بيانات المستخدمين والحضور عبر seeder migration script.

### 3.2 تسجيل دخول الموظف عبر `employee_number` + password
**القرار:** الموظف يسجّل بـ رقمه الوظيفي (وليس email)، والأدمن يسجّل بـ email أو employee_number.
**السبب:**
- كثير من الموظفين قد لا يملكون بريداً مؤسسياً موحّداً.
- الرقم الوظيفي معروف على البطاقة الشخصية = تجربة أسهل.
- الـ email يبقى في السجل لأغراض الإشعار والاسترجاع.
**التنفيذ:** custom guard في Sanctum يقبل حقل `login` ويبحث في `employee_number` أولاً ثم `email`.

### 3.3 مزوّد الذكاء الاصطناعي: Anthropic Claude
**القرار:** استخدام Anthropic Claude API كمزوّد افتراضي، لكن عبر **abstraction layer** يسمح بالتبديل لاحقاً.
**السبب:**
- جودة عالية للنصوص العربية (Motivation + Manager assistant).
- Prompt caching يخفّض التكلفة للنماذج المتكرّرة (Daily Motivation).
- API مستقر، توثيق ممتاز.
**التنفيذ:** `App\Modules\AI\Contracts\AIProvider` interface → `AnthropicProvider` implementation. كل الاستدعاءات عبر `AIService` مع تتبّع التوكنز والتكلفة في جدول `ai_messages`.
**مخاطر:** التكلفة قد ترتفع مع الاستخدام — نضع Rate limits per user وحدّاً شهرياً في `ai_settings`.

### 3.4 الاستضافة: VPS خاص + Docker Compose
**القرار:** نقل من cPanel إلى VPS يديره صاحب المشروع، بواسطة Docker Compose (وليس Kubernetes).
**السبب:**
- cPanel لا يدعم WebSockets (Reverb) ولا queue workers ولا Redis محلي بشكل موثوق.
- Kubernetes overkill لتطبيق واحد + فريق صغير.
- Docker Compose يعطي عزل الخدمات + سهولة نشر + قابلية نسخ للـ staging.
**التنفيذ:** ملف واحد `docker-compose.yml` يشغّل 9 خدمات (تفاصيل في [`01-architecture.md#docker-compose`](01-architecture.md)).

### 3.5 الواجهة: Next.js 15 (React) SPA منفصلة
**القرار:** بناء الواجهة كتطبيق Next.js 15 مستقل (App Router)، يستهلك REST API من Laravel.
**السبب:**
- Livewire ممتاز لتطبيقات بسيطة، لكن Kanban + Sprint Board + Realtime + PWA تحتاج تفاعلاً غنياً على العميل.
- فريق التطوير يفضّل React ecosystem (shadcn/ui, Zustand, TanStack Query).
- الفصل يسمح بتطبيق موبايل React Native لاحقاً بنفس الـ API.
**التنفيذ:** monorepo بـ `apps/api` و `apps/web`. Next.js في SSR mode للصفحات العامة (login, scan) وCSR للـ dashboard.

### 3.6 الوقت الحقيقي: Laravel Reverb (self-hosted)
**القرار:** استخدام Laravel Reverb (WebSocket server رسمي من Laravel) بدل Pusher السحابي أو Soketi.
**السبب:**
- مجاني تماماً + مدعوم رسمياً من Laravel.
- يتحدث بروتوكول Pusher = يعمل مباشرة مع `laravel-echo` و `pusher-js` على العميل.
- لا اعتماد على خدمة خارجية → الخصوصية والسيطرة.
- Soketi كان بديلاً ممتازاً لكنه توقّف عن التطوير.
**التنفيذ:** container مستقل `reverb` يستمع على منفذ 8080، خلف Nginx reverse proxy مع TLS.

### 3.7 تخزين الملفات: MinIO (S3-compatible)
**القرار:** تشغيل MinIO داخل Docker كخدمة تخزين متوافقة مع S3.
**السبب:**
- Laravel `filesystems.php` يدعم S3 driver مباشرة → التبديل لاحقاً إلى AWS S3 حقيقي بتغيير env فقط.
- Signed URLs جاهزة → أمان للملفات الحسّاسة (المرفقات، الإجازات، سجلات AI).
- عزل عن قرص التطبيق → backups مستقلة، وسِعة قابلة للتوسيع.
**التنفيذ:** MinIO container + 3 buckets (`avatars` / `attachments` / `exports`) + integration عبر `league/flysystem-aws-s3-v3`.

### 3.8 CI/CD: GitHub Actions → GHCR → docker compose pull
**القرار:** بناء صور Docker على GitHub Actions، دفعها إلى GitHub Container Registry (GHCR)، سحبها على VPS.
**السبب:**
- GHCR مجاني للمستودعات الخاصة بحجم معقول.
- Actions مدمج مع الـ repository = لا خدمة CI منفصلة.
- سحب الصورة على VPS أسرع وأنظف من `git pull && composer install && npm build`.
**التنفيذ:** workflow واحد يبني `api` و`web` images، يوسمها بـ SHA + `latest`، ثم SSH إلى VPS لتشغيل `docker compose pull && docker compose up -d`.

---

## 4. الانتقال من cPanel إلى VPS

### 4.1 لماذا الانتقال ضروري
| القيد على cPanel                          | الأثر                                                                                          |
| ------------------------------------------ | ---------------------------------------------------------------------------------------------- |
| لا WebSockets                              | Real-time (Reverb) مستحيل → لا إشعارات فورية ولا تحديث Kanban.                                  |
| لا queue workers دائمة                     | AI + Reports + SMS تحتاج queues — cron كل دقيقة لا يكفي (latency عالٍ + memory leaks).           |
| لا Redis محلي موثوق                        | Caching + Sessions + Broadcasting يحتاج Redis → Memcached في cPanel محدود.                     |
| لا Docker                                  | لا إمكانية تشغيل MinIO / Meilisearch / Reverb كخدمات محلية معزولة.                              |
| PHP / MySQL versions مقفلة                 | التحديث لـ PHP 8.3+ أو MySQL 8 قد لا يكون متاحاً.                                              |
| موارد مشتركة                               | تطبيق حساس (attendance بجيولوكيشن) يجب أن يستجيب سريعاً — jitter المشترك مشكلة.                 |

### 4.2 ما يتغيّر عملياً
| الجانب              | cPanel (v1)                                    | VPS (v2)                                                                        |
| ------------------- | ---------------------------------------------- | ------------------------------------------------------------------------------- |
| Web server          | Apache + `.htaccess`                           | Nginx (reverse proxy) + PHP-FPM في container                                    |
| SSL                 | AutoSSL cPanel                                 | Let's Encrypt عبر Certbot أو Traefik ACME                                       |
| Cron                | cPanel Cron UI                                 | container `scheduler` يشغّل `php artisan schedule:work`                          |
| Backups             | يدوي / JetBackup                              | script يومي `mysqldump` + `mc mirror` (MinIO) → إلى S3 خارجي أو BackBlaze B2   |
| Logs                | `error_log` في `public_html`                   | Docker JSON logs + `docker compose logs` أو Loki (اختياري لاحقاً)               |
| النشر               | FTP / Git-in-cPanel                            | GitHub Actions → GHCR → SSH pull                                                |

### 4.3 مسار الترحيل للبيانات الحالية
هذه خطة عملية لنقل بيانات v1 الحيّة إلى v2 عند لحظة الإطلاق:

1. **تجميد v1** — إعلان صيانة نصف ساعة، إيقاف تسجيل الدخول.
2. **Full mysqldump من cPanel** → `taqat_v1_final.sql`.
3. **تشغيل seeder ترحيل مخصّص** (`database/seeders/V1MigrationSeeder.php`) داخل بيئة v2 staging:
   - `users` → `users` (إعادة تعيين passwords → موظفون يجب أن يعيدوا التعيين عبر رابط SMS)
   - `employees` → `employees` (تعبئة الحقول الجديدة بقيم افتراضية: `first_name/last_name` من split للـ `name`، `status='active'`، `work_schedule_id=1` (Default))
   - `attendances` → `attendances` (نفس البيانات + تعبئة الحقول المحسوبة `late_minutes` بـ 0 مبدئياً — Working Hours Engine سيعيد احتساب بأثر رجعي لآخر 90 يوماً)
   - `leave_requests` → `leave_requests` + `leave_balances` (تخصيص default entitlement للسنة الحالية)
   - `settings` → `settings` (بالكامل — نفس البنية key/value)
   - `sms_logs` → `sms_logs` (بالكامل)
4. **إنشاء `companies` سجل واحد**، `departments` افتراضي "General"، `positions` "Employee/Manager"، `work_schedules` واحد افتراضي.
5. **تشغيل `php artisan taqat:recompute-attendance --from=<date>`** ليعيد احتساب المتأخّرات لآخر 90 يوماً بالقواعد الجديدة.
6. **تشغيل smoke tests**: كل موظف يظهر، سجل الحضور صحيح، الإجازات المفتوحة تنتقل إلى Workflow instance من نوع Leave.
7. **رفع DNS إلى VPS الجديد** + إشعار SMS جماعي بروابط إعادة تعيين كلمة المرور.
8. **إبقاء v1 read-only لمدة 30 يوماً** كنسخة احتياطية مرجعية.

### 4.4 مخاطر الترحيل ومعالجتها
| الخطر                                                | الأثر                       | المعالجة                                                                     |
| ---------------------------------------------------- | --------------------------- | ---------------------------------------------------------------------------- |
| موظفون لا يستقبلون SMS إعادة التعيين                  | لا يستطيعون الدخول          | fallback: default password = `taqat@<employee_number>` مع إجبار تغيير عند أول دخول |
| بيانات الحضور القديمة تُعاد حسابها بقواعد مختلفة       | فروقات في التقارير التاريخية | تجميد v1 read-only + وسم الأثر الرجعي في UI: "أُعيد الاحتساب في dd/mm/yyyy" |
| الطلبات المفتوحة (leave pending) لا تجد Workflow      | ضياع طلبات                  | seeder ينشئ `workflow_instances` جاهزة على step الموافقة المتبقية            |
| DNS propagation                                      | انقطاع 5-30 دقيقة            | إعلان مسبق + خفض TTL قبل التبديل بـ 24 ساعة                                  |

---

## 5. مبادئ التصميم

هذه المبادئ **ملزمة** لكل الوحدات، مستمدة من SRS ومن معايير الهندسة العالمية:

### 5.1 Configure, Don't Code
**التعريف:** أي شيء قابل للتغيّر عبر الزمن (types, statuses, form fields, workflow steps, working schedules, leave rules) يجب أن يُخزَّن في قاعدة البيانات، لا أن يُصلَّب في الكود.

**تطبيقات ملموسة:**
- `request_types.form_schema` = JSON يصف حقول النموذج → المسؤول ينشئ نوع طلب جديد من الواجهة بدون deploy.
- `workflows.steps` = صفوف قابلة للترتيب → تغيير مسار الموافقة = تعديل صف.
- `task_statuses` = مخصّصة لكل مشروع (لا `enum` في الكود).
- `leave_types` = صفوف بقواعد (paid, balance_based, allow_negative, max_consecutive) → إضافة نوع جديد = INSERT.
- `work_schedules` + `holidays` = صفوف → لا كود.

**استثناءات مقصودة:** الـ enums الأساسية للنظام (`employee.status`, `request.status`, `attendance.status`) تبقى في الكود لأنها تحكم منطقاً برمجياً.

### 5.2 API-first
**التعريف:** كل ميزة تُعرَض عبر REST endpoint موثّق قبل بناء الواجهة.

**تطبيقات ملموسة:**
- Contract testing على كل endpoint (Pest + OpenAPI schema).
- Postman collection تُولَّد من الكود (`scribe` package أو مماثل).
- كل response يتّبع نفس الشكل: `{ data: ..., meta: ..., errors: ... }`.
- HTTP status codes صارمة: `200/201/204/400/401/403/404/422/429/500`.
- Versioning عبر URL prefix: `/api/v1/...` (نبدأ بـ v1، v2 عند الحاجة).

### 5.3 Modular Monolith على الـ Backend
**التعريف:** تطبيق Laravel واحد، لكنه مقسّم داخلياً إلى **وحدات مستقلة** تحت `app/Modules/`، كل وحدة تحتوي كل ما يخصّها (Controllers, Services, Repositories, Models, Requests, Resources, Events, Listeners, Jobs, Tests).

**السبب:** يعطي فوائد Microservices (استقلال منطقي، اختبار معزول، حدود واضحة) بدون تعقيداتها (deployment، شبكة، consistency).

**قاعدة صارمة:** وحدة `Tasks` لا تستورد من `Projects` مباشرة — تستخدم `ProjectService` عبر contract في `Shared/Contracts`.

### 5.4 Clean Architecture: Controllers → Services → Repositories → Models
**الطبقات وقواعد التدفّق:**
```
┌─────────────────────────────────────────────────────┐
│ Controller (thin)                                    │
│   - يستقبل الطلب، يحقّق FormRequest، يستدعي Service   │
│   - يعيد Resource                                    │
└─────────────────────────────────────────────────────┘
                      ↓
┌─────────────────────────────────────────────────────┐
│ Service (business logic)                             │
│   - يقود التنفيذ (transactions, orchestration)       │
│   - يستدعي Repository                                │
│   - يطلق Events                                      │
└─────────────────────────────────────────────────────┘
                      ↓
┌─────────────────────────────────────────────────────┐
│ Repository (data access)                             │
│   - queries معقّدة، eager loading، pagination         │
│   - يعيد Models أو DTOs                              │
└─────────────────────────────────────────────────────┘
                      ↓
┌─────────────────────────────────────────────────────┐
│ Model (Eloquent)                                     │
│   - علاقات، mutators، scopes                         │
│   - لا business logic                                │
└─────────────────────────────────────────────────────┘
```

**قواعد صارمة:**
- Controller **ممنوع** أن يستدعي Model أو Query Builder مباشرة.
- Model **ممنوع** أن يحتوي منطق تجاري (رسائل، شروط موافقة، خصومات) — Service فقط.
- Repository **ممنوع** أن يعرف بالـ Request أو الـ Response.
- Service **يجوز** أن يستدعي Services أخرى، لكن عبر Constructor Injection.

### 5.5 مبادئ إضافية
- **SOLID + DRY**: مراجعة إلزامية في code review لكل PR.
- **12-Factor**: config عبر env، logs إلى stdout، processes stateless.
- **Immutability حيثما أمكن**: `readonly` DTOs، `insert-only` audit log.
- **Fail-fast**: validation في أعلى نقطة ممكنة، exceptions تحمل معلومات كافية للـ log.
- **Idempotency**: كل POST يقبل `Idempotency-Key` header لتجنّب التكرار (payments، requests submission).

---

## 6. المراحل والتسليمات (Deliverables & Phases)

الخريطة التالية تحوّل مراحل الـ SRS (Phase 1/2/3/4) إلى **milestones قابلة للقياس** مع مدد تقديرية (فريق: 2-3 مطوّرين full-time، مع مراجعة داعمة).

### Phase 1 — MVP (12 أسبوع)
> النطاق التفصيلي في [`03-phase-1-plan.md`](03-phase-1-plan.md). ملخص هنا:

| Milestone | العنوان                                          | المدة  | التسليمات الرئيسية                                                        |
| --------- | ------------------------------------------------ | ------ | ------------------------------------------------------------------------- |
| **M1**    | Monorepo + Docker + Auth scaffold                 | أسبوع 1  | `docker-compose.yml` يشغّل، Login endpoint + Next.js login page             |
| **M2**    | Employees + Org Structure                        | أسبوع 2  | CRUD موظفين + Departments + Teams + Positions + Manager hierarchy         |
| **M3**    | Attendance + Working Hours Engine                | أسبوع 3-4 | QR ديناميكي + Geofence + احتساب late/early/overtime تلقائياً + Holidays  |
| **M4**    | Leaves + Balances                                | أسبوع 5  | Leave Types + Balances سنوية + Entitlement + طلب إجازة يمرّ بـ Workflow    |
| **M5**    | Workflow Engine + Requests                       | أسبوع 6-7 | محرّك عام + 3 request types جاهزة (leave/business_mission/advance)         |
| **M6**    | Simple Tasks + Comments + Files                  | أسبوع 8-9 | Tasks بلا Projects — assignment + comments (@mention) + مرفقات MinIO      |
| **M7**    | Notifications + Employee Dashboard               | أسبوع 10 | مركز إشعارات (in-app + SMS) + لوحة الموظف                                 |
| **M8**    | Admin Dashboard + Reports + Audit + PWA          | أسبوع 11-12 | KPIs + CSV export + ActivityLog + PWA manifest                          |

### Phase 2 — Projects & Scrum (8 أسابيع)
| Milestone | العنوان                          | التسليمات                                                                     |
| --------- | -------------------------------- | ----------------------------------------------------------------------------- |
| **M9**    | Projects + Members               | Projects CRUD + role-based members + project settings                         |
| **M10**   | Sprints + Backlog                | Sprints + product backlog + sprint backlog + drag-drop                        |
| **M11**   | Scrum Ceremonies + Reports       | Sprint planning UI + retrospective + burndown chart + velocity                |
| **M12**   | Task advanced                    | Subtasks + Kanban board + custom statuses per project + drag-drop realtime    |
| **M13**   | WhatsApp + Excel/PDF Exports     | WhatsApp channel + Excel (`maatwebsite/excel`) + PDF (`spatie/browsershot`)   |
| **M14**   | Global Search + Meilisearch      | فهرسة الموظفين/المهام/الطلبات + بحث عام في الرأس                              |

### Phase 3 — AI + Executive (6 أسابيع)
| Milestone | العنوان                     | التسليمات                                                                    |
| --------- | --------------------------- | ---------------------------------------------------------------------------- |
| **M15**   | AI abstraction + Anthropic  | AI service + provider config + token/cost tracking + rate limits            |
| **M16**   | Daily Motivation            | scheduled job كل يوم 8am، رسالة مخصّصة لكل موظف                              |
| **M17**   | Manager Assistant           | ملخّصات الفريق + اقتراحات القرار في dashboard المدير                          |
| **M18**   | Executive Dashboard         | KPIs متعمّقة + مقارنات + الاتجاهات + heatmaps                                |

### Phase 4 — Polish + Scale (4 أسابيع)
| Milestone | العنوان                | التسليمات                                                                    |
| --------- | ---------------------- | ---------------------------------------------------------------------------- |
| **M19**   | Performance & KPIs     | جداول performance_reviews + KPIs محسوبة + تقييمات دورية                      |
| **M20**   | 2FA + SSO (اختياري)     | Time-based OTP + SAML (Okta/Azure AD) للمؤسسات الأكبر                        |
| **M21**   | Multi-Company (SaaS)   | tenant scoping + billing hooks                                               |
| **M22**   | Mobile app (React Native) | نفس الـ API — يبدأ MVP بحضور + مهام + إشعارات فقط                          |

**الإجمالي:** ~30 أسبوع (7.5 أشهر) لكل المراحل. Phase 1 وحده = 3 أشهر، وهو نقطة إطلاق قابلة للحياة.

---

## 7. معايير النجاح لـ Phase 1 (MVP Success Criteria)

مستمدة من SRS بندَي 72 و80. Phase 1 مقبول للـ production عند تحقيق **كل** ما يلي:

### 7.1 الوظيفية (Functional)
- [ ] كل موظف يستطيع تسجيل الدخول بـ `employee_number` + password.
- [ ] Super Admin يستطيع إضافة/تعديل/تعطيل موظف مع الحقول الجديدة كاملة.
- [ ] Departments + Teams + Positions قابلة للإدارة من UI (لا SQL).
- [ ] كل موظف مرتبط بـ Direct Manager (اختياري) و Department + Position.
- [ ] QR ديناميكي يتجدد كل 30 ثانية (قابل للتخصيص).
- [ ] المسح يفشل خارج نصف قطر geofence المسموح، ويسجّل السبب.
- [ ] Working Hours Engine يحسب تلقائياً كل ليلة: total, late, early, overtime, absence.
- [ ] Holidays تُستثنى من الاحتساب.
- [ ] Working Schedules قابلة للتخصيص (أيام عمل مختلفة، ساعات مختلفة).
- [ ] موظف يقدّم طلب إجازة يختار النوع، يرى رصيده الحالي، يرفق مستنداً، يتابع الحالة.
- [ ] الطلب يمرّ عبر Workflow يُنشئه المسؤول من UI (بدون كود).
- [ ] الموافقة تحديث الرصيد + تُشعر مقدّم الطلب SMS + in-app.
- [ ] Notification Center يعرض كل الإشعارات مع read/unread + preferences.
- [ ] Tasks بسيطة: إنشاء + assignment + comments (mentions) + مرفقات.
- [ ] لوحة الموظف تعرض: حضور اليوم، إجازتي، مهامي، طلباتي، إشعاراتي.
- [ ] لوحة الأدمن تعرض KPIs: عدد الموظفين النشطين، حضور اليوم، إجازات مفتوحة، طلبات معلّقة.
- [ ] CSV export للحضور والإجازات والطلبات.
- [ ] ActivityLog يسجّل كل تعديل حسّاس (إضافة موظف، موافقة على طلب، تغيير رصيد إجازة).
- [ ] PWA manifest + service worker + install prompt.

### 7.2 غير الوظيفية (Non-Functional)
- [ ] Response time < 300ms للـ 95th percentile على endpoints القراءة.
- [ ] Login → dashboard TTFB < 1.5s على شبكة 3G.
- [ ] Test coverage ≥ 70% (Pest للـ API + Playwright لأهم 10 flows).
- [ ] Zero secrets في الـ repo — كل شيء في env + Docker secrets.
- [ ] SSL A+ على SSL Labs.
- [ ] Audit log لا يمكن تعديله من التطبيق (INSERT فقط، RLS إن أمكن).
- [ ] Rate limiting: 60 req/min per user على معظم endpoints، 5/min على login.
- [ ] SQL injection / XSS / CSRF — اختبارات آلية تمرّ.
- [ ] Backups تلقائية يومية → مخزن خارجي.
- [ ] Health check endpoint `/api/health` يعيد حالة DB + Redis + MinIO + Reverb.

### 7.3 التشغيلية (Operational)
- [ ] GitHub Actions يبني ويختبر ويدفع الصور عند push إلى `main`.
- [ ] `docker compose up -d` يعمل من صفر على VPS نظيف مع `.env` صحيح.
- [ ] Zero-downtime deploy (rolling restart للـ api container خلف Nginx).
- [ ] `docker compose logs -f api` يُعطي logs واضحة (JSON structured).
- [ ] Runbook مكتوب: كيف نضيف موظف جديد، كيف نعيد نشر، كيف نستعيد نسخة احتياطية.
- [ ] Onboarding docs للموظف الجديد (كيف يستخدم النظام) + للأدمن (كيف يدير).

### 7.4 معيار الإطلاق (Go-live gate)
كل من: 100% من قائمة 7.1 ✅، ≥ 95% من 7.2 ✅، 100% من 7.3 ✅، + **UAT صريح من صاحب المشروع على 5 سيناريوهات كاملة** (تسجيل حضور، طلب إجازة موافقة + رفض، إنشاء مهمّة وتعليق، تصدير تقرير، إضافة موظف جديد + login بواسطته).

---

## 8. الأسئلة المفتوحة والمخاطر

هذه الأمور **لم يُحسم فيها بعد** أو تحمل مخاطر تستحق التنبيه المسبق. الحسم مطلوب قبل نهاية M1.

### 8.1 أسئلة مفتوحة (تحتاج إجابة من صاحب المشروع)

| # | السؤال                                                                                        | لماذا يهم                                                                | افتراضنا الحالي                                                              |
| - | --------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------ | ---------------------------------------------------------------------------- |
| 1 | Multi-company (SaaS) أم Single-company؟                                                       | يؤثر على `companies` scoping وسياسات الأمان                              | Single-company في Phase 1، `company_id` موجود في الجداول للتوسّع              |
| 2 | هل نحتاج تسجيل حضور خارج المكتب (Work From Home)؟                                              | يغيّر منطق geofence — قد نحتاج حالة "معتمد للـ WFH"                       | لا، Phase 1 يفرض geofence دائماً                                             |
| 3 | ما هي الرواتب/HR payroll؟ داخل TAQAT أم خارجه؟                                                 | إن كانت داخله، فيلزم جداول payroll + integration مع البنك                | خارج النطاق نهائياً (SRS لا يذكرها)                                          |
| 4 | ما دورة إعادة تعيين رصيد الإجازات؟ (سنوي بتاريخ التعيين، سنوي 1 يناير، أم قابل للتخصيص؟)      | يحدّد جدول `leave_balances` وcron الترحيل                                | 1 يناير سنوياً، مع تخصيص لاحق في Phase 2                                    |
| 5 | هل يمكن الموظف تعديل ملفه الشخصي (phone, avatar) أم أدمن فقط؟                                 | يحدّد endpoints + permissions                                             | نعم للـ phone + avatar فقط، الباقي أدمن                                     |
| 6 | ما لغة الواجهة الأمامية الأساسية؟ عربي فقط أم ثنائي؟                                           | يؤثر على i18n setup — Next.js `next-intl`                                | عربي RTL أساسي + i18n جاهز لإضافة English في Phase 2                       |
| 7 | ما سياسة كلمات المرور (طول، تعقيد، انتهاء)؟                                                     | يحدّد validation rules + password rotation policy                        | 8+ أحرف، حرف كبير + رقم + رمز، بلا انتهاء إلزامي                          |
| 8 | هل يوجد Active Directory / SSO موجود يجب دمجه؟                                                | يؤثر على Auth flow                                                       | لا، محلي فقط في Phase 1، SAML في Phase 4                                    |
| 9 | ما مدة الاحتفاظ بسجل النشاط (Audit log)؟                                                       | يؤثر على تخزين + archival job                                            | 2 سنة online + archival إلى MinIO بعدها                                    |
| 10 | ميزانية Anthropic AI الشهرية المتوقّعة؟                                                        | يحدّد rate limits + الاختيار بين Sonnet/Haiku                            | $50/شهر افتراضياً، Haiku للـ daily motivation، Sonnet للـ manager assistant |

### 8.2 المخاطر التقنية

| # | المخاطر                                                                              | الأثر إن حدث                                     | التخفيف                                                                                        |
| - | ------------------------------------------------------------------------------------- | ------------------------------------------------ | ---------------------------------------------------------------------------------------------- |
| 1 | **VPS واحد = نقطة فشل واحدة**                                                          | انقطاع كامل                                       | Docker snapshots يومية → BackBlaze B2 + runbook استعادة سريعة + مراقبة uptime                    |
| 2 | **MinIO داخل نفس الـ VPS يتشارك الـ disk**                                            | تلف قرص = فقدان ملفات + DB معاً                   | RAID أو volume منفصل + backups منتظمة إلى تخزين خارجي                                          |
| 3 | **Reverb container قد يتوقف** بدون مراقبة                                             | لا real-time (fallback إلى polling)               | health check + `restart: unless-stopped` + تنبيه Slack عند فشل health                            |
| 4 | **AI costs قد تنفجر** لو أُسيء استخدام                                                | فواتير غير متوقّعة                                | حدود صارمة per-user + شهرية، circuit breaker يوقف الاستدعاءات عند تجاوز 80% من الحدّ           |
| 5 | **QR الديناميكي يعتمد على وقت الخادم/العميل** — انزلاق ساعة = رفض مسح صحيح            | فوضى في الحضور                                   | NTP على VPS + قبول ±60 ثانية في التحقّق + رسالة خطأ واضحة                                       |
| 6 | **Migration data من v1 قد يكشف بيانات فاسدة** (nulls، phone formats غير موحّدة)      | فشل seeder، بلبلة                                | seeder يعالج مع dry-run report قبل التنفيذ، وقائمة exceptions للمعالجة اليدوية                |
| 7 | **Next.js SSR + Sanctum httpOnly cookies** — CSRF + CORS setup معقّد                  | ثغرات أمنية أو UX سيّئة                          | Sanctum SPA cookie mode + `SANCTUM_STATEFUL_DOMAINS` صحيح + Playwright test للـ auth flow      |
| 8 | **Modular Monolith قد يتحوّل إلى ball-of-mud** لو لم يُحترم فصل الوحدات               | صيانة صعبة بعد سنة                                | ESLint-like rule عبر `deptrac` (PHP) يمنع imports عبر الوحدات إلا من `Shared`                  |
| 9 | **Docker on Windows dev machine** قد يبطئ الـ file-watching                          | تجربة تطوير سيّئة                                | استخدام WSL2 + Docker Desktop على Linux mount + توثيق الإعداد في README                        |
| 10 | **RBAC معقّد** — 5 أدوار × ~40 جداول × عمليات متعدّدة = matrix كبيرة                 | صلاحيات مفقودة أو زائدة                          | Policy per Model + integration tests تفحص كل combination role×action لكل model حسّاس         |

### 8.3 مخاطر تنظيمية (Non-technical)

| # | المخاطر                                                             | التخفيف                                                                   |
| - | ------------------------------------------------------------------- | ------------------------------------------------------------------------- |
| 1 | تدريب الموظفين — أول أسبوع فوضى                                     | تسجيل فيديوهات قصيرة (2 دقيقة) لكل عملية + دليل PDF                       |
| 2 | مقاومة التغيير من الأدمن الحالي المعتاد على v1                       | UAT معه شخصياً في M8 قبل الإطلاق                                          |
| 3 | نطاق يزحف (scope creep) — SRS ضخم                                    | تجميد النطاق في نهاية M1، أي إضافة → Phase 2                              |
| 4 | جدول 12 أسبوع مضغوط لفريق 2-3                                       | تسليم M1-M4 مبكراً لاختبار تدفّق النشر ثم اتخاذ قرار حجم الفريق          |

---

## 9. المراجع

- **SRS الرسمي** — قسم 72 (نطاق Phase 1)، قسم 80 (معايير النجاح)، قسم 82 (مخطط البيانات).
- **v1 codebase snapshot** — commit `e64ad05` (queue → sync).
- الوثائق المرافقة في نفس المجلد.

## 10. تاريخ التعديلات
| الإصدار | التاريخ    | المؤلف            | التغيير             |
| ------- | ---------- | ----------------- | ------------------- |
| 1.0     | 2026-09-07 | Development Team  | الإصدار الأول من v2 planning package |
