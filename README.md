# TAQAT — منصة العمل الرقمية

> نظام متكامل لإدارة الحضور والإجازات والمهام والطلبات لموظفي شركة TAQAT.

**Stack:** Laravel 11 API + Next.js 15 (App Router) + React Native (Expo) + Docker + MySQL 8 + Redis + MinIO + Reverb (WebSocket) + Meilisearch  
**اللغة الرئيسية:** العربية (RTL بشكل كامل)  
**الحالة:** Production-ready • Phase 1 مكتمل 100% • Phase 2/3/4 مزايا مسبقة

---

## المحتويات

- [الميزات](#الميزات)
- [البنية المعمارية](#البنية-المعمارية)
- [التقدم على الـ Phases](#التقدم-على-الـ-phases)
- [الإعداد المحلي](#الإعداد-المحلي)
- [النشر (Deployment)](#النشر-deployment)
- [متغيرات البيئة](#متغيرات-البيئة)
- [الاختبارات](#الاختبارات)
- [الوصول والصلاحيات](#الوصول-والصلاحيات)
- [البنية داخل الريبو](#البنية-داخل-الريبو)

---

## الميزات

### 🔐 المصادقة والصلاحيات
- تسجيل دخول بالبريد الإلكتروني **أو** الرقم الوظيفي (auto-detect)
- Sanctum Personal Access Tokens (Bearer) — يعمل مع الـ SPA والموبايل
- RBAC حقيقي (spatie/laravel-permission) على `/admin/*`
- 5 أدوار: `super-admin` / `management` / `department-manager` / `team-leader` / `employee`

### 👥 الموظفون والهيكل التنظيمي
- CRUD كامل للموظفين مع رفع صور شخصية (MinIO S3-compatible)
- ترقيم متسلسل للموظفين (race-safe عبر lockForUpdate)
- الأقسام، الفرق، المسميات الوظيفية، جداول العمل، العطل الرسمية

### 📱 الحضور
- QR check-in/check-out عبر أجهزة الـ scan
- Working Hours Engine (يحسب الحضور، التأخير، المغادرة المبكرة، الوقت الإضافي)
- ملخص شهري للحضور
- سجل حضور مفصل قابل للبحث والفلترة

### 🌴 الإجازات
- أنواع إجازات متعددة (مدفوعة/غير مدفوعة، بحد أقصى/بدون، تحتاج مرفق/لا)
- أرصدة سنوية + تتبع pending/used/entitled
- Workflow موافقة متعدد الخطوات
- Self-service للموظف: طلب/إلغاء إجازة

### 📝 مسارات العمل والطلبات العامة
- Workflow Engine قابل للتوسع (أنواع طلبات مخصصة)
- Approvers متعددون (Department Manager, Team Leader, Custom)
- Approve / Reject / Return / Forward
- Approval Inbox للمديرين

### 📌 المهام
- Tasks مع Subtasks + Priorities + Statuses + Tags قابلة للتخصيص
- Comments + File Attachments (signed URLs)
- Kanban board (drag & drop)
- تعيين تلقائي بناءً على المسؤول

### 🔔 الإشعارات (Multi-channel)
- **قاعدة البيانات** (durable inbox + bell counter)
- **Reverb WebSocket** (realtime toast — dedup لتجنب flood)
- **البريد الإلكتروني** عبر Resend
- **SMS** عبر MTC Jordan
- **WhatsApp** عبر Meta Cloud API
- كل قناة اختيارية per notification (`sendSms`, `sendWhatsapp`)

### 🤖 AI Motivation (Anthropic Claude)
- رسالة تحفيزية يومية personalized لكل موظف
- سياق مبني على: attendance streak, task completion, upcoming leave
- Fallback library في حال فشل الـ API — dashboard لا 500
- Cached 24h/user في Redis
- Scheduled warmup daily at 07:00 (Asia/Amman)

### 📊 التقارير والـ Analytics
- Admin Dashboard مع KPIs حية (refetch كل 60s):
  - إجمالي الموظفين / حاضرين اليوم / بانتظار الموافقة / مهام مفتوحة
- Employee Home مع KPIs شخصية
- تقارير الحضور الشهرية بصيغ **CSV / Excel / PDF**
- تصدير Excel: RTL sheet + brand header + frozen pane
- تصدير PDF: Arabic font (Amiri) + brand-blue table
- سجل النشاط (Audit Log) عبر spatie/laravel-activitylog

### 🔍 البحث العام (Meilisearch)
- Cmd/Ctrl+K palette
- بحث فوري cross-index: الموظفون، المهام، الطلبات، الإجازات
- Typo-tolerant + Arabic tokenization
- Debounced (300ms)

### ⚙️ إعدادات النظام
- Admin Settings page — تعديل runtime بدون redeploy
- Mail (Resend key, from address, from name)
- SMS (MTC username, password, sender, endpoint, fake mode)
- WhatsApp (access token, phone number ID, business ID)
- AI (Anthropic API key, Claude model)
- Sensitive values **مشفّرة** في DB (Crypt::encryptString)
- Cache-first reads (1h TTL)

### 📱 التطبيقات
- **Web Admin Panel** (Next.js 15) — RTL بالكامل، بـ shadcn/ui، طوابع لوني TAQAT
- **Mobile App** (React Native + Expo) — Login, Tasks, Leaves, QR Scanner, Push notifications
- **PWA** — Serwist service worker + offline shell للـ /scan + install prompt

---

## البنية المعمارية

### Backend (Laravel 11 — Modular Monolith)
```
apps/api/
├── app/Modules/
│   ├── AI/                 ← Claude client + Motivation service
│   ├── Auth/               ← Login (email/employee_number) + Sanctum
│   ├── Attendance/         ← QR devices + scan flow + WHM calculator
│   ├── Employees/          ← Employee CRUD + org tree
│   ├── Leaves/             ← Types + Balances + Requests + Workflow
│   ├── Notifications/      ← TaqatNotification + dedup dispatch
│   ├── Organization/       ← Departments + Teams + Positions
│   ├── Reports/            ← Admin/Employee dashboards + CSV/Excel/PDF exports + Audit log
│   ├── Requests/           ← Generic Request submissions + Approvals
│   ├── Search/             ← Meilisearch cross-index search
│   ├── Settings/           ← DB-backed key/value with encryption
│   ├── Sms/                ← MTC gateway + queued jobs
│   ├── Tasks/              ← Tasks + Comments + Attachments + Kanban
│   ├── Whatsapp/           ← Meta Cloud API gateway
│   └── Workflow/           ← Workflow steps + approver resolver
├── routes/api.php          ← Single source of truth for routes
├── routes/channels.php     ← Broadcast auth (Reverb)
└── config/                 ← broadcasting, reverb, mail, services, scout, ...
```

### Frontend (Next.js 15 App Router)
```
apps/web/src/
├── app/
│   ├── (auth)/login        ← Public login
│   ├── (admin)/            ← Admin routes with AdminSidebar (RBAC-filtered)
│   │   ├── dashboard, employees, organization/*, attendance, leaves,
│   │   ├── requests, approvals, workflows, request-types,
│   │   ├── tasks, tasks-config/*, reports/attendance, audit,
│   │   ├── notifications, settings
│   ├── (employee)/         ← Employee routes with slim sidebar
│   │   └── home, my-tasks, my-leaves, my-requests
│   ├── (public)/scan       ← QR check-in (offline-capable via SW)
│   ├── offline             ← PWA offline fallback
│   ├── sw.ts               ← Serwist service worker
│   └── manifest.ts         ← PWA manifest
├── components/
│   ├── layout/             ← AdminSidebar, EmployeeSidebar, headers
│   ├── notifications/      ← Bell with Reverb + polling
│   ├── search/             ← Global search (Cmd+K palette)
│   ├── pwa/                ← Install prompt
│   ├── ui/                 ← shadcn primitives (TAQAT-branded)
│   └── {feature}/          ← Feature-specific components
├── lib/
│   ├── api/                ← Axios client + typed endpoints
│   ├── stores/             ← Zustand (auth-store)
│   └── echo.ts             ← Laravel Echo + Reverb setup
```

### Mobile (React Native + Expo)
```
apps/mobile/
├── app/
│   ├── (auth)/login        ← Login screen
│   ├── (tabs)/             ← Home / Tasks / Leaves / Profile
│   └── scan                ← QR scanner modal
├── lib/                    ← api, auth-store, theme, queryClient
├── components/             ← Button, Card (TAQAT-branded)
└── hooks/                  ← useAuth
```

### Infrastructure (Docker Compose)
```
docker-compose.simple.yml
├── db (MySQL 8, healthchecked)
├── redis (7-alpine, healthchecked)
├── minio (S3-compatible)
├── meilisearch (v1.10, private)
├── api (php-fpm, custom-built)
├── nginx (fronts php-fpm)
├── web (Next.js standalone)
├── queue (php artisan queue:work)
├── scheduler (php artisan schedule:work)
└── reverb (php artisan reverb:start)
```

---

## التقدم على الـ Phases

| Phase | المحدد الأصلي | الحالة | ملاحظات |
|-------|--------------|--------|---------|
| **Phase 1** (MVP — M1-M8) | 12 أسبوع | ✅ **100%** | كل الميزات + Realtime + PWA |
| **Phase 2** (Projects + Scrum + Polish) | 8 أسابيع | ✅ **~85%** | Excel/PDF ✅ · WhatsApp ✅ · Meilisearch ✅ · Kanban ✅ · Mail ✅ — Projects/Sprints فقط ناقصان |
| **Phase 3** (AI + Executive) | 6 أسابيع | ✅ **~35%** | AI Motivation ✅ · Manager Assistant + Executive Dashboard ⏸ |
| **Phase 4** (Scale + Mobile) | 4+ أسابيع | ✅ **~40%** | Mobile RN scaffold ✅ · 2FA/SSO/SAML ⏸ |

**سبقنا الجدول الأصلي بـ ~3-4 أشهر عمل.**

---

## الإعداد المحلي

### المتطلبات
- Docker Desktop 4.30+
- Node.js 20+ (للـ mobile app)
- Git

### التشغيل السريع
```bash
git clone https://github.com/ahmaddev27/Attendance.git taqat
cd taqat

# نسخ متغيرات البيئة
cp .env.example .env
# → افتح .env وضع قيم حقيقية (see "متغيرات البيئة" أدناه)

# رفع الحاويات
docker compose -f docker-compose.simple.yml up -d --build

# تشغيل الـ migrations + seeder الأدمن
docker compose -f docker-compose.simple.yml exec -T api php artisan migrate --seed --force

# افتح المتصفح
open http://localhost:8181
# Admin: admin@taqat.local / password
```

### الموبايل
```bash
cd apps/mobile
cp .env.example .env
# EXPO_PUBLIC_API_URL=http://192.168.x.x  ← IP الجهاز في نفس شبكة الوايفاي
npm install
npx expo start
# سكن QR على Expo Go
```

---

## النشر (Deployment)

### Auto-deploy (GitHub Actions → VPS)
كل push على `main` بيشغّل:
1. **CI** (api-tests + web-build)
2. **Build & Push Images** (GHCR)
3. **Deploy to VPS** (SSH → git pull → composer sync → docker build → up -d --force-recreate → migrate --force → cache config)

الـ deploy job:
- ✅ لا يلمس `.env` أبداً (backup تلقائي قبل git reset)
- ✅ يعيد بناء api + web فقط (db/redis/minio تبقى)
- ✅ يعيد resolve DNS للـ nginx بعد تجديد api container
- ✅ Health check post-deploy — fail loudly إذا أي container مش running

**الأسرار المطلوبة** في GitHub Settings → Secrets:
- `VPS_HOST`, `VPS_USER`, `VPS_SSH_KEY`, `VPS_SSH_PORT`, `VPS_APP_PATH`

راجع `docs/deployment/vps-deploy.md` للتفاصيل الكاملة.

### Apache Reverse Proxy (cPanel)
- `infra/apache/proxy.conf` — انسخه لـ `/etc/apache2/conf.d/userdata/{std,ssl}/2_4/<user>/<domain>/proxy.conf`
- يفوّض: `/api → nginx:8180` · `/app,/apps → reverb:8182` (WebSocket) · `/` → next.js:8181
- Force HTTPS redirect + Let's Encrypt AutoSSL

---

## متغيرات البيئة

### Application
```
APP_URL=https://attendees.taqatgaza.com
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...
```

### Database (MySQL — Docker internal)
```
DB_HOST=db
DB_DATABASE=taqat
DB_USERNAME=taqat
DB_PASSWORD=...
DB_ROOT_PASSWORD=...
```

### Redis
```
REDIS_HOST=redis
REDIS_PASSWORD=...
REDIS_CLIENT=predis
```

### Reverb (Realtime)
```
# Internal (Laravel → Reverb via Docker network)
REVERB_HOST=reverb
REVERB_PORT=8182               ← host port binding
REVERB_SERVER_PORT=8080        ← inside container
REVERB_SCHEME=http
REVERB_APP_ID=taqat
REVERB_APP_KEY=<random>
REVERB_APP_SECRET=<random>
BROADCAST_CONNECTION=reverb

# Frontend (browser → Reverb via Apache)
NEXT_PUBLIC_REVERB_APP_KEY=<same as above>
NEXT_PUBLIC_REVERB_HOST=attendees.taqatgaza.com
NEXT_PUBLIC_REVERB_PORT=443
NEXT_PUBLIC_REVERB_SCHEME=https
```

### MinIO (S3 storage)
```
MINIO_ROOT_USER=taqat_minio
MINIO_ROOT_PASSWORD=...
AWS_BUCKET=taqat-media
AWS_ENDPOINT=http://minio:9000
```

### Mail (Resend) — أو من admin UI
```
MAIL_MAILER=resend
MAIL_FROM_ADDRESS=noreply@taqatgaza.com
MAIL_FROM_NAME=TAQAT
RESEND_KEY=re_...
```

### MTC SMS — أو من admin UI
```
MTC_SMS_USERNAME=...
MTC_SMS_PASSWORD=...
MTC_SMS_SENDER=TAQAT
MTC_SMS_ENDPOINT=http://int.mtcsms.com/sendsms.aspx
MTC_SMS_FAKE=false
```

### WhatsApp (Meta Cloud API) — أو من admin UI
```
WHATSAPP_ACCESS_TOKEN=...
WHATSAPP_PHONE_NUMBER_ID=...
WHATSAPP_BUSINESS_ACCOUNT_ID=...
WHATSAPP_FAKE=false
```

### AI (Anthropic Claude) — أو من admin UI
```
ANTHROPIC_API_KEY=sk-ant-...
```

### Search (Meilisearch)
```
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=<random master key>
```

**💡 نصيحة:** الـ Mail + SMS + WhatsApp + AI credentials تقدر تحطها من الأدمن UI (`/settings`) بعد الـ deploy الأول، ما تحتاج تعدل `.env` كل مرة.

---

## الاختبارات

### Backend (Pest)
```bash
docker compose exec -T api php artisan test
```
تغطية:
- Auth (login by email + employee_number, token issue, logout)
- Employees + Organization CRUD
- Attendance (scan flow, fraud guard, monthly summary, race conditions)
- Leaves (submit, approve, reject, cancel, balance mgmt)
- Workflow + Approvals
- Tasks + Comments + Attachments (signed URLs)
- Admin Dashboard KPIs (with RBAC gate)
- Employee Dashboard KPIs
- Notifications (mail suppress, SMS opt-in, WhatsApp, dedup)
- AI Motivation (cache, fallback, endpoint)
- Reports (CSV/Excel/PDF export)
- Search (Scout collection driver)

### Frontend (TypeScript strict)
```bash
cd apps/web && npx tsc --noEmit
```

### CI (على GitHub Actions لكل push)
- api-tests (PHP 8.4 + MySQL + Redis)
- web-build (Node 20 + Next.js build)
- deploy (بعد نجاح CI)

---

## الوصول والصلاحيات

### Default Admin
- **Email:** `admin@taqat.local`
- **Password:** `password`
- **Employee Number:** `1000`
- **Role:** `super-admin` (كل الـ permissions)

### الأدوار والصلاحيات
| Role | manage-users | manage-departments | view-all-attendance | approve-leaves | create-tasks | manage-workflows | view-reports | view-audit-logs |
|------|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| super-admin | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| management | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | — |
| department-manager | — | — | جزئي | ✅ | ✅ | — | — | — |
| team-leader | — | — | جزئي | جزئي | ✅ | — | — | — |
| employee | — | — | ذاته | — | — | — | — | — |

Sidebar يفلتر حسب صلاحيات المستخدم runtime.

---

## البنية داخل الريبو

```
taqat/
├── .github/workflows/         ← CI + Deploy
├── apps/
│   ├── api/                   ← Laravel 11 backend
│   ├── web/                   ← Next.js 15 admin/employee
│   └── mobile/                ← React Native + Expo
├── docs/
│   ├── v2/                    ← Architecture + ERD + Phase plans
│   └── deployment/            ← VPS deploy guide
├── infra/
│   ├── apache/proxy.conf      ← Reverse proxy config
│   ├── docker/api/Dockerfile  ← PHP 8.4 image
│   ├── docker/web/Dockerfile  ← Next.js standalone
│   └── docker/nginx/          ← Nginx configs
├── docker-compose.simple.yml  ← VPS production stack
├── docker-compose.yml         ← Local dev stack
└── .env.example               ← Environment template
```

---

## ما اللي لسا مش مبني

المزايا التالية من الـ SRS الموسّع مؤجلة (Phase 2/3/4):

- **Projects + Sprints + Scrum ceremonies** (Kanban موجود بس بلا Projects)
- **Manager AI Assistant** (Motivation فقط مبنية)
- **Executive Dashboard** (KPIs للـ C-level)
- **Performance Reviews + KPI system**
- **2FA / SSO / SAML**
- **Multi-company (SaaS)**
- **Global search — row-level scoping** (كل مستخدم يشوف كل شي حالياً)
- **WhatsApp templates** للـ cold outreach خارج 24h session
- **Firebase Push notifications** على الموبايل (جاهز للـ wire-up)
- **App Store submission** للموبايل

---

## المرجعية

- **Backend API base:** `https://attendees.taqatgaza.com/api`
- **Web admin:** `https://attendees.taqatgaza.com`
- **Repository:** https://github.com/ahmaddev27/Attendance
- **الوثائق الداخلية:** `docs/v2/` (Architecture + ERD + Phase 1 Plan)

---

## License

Proprietary — TAQAT © 2026
