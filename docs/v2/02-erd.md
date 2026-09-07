# TAQAT v2 — Entity Relationship Diagram

> **الوثيقة رقم 3 من حزمة تخطيط v2**
> النطاق: كل جداول قاعدة البيانات مع الأعمدة والقيود والعلاقات والفهارس وقواعد العمل الجوهرية.
> الوثائق المرافقة: [`00-overview.md`](00-overview.md) · [`01-architecture.md`](01-architecture.md) · [`03-phase-1-plan.md`](03-phase-1-plan.md)

---

## 0. Legend

- **PK** = Primary Key. الافتراضي: `id BIGINT UNSIGNED AUTO_INCREMENT`.
- **FK** = Foreign Key. الافتراضي: `ON DELETE RESTRICT ON UPDATE CASCADE` إلا حيث يُذكر عكسه.
- **UQ** = Unique constraint.
- **IDX** = Index.
- **SD** = Soft Delete (`deleted_at TIMESTAMP NULL`).
- **T** = timestamps (`created_at`, `updated_at`).
- **CH** = CHECK constraint.

**Convention:**
- الجداول بصيغة الجمع snake_case (`employees`, `leave_requests`).
- FKs بصيغة `<table_singular>_id`.
- Enums عبر `ENUM` في MySQL، لكن يُفضَّل جدول lookup إذا كانت القيم قابلة للتغيّر.

---

## 1. ERD Summary (فهرس الجداول)

**الإجمالي: 44 جدولاً** (منها Spatie + Laravel defaults).

### 1.1 Auth & Users (9)
| الجدول                    | الغرض                                                     |
| ------------------------- | --------------------------------------------------------- |
| `users`                   | حساب دخول مرتبط بموظف (1:1)                                 |
| `password_reset_tokens`   | توكن استرجاع كلمة المرور                                  |
| `personal_access_tokens`  | Sanctum tokens (API keys للأجهزة، mobile app لاحقاً)         |
| `sessions`                | جلسات Sanctum SPA في Redis (schema فقط للـ fallback)       |
| `roles`                   | Spatie — أدوار النظام                                     |
| `permissions`             | Spatie — صلاحيات دقيقة                                    |
| `model_has_roles`         | Spatie pivot                                              |
| `model_has_permissions`   | Spatie pivot                                              |
| `role_has_permissions`    | Spatie pivot                                              |

### 1.2 Organization (4)
| `companies`   | جذر الهيكل (يبقى صف واحد في Phase 1)                       |
| `departments` | أقسام شجرية (parent_id)                                    |
| `teams`       | فرق تنتمي إلى قسم                                           |
| `positions`   | مسميات وظيفية                                              |

### 1.3 Employees (1)
| `employees`   | ملف الموظف الكامل (مرتبط بـ user 1:1 اختياري)              |

### 1.4 Attendance (4)
| `work_schedules`      | جداول العمل (أيام + ساعات + قواعد الإجهاد)                  |
| `holidays`            | أيام العطل الرسمية والشركة                                 |
| `attendances`         | سجل الحضور اليومي                                          |
| `attendance_devices`  | أجهزة QR (تولّد tokens متجدّدة)                              |

### 1.5 Leaves (3)
| `leave_types`     | أنواع الإجازات + قواعدها                                    |
| `leave_balances`  | رصيد كل موظف لكل نوع لكل سنة                                 |
| `leave_requests`  | طلبات الإجازة (تمرّ عبر workflow instance)                    |

### 1.6 Workflow & Requests (5)
| `workflows`           | مسارات العمل المعادة الاستخدام                                |
| `workflow_steps`      | خطوات كل مسار                                              |
| `request_types`       | تعريفات أنواع الطلبات + form_schema JSON                    |
| `requests`            | نماذج الطلبات المقدّمة (Purchase, Advance, …)               |
| `request_approvals`   | سجل الموافقات/الرفض/الإرجاع لكل طلب                          |

### 1.7 Tasks & Projects (10)
| `projects`            | المشاريع (Phase 2)                                          |
| `project_members`     | أعضاء المشروع بأدوارهم                                      |
| `sprints`             | Sprints (Phase 2)                                          |
| `task_statuses`       | حالات المهام (قابلة للتخصيص لكل مشروع)                       |
| `task_priorities`     | أولويات المهام (seeded)                                     |
| `tasks`               | المهام                                                     |
| `task_comments`       | التعليقات مع mentions                                       |
| `task_history`        | سجل تغييرات المهمة (insert-only)                             |
| `task_tags`           | وسوم                                                       |
| `taggables`           | pivot للوسوم                                               |

### 1.8 Notifications (2)
| `notifications`               | إشعارات Laravel القياسية (in-app)                             |
| `notification_preferences`    | تفضيلات القناة لكل موظف                                     |

### 1.9 AI (1)
| `ai_messages`   | سجل استدعاءات AI مع التوكنز والتكلفة                          |

### 1.10 Audit & Support (3)
| `activity_log`   | Spatie ActivityLog                                            |
| `settings`       | مفتاح/قيمة عامة (يستمرّ من v1)                                 |
| `sms_logs`       | سجل رسائل SMS (يستمرّ من v1)                                   |

### 1.11 Storage (1)
| `media`   | Spatie MediaLibrary — كل المرفقات polymorphic                    |

### 1.12 Queues / System (Laravel defaults)
| `jobs`, `job_batches`, `failed_jobs`, `cache`, `cache_locks` — بلا تعديل من skeleton |

---

## 2. Auth & Users

### 2.1 `users`
**الغرض:** حساب دخول مرتبط بموظف (1:1). كل موظف قابل للدخول له صف هنا.

| عمود                | نوع                       | قيود                                              | ملاحظة                                              |
| ------------------- | ------------------------- | ------------------------------------------------- | --------------------------------------------------- |
| `id`                | BIGINT UNSIGNED            | PK, AUTO_INCREMENT                                |                                                     |
| `employee_id`       | BIGINT UNSIGNED            | UQ, FK → `employees.id` ON DELETE CASCADE          | 1:1 مع الموظف                                        |
| `email`             | VARCHAR(255)               | UQ, NULL                                          | اختياري (بعض الموظفين بلا email)                    |
| `email_verified_at` | TIMESTAMP                  | NULL                                              |                                                     |
| `password`          | VARCHAR(255)                | NOT NULL                                          | Argon2id                                            |
| `remember_token`    | VARCHAR(100)                | NULL                                              |                                                     |
| `two_factor_secret` | TEXT                        | NULL                                              | مشفّر (Cast)                                         |
| `two_factor_confirmed_at` | TIMESTAMP              | NULL                                              |                                                     |
| `last_login_at`     | TIMESTAMP                  | NULL                                              |                                                     |
| `last_login_ip`     | VARCHAR(45)                 | NULL                                              | IPv6-friendly                                       |
| `created_at`, `updated_at` | T                    |                                                    |                                                     |

**IDX:** `idx_users_email` on `email`.
**Relationships:**
- `belongsTo Employee`
- `morphMany Notifications`
- `morphMany ActivityLog (causer)`
- `hasMany PersonalAccessToken` (Sanctum)

**Business Rules:**
- إنشاء user يستوجب `employee.status='active'`.
- عند حذف employee (soft) → user يُعطَّل (لا يُحذف).

### 2.2 `password_reset_tokens`
**Laravel default:** `email VARCHAR(255) PRIMARY KEY, token VARCHAR(255), created_at TIMESTAMP`.

### 2.3 `personal_access_tokens` (Sanctum)
**Standard Sanctum table.** يُستخدم في Phase 4 لتطبيق الموبايل.

### 2.4 `sessions`
**Schema-only** — الجلسات الفعلية في Redis. يبقى الجدول للـ fallback إن أُطفئ Redis مؤقتاً.

### 2.5 Spatie Permission Tables

**`roles`:**
| id | name (VARCHAR 125) | guard_name | timestamps |

**Seeded roles (Phase 1):**
- `super_admin` — الإدارة العليا للنظام (تقنية)
- `management` — الإدارة العليا (تجارية) — تصل لكل شيء عرضاً
- `manager` — مدير قسم
- `team_leader` — قائد فريق
- `employee` — موظف عادي

**`permissions`:**
| id | name (VARCHAR 125) | guard_name | timestamps |

