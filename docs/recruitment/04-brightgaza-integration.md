# Recruitment Module — BrightGaza Integration

> **الوثيقة رقم 5**
> النطاق: ربط مسار التوظيف في TAQAT بمنصة BrightGaza (taqatgaza.com): ما يوفّره الـ API الحالي، الإضافات المطلوبة من فريق BrightGaza، وخطة التنفيذ على جانب TAQAT.
> السابق: [`03-phase-1-plan.md`](03-phase-1-plan.md) · المرجع: OpenAPI 3.0 «Bright Gaza API Documentation v1.0.0» (النسخة المستلمة في 2026-09-14)

---

## 1. الهدف والنطاق

BrightGaza هي علامة التوظيف لدى TAQAT، لكنها تقنياً **سوق عمل حر (Freelance Marketplace)** يطوّره ويشغّله فريق آخر. القراران الثابتان:

1. فريق BrightGaza يضيف endpoints عند الطلب، وTAQAT تبني جانبها فقط.
2. **التوظيف كاملاً يجري داخل BrightGaza**: إعلان الوظيفة، العروض (proposals)، عروض التوظيف (hire offers)، العقود، والدفع. TAQAT تعكس كل خطوة على مراحل الـ Pipeline، وتسمح لموظفيها بالتنفيذ من داخل TAQAT حيث يسمح الـ API.

**مصدر الحقيقة:**

| البيانات | المصدر | دور TAQAT |
|---------|--------|-----------|
| حالة الإعلان، الـ proposals، عروض التوظيف، العقود، المدفوعات | BrightGaza | نسخة مرآة + أوامر عبر الـ API |
| المرحلة الحالية، المالك، المهام، الـ SLA، ملاحظات الفرز، قرار العميل | TAQAT | لا يُرسل إلى BrightGaza إلا `shortlisted` / `rejected` |

**داخل النطاق:** نشر الوظيفة وتعديلها وإغلاقها، استيراد المتقدمين كـ Candidates، دعوة مستقلين، إنشاء عرض التوظيف ومتابعته، متابعة حالة العقد، الانتقالات التلقائية بين المراحل.

**خارج النطاق:** المحافظ والـ escrow، الـ milestones، ساعات العمل الأسبوعية، النزاعات، المحادثات (نفتح الغرفة فقط)، وأي تكامل مع «Auth - Taqat SSO» (مزوّد هوية منفصل وليس هذا المستودع). لا تغيير على قرار عدم الـ tenancy.

---

## 2. مطابقة المراحل

المسارات القصيرة (`/jobs/...`، `/proposals/...`) هي المطلوبة في §4 تحت `/integrations/v1`؛ المسارات الكاملة موجودة اليوم (§3).

| مرحلة TAQAT | الكائن / الإجراء في BrightGaza | من يتصرّف | كيف تعلم TAQAT (webhook · poll بديل) | الانتقال في TAQAT |
|------------|-------------------------------|-----------|--------------------------------------|-------------------|
| `new` | لا شيء بعد | موظف TAQAT (مالك الوظيفة) | — | يدوي |
| `publish` | `POST /jobs` ← `pending_review` ثم `approved` (الـ spec يكشف إشرافاً: `status.name = "Approved"` مع `reject_details`) | موظف بصلاحية `publish-jobs` يضغط «نشر على BrightGaza» | `job.published` / `job.rejected` · `GET /jobs/{id}` | تلقائي عند `job.published`: `publication_url` + `published_at` ثم `receiving_apps` |
| `receiving_apps` | المستقلون يقدّمون proposals (الكائن الداخلي `JobApply`)؛ دعوات يدوية حتى 5 | المستقل؛ موظف TAQAT للدعوات | `proposal.submitted` / `proposal.updated` / `proposal.withdrawn` · `GET /jobs/{id}/proposals?updated_since=` | بلا تغيير مرحلة؛ upsert في `candidates` + `candidate_applications` |
| `screening` | لا شيء (فرز داخلي)؛ اختيارياً `under_review` | موظف `screen-candidates` | محلي | يدوي |
| `shortlist` | `PATCH /proposals/{id}/status` = `shortlisted` / `rejected` | موظف TAQAT | محلي + تأكيد عبر `proposal.updated` | يدوي |
| `interviewing` | غرفة محادثة مرتبطة بالـ proposal أو مقابلة خارج المنصة | موظف TAQAT + المستقل | محلي (لا حدث مقابلات في BrightGaza) | يدوي |
| `client_decision` | لا شيء | مالك الوظيفة مع العميل | محلي | يدوي |
| `contracting` | عرض توظيف من الـ proposal ← جولات تفاوض (حتى `max_rounds`) ← قبول ← دفع ← `JobContract` | موظف TAQAT ينشئ العرض ويرد على الـ counter؛ المستقل يقبل/يرفض/يفاوض؛ جهة الدفع تموّل | `hire_offer.*` · `GET /api/v1/client/dashboard/hire-offers/{offer_id}` و`/payment-status` | تلقائي عند إنشاء العرض من TAQAT إن كانت الوظيفة قبل `contracting` |
| `hired` | `JobContract` نشط (`job_contract_id` يُملأ بعد الدفع، `HireOffer.status = 4 Active`) | النظام | `contract.started` · `GET /jobs/{id}/contracts` | تلقائي عندما يبلغ عدد العقود النشطة `openings` ← `status = filled` ثم `POST /jobs/{id}/close` (`reason=filled`) |
| `cancelled` | إغلاق من TAQAT (`POST /jobs/{id}/close`) أو من BrightGaza (إشراف/انتهاء) | موظف TAQAT أو إدارة BrightGaza | `job.closed` · `GET /jobs/{id}` | تلقائي عند `job.closed` إذا لم يبدأ أي عقد |

بعد `hired` المرحلة نهائية: `contract.completed` / `contract.cancelled` تُحدّث `candidate_applications.contract_status` وتُنشئ إشعاراً ومهمة («مطلوب بديل») دون إرجاع المرحلة — `advanceStage()` يرفض الرجوع أصلاً.

---

## 3. ما هو موجود فعلاً في BrightGaza API

كل مسار أدناه متحقَّق منه في `spec.json`. جميعها `bearerAuth` (JWT لمستخدم) ما لم يُذكر «عام».