**نمط التسمية:** `<module>.<action>` — أمثلة:
- `employees.view.any`, `employees.view.own`, `employees.create`, `employees.update`, `employees.delete`
- `attendance.view.any`, `attendance.view.team`, `attendance.view.own`
- `leaves.approve`, `leaves.reject`, `requests.approve`
- `workflows.manage`, `request_types.manage`
- `reports.export`
- `settings.manage`, `audit.view`

**`model_has_roles`, `model_has_permissions`, `role_has_permissions`:**
جداول pivot معيارية من Spatie — `model_type + model_id + role_id/permission_id`.

**IDX:** `idx_role_model` on `(model_type, model_id)`.

---

## 3. Organization

### 3.1 `companies`
**الغرض:** جذر الهيكل. صف واحد في Phase 1 (نضع `company_id` في الجداول للسماح بـ multi-tenancy مستقبلاً بدون migrations كبيرة).

| عمود           | نوع            | قيود           | ملاحظة                                     |
| -------------- | -------------- | -------------- | ------------------------------------------- |
| `id`           | BIGINT UNSIGNED | PK             |                                             |
| `name`         | VARCHAR(255)    | NOT NULL       |                                             |
| `code`         | VARCHAR(50)     | UQ             |                                             |
| `logo_path`    | VARCHAR(500)    | NULL           | مفتاح في bucket avatars                    |
| `timezone`     | VARCHAR(64)     | NOT NULL, DEFAULT 'Asia/Amman' |                                    |
| `default_locale` | CHAR(5)      | NOT NULL, DEFAULT 'ar'   |                                          |
| `settings`     | JSON            | NULL           | إعدادات على مستوى الشركة (working week, etc.) |
| `created_at`, `updated_at` | T   |                |                                             |

**IDX:** `idx_companies_code` on `code`.

### 3.2 `departments`
**الغرض:** أقسام هرمية داخل شركة.

| عمود           | نوع            | قيود                                                        | ملاحظة                                   |
| -------------- | -------------- | ----------------------------------------------------------- | ----------------------------------------- |
| `id`           | BIGINT UNSIGNED | PK                                                          |                                           |
| `company_id`   | BIGINT UNSIGNED | FK → companies.id, NOT NULL                                 |                                           |
| `parent_id`    | BIGINT UNSIGNED | FK → departments.id ON DELETE SET NULL, NULL                | تسلسل هرمي                                |
| `name`         | VARCHAR(255)    | NOT NULL                                                    |                                           |
| `code`         | VARCHAR(50)     | NOT NULL                                                    | UQ per company                            |
| `manager_id`   | BIGINT UNSIGNED | FK → employees.id ON DELETE SET NULL, NULL                  | مدير القسم                                 |
| `description`  | TEXT            | NULL                                                        |                                           |
| `is_active`    | BOOLEAN         | NOT NULL, DEFAULT TRUE                                       |                                           |
| `created_at`, `updated_at` | T   |                                                              |                                           |

**IDX:** UQ `(company_id, code)`, IDX `parent_id`, IDX `manager_id`.
**Business Rules:**
- FK `manager_id` **يُنشأ في migration منفصلة** بعد جدول `employees` (كسر دائرة FK).
- منع الحلقات في `parent_id` (يُتحقّق في Service قبل الحفظ).

### 3.3 `teams`
| عمود           | نوع            | قيود                                                        |
| -------------- | -------------- | ----------------------------------------------------------- |
| `id`           | BIGINT UNSIGNED | PK                                                          |
| `department_id`| BIGINT UNSIGNED | FK → departments.id, NOT NULL                               |
| `name`         | VARCHAR(255)    | NOT NULL                                                    |
| `code`         | VARCHAR(50)     | NOT NULL, UQ per department                                 |
| `leader_id`    | BIGINT UNSIGNED | FK → employees.id ON DELETE SET NULL, NULL                  |
| `is_active`    | BOOLEAN         | NOT NULL, DEFAULT TRUE                                       |
| T              |                 |                                                              |

**IDX:** UQ `(department_id, code)`, IDX `leader_id`.

### 3.4 `positions`
| عمود           | نوع            | قيود                                                        |
| -------------- | -------------- | ----------------------------------------------------------- |
| `id`           | BIGINT UNSIGNED | PK                                                          |
| `department_id`| BIGINT UNSIGNED | FK → departments.id ON DELETE SET NULL, NULL                |
| `title`        | VARCHAR(255)    | NOT NULL                                                    |
| `code`         | VARCHAR(50)     | UQ per company                                              |
| `description`  | TEXT            | NULL                                                        |
| `is_active`    | BOOLEAN         | NOT NULL, DEFAULT TRUE                                       |
| T              |                 |                                                              |

**IDX:** IDX `department_id`.

---

## 4. Employees

### 4.1 `employees`
**الغرض:** الملف الكامل للموظف — المرجع الأول لكل ما يخصّه.

| عمود                     | نوع                                       | قيود                                                              | ملاحظة                                              |
| ------------------------ | ----------------------------------------- | ----------------------------------------------------------------- | --------------------------------------------------- |
| `id`                     | BIGINT UNSIGNED                            | PK                                                                |                                                     |
| `user_id`                | BIGINT UNSIGNED                            | UQ, NULL, FK → users.id ON DELETE SET NULL                        | 1:1 مع user (nullable — قد لا يدخل النظام)          |
| `company_id`             | BIGINT UNSIGNED                            | FK → companies.id, NOT NULL                                       |                                                     |
| `employee_number`        | VARCHAR(50)                                | UQ NOT NULL                                                       | مفتاح تسجيل الدخول                                  |
| `first_name`             | VARCHAR(100)                               | NOT NULL                                                          |                                                     |
| `last_name`              | VARCHAR(100)                               | NOT NULL                                                          |                                                     |
| `full_name`              | VARCHAR(255)                               | GENERATED VIRTUAL AS `CONCAT(first_name,' ',last_name)`           | للبحث السريع + الفهرسة                              |
| `email`                  | VARCHAR(255)                               | UQ NULL                                                           | بريد العمل                                          |
| `personal_email`         | VARCHAR(255)                               | NULL                                                              | خاص                                                 |
| `phone`                  | VARCHAR(20)                                | NOT NULL                                                          | E.164                                               |
| `secondary_phone`        | VARCHAR(20)                                | NULL                                                              |                                                     |
| `department_id`          | BIGINT UNSIGNED                            | FK → departments.id ON DELETE SET NULL, NULL                       |                                                     |
| `team_id`                | BIGINT UNSIGNED                            | FK → teams.id ON DELETE SET NULL, NULL                             |                                                     |
| `position_id`            | BIGINT UNSIGNED                            | FK → positions.id ON DELETE SET NULL, NULL                         |                                                     |
| `direct_manager_id`      | BIGINT UNSIGNED                            | FK → employees.id ON DELETE SET NULL, NULL                         | الرئيس المباشر                                        |
| `work_schedule_id`       | BIGINT UNSIGNED                            | FK → work_schedules.id ON DELETE RESTRICT, NOT NULL                | كل موظف مرتبط بجدول عمل                              |
| `employment_type`        | ENUM('full_time','part_time','contract','intern','consultant') | NOT NULL, DEFAULT 'full_time' |                                          |
| `status`                 | ENUM('active','inactive','on_leave','terminated','probation') | NOT NULL, DEFAULT 'active' |                                          |
| `joining_date`           | DATE                                       | NOT NULL                                                          |                                                     |
| `probation_ends_at`      | DATE                                       | NULL                                                              |                                                     |
| `termination_date`       | DATE                                       | NULL                                                              |                                                     |
| `birth_date`             | DATE                                       | NULL                                                              |                                                     |
| `gender`                 | ENUM('male','female','other','prefer_not_to_say') | NOT NULL, DEFAULT 'prefer_not_to_say'                        |                                                     |
| `national_id`            | VARCHAR(50)                                | NULL, UQ per company                                              | مشفّر عبر Cast                                       |
| `nationality`            | VARCHAR(100)                               | NULL                                                              |                                                     |
| `address`                | TEXT                                       | NULL                                                              |                                                     |
| `emergency_contact`      | JSON                                       | NULL                                                              | `{name, phone, relationship}`                       |
| `avatar_path`            | VARCHAR(500)                               | NULL                                                              | key في bucket avatars                              |
| `bio`                    | TEXT                                       | NULL                                                              |                                                     |
| `notes`                  | TEXT                                       | NULL                                                              | ملاحظات إدارية داخلية                              |
| `metadata`               | JSON                                       | NULL                                                              | حقول قابلة للتوسّع بدون migration                    |
| `created_at`, `updated_at`, `deleted_at` | T + SD                     |                                                                    |                                                     |