| Method | Path | الغرض | استخدام TAQAT |
|--------|------|-------|----------------|
| GET | `/api/v1/auth/profile` | ملف المستخدم الحالي | «اختبار الاتصال» إلى أن تتوفر مصادقة الخادم |
| GET | `/api/v1/freelancers` (عام) | بحث المواهب: `search` (الاسم والنبذة فقط)، `skills`، `category_id`، `location`، `hourly_from/to`، `per_page ≤ 100` | Sourcing قبل الدعوة |
| GET | `/api/v1/freelancers/view/{id}` (عام) | الملف العام؛ `email` لصاحبه فقط و`mobile` لا يظهر أبداً | عرض ملف المرشح |
| GET · POST | `/client/profile/jobs/{jobId}/invites` | عرض/إرسال دعوات: `freelancer_ids` (1–5، تراكمي حتى 5 للوظيفة، يرفض direct-hire وغير المعتمدة) ← `invited` / `already_invited` / `skipped` / `total_invites` | دعوة مرشحين من TAQAT |
| DELETE | `/client/profile/jobs/{jobId}/invites/{freelancerId}` | إلغاء دعوة | نفسه |
| POST | `/api/v1/chat-rooms/create-chat-room-proposal` | فتح/إعادة استخدام غرفة لـ `job_apply_id` (idempotent) | تنسيق المقابلة |
| POST | `/api/v1/client/dashboard/hire-offers` | عرض «Hire-Now» جولة 1: `freelancer_id` (Freelancer.id وليس user.id)، `title`، `description`، `contract_type` (1 ساعة، 2 ثابت)، `amount` (ثابت = الإجمالي؛ ساعة = السعر 3–500)، `weekly_limit_hours` (ساعة 1–70)، `milestones` (مجموعها = `amount`)، `note` | إنشاء عرض — **لا يحمل `job_id` ولا `proposal_id`** |
| GET | `/api/v1/client/dashboard/hire-offers` · `/{offer_id}` · `/{offer_id}/history` | قائمة (`status` 0=Pending … 6=Withdrawn) / عرض / سجل الجولات | المتابعة والـ poll |
| PUT | `/api/v1/client/dashboard/hire-offers/{offer_id}/counter` · `/accept-counter` · `/reject-counter` · `/withdraw` | التفاوض؛ `expected_version` ← 409 عند التعارض | الرد على الـ counter من TAQAT |
| POST · GET | `/api/v1/client/dashboard/hire-offers/{offer_id}/checkout` · `/payment-status` | جلسة دفع (`lahza` / `ngenius`) تُرجع رابط بوابة للمتصفح؛ `is_paid` + `job_contract_id` | تحويل المستخدم للدفع ثم الـ poll |
| POST | `/client/dashboard/hourly-contracts/start` | إنشاء وتمويل عقد بالساعة من المحفظة (`source_type` = `proposal` / `direct-hire`، `item_id` = `JobApplyOffer.id` / `MarketJob.id`) | تمويل بلا متصفح — بالساعة فقط |
| GET | `/client/dashboard/jobs/fixed-price-contracts` · `/{contract_id}` · `/client/dashboard/jobs/hourly-job-contract` | قوائم العقود (بلا فلتر على الوظيفة) | poll احتياطي |
| PUT | `/api/v1/jobs/{job_id}/completed-job` | تعليم وظيفة معتمدة كمكتملة (400 إن كانت مكتملة) | إغلاق بعد التعيين (السؤال 12) |
| GET · POST · PATCH | `/client/dashboard/jobs/direct-hire` · `/{id}` · `/{id}/withdraw` | وظيفة **خاصة** لمستقل أو فريق واحد | مسار «مرشح معروف» فقط، ليس إعلاناً عاماً |

**غير موجود:** إنشاء أو عرض إعلانات العميل العامة، قائمة الـ proposals على وظيفة، عرض توظيف من proposal (`JobApplyOffer` يظهر ضمنياً دون endpoint)، webhooks صادرة (الموجود استقبال Clockify ودفع البوابة فقط)، مصادقة خادم لخادم، كتالوج التصنيفات والمهارات.

**ملاحظات جودة الـ spec (تُصلَح قبل التكامل):** `servers` = `http://my-default-host.com`؛ بادئات مختلطة (`/api/v1/...` مقابل `/client/dashboard/...`)؛ مسار حرفي من Postman `{{url}}/client/dashboard/jobs/fixed-price-contracts/contract-details/35`؛ غلاف غير موحّد (`status` + `code` أحياناً وبدون `code` أحياناً)؛ تواريخ بلا منطقة زمنية وبصيغ متعددة (`2026-05-24 14:30`، `Jan 15, 2024`، `d-m-Y`)؛ مبالغ كنص منسّق (`"$1,250.00"`، `"25.000"`) وأحياناً رقم؛ `contract_type` في direct-hire مقابل `contract_time_type` في `GeneralJob`؛ روابط صور إلى `localhost`؛ نطاقات أمثلة متعددة (`staging.taqatgaza.com`، `staging.brightgaza.com`، `staging.taqatportal.com`).

---

## 4. الإضافات المطلوبة من فريق BrightGaza

> هذا القسم مكتوب ليُسلَّم كما هو. كل المسارات الجديدة تحت بادئة واحدة `/integrations/v1`. الأمثلة توضيحية، وأسماء الحقول تتبع `GeneralJob` و`HireOffer` حيث أمكن.

### 4.1 مصادقة خادم لخادم (Organisation Account)

- **حساب منظمة:** حساب client في BrightGaza تملكه TAQAT («TAQAT Recruitment») تُنشر منه الوظائف وتُدار منه العقود (حساب فرعي لكل عميل؟ السؤال 6).
- **OAuth2 Client Credentials** (المفضّل)، أو مفتاح API بنفس الـ scopes.
- **Scopes:** `jobs:read jobs:write proposals:read proposals:write offers:read offers:write contracts:read webhooks:manage`.
- **عمر الـ token:** 3600 ثانية بلا refresh token؛ TAQAT تخزّنه في Redis حتى `expires_in - 60`.
- **التدوير:** سرّان نشطان كحد أقصى لكل client؛ إنشاء الجديد ثم إبطال القديم من لوحة BrightGaza أو `POST /integrations/v1/credentials/{id}/revoke`.
- **IP allow-list اختياري** لكل client (عناوين خروج خادم TAQAT).
- لا نستخدم JWT مستخدم ولا مسار Taqat SSO لهذا الغرض. كل عملية تُسجَّل لدى BrightGaza بـ `client_id` كجهة فاعلة.