**IDX:**
- UQ `employee_number`
- UQ `(company_id, email)`
- UQ `(company_id, national_id)` where NOT NULL
- IDX `department_id`, `team_id`, `direct_manager_id`, `status`, `work_schedule_id`

**Relationships:**
- `hasOne User`
- `belongsTo Company, Department, Team, Position, WorkSchedule`
- `belongsTo directManager (Employee)`
- `hasMany subordinates (Employee where direct_manager_id = this.id)`
- `hasMany Attendances, LeaveRequests, Requests, Tasks (assignedTo), TaskComments (asUser)`
- `belongsToMany Projects` via `project_members`
- `hasMany LeaveBalances`
- `morphMany Media` via Spatie (for avatar collection)

**Business Rules:**
- `direct_manager_id` **لا** يمكن أن يكون نفس `id` (CHECK constraint في MySQL 8).
- تغيير `status` إلى `terminated` → user يُعطَّل تلقائياً + كل الجلسات النشطة تُبطَل.
- `full_name` مولّد ديناميكياً — لا يُكتب مباشرة.

---

## 5. Attendance

### 5.1 `work_schedules`
**الغرض:** تعريف جدول عمل (أيام + ساعات + إجهاد).

| عمود                       | نوع                              | قيود                                          | ملاحظة                                       |
| -------------------------- | -------------------------------- | --------------------------------------------- | -------------------------------------------- |
| `id`                       | BIGINT UNSIGNED                   | PK                                            |                                              |
| `company_id`               | BIGINT UNSIGNED                   | FK → companies.id, NOT NULL                    |                                              |
| `name`                     | VARCHAR(255)                      | NOT NULL                                      | e.g. "Standard 8-4", "Ramadan Hours"          |
| `timezone`                 | VARCHAR(64)                       | NOT NULL, DEFAULT 'Asia/Amman'                 |                                              |
| `check_in_time`            | TIME                              | NOT NULL                                      | e.g. 08:00                                    |
| `check_out_time`           | TIME                              | NOT NULL                                      | e.g. 16:00                                    |
| `min_hours_per_day`        | DECIMAL(4,2)                      | NOT NULL, DEFAULT 8.00                         |                                              |
| `grace_late_minutes`       | SMALLINT UNSIGNED                 | NOT NULL, DEFAULT 15                           | لا يُحتسب تأخّراً                            |
| `grace_early_leave_minutes`| SMALLINT UNSIGNED                 | NOT NULL, DEFAULT 15                           |                                              |
| `workdays`                 | JSON                              | NOT NULL                                      | `[0,1,2,3,4]` = Sun..Thu                     |
| `is_flexible`              | BOOLEAN                           | NOT NULL, DEFAULT FALSE                        | لو TRUE، تُهمَل check_in_time (يُطلب فقط min_hours) |
| `overtime_starts_after_min`| SMALLINT UNSIGNED                 | NULL                                          | 30 دقيقة بعد نهاية اليوم مثلاً                |
| `is_default`               | BOOLEAN                           | NOT NULL, DEFAULT FALSE                        | يُخصَّص للموظفين الجدد                       |
| `is_active`                | BOOLEAN                           | NOT NULL, DEFAULT TRUE                         |                                              |
| T                          |                                  |                                                |                                              |

**IDX:** IDX `company_id`.

**Business Rules:**
- يمنع تعطيل جدول مرتبط بموظفين نشطين — يجب نقلهم أولاً.
- `workdays[]` يستخدم Sun=0 حتى Sat=6 (تقليد Laravel).

### 5.2 `holidays`
| عمود           | نوع            | قيود                                          | ملاحظة                                    |
| -------------- | -------------- | --------------------------------------------- | ------------------------------------------ |
| `id`           | BIGINT UNSIGNED | PK                                            |                                            |
| `company_id`   | BIGINT UNSIGNED | FK → companies.id, NOT NULL                    |                                            |
| `date`         | DATE            | NOT NULL                                      |                                            |
| `name`         | VARCHAR(255)    | NOT NULL                                      |                                            |
| `type`         | ENUM('official','company','special','religious') | NOT NULL, DEFAULT 'official'          |                                            |
| `is_recurring` | BOOLEAN         | NOT NULL, DEFAULT FALSE                        | e.g. 1 يناير                                |
| `description`  | TEXT            | NULL                                          |                                            |
| T              |                 |                                                |                                            |

**IDX:** UQ `(company_id, date)` where `is_recurring=FALSE`, IDX `date`.

### 5.3 `attendances`
**الغرض:** سجل حضور يوم واحد لموظف واحد.

| عمود                       | نوع                                    | قيود                                                       | ملاحظة                                             |
| -------------------------- | -------------------------------------- | ---------------------------------------------------------- | -------------------------------------------------- |
| `id`                       | BIGINT UNSIGNED                         | PK                                                         |                                                    |
| `employee_id`              | BIGINT UNSIGNED                         | FK → employees.id ON DELETE CASCADE, NOT NULL              |                                                    |
| `date`                     | DATE                                    | NOT NULL                                                   |                                                    |
| `work_schedule_snapshot`   | JSON                                    | NOT NULL                                                   | نسخة من جدول العمل وقت التسجيل (احترام تاريخي)    |
| `check_in_at`              | TIMESTAMP                                | NULL                                                       |                                                    |
| `check_out_at`             | TIMESTAMP                                | NULL                                                       |                                                    |
| `check_in_ip`              | VARCHAR(45)                              | NULL                                                       |                                                    |
| `check_out_ip`             | VARCHAR(45)                              | NULL                                                       |                                                    |
| `check_in_location`        | POINT SRID 4326                          | NULL                                                       | نقطة جغرافية                                        |
| `check_out_location`       | POINT SRID 4326                          | NULL                                                       |                                                    |
| `check_in_device_id`       | BIGINT UNSIGNED                          | FK → attendance_devices.id ON DELETE SET NULL, NULL         |                                                    |
| `check_in_user_agent`      | VARCHAR(500)                             | NULL                                                       |                                                    |
| `total_minutes`            | INT UNSIGNED                             | NULL                                                       | يُحسب ليلاً                                          |
| `late_minutes`             | SMALLINT UNSIGNED                        | NOT NULL, DEFAULT 0                                        |                                                    |
| `early_leave_minutes`      | SMALLINT UNSIGNED                        | NOT NULL, DEFAULT 0                                        |                                                    |
| `overtime_minutes`         | SMALLINT UNSIGNED                        | NOT NULL, DEFAULT 0                                        |                                                    |
| `status`                   | ENUM('pending','present','late','absent','early_leave','on_leave','holiday','weekend') | NOT NULL, DEFAULT 'pending' |                                          |
| `is_manually_adjusted`     | BOOLEAN                                  | NOT NULL, DEFAULT FALSE                                    | معدّل يدوياً بواسطة أدمن                            |
| `adjusted_by`              | BIGINT UNSIGNED                          | FK → users.id ON DELETE SET NULL, NULL                     |                                                    |
| `notes`                    | TEXT                                     | NULL                                                       |                                                    |
| T                          |                                          |                                                             |                                                    |

**IDX:**
- UQ `(employee_id, date)`
- IDX `date`
- IDX `status`
- SPATIAL IDX `check_in_location` (لاستعلامات القرب لاحقاً)

**Business Rules:**
- **صف واحد فقط لكل موظف لكل يوم.** إعادة تسجيل الدخول قبل التسجيل الأول = يُحدّث نفس الصف.
- **race condition:** لا محاولتان لتحديث نفس الصف في نفس اللحظة — استخدام `SELECT ... FOR UPDATE` داخل transaction (نمط v1 commit `4c95469`).
- `work_schedule_snapshot` يُلتقط عند check_in الأول، لا يتغيّر بعدها.
- `Working Hours Engine` (Job ليلي) يحسب: `total_minutes = check_out - check_in - unpaid_breaks`، ثم `late/early/overtime`، ثم يُثبّت `status`.
- إذا `check_in` بلا `check_out` مع تجاوز 24 ساعة → `status = 'absent'` مع تنبيه للأدمن.