```http
POST /oauth/token
Content-Type: application/x-www-form-urlencoded

grant_type=client_credentials&client_id=taqat-recruitment&client_secret=***&scope=jobs:write proposals:read
```

```json
{ "token_type": "Bearer", "access_token": "eyJhbGciOiJSUzI1NiJ9...", "expires_in": 3600, "scope": "jobs:write proposals:read" }
```

### 4.2 قواعد مشتركة

| البند | المطلوب |
|------|---------|
| Base URL | `https://api.<domain>/integrations/v1` للإنتاج و`https://sandbox-api.<domain>/integrations/v1` لبيئة sandbox ببيانات ومدفوعات تجريبية منفصلة (النطاق: السؤال 1) |
| المعرّفات | ثابتة ولا يُعاد استخدامها، تُرجع كنص (`"id": "4812"`) |
| الوقت | UTC بصيغة ISO 8601 (`2026-09-14T08:30:00Z`) في كل الحقول |
| المال | `amount` نص عشري (`"1500.00"`) + `currency` بصيغة ISO 4217 |
| الـ Enums | رموز نصية ثابتة (`"status": "approved"`)؛ يمكن إبقاء الرقم القديم في حقل منفصل |
| الترقيم | `?limit=50&cursor=<opaque>` ← `{ "data": [...], "next_cursor": "..." \| null }`، مع `updated_since=<ISO 8601>` مرتباً حسب `updated_at, id` |
| Idempotency | ترويسة `Idempotency-Key` على كل `POST`؛ نفس المفتاح خلال 24 ساعة يُرجع نفس الاستجابة |
| Rate limits | 600 طلب/دقيقة كبداية؛ `X-RateLimit-Limit` و`X-RateLimit-Remaining`؛ 429 مع `Retry-After` |
| الأخطاء | غلاف واحد (أدناه) وأكواد HTTP صحيحة: 400/401/403/404/409/422/429/5xx |
| الإصدارات | الإصدار في المسار؛ إضافة الحقول لا تكسر التوافق؛ الحذف أو تغيير المعنى بإشعار 90 يوماً وترويسة `Sunset` |
| التتبع | `X-Request-Id` في كل استجابة |
| التوثيق | OpenAPI منفصل للتكامل بـ `servers` حقيقية وأمثلة صالحة |

```json
{
  "error": {
    "code": "validation_failed",
    "message": "The given data was invalid.",
    "fields": { "budget_to": ["must be greater than or equal to budget_from"] },
    "request_id": "req_01J8ZK3V9Q"
  }
}
```

### 4.3 Endpoints المطلوبة

| # | Method | Path | الغرض | Scope |
|---|--------|------|-------|-------|
| E1 | POST | `/jobs` | إنشاء إعلان عام باسم حساب المنظمة | `jobs:write` |
| E2 | PATCH | `/jobs/{id}` | تعديل جزئي (409 إذا `is_editable = false`) | `jobs:write` |
| E3 | POST | `/jobs/{id}/close` | إغلاق: `reason` = `filled` \| `cancelled` \| `expired` + `note` | `jobs:write` |
| E4 | GET | `/jobs/{id}` | الوظيفة كاملة مع `public_url` والحالة | `jobs:read` |
| E5 | GET | `/jobs?updated_since=&cursor=&limit=` | وظائف الحساب المتغيّرة | `jobs:read` |
| E6 | GET | `/jobs/{id}/proposals?status=&updated_since=&cursor=` | قائمة الـ proposals | `proposals:read` |
| E7 | GET | `/proposals/{id}` | proposal واحد مع ملف المستقل الموسّع (`languages`، `educations`، `work_experiences`، `certifications`) | `proposals:read` |
| E8 | PATCH | `/proposals/{id}/status` | `under_review` \| `shortlisted` \| `rejected` + `notify_freelancer` | `proposals:write` |
| E9 | POST | `/proposals/{id}/hire-offers` | عرض توظيف مربوط بالوظيفة والـ proposal | `offers:write` |
| E10 | GET | `/jobs/{id}/contracts` | العقود الناتجة عن الوظيفة | `contracts:read` |
| E11 | GET | `/catalog/categories` · `/catalog/skills?search=` | معرّفات التصنيفات والمهارات | `jobs:read` |
| E12 | POST | `/hire-offers/{id}/fund` | (اختياري) تمويل عرض مقبول من محفظة المنظمة بلا متصفح، ثابت وبالساعة | `offers:write` |

**بديل مقبول لـ E9:** إضافة `job_id` و`proposal_id` اختياريين إلى `HireOfferStoreRequest` الحالي وإرجاعهما في `HireOffer`؛ عندها تكفي endpoints التفاوض والدفع الحالية (مع نقلها إلى bearer المنظمة). بدون هذا الربط لا تستطيع TAQAT معرفة أي عقد يخص أي وظيفة.

**E1 — Request**

```json
{
  "external_reference": "JOB-2026-0045",
  "title": "Senior Backend Developer",
  "description": "…",
  "category_id": "12", "sub_category_id": "88", "skill_ids": ["101", "245"],
  "contract_time_type": "hourly", "weekly_hours": 40, "duration": "3_6_months",
  "experience_level": "expert",
  "budget_from": "9.00", "budget_to": "12.00", "currency": "USD",
  "number_of_open_positions": 2,
  "allow_countries": ["PS", "JO", "EG"],
  "application_deadline": "2026-10-15T20:59:59Z",
  "attachment_ids": []
}
```

**E1 — Response `201`** (نفس شكل E4 وE5)

```json
{
  "data": {
    "id": "4812", "external_reference": "JOB-2026-0045",
    "status": "pending_review", "reject_details": [],
    "public_url": null, "published_at": null, "closed_at": null, "close_reason": null,
    "is_open": false, "is_editable": true, "is_cancellable": true,
    "proposal_count": 0, "last_proposal_at": null, "hired_count": 0,
    "title": "Senior Backend Developer", "contract_time_type": "hourly",
    "budget_from": "9.00", "budget_to": "12.00", "currency": "USD",
    "number_of_open_positions": 2,
    "created_at": "2026-09-14T08:30:00Z", "updated_at": "2026-09-14T08:30:00Z"
  }
}
```

بعد الاعتماد: `status = "approved"`، `is_open = true`، `public_url = "https://<domain>/jobs/4812"`، و`published_at` مملوء. تكرار `external_reference` ← `409 external_reference_taken`.

**E6 — Response `200`** (عنصر واحد)

```json
{
  "data": [{
    "id": "58", "job_id": "4812", "status": "submitted",
    "freelancer": {
      "id": "163", "name": "Amina Aman", "headline": "Laravel Developer",
      "country": "PS", "hourly_rate": "10.00", "currency": "USD",
      "years_experience": 4, "rating": 4.6, "total_reviews": 12, "identity_verified": true,
      "skills": [{ "id": "101", "name": "Laravel" }],
      "profile_url": "https://<domain>/freelancers/163",
      "email": "a@example.com", "phone": "+970599000000"
    },
    "cover_letter": "…",
    "bid": { "type": "hourly", "amount": "11.00", "currency": "USD", "weekly_hours": 40, "estimated_duration": "3_6_months" },
    "attachments": [{
      "id": "901", "file_name": "cv.pdf", "mime_type": "application/pdf", "size": 184233,
      "url": "https://files.<domain>/signed/…", "url_expires_at": "2026-09-14T09:40:00Z"
    }],
    "consent": { "shared_with_recruiter_at": "2026-09-14T08:40:00Z" },
    "hire_offer_id": null, "contract_id": null,
    "submitted_at": "2026-09-14T08:40:00Z", "updated_at": "2026-09-14T08:40:00Z", "withdrawn_at": null
  }],
  "next_cursor": null
}
```

- `email` و`phone` فقط عند وجود `consent` (السؤال 9)؛ روابط المرفقات صالحة 60 دقيقة على الأقل.
- قيم `status`: `submitted`، `under_review`، `shortlisted`، `rejected`، `offer_sent`، `hired`، `withdrawn`.

**E8 — Request** `{ "status": "shortlisted", "note": "…", "notify_freelancer": false }` ← `200` بكائن الـ proposal.

**E9 — Request** (نفس `HireOfferStoreRequest` بدون `freelancer_id`)

```json
{ "title": "Senior Backend Developer", "description": "…", "contract_type": "hourly", "amount": "11.00", "currency": "USD", "weekly_limit_hours": 40, "milestones": null, "note": "…" }
```

← `201` بكائن `HireOffer` الحالي مضافاً إليه `job_id` و`proposal_id`، و`status` كرمز نصي: `pending`، `countered`، `accepted`، `declined`، `expired`، `active`، `withdrawn`.

**E10 — Response `200`**

```json
{
  "data": [{
    "id": "78", "job_contract_number": "JC-2026-0078",
    "job_id": "4812", "proposal_id": "58", "hire_offer_id": "12", "freelancer_id": "163",
    "type": "hourly", "status": "active",
    "amount": "11.00", "currency": "USD", "weekly_limit_hours": 40,
    "started_at": "2026-09-20T10:00:00Z", "ended_at": null, "end_reason": null,
    "updated_at": "2026-09-20T10:00:00Z"
  }],
  "next_cursor": null
}
```

### 4.4 Webhooks

**التسجيل:** من لوحة BrightGaza أو `POST /webhooks/endpoints` بـ `{ "url": "https://<taqat-api>/api/webhooks/brightgaza", "events": ["*"] }` ← يُرجع `secret` مرة واحدة. endpoint واحد لكل بيئة.

| الحدث | يُطلق عند | `data.object` | ما تفعله TAQAT |
|------|-----------|---------------|----------------|
| `job.published` | اعتماد الإعلان وظهوره | Job (E4) | `publication_url` + انتقال `publish → receiving_apps` |
| `job.rejected` | رفض الإشراف | Job + `reject_details` | مهمة لمالك مرحلة `publish` |
| `job.updated` | تعديل من جهة BrightGaza | Job | تحديث `external_status` |
| `job.closed` | إغلاق لأي سبب | Job + `close_reason` | `cancelled` إن لم يبدأ عقد |
| `proposal.submitted` | تقديم جديد | Proposal (E6) | upsert مرشح + طلب |
| `proposal.updated` | تعديل العرض أو الحالة | Proposal | تحديث الطلب |
| `proposal.withdrawn` | سحب المستقل | Proposal | `withdrawn` |
| `hire_offer.countered` | counter من المستقل | HireOffer | مهمة لمالك `contracting` |
| `hire_offer.accepted` | قبول المستقل | HireOffer | `offer_accepted` + مهمة الدفع |
| `hire_offer.declined` | رفض | HireOffer + `reason` | الطلب يعود `shortlisted` + مهمة |
| `hire_offer.expired` | انتهاء `expires_at` | HireOffer | نفسه |
| `hire_offer.withdrawn` | سحب العرض | HireOffer | نفسه |
| `contract.started` | نجاح الدفع وإنشاء `JobContract` | Contract (E10) | الطلب `hired`؛ الوظيفة `hired` عند اكتمال `openings` |
| `contract.completed` | انتهاء العقد | Contract | تحديث `contract_status` |
| `contract.cancelled` | إنهاء مبكر | Contract + `end_reason` | إشعار + مهمة «مطلوب بديل» |
| `freelancer.erasure_requested` | طلب حذف بيانات | `{ "freelancer_id": "163" }` | إخفاء هوية المرشح (§7) |

**الطلب**

```http
POST /api/webhooks/brightgaza
Content-Type: application/json
X-BrightGaza-Event-Id: evt_01J8ZQ4M2T6
X-BrightGaza-Signature: t=1726303201,v1=5f2b0c…e91a
```

```json
{
  "id": "evt_01J8ZQ4M2T6",
  "type": "proposal.submitted",
  "api_version": "v1",
  "created_at": "2026-09-14T08:40:01Z",
  "delivery_attempt": 1,
  "data": { "object": { "id": "58", "job_id": "4812", "status": "submitted", "version": 1, "updated_at": "2026-09-14T08:40:00Z" } }
}
```