### 5.4 `attendance_devices`
**الغرض:** أجهزة عرض QR (شاشة/تابلت في المكتب).

| عمود                          | نوع                                    | قيود                                                       | ملاحظة                                             |
| ----------------------------- | -------------------------------------- | ---------------------------------------------------------- | -------------------------------------------------- |
| `id`                          | BIGINT UNSIGNED                         | PK                                                         |                                                    |
| `company_id`                  | BIGINT UNSIGNED                         | FK → companies.id, NOT NULL                                 |                                                    |
| `department_id`               | BIGINT UNSIGNED                         | FK → departments.id ON DELETE SET NULL, NULL               | لو محصور بقسم                                        |
| `code`                        | VARCHAR(50)                             | UQ NOT NULL                                                | slug للـ URL: `/scan/{code}`                        |
| `name`                        | VARCHAR(255)                            | NOT NULL                                                   | e.g. "المدخل الرئيسي"                              |
| `current_token`               | VARCHAR(255)                            | NULL                                                       | آخر token صادر                                     |
| `token_rotates_every_seconds` | SMALLINT UNSIGNED                       | NOT NULL, DEFAULT 30                                        |                                                    |
| `last_token_rotated_at`       | TIMESTAMP                               | NULL                                                       |                                                    |
| `allowed_location`            | POINT SRID 4326                         | NULL                                                       | نقطة مركزية                                         |
| `allowed_radius_meters`       | SMALLINT UNSIGNED                       | NULL, DEFAULT 100                                          |                                                    |
| `ip_whitelist`                | JSON                                    | NULL                                                       | `["192.168.1.0/24","10.0.0.5"]`                    |
| `is_active`                   | BOOLEAN                                 | NOT NULL, DEFAULT TRUE                                     |                                                    |
| T                             |                                         |                                                             |                                                    |

**IDX:** UQ `code`, IDX `company_id`, SPATIAL IDX `allowed_location`.

**Business Rules:**
- Token يُخزَّن في Redis مع TTL = `token_rotates_every_seconds`. الحقل هنا للـ audit + fallback.
- عند مسح موظف token فترة السماح: الـ Service يفكّ tokenSignature (HMAC-SHA256 بمفتاح الجهاز) → يتحقّق من time drift ≤ 60s.

---

## 6. Leaves