`data.object` يحمل الكائن كاملاً بنفس شكل الـ GET المقابل (مختصر هنا). مثال عقد:

```json
{
  "id": "evt_01J9A2K7X0B", "type": "contract.started", "api_version": "v1",
  "created_at": "2026-09-20T10:00:02Z", "delivery_attempt": 1,
  "data": { "object": { "id": "78", "job_id": "4812", "proposal_id": "58", "hire_offer_id": "12", "type": "hourly", "status": "active", "version": 1, "started_at": "2026-09-20T10:00:00Z", "updated_at": "2026-09-20T10:00:00Z" } }
}
```

- **التوقيع:** `v1 = hex(HMAC-SHA256(secret, "{t}.{raw_body}"))` حيث `t` بالثواني (Unix). ترفض TAQAT الطلب إذا ابتعد `t` عن الوقت الحالي أكثر من 300 ثانية أو لم يتطابق التوقيع. أثناء تدوير السر يُرسل توقيعان: `t=…,v1=…,v1=…`.
- **التسليم:** at-least-once، ومهلة 10 ثوانٍ. أي رد غير `2xx` ← إعادة بعد 1د، 5د، 30د، 2س، 6س، 12س، 24س (حتى 72 ساعة)، ثم `failed` مع `GET /webhooks/events?status=failed&since=` و`POST /webhooks/events/{id}/redeliver`.
- **الترتيب:** غير مضمون. كل كائن يحمل `version` متزايداً و`updated_at`؛ TAQAT تتجاهل النسخة الأقدم وتعيد جلب الكائن قبل التطبيق.
- **Idempotency:** `id` الحدث فريد عالمياً وثابت عبر كل محاولات التسليم.

---

## 5. مطابقة الحقول: `JobRequirement` ← BrightGaza Job

| TAQAT (`job_requirements`) | BrightGaza | التحويل | فجوات / أسئلة |
|---------------------------|-----------|---------|----------------|
| `job_number` | `external_reference` (جديد) | كما هو | — |
| `external_source` / `external_id` (جديد) | `"brightgaza"` / `id` | من استجابة E1 | — |
| `title` (200) | `title` (255) | كما هو | — |
| `description` + `responsibilities` + `nice_to_have_skills` + `education_level` + `required_languages` | `description` | دمج بعناوين فرعية | نص عادي أم HTML/Markdown؟ |
| `required_skills` (نصوص حرة) | `skill_ids` | مطابقة بالاسم عبر E11 في نافذة النشر؛ غير المطابق يُضاف للوصف | لا كتالوج مهارات في TAQAT |
| — | `category_id` / `sub_category_id` | اختيار إلزامي في نافذة النشر ← `external_meta` | لا تصنيف مقابل في TAQAT |
| `openings` | `number_of_open_positions` | كما هو | — |
| `salary_min` / `salary_max` | `budget_from` / `budget_to` | شهري ← ساعة: `salary ÷ (weekly_hours × 4.33)`؛ ثابت: الإجمالي للعقد | راتب شهري مقابل سعر ساعة/مبلغ مشروع (السؤال 14) |
| `salary_currency` (افتراضي USD) | `currency` (جديد) | كما هو | المحافظ بالدولار في الـ spec ← نرفض غير USD حالياً (السؤال 7) |
| `employment_type` | `contract_time_type` (1=hourly، 2=fixed) + `weekly_hours` | `full_time` ← hourly/40؛ `part_time` ← hourly/20؛ `contract` ← يختار الناشر؛ `intern` / `temporary` ← hourly | تأكيد القاعدة مع الإدارة |
| `work_mode` | — | `remote` فقط يُنشر؛ `onsite` / `hybrid` تُمنع افتراضياً | السؤال 17 |
| `location` (نص حر) | `allow_countries` (ISO alpha-2) | اختيار دول في نافذة النشر | `CountryMini` في الـ spec يحمل `alpha-2` |
| `required_experience_years` | `experience_level` | 0–1 ← `entry`؛ 2–4 ← `intermediate`؛ 5+ ← `expert` | القيم المسموحة غير موثقة (المثال `intermediate` فقط) |
| — (مدة العقد) | `duration` (1=1–3 أشهر، 2=3–6، 3=6–12، 4=أكثر من 12) | اختيار في نافذة النشر ← `external_meta` | لا حقل مدة في TAQAT |
| `application_deadline` (date) | `application_deadline` (جديد) | نهاية اليوم بتوقيت `Asia/Amman` ← UTC | غير موجود في BrightGaza |
| `publication_url` / `published_at` | `public_url` / `published_at` | من `job.published` | — |
| `status` / المرحلة | `status` + الأحداث | §2 | — |
| `owner_id` | — | لا يُرسل | — |

---

## 6. خطة التنفيذ على جانب TAQAT

وحدة جديدة `apps/api/app/Modules/BrightGaza/` مسؤولة عن النقل فقط (Gateway، Webhook، سجلات المزامنة)، بنفس فصل `Sms` و`Whatsapp`. منطق المراحل والمرشحين يبقى في `Modules/Recruitment` ويعتمد على العقد `BrightGazaGateway` فقط.

### Phase A — الأساس (بدون اتصال شبكة)

**Migrations** (كلها بـ `down()`، وتُضاف إلى نمط `RecruitmentMigrationsReversibleTest`):

| الجدول | الأعمدة الأساسية | القيود والفهارس |
|--------|------------------|-----------------|
| `job_requirements` (توسعة) | `external_source` varchar(30) null، `external_id` varchar(64) null، `external_status` varchar(30) null، `external_meta` json null، `external_synced_at` timestamp null | `UNIQUE (external_source, external_id)` |
| `candidates` | `full_name`، `email` (encrypted) null، `email_hash` char(64) null، `phone` (encrypted) null، `country_code` char(2) null، `headline`، `skills` json، `hourly_rate` decimal(10,2) null، `external_source`، `external_id`، `profile_url`، `consent_at`، `anonymized_at`، timestamps + softDeletes | `UNIQUE (external_source, external_id)`، `INDEX email_hash` |
| `candidate_applications` | `job_requirement_id` FK، `candidate_id` FK، `external_source`، `external_id` (proposal)، `status`، `cover_letter` text، `bid_type`، `bid_amount` decimal(10,2)، `bid_currency` char(3)، `weekly_hours`، `screening_score`، `screening_notes`، `external_hire_offer_id`، `external_contract_id`، `contract_status`، `submitted_at`، `external_version`، `external_updated_at`، timestamps | `UNIQUE (external_source, external_id)`، `UNIQUE (job_requirement_id, candidate_id)`، `INDEX (job_requirement_id, status)` |
| `brightgaza_webhook_events` | `event_id` varchar(64)، `type`، `payload` json، `status` (`pending` / `processed` / `ignored` / `failed`)، `attempts`، `error`، `received_at`، `processed_at` | `UNIQUE event_id`، `INDEX (status, received_at)` |
| `brightgaza_sync_logs` | `direction` (`outbound` / `webhook` / `reconcile`)، `operation`، `entity_type`، `entity_id`، `http_method`، `path` (بلا query)، `http_status`، `duration_ms`، `request_id`، `success`، `error`، `created_at` | `INDEX (entity_type, entity_id)`، `INDEX created_at` |

- `candidates` و`candidate_applications` هما جدولا Phase 2 نفسهما: `external_source` عام، والاستيراد اليدوي (CSV) يترك `external_source = null`.
- Enums: `ExternalSource`، `CandidateApplicationStatus`، وحالة `CandidateApplication` في `TaskEntityType`.
- صلاحيات `view-candidates`، `manage-candidates`، `manage-brightgaza-offers` عبر data migration (لا seeders في الإنتاج).

**الإعدادات:** مجموعة `brightgaza` في `SettingsController::GROUPS` (مع قواعدها في `update()`):

| key | النوع | ملاحظة |
|-----|------|--------|
| `enabled` | boolean | الـ feature flag، معطّل افتراضياً |
| `fake` | boolean | يفرض `FakeBrightGazaGateway` خارج بيئة الاختبار |
| `base_url` | url | `url:https` + `endpointHostRule()`؛ يُضاف مضيفا الإنتاج والـ sandbox إلى `ALLOWED_ENDPOINT_HOSTS` بعد تأكيدهما |
| `client_id` | text | — |
| `client_secret` | password (encrypted) | — |
| `webhook_secret` / `webhook_secret_previous` | password (encrypted) | الثاني أثناء التدوير فقط |

`POST /api/admin/settings/test/brightgaza` تحت `permission:manage-settings` و`throttle:10,1,settings-probe`: يطلب token ثم `GET /jobs?limit=1`، يسجّل `settings_probe_sent`، ولا يُرجع جسم الاستجابة الخام (نفس سبب الـ SSRF oracle في `testSms`).

**Gateway:** `App\Modules\BrightGaza\Contracts\BrightGazaGateway` بدوال `ping`، `createJob`، `updateJob`، `closeJob`، `getJob`، `listJobsUpdatedSince`، `listProposals`، `getProposal`، `updateProposalStatus`، `createHireOffer`، `getHireOffer`، `listContracts`، `inviteFreelancers`. لا ترمي استثناء لأخطاء النقل، بل تُرجع `BrightGazaResult` (نمط `SmsGateway` / `SmsResult`) بتصنيف: `transport`، `rate_limited` (+ `retry_after`)، `auth`، `validation`، `conflict`، `not_found`.

- `HttpBrightGazaGateway`: token في Redis، طلب token جديد مرة واحدة عند 401، `Idempotency-Key` ثابت لكل عملية (`taqat-job-{id}-create`)، مهلة 10 ثوانٍ، وسطر في `brightgaza_sync_logs` لكل طلب.
- `FakeBrightGazaGateway`: تخزين في الذاكرة، ردود حتمية، وتسجيل الاستدعاءات للـ assertions.
- الربط في `AppServiceProvider::registerBrightGazaGateway()` بنفس شرط `registerSmsGateway()`: بيئة `testing` أو `brightgaza.fake` ← Fake.

### Phase B — النشر الصادر

- `BrightGazaPublishingService` (داخل Recruitment): `publish(JobRequirement $job, array $options, User $actor)` يتحقق من المطابقة (§5)، يستدعي `createJob`، ويحفظ `external_*` داخل transaction. `syncUpdate()` يعمل عبر job مؤجّل `PushJobUpdateToBrightGaza` عند تعديل حقل مطابق. `JobRequirementService::cancel()` يستدعي `closeJob` بعد الـ commit وليس داخله.
- Routes جديدة:

| Method | Route | Permission |
|--------|-------|------------|
| POST | `/api/jobs/{job}/brightgaza/publish` | `publish-jobs` |
| POST | `/api/jobs/{job}/brightgaza/link` (ربط وظيفة منشورة مسبقاً بـ `external_id`) | `publish-jobs` |
| POST | `/api/jobs/{job}/brightgaza/invites` | `manage-candidates` |
| GET | `/api/jobs/{job}/applications` · `/api/candidate-applications/{application}` | `view-candidates` |
| PATCH | `/api/candidate-applications/{application}/status` (يدفع E8) | `manage-candidates` |
| POST | `/api/candidate-applications/{application}/hire-offer` · `/hire-offer/counter` · `/hire-offer/withdraw` | `manage-brightgaza-offers` |
| GET | `/api/candidate-applications/{application}/cv` (رابط موقّع) | `view-candidates` |

- الواجهة: نافذة «نشر على BrightGaza» في صفحة الوظيفة (التصنيف، المهارات، الدول، المدة، نوع العقد) + شارة حالة المزامنة + تبويب «المتقدمون».

### Phase C — الاستقبال (Webhook)