### 6.1 `leave_types`
**الغرض:** أنواع الإجازات المتاحة (Configure Don't Code).

| عمود                        | نوع                                    | قيود                              | ملاحظة                                                            |
| --------------------------- | -------------------------------------- | --------------------------------- | ----------------------------------------------------------------- |
| `id`                        | BIGINT UNSIGNED                         | PK                                |                                                                   |
| `company_id`                | BIGINT UNSIGNED                         | FK → companies.id, NOT NULL       |                                                                   |
| `name`                      | VARCHAR(255)                            | NOT NULL                          | "إجازة سنوية"                                                     |
| `code`                      | VARCHAR(50)                             | UQ per company                     | `annual`, `sick`, `unpaid`, `emergency`, `maternity`               |
| `is_paid`                   | BOOLEAN                                 | NOT NULL, DEFAULT TRUE            |                                                                   |
| `is_balance_based`          | BOOLEAN                                 | NOT NULL, DEFAULT TRUE            | لو FALSE → لا رصيد (unpaid indefinite)                             |
| `default_annual_entitlement`| DECIMAL(6,2)                            | NULL                              | مثال 21.00 يوم                                                     |
| `allow_negative_balance`    | BOOLEAN                                 | NOT NULL, DEFAULT FALSE           |                                                                   |
| `requires_attachment`       | BOOLEAN                                 | NOT NULL, DEFAULT FALSE           | إجازة مرضية = TRUE                                                 |
| `max_consecutive_days`      | SMALLINT UNSIGNED                       | NULL                              | حدّ أقصى للمتتالية                                                 |
| `min_notice_days`           | SMALLINT UNSIGNED                       | NULL                              | كم يوم قبل التاريخ يجب أن يُقدَّم                                 |
| `carry_over_max_days`       | DECIMAL(6,2)                            | NULL                              | كم يمكن ترحيله للسنة التالية                                       |
| `applies_to_employment_types`| JSON                                   | NULL                              | `["full_time","part_time"]`                                        |
| `workflow_id`               | BIGINT UNSIGNED                         | FK → workflows.id, NULL           | مسار الموافقة (يُحدّد لاحقاً)                                       |
| `color`                     | CHAR(7)                                 | NOT NULL, DEFAULT '#2678C4'       | HEX                                                               |
| `icon`                      | VARCHAR(50)                             | NULL                              | اسم أيقونة lucide                                                  |
| `sort_order`                | SMALLINT UNSIGNED                       | NOT NULL, DEFAULT 0                |                                                                   |
| `is_active`                 | BOOLEAN                                 | NOT NULL, DEFAULT TRUE            |                                                                   |
| T                           |                                         |                                    |                                                                   |

**IDX:** UQ `(company_id, code)`.

### 6.2 `leave_balances`
**الغرض:** رصيد كل موظف لكل نوع لكل سنة.

| عمود              | نوع                              | قيود                                                          | ملاحظة                                          |
| ----------------- | -------------------------------- | ------------------------------------------------------------- | ------------------------------------------------ |
| `id`              | BIGINT UNSIGNED                   | PK                                                            |                                                  |
| `employee_id`     | BIGINT UNSIGNED                   | FK → employees.id ON DELETE CASCADE, NOT NULL                 |                                                  |
| `leave_type_id`   | BIGINT UNSIGNED                   | FK → leave_types.id ON DELETE CASCADE, NOT NULL                |                                                  |
| `year`            | SMALLINT UNSIGNED                 | NOT NULL                                                      |                                                  |
| `entitlement`     | DECIMAL(6,2)                      | NOT NULL, DEFAULT 0                                            | كم يستحق                                          |
| `used`            | DECIMAL(6,2)                      | NOT NULL, DEFAULT 0                                            | كم استُهلك                                         |
| `pending`         | DECIMAL(6,2)                      | NOT NULL, DEFAULT 0                                            | كم قيد الموافقة                                    |
| `carried_over`    | DECIMAL(6,2)                      | NOT NULL, DEFAULT 0                                            | من السنة السابقة                                    |
| `remaining`       | DECIMAL(7,2)                      | GENERATED VIRTUAL AS `(entitlement + carried_over - used - pending)` |                                              |
| T                 |                                  |                                                                |                                                  |

**IDX:** UQ `(employee_id, leave_type_id, year)`.

**Business Rules:**
- كل تحديث لـ `used/pending` يجب أن يكون داخل transaction مع `SELECT FOR UPDATE` على الصف.
- Cron يومي يعيد احتساب `remaining` (لأنه virtual، يحدث تلقائياً في SELECT).
- 1 يناير Job (`AnnualBalanceRollover`) ينشئ صف السنة الجديدة + `carried_over = min(remaining_last_year, carry_over_max_days)`.

### 6.3 `leave_requests`
**الغرض:** طلب إجازة يمرّ عبر workflow.

| عمود                    | نوع                                      | قيود                                                          | ملاحظة                                                |
| ----------------------- | ---------------------------------------- | ------------------------------------------------------------- | ------------------------------------------------------ |
| `id`                    | BIGINT UNSIGNED                           | PK                                                            |                                                        |
| `employee_id`           | BIGINT UNSIGNED                           | FK → employees.id ON DELETE CASCADE, NOT NULL                 |                                                        |
| `leave_type_id`         | BIGINT UNSIGNED                           | FK → leave_types.id, NOT NULL                                  |                                                        |
| `start_date`            | DATE                                      | NOT NULL                                                      |                                                        |
| `end_date`              | DATE                                      | NOT NULL                                                      |                                                        |
| `is_half_day`           | BOOLEAN                                   | NOT NULL, DEFAULT FALSE                                        |                                                        |
| `half_day_period`       | ENUM('morning','afternoon')                | NULL                                                          |                                                        |
| `days`                  | DECIMAL(5,2)                              | NOT NULL                                                      | محسوب بعد استبعاد weekends/holidays                    |
| `reason`                | TEXT                                      | NOT NULL                                                      |                                                        |
| `handover_notes`        | TEXT                                      | NULL                                                          | من سيغطّي عمله                                          |
| `substitute_employee_id`| BIGINT UNSIGNED                           | FK → employees.id ON DELETE SET NULL, NULL                     |                                                        |
| `status`                | ENUM('draft','submitted','pending','approved','rejected','cancelled','returned') | NOT NULL, DEFAULT 'draft' |                                                       |
| `submitted_at`          | TIMESTAMP                                 | NULL                                                          |                                                        |
| `decided_at`            | TIMESTAMP                                 | NULL                                                          |                                                        |
| `workflow_id`           | BIGINT UNSIGNED                           | FK → workflows.id, NULL                                        | snapshot من leave_type.workflow_id                     |
| `current_step_id`       | BIGINT UNSIGNED                           | FK → workflow_steps.id ON DELETE SET NULL, NULL                | الخطوة الحالية                                          |
| `cancellation_reason`   | TEXT                                      | NULL                                                          |                                                        |
| T                       |                                          |                                                                |                                                        |

**IDX:** IDX `employee_id`, IDX `status`, IDX `(start_date, end_date)`, IDX `current_step_id`.

**Relationships:**
- `belongsTo Employee, LeaveType, Workflow, currentStep (WorkflowStep)`
- `hasMany RequestApprovals` — لكن نموذجياً يمكن مشاركة `request_approvals` أو إنشاء `leave_approvals` منفصل. **قرار:** نعيد استخدام `request_approvals` مع polymorphic `approvable_type/id`. (يُنقّح لاحقاً — راجع Open Question في الأسفل.)
- `morphMany Media` (attachment)

**Business Rules:**
- عند submit: `days` تُحسب في Service (تستبعد workdays=false و holidays)، `pending` في leave_balance يُزاد.
- عند approve: `pending` يُنقص، `used` يُزاد، `status = approved`، `employees.status = 'on_leave'` لو الآن ضمن المدى.
- عند reject/cancel قبل approve: `pending` يُنقص.
- **min_notice_days** يُتحقّق في FormRequest.

---

## 7. Workflow & Requests

### 7.1 `workflows`
| عمود           | نوع              | قيود                              | ملاحظة                                     |
| -------------- | ---------------- | --------------------------------- | ------------------------------------------- |
| `id`           | BIGINT UNSIGNED   | PK                                |                                             |
| `company_id`   | BIGINT UNSIGNED   | FK → companies.id, NOT NULL       |                                             |
| `name`         | VARCHAR(255)      | NOT NULL                          | "Standard Leave Approval"                    |
| `code`         | VARCHAR(50)       | UQ per company                    |                                             |
| `description`  | TEXT              | NULL                              |                                             |
| `is_active`    | BOOLEAN           | NOT NULL, DEFAULT TRUE            |                                             |
| T              |                  |                                    |                                             |

**IDX:** UQ `(company_id, code)`.

### 7.2 `workflow_steps`
| عمود                   | نوع                                                                                       | قيود                                       | ملاحظة                                          |
| ---------------------- | ----------------------------------------------------------------------------------------- | ------------------------------------------ | ------------------------------------------------ |
| `id`                   | BIGINT UNSIGNED                                                                            | PK                                         |                                                  |
| `workflow_id`          | BIGINT UNSIGNED                                                                            | FK → workflows.id ON DELETE CASCADE, NOT NULL |                                                  |
| `step_order`           | SMALLINT UNSIGNED                                                                          | NOT NULL                                   | 1,2,3…                                            |
| `name`                 | VARCHAR(255)                                                                               | NOT NULL                                   | "Direct Manager", "HR Review"                     |
| `approver_type`        | ENUM('direct_manager','department_manager','specific_employee','role','user_field_reference') | NOT NULL                                  |                                                  |
| `approver_id`          | BIGINT UNSIGNED                                                                            | NULL                                       | لو type=specific_employee → employees.id         |
| `approver_role`        | VARCHAR(125)                                                                               | NULL                                       | لو type=role → اسم role                          |
| `approver_field_ref`   | VARCHAR(100)                                                                               | NULL                                       | e.g. `form_data.hr_representative_id`             |
| `can_reject`           | BOOLEAN                                                                                    | NOT NULL, DEFAULT TRUE                     |                                                  |
| `can_return`           | BOOLEAN                                                                                    | NOT NULL, DEFAULT TRUE                     | إرجاع للطالب لتعديل                              |
| `can_forward`          | BOOLEAN                                                                                    | NOT NULL, DEFAULT FALSE                    | تحويل إلى شخص آخر                                 |
| `sla_hours`            | SMALLINT UNSIGNED                                                                          | NULL                                       | إن تُوجّهت هذه الخطوة أكثر من X ساعة → escalation |
| `escalate_to_id`       | BIGINT UNSIGNED                                                                            | FK → employees.id ON DELETE SET NULL, NULL |                                                  |
| `is_parallel_with_previous` | BOOLEAN                                                                              | NOT NULL, DEFAULT FALSE                    | تنفيذ متزامن مع الخطوة السابقة                    |
| T                      |                                                                                            |                                             |                                                  |

**IDX:** UQ `(workflow_id, step_order)`, IDX `approver_id`.

**Business Rules:**
- WorkflowEngine يستخدم `ApproverResolver` لتحويل `approver_type + config` إلى `employee_id` فعلي وقت التقييم.
- `sla_hours` يُراقَب في `SlaMonitor` كل ساعة.

### 7.3 `request_types`
**الغرض:** تعريف نوع طلب مع نموذجه الديناميكي.

| عمود           | نوع              | قيود                              | ملاحظة                                                       |
| -------------- | ---------------- | --------------------------------- | ------------------------------------------------------------- |
| `id`           | BIGINT UNSIGNED   | PK                                |                                                               |
| `company_id`   | BIGINT UNSIGNED   | FK → companies.id, NOT NULL       |                                                               |
| `name`         | VARCHAR(255)      | NOT NULL                          | "Purchase Request"                                             |
| `code`         | VARCHAR(50)       | UQ per company                    | `purchase`, `advance`, `business_mission`, `complaint`         |
| `description`  | TEXT              | NULL                              |                                                               |
| `form_schema`  | JSON              | NOT NULL                          | مصفوفة تعريفات الحقول (details below)                          |
| `workflow_id`  | BIGINT UNSIGNED   | FK → workflows.id, NOT NULL       | مسار الموافقة                                                  |
| `icon`         | VARCHAR(50)       | NULL                              |                                                               |
| `color`        | CHAR(7)           | NOT NULL, DEFAULT '#2678C4'       |                                                               |
| `is_active`    | BOOLEAN           | NOT NULL, DEFAULT TRUE            |                                                               |
| `sort_order`   | SMALLINT UNSIGNED | NOT NULL, DEFAULT 0               |                                                               |
| T              |                  |                                    |                                                               |

**IDX:** UQ `(company_id, code)`.

**form_schema shape (JSON):**
```json
[
  {
    "key": "amount",
    "label": "المبلغ",
    "type": "number",
    "required": true,
    "min": 1,
    "max": 10000,
    "validation": ["numeric","gt:0"]
  },
  {
    "key": "purpose",
    "label": "السبب",
    "type": "textarea",
    "required": true,
    "max_length": 1000
  },
  {
    "key": "attachment",
    "label": "مرفق",
    "type": "file",
    "required": false,
    "accept": [".pdf",".jpg",".png"],
    "max_size_mb": 5
  }
]
```

**Supported field types:** `text, textarea, number, date, datetime, select, multiselect, radio, checkbox, file, employee_picker, department_picker`.

### 7.4 `requests`
**الغرض:** نموذج طلب مقدّم يمرّ عبر workflow.

| عمود                | نوع                                                                       | قيود                                                        | ملاحظة                                            |
| ------------------- | ------------------------------------------------------------------------- | ----------------------------------------------------------- | -------------------------------------------------- |
| `id`                | BIGINT UNSIGNED                                                            | PK                                                          |                                                    |
| `request_number`    | VARCHAR(30)                                                                | UQ NOT NULL                                                 | `REQ-2026-00042` (auto)                            |
| `employee_id`       | BIGINT UNSIGNED                                                            | FK → employees.id ON DELETE RESTRICT, NOT NULL              |                                                    |
| `request_type_id`   | BIGINT UNSIGNED                                                            | FK → request_types.id, NOT NULL                             |                                                    |
| `form_data`         | JSON                                                                       | NOT NULL                                                    | القيم المطابقة لـ form_schema                       |
| `status`            | ENUM('draft','submitted','pending','approved','rejected','returned','cancelled','completed') | NOT NULL, DEFAULT 'draft' |                                                    |
| `workflow_id`       | BIGINT UNSIGNED                                                            | FK → workflows.id, NULL                                     | snapshot                                            |
| `current_step_id`   | BIGINT UNSIGNED                                                            | FK → workflow_steps.id ON DELETE SET NULL, NULL              |                                                    |
| `current_approver_id`| BIGINT UNSIGNED                                                           | FK → employees.id ON DELETE SET NULL, NULL                   | يُحسب من ApproverResolver                          |
| `submitted_at`      | TIMESTAMP                                                                  | NULL                                                        |                                                    |
| `completed_at`      | TIMESTAMP                                                                  | NULL                                                        |                                                    |
| `sla_breach_at`     | TIMESTAMP                                                                  | NULL                                                        | متى تجاوز SLA                                       |
| T                   |                                                                            |                                                              |                                                    |

**IDX:** UQ `request_number`, IDX `employee_id`, IDX `status`, IDX `current_approver_id`, IDX `request_type_id`.

**Relationships:**
- `belongsTo Employee, RequestType, Workflow, currentStep, currentApprover`
- `hasMany RequestApprovals`
- `morphMany Media` (attachments)

### 7.5 `request_approvals`
**الغرض:** سجل قرارات الموافقة (insert-only).

| عمود                | نوع                                                     | قيود                                                        |
| ------------------- | ------------------------------------------------------- | ----------------------------------------------------------- |
| `id`                | BIGINT UNSIGNED                                          | PK                                                          |
| `approvable_type`   | VARCHAR(255)                                             | NOT NULL                                                    |
| `approvable_id`     | BIGINT UNSIGNED                                          | NOT NULL                                                    |
| `workflow_step_id`  | BIGINT UNSIGNED                                          | FK → workflow_steps.id, NOT NULL                             |
| `approver_id`       | BIGINT UNSIGNED                                          | FK → employees.id, NOT NULL                                 |
| `action`            | ENUM('approved','rejected','returned','forwarded','delegated') | NOT NULL                                              |
| `forwarded_to_id`   | BIGINT UNSIGNED                                          | FK → employees.id, NULL                                     |
| `comment`           | TEXT                                                     | NULL                                                        |
| `decided_at`        | TIMESTAMP                                                | NOT NULL                                                    |
| `created_at`        | TIMESTAMP                                                | NOT NULL DEFAULT CURRENT_TIMESTAMP                          |

**IDX:** IDX `(approvable_type, approvable_id)`, IDX `approver_id`, IDX `workflow_step_id`.

**Business Rules:**
- **Insert-only** — trigger أو Policy تمنع UPDATE/DELETE.
- polymorphic → يخدم `requests` و`leave_requests` (ربما لاحقاً `expense_reports`).

---

## 8. Tasks & Projects

### 8.1 `projects` (Phase 2 لكن الجدول موجود من Phase 1 لتفادي migrations متأخّرة)
| عمود           | نوع                                                          | قيود                                       | ملاحظة                                    |
| -------------- | ------------------------------------------------------------ | ------------------------------------------ | ------------------------------------------ |
| `id`           | BIGINT UNSIGNED                                               | PK                                         |                                            |
| `company_id`   | BIGINT UNSIGNED                                               | FK → companies.id, NOT NULL                 |                                            |
| `code`         | VARCHAR(30)                                                   | UQ per company                             | prefix للـ task_number                      |
| `name`         | VARCHAR(255)                                                  | NOT NULL                                   |                                            |
| `description`  | TEXT                                                          | NULL                                       |                                            |
| `client_name`  | VARCHAR(255)                                                  | NULL                                       |                                            |
| `manager_id`   | BIGINT UNSIGNED                                               | FK → employees.id ON DELETE RESTRICT, NOT NULL |                                        |
| `status`       | ENUM('planning','active','on_hold','completed','cancelled') | NOT NULL, DEFAULT 'planning'                |                                            |
| `start_date`   | DATE                                                          | NULL                                       |                                            |
| `end_date`     | DATE                                                          | NULL                                       |                                            |
| `budget`       | DECIMAL(15,2)                                                 | NULL                                       |                                            |
| `color`        | CHAR(7)                                                       | NOT NULL, DEFAULT '#2678C4'                 |                                            |
| `settings`     | JSON                                                          | NULL                                       | مثل default_story_points_scale             |
| T + SD         |                                                              |                                             |                                            |

**IDX:** UQ `(company_id, code)`, IDX `manager_id`, IDX `status`.

### 8.2 `project_members`
| عمود           | نوع                                     | قيود                                            |
| -------------- | ---------------------------------------- | ----------------------------------------------- |
| `id`           | BIGINT UNSIGNED                           | PK                                              |
| `project_id`   | BIGINT UNSIGNED                           | FK → projects.id ON DELETE CASCADE, NOT NULL     |
| `employee_id`  | BIGINT UNSIGNED                           | FK → employees.id ON DELETE CASCADE, NOT NULL    |
| `role`         | ENUM('manager','lead','member','viewer')  | NOT NULL, DEFAULT 'member'                       |
| `joined_at`    | DATE                                       | NOT NULL DEFAULT (CURDATE())                    |
| `left_at`      | DATE                                       | NULL                                            |
| T              |                                           |                                                  |

**IDX:** UQ `(project_id, employee_id)` where `left_at IS NULL`.

### 8.3 `sprints` (Phase 2)
| عمود           | نوع                                                     | قيود                                     |
| -------------- | ------------------------------------------------------- | ---------------------------------------- |
| `id`           | BIGINT UNSIGNED                                          | PK                                       |
| `project_id`   | BIGINT UNSIGNED                                          | FK → projects.id ON DELETE CASCADE, NOT NULL |
| `sprint_number`| SMALLINT UNSIGNED                                        | NOT NULL                                 |
| `name`         | VARCHAR(255)                                             | NOT NULL                                 |
| `goal`         | TEXT                                                     | NULL                                     |
| `start_date`   | DATE                                                     | NOT NULL                                 |
| `end_date`     | DATE                                                     | NOT NULL                                 |
| `status`       | ENUM('planned','active','completed','cancelled')          | NOT NULL, DEFAULT 'planned'               |
| `capacity_points` | SMALLINT UNSIGNED                                     | NULL                                     |
| `retrospective_notes` | TEXT                                              | NULL                                     |
| `created_by`   | BIGINT UNSIGNED                                          | FK → employees.id, NOT NULL              |
| T              |                                                          |                                          |

**IDX:** UQ `(project_id, sprint_number)`, IDX `status`.

### 8.4 `task_statuses`
| عمود                    | نوع                              | قيود                                             |
| ----------------------- | -------------------------------- | ------------------------------------------------ |
| `id`                    | BIGINT UNSIGNED                   | PK                                               |
| `project_id`            | BIGINT UNSIGNED                   | FK → projects.id ON DELETE CASCADE, NULL          |
| `name`                  | VARCHAR(100)                      | NOT NULL                                         |
| `code`                  | VARCHAR(50)                       | NOT NULL                                         |
| `color`                 | CHAR(7)                           | NOT NULL, DEFAULT '#94a3b8'                       |
| `sort_order`            | SMALLINT UNSIGNED                 | NOT NULL, DEFAULT 0                              |
| `is_done_state`         | BOOLEAN                           | NOT NULL, DEFAULT FALSE                           |
| `is_cancelled_state`    | BOOLEAN                           | NOT NULL, DEFAULT FALSE                           |
| T                       |                                  |                                                   |

**IDX:** UQ `(project_id, code)`. لو `project_id IS NULL` → status عام.
**Seeded global statuses (Phase 1):** `todo, in_progress, review, done, cancelled`.

### 8.5 `task_priorities`
| id | name | code (UQ) | color | sort_order | icon | T |
**Seeded:** `low(gray), medium(blue), high(orange), urgent(red)`.

### 8.6 `tasks`
| عمود                    | نوع                                    | قيود                                                      | ملاحظة                                              |
| ----------------------- | -------------------------------------- | --------------------------------------------------------- | --------------------------------------------------- |
| `id`                    | BIGINT UNSIGNED                         | PK                                                        |                                                     |
| `company_id`            | BIGINT UNSIGNED                         | FK → companies.id, NOT NULL                                |                                                     |
| `project_id`            | BIGINT UNSIGNED                         | FK → projects.id ON DELETE CASCADE, NULL                   | NULL في Phase 1 (لا project)                        |
| `sprint_id`             | BIGINT UNSIGNED                         | FK → sprints.id ON DELETE SET NULL, NULL                   | Phase 2                                              |
| `parent_task_id`        | BIGINT UNSIGNED                         | FK → tasks.id ON DELETE CASCADE, NULL                       | subtask                                              |
| `task_number`           | VARCHAR(50)                             | UQ NOT NULL                                                | e.g. `TSK-42` أو `PRJ-42` بحسب مشروع                |
| `title`                 | VARCHAR(500)                            | NOT NULL                                                   |                                                     |
| `description`           | LONGTEXT                                 | NULL                                                       | يدعم markdown                                        |
| `status_id`             | BIGINT UNSIGNED                          | FK → task_statuses.id, NOT NULL                             |                                                     |
| `priority_id`           | BIGINT UNSIGNED                          | FK → task_priorities.id, NOT NULL                           |                                                     |
| `created_by`            | BIGINT UNSIGNED                          | FK → employees.id, NOT NULL                                 |                                                     |
| `assigned_to`           | BIGINT UNSIGNED                          | FK → employees.id ON DELETE SET NULL, NULL                   |                                                     |
| `reporter_id`           | BIGINT UNSIGNED                          | FK → employees.id ON DELETE SET NULL, NULL                   | من يتلقّى updates                                    |
| `story_points`          | SMALLINT UNSIGNED                        | NULL                                                       |                                                     |
| `estimated_hours`       | DECIMAL(6,2)                             | NULL                                                       |                                                     |
| `actual_hours`          | DECIMAL(6,2)                             | NULL                                                       |                                                     |
| `progress_percent`      | TINYINT UNSIGNED                         | NOT NULL, DEFAULT 0, CHECK (progress_percent BETWEEN 0 AND 100) |                                             |
| `start_date`            | DATE                                     | NULL                                                       |                                                     |
| `due_date`              | DATE                                     | NULL                                                       |                                                     |
| `completed_at`          | TIMESTAMP                                | NULL                                                       |                                                     |
| `sort_order`            | INT                                      | NOT NULL, DEFAULT 0                                        | ترتيب في القائمة                                     |
| T + SD                  |                                          |                                                             |                                                     |

**IDX:**
- UQ `task_number`
- IDX `project_id`, `sprint_id`, `parent_task_id`
- IDX `status_id`, `priority_id`
- IDX `assigned_to`, `created_by`
- IDX `due_date`

**Business Rules:**
- `task_number` مولّد: `<project.code | 'TSK'>-<sequence>` عبر service.
- تغيير `status_id` إلى `is_done_state=true` → `completed_at = now()`.
- Observer يسجّل كل تغيير في `task_history`.

### 8.7 `task_comments`
| عمود                    | نوع                                | قيود                                                       |
| ----------------------- | ---------------------------------- | ---------------------------------------------------------- |
| `id`                    | BIGINT UNSIGNED                     | PK                                                         |
| `task_id`               | BIGINT UNSIGNED                     | FK → tasks.id ON DELETE CASCADE, NOT NULL                   |
| `user_id`               | BIGINT UNSIGNED                     | FK → users.id ON DELETE CASCADE, NOT NULL                   |
| `parent_id`             | BIGINT UNSIGNED                     | FK → task_comments.id ON DELETE CASCADE, NULL               |
| `body`                  | TEXT                                | NOT NULL                                                   |
| `mentions`              | JSON                                | NULL                                                       |
| `edited_at`             | TIMESTAMP                           | NULL                                                       |
| T + SD                  |                                     |                                                             |

**IDX:** IDX `task_id`, IDX `user_id`, IDX `parent_id`.

**Business Rules:**
- `mentions` = `[user_id, ...]` — يُستخرَج من `body` (parse `@employee_number`) في Service.
- كل ذكر يُنشئ Notification.

### 8.8 `task_history`
**الغرض:** سجل تغييرات — insert-only.

| عمود           | نوع                                                                       | قيود                                       |
| -------------- | ------------------------------------------------------------------------- | ------------------------------------------ |
| `id`           | BIGINT UNSIGNED                                                            | PK                                         |
| `task_id`      | BIGINT UNSIGNED                                                            | FK → tasks.id ON DELETE CASCADE, NOT NULL   |
| `user_id`      | BIGINT UNSIGNED                                                            | FK → users.id ON DELETE SET NULL, NULL      |
| `action`       | ENUM('created','assigned','unassigned','status_changed','priority_changed','commented','attached_file','deleted','updated','moved_to_sprint','estimate_changed') | NOT NULL |
| `field`        | VARCHAR(100)                                                               | NULL                                       |
| `old_value`    | JSON                                                                       | NULL                                       |
| `new_value`    | JSON                                                                       | NULL                                       |
| `created_at`   | TIMESTAMP                                                                  | NOT NULL DEFAULT CURRENT_TIMESTAMP         |

**IDX:** IDX `(task_id, created_at)`, IDX `user_id`, IDX `action`.

**Business Rules:** insert-only (Policy + trigger).

### 8.9 `task_tags`
| id | company_id (FK) | name (VARCHAR 100) | code (UQ per company) | color (CHAR 7) | T |

### 8.10 `taggables` (polymorphic pivot)
| id | tag_id (FK) | taggable_type | taggable_id | created_at |
**UQ:** `(tag_id, taggable_type, taggable_id)`.

---

## 9. Notifications

### 9.1 `notifications` (Laravel default)
| id (UUID CHAR 36) | type | notifiable_type | notifiable_id | data (JSON) | read_at | created_at | updated_at |
**IDX:** IDX `(notifiable_type, notifiable_id)`, IDX `read_at`.

### 9.2 `notification_preferences`
| عمود           | نوع                                    | قيود                                       |
| -------------- | -------------------------------------- | ------------------------------------------ |
| `id`           | BIGINT UNSIGNED                         | PK                                         |
| `user_id`      | BIGINT UNSIGNED                         | FK → users.id ON DELETE CASCADE, NOT NULL   |
| `event`        | VARCHAR(100)                            | NOT NULL                                   |
| `in_app`       | BOOLEAN                                 | NOT NULL, DEFAULT TRUE                     |
| `email`        | BOOLEAN                                 | NOT NULL, DEFAULT TRUE                     |
| `sms`          | BOOLEAN                                 | NOT NULL, DEFAULT FALSE                    |
| `whatsapp`     | BOOLEAN                                 | NOT NULL, DEFAULT FALSE                    |
| T              |                                        |                                             |

**IDX:** UQ `(user_id, event)`.
**Events:** `leave_approved`, `leave_rejected`, `request_pending_your_approval`, `task_assigned`, `mentioned_in_comment`, `attendance_missed`, ...

---

## 10. AI

### 10.1 `ai_messages`
| عمود                | نوع                                                                | قيود                                              |
| ------------------- | ------------------------------------------------------------------ | ------------------------------------------------- |
| `id`                | BIGINT UNSIGNED                                                     | PK                                                |
| `employee_id`       | BIGINT UNSIGNED                                                     | FK → employees.id ON DELETE CASCADE, NULL          |
| `purpose`           | ENUM('daily_motivation','manager_assistant','task_helper','summary') | NOT NULL                                       |
| `provider`          | VARCHAR(50)                                                         | NOT NULL, DEFAULT 'anthropic'                     |
| `model`             | VARCHAR(100)                                                        | NOT NULL                                          |
| `input_context`     | JSON                                                                | NOT NULL                                          |
| `output`            | TEXT                                                                | NOT NULL                                          |
| `tokens_input`      | INT UNSIGNED                                                        | NOT NULL, DEFAULT 0                                |
| `tokens_output`     | INT UNSIGNED                                                        | NOT NULL, DEFAULT 0                                |
| `cost_cents`        | INT UNSIGNED                                                        | NULL                                              |
| `latency_ms`        | INT UNSIGNED                                                        | NULL                                              |
| `error`             | TEXT                                                                | NULL                                              |
| `generated_at`      | TIMESTAMP                                                           | NOT NULL DEFAULT CURRENT_TIMESTAMP                 |
| T                   |                                                                    |                                                    |

**IDX:** IDX `employee_id`, IDX `purpose`, IDX `generated_at`.

**Business Rules:**
- كل استدعاء يُسجَّل حتى الفاشل.
- `cost_cents` يُحسب من tokens × price per token model-dependent.
- View: `ai_monthly_costs` تجميع per month per model للـ Dashboard.

---

## 11. Audit & Support

### 11.1 `activity_log` (Spatie)
**Standard Spatie schema:**
| id | log_name | description | subject_type | subject_id | causer_type | causer_id | properties (JSON) | batch_uuid | event | created_at | updated_at |

**IDX:** IDX `(subject_type, subject_id)`, IDX `(causer_type, causer_id)`, IDX `log_name`.

**Configuration:**
- Log على كل Model حسّاس عبر `LogsActivity` trait.
- Retention 2 سنة online، بعدها archival إلى MinIO كملف JSONL شهري.

### 11.2 `settings` (يستمرّ من v1)
| id | key (UQ) | value (TEXT) | type (VARCHAR: 'string','int','bool','json','encrypted') | T |
**نمط الاستخدام:** `Setting::get('mtc_sms_credentials')` — encrypted values تُفكّ تلقائياً في Cast.

### 11.3 `sms_logs` (يستمرّ من v1)
| id | to_number | message | status | provider_response | sent_at | user_id (FK NULL) | employee_id (FK NULL) | T |
**IDX:** IDX `to_number`, IDX `status`, IDX `sent_at`.

---

## 12. Storage

### 12.1 `media` (Spatie MediaLibrary)
**Standard Spatie schema — brief:**
| id | model_type | model_id | uuid | collection_name | name | file_name | mime_type | disk | conversions_disk | size (BIGINT) | manipulations (JSON) | custom_properties (JSON) | generated_conversions (JSON) | responsive_images (JSON) | order_column | T |

**Configuration:**
- Disk default = `attachments` (MinIO private).
- Collections per Model:
  - Employee → `avatar` (max 1)
  - Task → `attachments` (multiple)
  - LeaveRequest → `attachments` (max 5)
  - Request → `attachments` (max 10)
- Access via signed URLs فقط (لا public URLs للـ private disks).

---

## 13. Queue / System (Laravel defaults)

| الجدول         | الغرض                                          |
| -------------- | ---------------------------------------------- |
| `jobs`         | Redis fallback، schema فقط                      |
| `job_batches`  | لتنفيذ groups (Reports batch)                   |
| `failed_jobs`  | Jobs فاشلة بعد كل retries                       |
| `cache`        | schema fallback فقط                             |
| `cache_locks`  | schema fallback فقط                             |

---

## 14. High-Level Relationships Diagram

```
companies (1) ─┬─< departments (1) ─┬─< teams (1) ──< employees
               │                     │
               ├─< positions ────────┘
               ├─< work_schedules ──< employees
               ├─< holidays
               ├─< leave_types
               ├─< workflows ─────< workflow_steps
               ├─< request_types ─┬─(workflow_id)
               ├─< projects ──┬───< project_members ──> employees
               │              └───< sprints ──< tasks
               └─< task_tags

employees (1) ─┬─(user_id 1:1)─ users ─┬─< notifications (morphs)
               │                        └─< personal_access_tokens
               │
               ├─< attendances ─(check_in_device_id)─> attendance_devices
               ├─< leave_balances ─(leave_type_id)─> leave_types
               ├─< leave_requests ─(workflow_id, current_step_id)
               ├─< requests ─(request_type_id, workflow_id, current_step_id)
               ├─< tasks ─(assigned_to, created_by)
               ├─< task_comments ─(via user_id)
               └─(direct_manager_id self-ref)

Polymorphic:
  request_approvals.approvable_type → { requests, leave_requests }
  taggables → tasks (initially), then projects/employees etc.
  media → { employees, tasks, requests, leave_requests }
  activity_log → any subject
  notifications → users
```

---

## 15. Migration Order

الترتيب المُوصى به لإنشاء الجداول (يحترم FKs، مع بعض `ALTER TABLE ... ADD FOREIGN KEY` للحلقات).

**Wave 1 — Core**
1. `companies`
2. `positions` (بلا FK لـ departments بعد)
3. `departments` (بلا manager_id بعد)
4. `teams` (بلا leader_id بعد)
5. `work_schedules`
6. `holidays`

**Wave 2 — Employees & Users**
7. `employees` (بلا direct_manager_id بعد)
8. `users` (FK لـ employees)
9. `password_reset_tokens`
10. `personal_access_tokens`
11. `sessions`
12. `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` (Spatie)

**Wave 3 — Circular FKs (via ALTER)**
13. `ALTER employees ADD direct_manager_id FK employees.id`
14. `ALTER departments ADD manager_id FK employees.id`
15. `ALTER teams ADD leader_id FK employees.id`

**Wave 4 — Attendance**
16. `attendance_devices`
17. `attendances`

**Wave 5 — Workflow**
18. `workflows`
19. `workflow_steps`

**Wave 6 — Requests & Leaves**
20. `leave_types` (FK لـ workflows)
21. `leave_balances`
22. `leave_requests` (FK لـ workflows, workflow_steps)
23. `request_types` (FK لـ workflows)
24. `requests` (FK لـ workflows, workflow_steps)
25. `request_approvals` (polymorphic)

**Wave 7 — Tasks**
26. `projects`
27. `project_members`
28. `sprints`
29. `task_priorities`
30. `task_statuses`
31. `tasks`
32. `task_comments`
33. `task_history`
34. `task_tags`
35. `taggables`

**Wave 8 — Notifications & AI**
36. `notifications`
37. `notification_preferences`
38. `ai_messages`

**Wave 9 — Audit & Support**
39. `activity_log` (Spatie)
40. `settings`
41. `sms_logs`

**Wave 10 — Storage**
42. `media` (Spatie)

**Wave 11 — Laravel system (تُنشأ مع skeleton)**
43. `jobs`, `job_batches`, `failed_jobs`, `cache`, `cache_locks`

---

## 16. Critical Business Rules Summary

### 16.1 Consistency
1. **Employee status cascade:** تغيير `status='terminated'` → إبطال user sessions، إلغاء pending tasks assignments، إبطال pending leave/requests.
2. **Direct manager cannot be self:** enforced by CHECK.
3. **Leave balance atomicity:** كل update داخل transaction مع `FOR UPDATE`.
4. **Attendance uniqueness:** صف واحد per (employee, date) — enforced by UQ.
5. **Task numbering:** sequence per project (أو global لو project_id NULL).
6. **Workflow immutability during flow:** بمجرد submit، لا يمكن تعديل workflow_id للطلب — يبقى snapshot.

### 16.2 Security
1. **request_approvals + task_history + activity_log = insert-only** (Policy + trigger).
2. **National ID + 2FA secret** = مشفّرة عبر Cast (`EncryptedString`).
3. **Signed URLs** للـ private media، expiry ≤ 15 دقيقة.
4. **RBAC checks** في كل Controller عبر Policies.

### 16.3 Data Integrity
1. **ON DELETE strategy:**
   - CASCADE: attendances, leave_balances, task_comments, task_history, project_members (البيانات التابعة للأب).
   - SET NULL: manager references، assigned_to (نحتفظ بالسجل).
   - RESTRICT: request approver history، لا تُحذف employee لديها approved leaves.
2. **Soft delete:** employees, tasks, projects, task_comments — إعادة للحياة ممكنة.
3. **Timezone:** كل TIMESTAMP بـ UTC في DB، تحويل في Service حسب `employee.work_schedule.timezone`.

---

## 17. Open Questions on Data Model

1. **`request_approvals` polymorphic vs. separate table per subject?** — القرار الحالي polymorphic لتقليل التكرار؛ لكن queries معقّدة (JOIN مع morph) قد تكون أبطأ. إعادة تقييم بعد Phase 1.
2. **Multi-currency للـ budget/advance requests؟** — الافتراض حالياً currency واحدة على مستوى الشركة. إضافة `currency` عمود في request إذا لزم.
3. **Time entries على المهام (`task_time_entries`)؟** — SRS لا يذكرها صراحة. مؤجَّل لـ Phase 3 لو طُلب.
4. **Recurring tasks / recurring requests؟** — غير مذكور. مؤجَّل.

---

## 18. تاريخ التعديلات
| الإصدار | التاريخ    | التغيير              |
| ------- | ---------- | -------------------- |
| 1.0     | 2026-09-07 | الإصدار الأول        |