1. Route عام خارج `auth:sanctum`: `POST /api/webhooks/brightgaza` مع `withoutMiddleware('throttle:api')` و`throttle:600,1,brightgaza-webhook` (نفس نمط مسارات `scan`).
2. Middleware `VerifyBrightGazaSignature`: جسم خام ≤ 1MB، `t` ضمن 300 ثانية، `hash_equals` مع السر الحالي ثم السابق؛ الفشل ← 401 بلا تفاصيل.
3. `BrightGazaWebhookController` رفيع: `insertOrIgnore` في `brightgaza_webhook_events` على `event_id` ← dispatch `ProcessBrightGazaWebhookEvent` ← `200` فوراً. الحدث المكرر يُرجع `200` بلا عمل.
4. `ProcessBrightGazaWebhookEvent` (queue `integrations`، `tries = 5`، `backoff = [10, 60, 300, 900, 3600]`، `ShouldBeUnique` على `event_id`): يعيد جلب الكائن عبر الـ Gateway، يتجاهل ما `version` فيه ≤ المخزن، ثم يوجّه إلى `JobEventHandler` / `ProposalEventHandler` / `HireOfferEventHandler` / `ContractEventHandler`. عند `brightgaza.enabled = false` تُخزن الأحداث وتبقى `pending`.
5. `DownloadProposalAttachments`: ينزّل الـ CV قبل `url_expires_at` إلى media collection خاصة على `CandidateApplication` (Spatie MediaLibrary، نفس تخزين مرفقات المهام).

### Phase D — المصالحة المجدولة

`brightgaza:reconcile` في `routes/console.php` كل 15 دقيقة مع `withoutOverlapping()->runInBackground()`:

1. E5 بـ `updated_since` = آخر نقطة ناجحة (مفتاح `brightgaza.reconciled_until` في `settings`).
2. E6 لكل وظيفة مفتوحة مربوطة، بـ `updated_since` = `external_synced_at`.
3. `GET /api/v1/client/dashboard/hire-offers/{offer_id}/payment-status` لكل عرض مقبول لم يُدفع.
4. يمر عبر نفس الـ handlers فيبقى idempotent. تمريرة كاملة ليلية لكل الوظائف المفتوحة المربوطة.

### Phase E — الأتمتة

كل انتقال يمر عبر `JobRequirementService::advanceStage()` (نقطة الانتقال الوحيدة) بـ `target_stage_id` لاحق و`handoff_note` يذكر `event_id`؛ `JobRequirementStageAdvanced` يولّد المهام كالمعتاد، ولا تُنشأ مهام للمراحل المتجاوزة.

| المُطلِق | الأثر |
|---------|-------|
| `job.published` | `publish → receiving_apps` مع `fields.publication_url` (يحقق `requires_fields`) |
| `job.rejected` | مهمة `Fix BrightGaza rejection: {job.title}` لمالك `publish` |
| `proposal.submitted` | upsert + إشعار مجمّع لمالك المرحلة (عبر الـ dedup الحالي في `NotificationService`، داخل try/catch) |
| إنشاء عرض من TAQAT | انتقال إلى `contracting` إن كانت الوظيفة قبلها؛ الطلب `offer_sent` |
| `hire_offer.countered` / `declined` / `expired` / `withdrawn` | مهمة لمالك `contracting`؛ الطلب يعود `shortlisted` عند الإنهاء |
| `contract.started` | الطلب `hired`؛ إذا بلغ عدد `hired` قيمة `openings` ← `hired` (`status = filled`) + E3 `reason=filled` |
| `job.closed` بلا عقود | ← `cancelled` |

**يبقى يدوياً:** الانتقالات `receiving_apps → screening → shortlist → interviewing → client_decision`، تقييم الفرز، المقابلات، قرار العميل، شروط العرض والرد على الـ counter، الدفع عبر رابط البوابة (ما لم يتوفر E12)، واعتماد الـ milestones والأسابيع والنزاعات (داخل BrightGaza).

---

## 7. الأمان والخصوصية

- **أقل قدر من البيانات الشخصية:** الاسم، الدولة، العنوان المهني، المهارات، السعر، رابط الملف، ومعرّفات BrightGaza. البريد والهاتف فقط عند وجود `consent`، مشفّران (`encrypted` cast) مع `email_hash` لكشف التكرار.
- **الـ CV:** لا نمرّر روابط BrightGaza المؤقتة للمتصفح. الملف يُخزَّن في media collection خاصة ويُقدَّم بـ `URL::temporarySignedRoute` لمدة 30 دقيقة خلف `auth:sanctum` + `signed` + `view-candidates` (نمط `TaskAttachmentService::signedDownloadUrl()`). الأنواع المقبولة `pdf` / `doc` / `docx` بحد 10MB.
- **الموافقة:** BrightGaza تُبلغ المستقل عند التقديم بأن بياناته تُشارك مع TAQAT كجهة توظيف وتُرجع `consent.shared_with_recruiter_at`؛ بدونها لا نخزن الـ CV ولا بيانات الاتصال.
- **الاحتفاظ:** طلبات غير المعيَّنين تُخفى هويتها بعد 24 شهراً من إغلاق الوظيفة (الاسم ← `Candidate #id`، حذف الـ CV والاتصال، `anonymized_at`). `brightgaza_webhook_events` و`brightgaza_sync_logs` تُحذف بعد 90 يوماً بإضافتها إلى `taqat:prune-old-rows` (مثل `sms_logs`). `freelancer.erasure_requested` يُخفي الهوية فوراً.
- **التدقيق:** `LogsActivity` على `Candidate` و`CandidateApplication` (`recruitment.candidate`، `recruitment.candidate_application`). التغييرات الآلية تُسجَّل بلا causer مع `properties.source = brightgaza` و`event_id`. تغييرات الإعدادات مغطاة بسجل `setting_updated` الحالي (hash فقط).
- **السجلات:** `brightgaza_sync_logs` بلا أجسام طلبات ولا tokens ولا query strings؛ ترويسة `Authorization` محجوبة في أي `Log::warning`.
- **الأسرار:** `client_secret` و`webhook_secret` مشفّرة في `settings` ولا تُعرض بعد الحفظ (`has_value` فقط).
- **الـ Webhook:** توقيع إلزامي، نافذة replay 300 ثانية، حد حجم، rate limit مستقل، 401 عام، وIP allow-list اختياري إن نشرت BrightGaza عناوين الخروج.
- **SSRF:** `base_url` مقيَّد بـ `ALLOWED_ENDPOINT_HOSTS` وHTTPS فقط.

---

## 8. الاختبار والإطلاق

### 8.1 الاختبارات (`tests/Feature/BrightGaza/*`)

| الملف | يغطي |
|------|------|
| `PublishJobTest` | مطابقة الحقول، رفض `onsite` وغير USD، حفظ `external_id`، إعادة النشر بنفس `Idempotency-Key`، بوابة `publish-jobs` |
| `WebhookSignatureTest` | توقيع صحيح / خاطئ / `t` منتهي / السر السابق |
| `WebhookIdempotencyTest` | نفس `event_id` مرتين ← معالجة واحدة |
| `WebhookOrderingTest` | `proposal.updated` قبل `proposal.submitted`، ونسخة أقدم من المخزنة |
| `StageAutomationTest` | `job.published` ← `receiving_apps`؛ `openings = 2` لا يصل `hired` إلا مع العقد الثاني؛ `job.closed` بعد عقد لا يلغي |
| `ReconcileCommandTest` | تشغيل مرتين بلا صفوف مكررة |
| `HttpBrightGazaGatewayTest` | `Http::fake`: تخزين الـ token، 401 ثم إعادة، 429 مع `Retry-After`، 5xx ← `transport` |
| `SettingsBrightGazaTest` | رفض مضيف خارج القائمة، عدم إرجاع الأسرار، الـ probe |

- كل الاختبارات تستخدم `FakeBrightGazaGateway` تلقائياً (بيئة `testing`).
- ردود sandbox حقيقية تُحفظ في `tests/Fixtures/BrightGaza/*.json` كاختبار عقد لـ `HttpBrightGazaGateway`.
- تشغيل Pest على MySQL قبل دفع الـ migrations (الفهارس الفريدة المركّبة و`json`).

### 8.2 خطوات الإطلاق

1. نشر الوحدة والـ migrations مع `brightgaza.enabled = false`.
2. staging ببيانات sandbox: «اختبار الاتصال» ثم رحلة كاملة (نشر ← proposal ← عرض ← دفع تجريبي ← عقد) باستخدام أداة محاكاة أحداث من BrightGaza (مطلوبة، مثل `POST /sandbox/simulate-event`).
3. الإنتاج: تفعيل الـ flag لناشر واحد ووظيفتين تجريبيتين.
4. تشغيل `brightgaza:reconcile` ومراقبة `brightgaza_sync_logs` و`brightgaza_webhook_events.status = failed` لمدة أسبوع.
5. التعميم على كل وظائف `remote`.

### 8.3 ربط الوظائف الحالية (Backfill)

لا دخول للخادم (كل النشر عبر GitHub Actions) ولا استدعاءات شبكة داخل migration، لذلك الربط من الواجهة: شاشة «ربط وظائف منشورة» تعرض الوظائف التي يشير `publication_url` فيها إلى نطاق BrightGaza وتستخرج المعرّف من الرابط كاقتراح. الموظف يؤكد ← `POST /api/jobs/{job}/brightgaza/link` يتحقق عبر `getJob` ويحفظ `external_id` ← المصالحة التالية تستورد الـ proposals الحالية دون تغيير المرحلة.

---

## 9. أسئلة مفتوحة

### لفريق BrightGaza

1. ما النطاق وعناوين الـ API النهائية للإنتاج والـ sandbox؟ الـ spec يذكر `taqatgaza.com` و`brightgaza.com` و`taqatportal.com`.
2. هل إنشاء الإعلان العام (MarketJob غير direct) موجود داخلياً؟ هل تمر وظائف حساب المنظمة على الإشراف، وكم يستغرق؟
3. ما الفرق بين `JobApply` و`JobApplyOffer`؟ أين يُخزَّن مبلغ العرض والمرفقات؟ وهل توجد أسئلة فرز؟
4. التوظيف من proposal: هل ننشئ `JobApplyOffer` (E9) أم نضيف `job_id` / `proposal_id` إلى `HireOffer`؟ وهل `hourly-contracts/start` بـ `source_type=proposal` هو المسار الصحيح للعقد بالساعة؟
5. هل يمكن تمويل العقود الثابتة والساعية من محفظة المنظمة عبر API (E12) بدل رابط البوابة؟ وهل تتوفر فوترة دورية لـ TAQAT؟
6. حساب منظمة واحد لكل عملاء TAQAT أم حساب فرعي لكل عميل؟ (يؤثر على `GeneralJobClient` الظاهر للعامة: `name`، `total_spent`، `hire_rate`.)
7. هل كل المبالغ بالدولار فقط؟
8. هل معرّفات التصنيفات والمهارات ثابتة بين الإنتاج والـ sandbox؟
9. هل يمكن مشاركة بريد وهاتف المتقدم مع TAQAT، وبأي صيغة موافقة؟
10. هل تدعم البنية الحالية webhooks صادرة؟ ما مدة حفظ سجل الأحداث وإمكانية إعادة الإرسال؟
11. ما حدود الاستخدام الفعلية لحساب التكامل؟
12. ماذا يفعل `PUT /api/v1/jobs/{job_id}/completed-job` بالـ proposals والعقود المفتوحة؟ هل هو نفسه E3 `reason=filled`؟
13. هل مزوّد «Taqat SSO» (`client_id=brightgaza`) تديره TAQAT؟ وهل نحتاج ربط موظفي TAQAT بمستخدمي BrightGaza لإظهار «من نفّذ» عند التصرف اليدوي داخل المنصة؟

### لإدارة TAQAT

14. وظائف TAQAT توظيف براتب شهري، وBrightGaza عمل حر بالساعة أو بمبلغ ثابت: أي الوظائف تُنشر هناك، وما قاعدة التحويل؟
15. من يدفع داخل BrightGaza (محفظة TAQAT أم العميل)، ومن يعتمد الدفع؟ هل نحتاج صلاحية مالية منفصلة؟
16. هل يظهر اسم العميل على الإعلان العام أم يُنشر باسم TAQAT؟
17. هل تُستبعد وظائف `onsite` و`hybrid` من BrightGaza؟
18. ما مدة الاحتفاظ ببيانات غير المعيَّنين (المقترح 24 شهراً) وما نص الموافقة؟
19. هل يتحول المستقل المعيَّن إلى موظف في موديول `Employees` أم يبقى متعاقداً داخل BrightGaza فقط؟
20. هل يُسجِّل مدير الحساب قرار العميل داخل TAQAT، أم نحتاج بوابة للعميل؟

---

**التالي:** مراجعة §4 مع فريق BrightGaza وتثبيت إجابات الأسئلة 1–6 و14–16 قبل بدء Phase A.
