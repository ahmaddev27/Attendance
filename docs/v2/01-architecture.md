# TAQAT v2 — Architecture

> **الوثيقة رقم 2 من حزمة تخطيط v2**
> النطاق: البنية عالية المستوى، Docker services، Monorepo layout، Laravel modular structure، Next.js structure، Auth، CORS، Real-time، Storage، Queues، Env، Deployment.
> الوثائق المرافقة: [`00-overview.md`](00-overview.md) · [`02-erd.md`](02-erd.md) · [`03-phase-1-plan.md`](03-phase-1-plan.md)

---

## 1. High-Level Architecture

```
                                  ┌────────────────────────────┐
                                  │   Users' Browsers / PWA    │
                                  │   (Chrome / Safari / …)    │
                                  └──────────────┬─────────────┘
                                                 │  HTTPS 443
                                                 ▼
                          ┌────────────────────────────────────────┐
                          │           Nginx (reverse proxy)         │
                          │   • TLS termination (Let's Encrypt)     │
                          │   • Static asset caching                 │
                          │   • Route splitting:                     │
                          │       taqat.example.com          → web   │
                          │       taqat.example.com/api/*    → api   │
                          │       taqat.example.com/ws       → reverb│
                          │       minio.example.com          → minio │
                          └───┬────────────┬──────────────┬─────────┘
                              │            │              │
              ┌───────────────┴─────┐  ┌───┴──────┐  ┌────┴────────┐
              │  web (Next.js 15)   │  │ api      │  │ reverb      │
              │   Node 20, port 3000│  │ Laravel  │  │ WebSockets  │
              │   SSR + CSR         │  │ PHP-FPM  │  │ port 8080   │
              │                     │  │ port 9000│  │             │
              └─────────────────────┘  └─────┬────┘  └──────┬──────┘
                                             │              │
                                             ▼              │
                                       ┌──────────┐         │
                                       │  queue   │         │
                                       │ (worker) │         │
                                       └────┬─────┘         │
                                            │               │
                                       ┌────┴──────────┐    │
                                       │ scheduler     │    │
                                       └────┬──────────┘    │
                                            │               │
                            ┌───────────────┼──────┬────────┼──────────┬─────────────┐
                            ▼               ▼      ▼        ▼          ▼             ▼
                     ┌───────────┐   ┌───────────┐  ┌────────────┐  ┌─────────┐  ┌──────────┐
                     │  MySQL 8  │   │  Redis 7  │  │  MinIO     │  │ MTC SMS │  │ Anthropic │
                     │  (db)     │   │  (cache + │  │ (S3-compat.│  │ (HTTP)  │  │ Claude    │
                     │  port 3306│   │  queues + │  │  buckets)  │  │ external│  │ (HTTP)    │
                     │           │   │  sessions)│  │  port 9000 │  │         │  │ external  │
                     └───────────┘   └───────────┘  └────────────┘  └─────────┘  └──────────┘
```

**تدفّق طلب مثالي (Employee opens /tasks):**
1. المتصفح ← `GET /tasks` ← Nginx يوجّه إلى `web` container (Next.js SSR).
2. Next.js يقرأ Sanctum httpOnly cookie، يستدعي `GET /api/v1/tasks?assignee_id=me` عبر شبكة Docker الداخلية.
3. Nginx يستقبل الطلب ← يوجّه إلى `api` container.
4. Laravel يتحقّق من token، يستدعي `TaskService::listForEmployee(...)` ← Repository ← MySQL.
5. Response JSON → Next.js يعيد HTML مُحمَّل.
6. عندما تُنشأ مهمّة جديدة، `TaskCreated` event يذهب إلى `queue` → يبثّ عبر `reverb` → المتصفح يستلم event → يحدّث الـ Kanban.

---

## 2. Docker Compose Services

### 2.1 قائمة الخدمات (9 حاويات)

| # | الاسم         | الصورة                          | الغرض                                       | Ports    | Depends_on               |
| - | ------------- | ------------------------------- | ------------------------------------------- | -------- | ------------------------ |
| 1 | `nginx`       | `nginx:1.27-alpine`             | Reverse proxy + TLS                          | 80,443   | api, web, reverb, minio  |
| 2 | `api`         | `ghcr.io/<org>/taqat-api:latest` | Laravel 11 (PHP 8.3 FPM)                     | internal | db, redis, minio         |
| 3 | `web`         | `ghcr.io/<org>/taqat-web:latest` | Next.js 15 (Node 20)                         | internal | api                      |
| 4 | `db`          | `mysql:8.0`                     | MySQL 8                                     | 3306*    | —                        |
| 5 | `redis`       | `redis:7-alpine`                | Cache + queues + broadcasting + sessions    | 6379*    | —                        |
| 6 | `queue`       | نفس صورة `api`                  | `php artisan queue:work` (3 queues)          | internal | db, redis                |
| 7 | `scheduler`   | نفس صورة `api`                  | `php artisan schedule:work`                  | internal | db, redis                |
| 8 | `reverb`      | نفس صورة `api`                  | `php artisan reverb:start`                   | 8080*    | db, redis                |
| 9 | `minio`       | `minio/minio:latest`            | S3-compatible storage                       | 9000,9001| —                        |

`*` = مكشوف على loopback فقط (`127.0.0.1:...`)، لا يُفتح على الإنترنت.

**Phase 2 (اختياري):**
- `horizon` — dashboard للطوابير
- `meilisearch` — بحث شامل
- `loki + grafana` — logs + metrics

### 2.2 عيّنة `docker-compose.yml` (production)

```yaml
version: '3.9'

networks:
  taqat_net:
    driver: bridge

volumes:
  db_data:
  redis_data:
  minio_data:
  api_storage:
  letsencrypt:

x-api-env: &api-env
  APP_ENV: production
  APP_KEY: ${APP_KEY}
  APP_URL: https://${DOMAIN}
  DB_HOST: db
  DB_DATABASE: ${DB_DATABASE}
  DB_USERNAME: ${DB_USERNAME}
  DB_PASSWORD: ${DB_PASSWORD}
  REDIS_HOST: redis
  QUEUE_CONNECTION: redis
  CACHE_DRIVER: redis
  SESSION_DRIVER: redis
  BROADCAST_DRIVER: reverb
  REVERB_APP_ID: ${REVERB_APP_ID}
  REVERB_APP_KEY: ${REVERB_APP_KEY}
  REVERB_APP_SECRET: ${REVERB_APP_SECRET}
  REVERB_HOST: reverb
  REVERB_PORT: 8080
  FILESYSTEM_DISK: s3
  AWS_ACCESS_KEY_ID: ${MINIO_ROOT_USER}
  AWS_SECRET_ACCESS_KEY: ${MINIO_ROOT_PASSWORD}
  AWS_DEFAULT_REGION: us-east-1
  AWS_BUCKET: attachments
  AWS_ENDPOINT: http://minio:9000
  AWS_USE_PATH_STYLE_ENDPOINT: 'true'
  ANTHROPIC_API_KEY: ${ANTHROPIC_API_KEY}
  MTC_SMS_USERNAME: ${MTC_SMS_USERNAME}
  MTC_SMS_PASSWORD: ${MTC_SMS_PASSWORD}
  MTC_SMS_SENDER: ${MTC_SMS_SENDER}

services:
  nginx:
    image: nginx:1.27-alpine
    restart: unless-stopped
    ports: ["80:80", "443:443"]
    volumes:
      - ./infra/docker/nginx/conf.d:/etc/nginx/conf.d:ro
      - letsencrypt:/etc/letsencrypt
    depends_on: [api, web, reverb, minio]
    networks: [taqat_net]

  api:
    image: ghcr.io/${GH_ORG}/taqat-api:${IMAGE_TAG:-latest}
    restart: unless-stopped
    environment: *api-env
    volumes:
      - api_storage:/var/www/html/storage
    depends_on:
      db: {condition: service_healthy}
      redis: {condition: service_healthy}
      minio: {condition: service_healthy}
    networks: [taqat_net]
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/api/health"]
      interval: 30s
      timeout: 5s
      retries: 3

  web:
    image: ghcr.io/${GH_ORG}/taqat-web:${IMAGE_TAG:-latest}
    restart: unless-stopped
    environment:
      NODE_ENV: production
      NEXT_PUBLIC_API_URL: https://${DOMAIN}
      NEXT_PUBLIC_REVERB_HOST: ${DOMAIN}
      NEXT_PUBLIC_REVERB_PORT: 443
      NEXT_PUBLIC_REVERB_KEY: ${REVERB_APP_KEY}
      INTERNAL_API_URL: http://api
    depends_on: [api]
    networks: [taqat_net]

  db:
    image: mysql:8.0
    restart: unless-stopped
    command: --default-authentication-plugin=caching_sha2_password
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
      MYSQL_DATABASE: ${DB_DATABASE}
      MYSQL_USER: ${DB_USERNAME}
      MYSQL_PASSWORD: ${DB_PASSWORD}
    volumes:
      - db_data:/var/lib/mysql
    ports: ["127.0.0.1:3306:3306"]
    networks: [taqat_net]
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 10s
      timeout: 5s
      retries: 10

  redis:
    image: redis:7-alpine
    restart: unless-stopped
    command: redis-server --appendonly yes --requirepass ${REDIS_PASSWORD}
    volumes:
      - redis_data:/data
    ports: ["127.0.0.1:6379:6379"]
    networks: [taqat_net]
    healthcheck:
      test: ["CMD", "redis-cli", "-a", "${REDIS_PASSWORD}", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5

  queue:
    image: ghcr.io/${GH_ORG}/taqat-api:${IMAGE_TAG:-latest}
    restart: unless-stopped
    command: >
      php artisan queue:work redis
        --queue=notifications,ai,reports,default
        --tries=3
        --backoff=5,30,120
        --max-time=3600
    environment: *api-env
    volumes:
      - api_storage:/var/www/html/storage
    depends_on:
      db: {condition: service_healthy}
      redis: {condition: service_healthy}
    networks: [taqat_net]

  scheduler:
    image: ghcr.io/${GH_ORG}/taqat-api:${IMAGE_TAG:-latest}
    restart: unless-stopped
    command: php artisan schedule:work
    environment: *api-env
    volumes:
      - api_storage:/var/www/html/storage
    depends_on:
      db: {condition: service_healthy}
      redis: {condition: service_healthy}
    networks: [taqat_net]

  reverb:
    image: ghcr.io/${GH_ORG}/taqat-api:${IMAGE_TAG:-latest}
    restart: unless-stopped
    command: php artisan reverb:start --host=0.0.0.0 --port=8080
    environment: *api-env
    ports: ["127.0.0.1:8080:8080"]
    depends_on:
      redis: {condition: service_healthy}
    networks: [taqat_net]

  minio:
    image: minio/minio:latest
    restart: unless-stopped
    command: server /data --console-address ":9001"
    environment:
      MINIO_ROOT_USER: ${MINIO_ROOT_USER}
      MINIO_ROOT_PASSWORD: ${MINIO_ROOT_PASSWORD}
    volumes:
      - minio_data:/data
    ports:
      - "127.0.0.1:9000:9000"
      - "127.0.0.1:9001:9001"
    networks: [taqat_net]
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:9000/minio/health/live"]
      interval: 30s
      timeout: 5s
      retries: 3
```

### 2.3 Nginx routing (`infra/docker/nginx/conf.d/taqat.conf`)

```nginx
upstream api_upstream { server api:80; }
upstream web_upstream { server web:3000; }
upstream reverb_upstream { server reverb:8080; }

server {
    listen 80;
    server_name taqat.example.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name taqat.example.com;

    ssl_certificate     /etc/letsencrypt/live/taqat.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/taqat.example.com/privkey.pem;

    client_max_body_size 25M;

    # API
    location /api/ {
        proxy_pass http://api_upstream;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto https;
    }

    # WebSockets
    location /ws {
        proxy_pass http://reverb_upstream;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host $host;
        proxy_read_timeout 3600s;
    }

    # Next.js
    location / {
        proxy_pass http://web_upstream;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-Proto https;
    }
}
```

---

## 3. Monorepo Layout

```
taqat/
├── apps/
│   ├── api/                       ← Laravel 11
│   │   ├── app/
│   │   ├── bootstrap/
│   │   ├── config/
│   │   ├── database/
│   │   ├── public/
│   │   ├── routes/
│   │   ├── storage/
│   │   ├── tests/
│   │   ├── composer.json
│   │   └── .env.example
│   └── web/                       ← Next.js 15
│       ├── src/
│       ├── public/
│       ├── package.json
│       ├── next.config.mjs
│       ├── tailwind.config.ts
│       └── .env.example
├── infra/
│   ├── docker/
│   │   ├── api/
│   │   │   ├── Dockerfile
│   │   │   ├── php.ini
│   │   │   └── entrypoint.sh
│   │   ├── web/
│   │   │   ├── Dockerfile
│   │   │   └── entrypoint.sh
│   │   └── nginx/
│   │       ├── conf.d/taqat.conf
│   │       └── nginx.conf
│   ├── docker-compose.yml          ← production
│   ├── docker-compose.dev.yml      ← local development (mounts source, uses xdebug)
│   └── scripts/
│       ├── backup.sh
│       ├── restore.sh
│       └── first-boot.sh
├── docs/
│   ├── v2/
│   │   ├── 00-overview.md
│   │   ├── 01-architecture.md      ← anta huna
│   │   ├── 02-erd.md
│   │   └── 03-phase-1-plan.md
│   └── runbooks/
├── .github/
│   └── workflows/
│       ├── ci.yml                  ← lint + tests
│       ├── build-and-push.yml      ← builds images on push to main
│       └── deploy.yml              ← ssh deploy
├── .gitignore
├── README.md
└── LICENSE
```

**قواعد الـ monorepo:**
- كل `apps/*` قابل للتشغيل مستقلاً (لأغراض التطوير).
- الـ Docker builds تحدث من الجذر (`context: .`) حتى الـ Dockerfile يرى `apps/api` و `apps/web`.
- `.github/workflows` تُشغَّل تلقائياً عند push إلى `main` أو `develop`.

---

## 4. Laravel App Structure (Modular Monolith)

### 4.1 المخطط الكامل

```
apps/api/app/
├── Http/
│   ├── Kernel.php
│   ├── Middleware/
│   │   ├── EnsureEmployeeIsActive.php
│   │   ├── SetLocale.php
│   │   └── LogSensitiveActions.php
│   └── Controllers/
│       └── Api/
│           └── HealthController.php      ← /api/health
│
├── Modules/
│   ├── Auth/
│   │   ├── Contracts/
│   │   │   └── AuthenticatesEmployees.php
│   │   ├── Controllers/
│   │   │   ├── LoginController.php
│   │   │   ├── LogoutController.php
│   │   │   ├── MeController.php
│   │   │   └── PasswordResetController.php
│   │   ├── Services/
│   │   │   ├── LoginService.php
│   │   │   └── PasswordResetService.php
│   │   ├── Requests/
│   │   │   └── LoginRequest.php
│   │   ├── Resources/
│   │   │   └── AuthenticatedEmployeeResource.php
│   │   ├── Events/
│   │   │   └── EmployeeLoggedIn.php
│   │   └── routes.php
│   │
│   ├── Employees/
│   │   ├── Models/
│   │   │   └── Employee.php
│   │   ├── Controllers/
│   │   │   ├── EmployeeController.php
│   │   │   └── EmployeeProfileController.php
│   │   ├── Services/
│   │   │   ├── EmployeeService.php
│   │   │   └── EmployeeOnboardingService.php
│   │   ├── Repositories/
│   │   │   └── EmployeeRepository.php
│   │   ├── Requests/
│   │   │   ├── StoreEmployeeRequest.php
│   │   │   └── UpdateEmployeeRequest.php
│   │   ├── Resources/
│   │   │   ├── EmployeeResource.php
│   │   │   └── EmployeeListResource.php
│   │   ├── Policies/
│   │   │   └── EmployeePolicy.php
│   │   └── routes.php
│   │
│   ├── Organization/
│   │   ├── Models/
│   │   │   ├── Company.php
│   │   │   ├── Department.php
│   │   │   ├── Team.php
│   │   │   └── Position.php
│   │   ├── Controllers/…
│   │   ├── Services/
│   │   │   └── OrgTreeService.php
│   │   ├── Repositories/
│   │   └── routes.php
│   │
│   ├── Attendance/
│   │   ├── Models/
│   │   │   ├── Attendance.php
│   │   │   ├── AttendanceDevice.php
│   │   │   ├── WorkSchedule.php
│   │   │   └── Holiday.php
│   │   ├── Controllers/
│   │   │   ├── QrScanController.php
│   │   │   ├── AttendanceController.php
│   │   │   └── DeviceController.php
│   │   ├── Services/
│   │   │   ├── QrTokenService.php          ← دوران التوكن
│   │   │   ├── GeofenceValidator.php
│   │   │   ├── AttendanceService.php
│   │   │   └── WorkingHoursEngine.php      ← الاحتساب الليلي
│   │   ├── Jobs/
│   │   │   ├── RecomputeAttendanceForDate.php
│   │   │   └── RotateQrTokens.php
│   │   ├── Repositories/
│   │   └── routes.php
│   │
│   ├── Leaves/
│   │   ├── Models/
│   │   │   ├── LeaveType.php
│   │   │   ├── LeaveBalance.php
│   │   │   └── LeaveRequest.php
│   │   ├── Controllers/…
│   │   ├── Services/
│   │   │   ├── LeaveRequestService.php
│   │   │   └── LeaveBalanceService.php
│   │   ├── Jobs/
│   │   │   └── AnnualBalanceRollover.php
│   │   ├── Repositories/
│   │   └── routes.php
│   │
│   ├── Workflow/                     ← محرّك عام
│   │   ├── Models/
│   │   │   ├── Workflow.php
│   │   │   └── WorkflowStep.php
│   │   ├── Services/
│   │   │   ├── WorkflowEngine.php        ← يدير التنقّل بين الخطوات
│   │   │   ├── ApproverResolver.php      ← يحلّ approver_type إلى employee_id
│   │   │   └── SlaMonitor.php
│   │   ├── Contracts/
│   │   │   └── WorkflowSubject.php        ← الطلبات/الإجازات تُنفّذ هذا
│   │   ├── Jobs/
│   │   │   └── EscalateStaleApprovals.php
│   │   └── routes.php
│   │
│   ├── Requests/                     ← Request Builder + Types + Instances
│   │   ├── Models/
│   │   │   ├── RequestType.php
│   │   │   ├── EmployeeRequest.php       ← الاسم Request محجوز في Laravel
│   │   │   └── RequestApproval.php
│   │   ├── Controllers/…
│   │   ├── Services/
│   │   │   ├── RequestFormValidator.php  ← يقرأ form_schema
│   │   │   └── RequestService.php
│   │   └── routes.php
│   │
│   ├── Tasks/
│   │   ├── Models/
│   │   │   ├── Task.php
│   │   │   ├── TaskComment.php
│   │   │   ├── TaskHistory.php
│   │   │   ├── TaskStatus.php
│   │   │   ├── TaskPriority.php
│   │   │   └── TaskTag.php
│   │   ├── Controllers/…
│   │   ├── Services/
│   │   │   ├── TaskService.php
│   │   │   ├── TaskCommentService.php    ← يعالج @mentions
│   │   │   └── TaskHistoryRecorder.php   ← observer wrapper
│   │   ├── Repositories/
│   │   └── routes.php
│   │
│   ├── Projects/                     ← Phase 2
│   │   ├── Models/
│   │   │   ├── Project.php
│   │   │   └── ProjectMember.php
│   │   └── …
│   │
│   ├── Sprints/                      ← Phase 2
│   │   ├── Models/
│   │   │   └── Sprint.php
│   │   └── …
│   │
│   ├── Notifications/
│   │   ├── Channels/
│   │   │   ├── MtcSmsChannel.php
│   │   │   └── WhatsAppChannel.php   ← Phase 2
│   │   ├── Services/
│   │   │   ├── NotificationDispatcher.php
│   │   │   └── PreferencesService.php
│   │   └── routes.php
│   │
│   ├── AI/                           ← Phase 3
│   │   ├── Contracts/
│   │   │   └── AIProvider.php
│   │   ├── Providers/
│   │   │   └── AnthropicProvider.php
│   │   ├── Services/
│   │   │   ├── AIService.php
│   │   │   ├── DailyMotivationGenerator.php
│   │   │   └── ManagerAssistantService.php
│   │   ├── Jobs/
│   │   │   └── GenerateDailyMotivation.php
│   │   └── Models/
│   │       └── AiMessage.php
│   │
│   ├── Reports/
│   │   ├── Controllers/…
│   │   ├── Services/
│   │   │   ├── AttendanceReportService.php
│   │   │   ├── LeaveReportService.php
│   │   │   └── CsvExporter.php
│   │   ├── Jobs/
│   │   │   └── GenerateReportAsync.php
│   │   └── routes.php
│   │
│   └── Audit/
│       ├── Observers/
│       │   └── AuditObserver.php       ← يلتقط كل تعديل حسّاس
│       └── Services/
│           └── AuditQueryService.php
│
├── Shared/
│   ├── Contracts/                   ← بروتوكولات بين الوحدات
│   │   └── EmployeeReadable.php
│   ├── DTOs/
│   ├── Exceptions/
│   │   ├── BusinessException.php
│   │   ├── WorkflowException.php
│   │   └── GeofenceRejectedException.php
│   ├── Support/
│   │   ├── ArabicDate.php
│   │   └── PhoneNormalizer.php
│   ├── Casts/
│   │   ├── EncryptedString.php
│   │   └── PointCast.php
│   └── ValueObjects/
│       ├── GeoPoint.php
│       └── TimeRange.php
│
├── Console/
│   ├── Kernel.php                   ← جدولة كل الـ Jobs
│   └── Commands/
│       ├── TaqatRecomputeAttendance.php
│       ├── TaqatSeedV1Migration.php
│       └── TaqatRotateQrTokens.php
│
└── Providers/
    ├── AppServiceProvider.php
    ├── AuthServiceProvider.php
    ├── BroadcastServiceProvider.php
    ├── EventServiceProvider.php
    └── ModulesServiceProvider.php   ← يسجّل route files لكل وحدة
```

### 4.2 قواعد الفصل بين الوحدات (Enforcement)

- **`deptrac`** (composer package) يمنع أي `use App\Modules\X\...` من داخل `App\Modules\Y\...` إلا عبر contract في `App\Shared\Contracts`.
- Config file `deptrac.yaml` يعرّف الطبقات والاتجاهات المسموحة.
- CI ينفّذ `deptrac analyse` ويفشل على أي انتهاك.

### 4.3 قواعد Clean Architecture

راجع [`00-overview.md#5.4`](00-overview.md). في ملخص:
- Controller = 15 سطراً كحد أقصى (validation → service → resource).
- Service = orchestration + transactions + events. لا queries مباشرة.
- Repository = eager loading + pagination + queries معقّدة.
- Model = علاقات + scopes + accessors فقط.

---

## 5. Next.js App Structure (App Router)

### 5.1 المخطط الكامل

```
apps/web/
├── src/
│   ├── app/
│   │   ├── layout.tsx                  ← RTL + Tajawal + Providers
│   │   ├── globals.css
│   │   ├── page.tsx                    ← redirect إلى /login أو /dashboard
│   │   ├── (auth)/
│   │   │   ├── login/
│   │   │   │   └── page.tsx
│   │   │   ├── forgot-password/
│   │   │   └── reset-password/
│   │   ├── (employee)/
│   │   │   ├── layout.tsx              ← sidebar + topbar للموظف
│   │   │   ├── dashboard/
│   │   │   │   └── page.tsx
│   │   │   ├── attendance/
│   │   │   │   ├── page.tsx
│   │   │   │   └── history/page.tsx
│   │   │   ├── leaves/
│   │   │   │   ├── page.tsx            ← رصيدي + طلباتي
│   │   │   │   └── new/page.tsx
│   │   │   ├── requests/
│   │   │   │   ├── page.tsx
│   │   │   │   └── new/[typeCode]/page.tsx  ← form ديناميكي من form_schema
│   │   │   ├── tasks/
│   │   │   │   ├── page.tsx            ← قائمة المهام
│   │   │   │   └── [taskId]/page.tsx
│   │   │   ├── notifications/
│   │   │   ├── projects/               ← Phase 2
│   │   │   │   └── [projectId]/
│   │   │   │       ├── page.tsx
│   │   │   │       ├── board/page.tsx  ← Kanban
│   │   │   │       └── sprints/[sprintId]/page.tsx
│   │   │   └── profile/
│   │   ├── (admin)/
│   │   │   ├── layout.tsx              ← admin nav
│   │   │   ├── dashboard/
│   │   │   ├── employees/
│   │   │   │   ├── page.tsx
│   │   │   │   ├── new/page.tsx
│   │   │   │   └── [id]/edit/page.tsx
│   │   │   ├── departments/
│   │   │   ├── teams/
│   │   │   ├── positions/
│   │   │   ├── work-schedules/
│   │   │   ├── holidays/
│   │   │   ├── leave-types/
│   │   │   ├── workflows/
│   │   │   │   ├── page.tsx
│   │   │   │   └── [id]/edit/page.tsx   ← Workflow builder
│   │   │   ├── request-types/
│   │   │   │   └── [id]/edit/page.tsx   ← Form Builder (drag-drop)
│   │   │   ├── reports/
│   │   │   │   ├── attendance/
│   │   │   │   ├── leaves/
│   │   │   │   └── requests/
│   │   │   ├── audit-logs/
│   │   │   └── settings/
│   │   ├── (public)/
│   │   │   └── scan/[deviceCode]/
│   │   │       └── page.tsx            ← صفحة تعرض QR live rotation
│   │   └── api/                        ← Next.js route handlers (proxy فقط عند الحاجة)
│   │       └── health/route.ts
│   │
│   ├── components/
│   │   ├── ui/                         ← shadcn/ui primitives
│   │   │   ├── button.tsx
│   │   │   ├── input.tsx
│   │   │   ├── dialog.tsx
│   │   │   ├── select.tsx
│   │   │   └── …
│   │   ├── layout/
│   │   │   ├── SidebarEmployee.tsx
│   │   │   ├── SidebarAdmin.tsx
│   │   │   ├── Topbar.tsx
│   │   │   └── NotificationsBell.tsx
│   │   ├── modules/
│   │   │   ├── attendance/
│   │   │   │   ├── QrScanner.tsx
│   │   │   │   ├── QrDisplay.tsx       ← يعرض QR ديناميكي
│   │   │   │   └── AttendanceHistoryTable.tsx
│   │   │   ├── tasks/
│   │   │   │   ├── TaskKanban.tsx      ← Phase 2
│   │   │   │   ├── TaskCard.tsx
│   │   │   │   ├── TaskDetail.tsx
│   │   │   │   └── CommentThread.tsx
│   │   │   ├── projects/
│   │   │   │   ├── SprintBoard.tsx     ← Phase 2
│   │   │   │   └── BurndownChart.tsx
│   │   │   ├── requests/
│   │   │   │   ├── DynamicForm.tsx     ← يبني form من form_schema
│   │   │   │   └── ApprovalTimeline.tsx
│   │   │   ├── workflow/
│   │   │   │   └── WorkflowBuilder.tsx ← drag-drop steps
│   │   │   ├── employees/
│   │   │   │   ├── EmployeeCard.tsx
│   │   │   │   └── OrgTree.tsx
│   │   │   └── notifications/
│   │   │       └── NotificationList.tsx
│   │   └── icons/
│   │
│   ├── lib/
│   │   ├── api/
│   │   │   ├── client.ts               ← axios instance مع interceptors
│   │   │   ├── endpoints/
│   │   │   │   ├── auth.ts
│   │   │   │   ├── employees.ts
│   │   │   │   ├── attendance.ts
│   │   │   │   ├── leaves.ts
│   │   │   │   ├── requests.ts
│   │   │   │   ├── tasks.ts
│   │   │   │   └── workflows.ts
│   │   │   └── types.ts                ← TS types مطابقة للـ API Resources
│   │   ├── auth/
│   │   │   ├── session.ts              ← server-side session helpers
│   │   │   └── requireAuth.ts          ← middleware للـ (employee) و (admin)
│   │   ├── hooks/
│   │   │   ├── useAuth.ts
│   │   │   ├── useMe.ts
│   │   │   ├── useNotifications.ts
│   │   │   └── useRealtime.ts          ← Echo + Reverb
│   │   ├── stores/
│   │   │   ├── authStore.ts            ← Zustand
│   │   │   ├── notificationsStore.ts
│   │   │   └── uiStore.ts
│   │   ├── i18n/
│   │   │   ├── ar.json
│   │   │   └── en.json
│   │   └── utils/
│   │       ├── dateArabic.ts
│   │       └── formatCurrency.ts
│   │
│   ├── middleware.ts                   ← Next.js middleware للحماية
│   │
│   └── styles/
│       └── tokens.css                  ← --color-primary: #2678C4 …
│
├── public/
│   ├── img/
│   │   └── logo.png                    ← من v1
│   ├── fonts/                          ← Tajawal
│   ├── manifest.webmanifest            ← PWA
│   └── icons/
├── next.config.mjs
├── tailwind.config.ts
├── package.json
└── tsconfig.json
```

### 5.2 حزم الواجهة الرئيسية

| المهمّة                   | الحزمة                                    |
| ------------------------- | ----------------------------------------- |
| UI primitives             | `shadcn/ui` (Radix + Tailwind)            |
| State management          | `zustand`                                 |
| Data fetching + caching   | `@tanstack/react-query`                   |
| Forms                     | `react-hook-form` + `zod`                 |
| Realtime                  | `laravel-echo` + `pusher-js`              |
| Charts                    | `recharts`                                |
| Drag-drop (Kanban)        | `@dnd-kit/*`                              |
| Date pickers              | `react-day-picker`                        |
| Icons                     | `lucide-react`                            |
| QR generation             | `qrcode.react`                            |
| QR scanning               | `@yudiel/react-qr-scanner`                |
| i18n                      | `next-intl`                               |
| Tables                    | `@tanstack/react-table`                   |

---

## 6. Authentication Strategy

### 6.1 المخطط العام

```
┌──────────────┐    POST /api/v1/auth/login       ┌──────────────┐
│  Next.js SSR │  { login, password }             │  Laravel API │
│  (server)    │ ───────────────────────────────► │              │
│              │                                  │              │
│              │ ◄── Set-Cookie: taqat_session=…  │              │
│              │      + { user, roles, perms }    │              │
└──────┬───────┘                                  └──────────────┘
       │
       │ (subsequent requests carry cookie)
       ▼
┌──────────────┐    GET /api/v1/tasks              ┌──────────────┐
│  Next.js SSR │  Cookie: taqat_session=…         │  Laravel API │
│  or CSR      │ ───────────────────────────────► │  Sanctum     │
└──────────────┘                                  │  auth guard  │
                                                  └──────────────┘
```

### 6.2 تفاصيل

- **Sanctum SPA mode** (وليس token-based) لأنه:
  - Cookie httpOnly + Secure + SameSite=Lax → لا XSS يستطيع سرقتها.
  - CSRF مدمج (double-submit).
  - Session في Redis → invalidation فوري.

- **Login endpoint:** `POST /api/v1/auth/login`
  - Body: `{ "login": "EMP-1023 أو ahmad@x.com", "password": "…" }`
  - `LoginService::authenticate(login, password)`:
    1. يبحث في `employees.employee_number` ثم `employees.email`.
    2. يتحقّق `employee.status === 'active'` (else 403).
    3. يتحقّق `Hash::check(password, user.password)`.
    4. `Auth::login(user)` + `session()->regenerate()`.
    5. يطلق `EmployeeLoggedIn` event → Audit + optional SMS notification.
  - Response: `{ data: AuthenticatedEmployeeResource, meta: { permissions: [...] } }`

- **Rate limit:** 5 محاولات per IP+login في الدقيقة (throttle middleware).

- **Password reset:**
  - `POST /api/v1/auth/forgot-password` → يستقبل `employee_number` أو `email`.
  - يولّد token + يرسل SMS (via MTC) بالرابط: `https://taqat.example.com/reset-password?token=...`.
  - `POST /api/v1/auth/reset-password` → `{ token, password, password_confirmation }`.

- **Logout:** `POST /api/v1/auth/logout` → session invalidate + audit.

- **Me:** `GET /api/v1/auth/me` → يعيد الموظف الحالي + roles + permissions.

### 6.3 Refresh strategy
Sanctum SPA لا يستخدم refresh tokens — الـ session cookie تُجدَّد تلقائياً مع كل request عبر Laravel session guard. المدّة الافتراضية: 8 ساعات نشاط، مع تجديد rolling.

### 6.4 2FA (اختياري في Phase 4)
- `users.2fa_secret` (nullable) — TOTP.
- عند التفعيل، Login flow يضيف step ثانٍ: `POST /api/v1/auth/verify-otp`.
- QR code generation عبر `pragmarx/google2fa`.

---

## 7. CORS Setup

الحالة العادية: `web` و `api` خلف نفس الـ domain → لا CORS.
لكن للتطوير المحلي (Next.js على :3000، Laravel على :8000)، أو للـ mobile apps مستقبلاً، يجب إعداد CORS بعناية.

**`apps/api/config/cors.php`:**
```php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000')),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,   // ضروري للـ cookie
];
```

**`.env`:**
```
CORS_ALLOWED_ORIGINS=https://taqat.example.com,http://localhost:3000
SANCTUM_STATEFUL_DOMAINS=taqat.example.com,localhost:3000
SESSION_DOMAIN=.example.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
```

---

## 8. Real-time via Reverb

### 8.1 القنوات (Channels)

| القناة                                    | النوع     | من يشترك                       | ماذا يُبثّ                                              |
| ----------------------------------------- | --------- | ------------------------------ | ------------------------------------------------------- |
| `App.Models.User.{userId}`                | Private   | الموظف نفسه                     | إشعاراته الشخصية، تحديث الحضور، الموافقة على طلباته      |
| `team.{teamId}`                           | Presence  | أعضاء الفريق                   | تحديث المهام في نفس المشروع، رسائل الفريق                |
| `department.{departmentId}`               | Presence  | أعضاء القسم                    | إعلانات القسم                                            |
| `project.{projectId}.tasks`               | Private   | أعضاء المشروع                  | Task created/updated/moved (Kanban realtime)             |
| `company.announcements`                   | Public    | جميع الموظفين                   | إعلانات عامة من الإدارة                                  |
| `admin.system`                            | Private   | أدوار Super Admin / Management | health alerts، failures                                   |

### 8.2 Broadcasting (Laravel side)

```php
// app/Modules/Notifications/Events/UserNotified.php
class UserNotified implements ShouldBroadcast
{
    public function __construct(public int $userId, public array $payload) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("App.Models.User.{$this->userId}")];
    }

    public function broadcastAs(): string { return 'notification.received'; }
}
```

### 8.3 استهلاك في Next.js

```ts
// apps/web/src/lib/hooks/useRealtime.ts
'use client';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

let echo: Echo | null = null;

export function getEcho(userId: number) {
  if (echo) return echo;
  (window as any).Pusher = Pusher;
  echo = new Echo({
    broadcaster: 'reverb',
    key: process.env.NEXT_PUBLIC_REVERB_KEY!,
    wsHost: process.env.NEXT_PUBLIC_REVERB_HOST!,
    wsPort: Number(process.env.NEXT_PUBLIC_REVERB_PORT ?? 443),
    forceTLS: true,
    enabledTransports: ['wss'],
    authEndpoint: '/api/v1/broadcasting/auth',
    auth: { headers: {} },
  });
  return echo;
}
```

### 8.4 Fallback
إذا فشل الاتصال بـ Reverb، Next.js يعود إلى **polling كل 30 ثانية** للـ notifications. `useRealtime` hook يدير هذا التبديل.

---

## 9. File Storage via MinIO

### 9.1 الإعداد

**`apps/api/config/filesystems.php`:**
```php
'disks' => [
    's3' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'bucket' => env('AWS_BUCKET'),
        'endpoint' => env('AWS_ENDPOINT'),
        'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', true),
        'throw' => true,
        'visibility' => 'private',
    ],
    // نفصل buckets عبر disk config متعدّد
    'avatars' => [ ...same as above..., 'bucket' => 'avatars', 'visibility' => 'public' ],
    'exports' => [ ...same as above..., 'bucket' => 'exports', 'visibility' => 'private' ],
],
```

### 9.2 Buckets

| Bucket        | Visibility | المحتوى                                  | Retention           |
| ------------- | ---------- | ---------------------------------------- | ------------------- |
| `avatars`     | public     | صور الملفات الشخصية                       | حتى الحذف           |
| `attachments` | private    | مرفقات المهام والطلبات                    | حسب لحظة حذف السجل  |
| `exports`     | private    | تقارير Excel/CSV/PDF مولّدة              | 7 أيام (auto-clean) |

### 9.3 Signed URLs

للملفات الخاصة، Laravel يولّد رابط موقّت (مثلاً 15 دقيقة):
```php
$url = Storage::disk('attachments')->temporaryUrl(
    $mediaPath,
    now()->addMinutes(15)
);
```

Next.js يعرض هذا الرابط للـ `<a>` أو `<img>` مباشرة.

### 9.4 Spatie Media Library
للمرفقات المرتبطة بـ Model (Task, EmployeeRequest, LeaveRequest):
- تُستخدم `spatie/laravel-medialibrary`.
- disk افتراضي = `attachments`.
- Collections: `default`, `attachments`, `avatar`.
- يعرض [`02-erd.md#media`](02-erd.md) الجدول.

---

## 10. Queue Strategy

### 10.1 الطوابير

| Queue           | Priority | مثال Jobs                                                                    |
| --------------- | -------- | ---------------------------------------------------------------------------- |
| `notifications` | High     | `SendMtcSms`, `SendMailJob`, `BroadcastToUser`                               |
| `ai`            | Medium   | `GenerateDailyMotivation`, `SummarizeTeamForManager`                         |
| `reports`       | Low      | `GenerateAttendanceReport`, `ExportEmployeesCSV`                             |
| `default`       | Low      | Housekeeping, `RecomputeAttendanceForDate`, `AnnualBalanceRollover`          |

**Command في `queue` container:**
```
php artisan queue:work redis \
  --queue=notifications,ai,reports,default \
  --tries=3 --backoff=5,30,120 --max-time=3600
```

الترتيب مهم — `notifications` تُخدَم قبل `ai` قبل `reports` قبل `default`.

### 10.2 Retry & Backoff
- 3 محاولات، backoff: 5s → 30s → 2min.
- Fails تذهب إلى `failed_jobs` table + إشعار Slack عبر `AboutToFailNotification`.

### 10.3 Timeouts
- Job افتراضي: 60 ثانية.
- AI jobs: 120 ثانية (Claude قد يستغرق).
- Reports jobs: 600 ثانية.

### 10.4 Horizon (Phase 2)
عند إضافة Horizon container، لوحة قيادة على `/horizon` تعرض حالة الـ workers + supervisors + الفشل.

### 10.5 Scheduling
`scheduler` container يشغّل `php artisan schedule:work`، والـ Kernel يعرّف:
- كل ليلة 00:15 → `WorkingHoursEngine` يحسب حضور اليوم السابق.
- كل ليلة 02:00 → `RotateQrTokens` (احتياط، الـ tokens تُدار عبر Redis TTL أيضاً).
- 1 يناير 00:05 → `AnnualBalanceRollover`.
- كل يوم 08:00 → `GenerateDailyMotivation` (Phase 3).
- كل ساعة → `SlaMonitor` (يفحص طلبات تجاوزت SLA).

---

## 11. Environment Variables

### 11.1 `.env.example` للـ API

```bash
APP_NAME=TAQAT
APP_ENV=production
APP_KEY=                              # php artisan key:generate
APP_DEBUG=false
APP_URL=https://taqat.example.com
APP_TIMEZONE=Asia/Amman
APP_LOCALE=ar

LOG_CHANNEL=stack
LOG_LEVEL=info

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=taqat
DB_USERNAME=taqat_user
DB_PASSWORD=                          # docker secret

REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=                       # docker secret
REDIS_CLIENT=phpredis

QUEUE_CONNECTION=redis
CACHE_DRIVER=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=480                  # 8 hours
SESSION_DOMAIN=.example.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

BROADCAST_DRIVER=reverb
REVERB_APP_ID=taqat-app
REVERB_APP_KEY=                       # random string
REVERB_APP_SECRET=                    # random string
REVERB_HOST=reverb
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_HOST_PUBLIC=taqat.example.com
REVERB_PORT_PUBLIC=443
REVERB_SCHEME_PUBLIC=https

FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=                    # = MINIO_ROOT_USER
AWS_SECRET_ACCESS_KEY=                # = MINIO_ROOT_PASSWORD
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=attachments
AWS_ENDPOINT=http://minio:9000
AWS_USE_PATH_STYLE_ENDPOINT=true

SANCTUM_STATEFUL_DOMAINS=taqat.example.com
CORS_ALLOWED_ORIGINS=https://taqat.example.com

MTC_SMS_USERNAME=
MTC_SMS_PASSWORD=
MTC_SMS_SENDER=TAQAT

ANTHROPIC_API_KEY=
ANTHROPIC_MODEL_DEFAULT=claude-haiku-4-5
ANTHROPIC_MODEL_PREMIUM=claude-sonnet-4-5
ANTHROPIC_MONTHLY_BUDGET_CENTS=5000   # $50/month

MAIL_MAILER=log                        # switch to smtp for production
```

### 11.2 `.env.example` للـ Web (Next.js)

```bash
NODE_ENV=production
NEXT_PUBLIC_API_URL=https://taqat.example.com
NEXT_PUBLIC_APP_NAME=TAQAT
NEXT_PUBLIC_REVERB_KEY=                # نفس REVERB_APP_KEY
NEXT_PUBLIC_REVERB_HOST=taqat.example.com
NEXT_PUBLIC_REVERB_PORT=443
NEXT_PUBLIC_REVERB_SCHEME=https

# server-only (لا public)
INTERNAL_API_URL=http://api            # داخل Docker network
```

### 11.3 إدارة الأسرار
- في التطوير: `.env` محلي.
- في الإنتاج: `.env` على VPS (chmod 600، owner=root) + متغيرات حسّاسة تُقرأ من Docker secrets عند التوسّع.
- **ممنوع** الكتابة إلى git — `.gitignore` يحوي `.env*` مع استثناء `.env.example`.

---

## 12. Deployment Flow (CI/CD)

### 12.1 Pipeline

```
┌──────────────────┐   git push main   ┌─────────────────────┐
│  Local machine   │ ────────────────► │  GitHub repository  │
└──────────────────┘                   └──────────┬──────────┘
                                                  │
                                                  ▼
                                    ┌─────────────────────────┐
                                    │  GitHub Actions          │
                                    │  ┌──────────────────┐    │
                                    │  │ 1. Lint + Test   │    │
                                    │  │    (parallel)    │    │
                                    │  │    - api: Pest   │    │
                                    │  │    - web: TS+ESL │    │
                                    │  └────────┬─────────┘    │
                                    │           ▼              │
                                    │  ┌──────────────────┐    │
                                    │  │ 2. Build images  │    │
                                    │  │    - api Docker  │    │
                                    │  │    - web Docker  │    │
                                    │  │    tag: sha,latest│   │
                                    │  └────────┬─────────┘    │
                                    │           ▼              │
                                    │  ┌──────────────────┐    │
                                    │  │ 3. Push to GHCR  │    │
                                    │  └────────┬─────────┘    │
                                    │           ▼              │
                                    │  ┌──────────────────┐    │
                                    │  │ 4. SSH to VPS    │    │
                                    │  │    docker compose│    │
                                    │  │    pull && up -d │    │
                                    │  └──────────────────┘    │
                                    └─────────────────────────┘
```

### 12.2 `.github/workflows/build-and-push.yml`

```yaml
name: Build & Push
on:
  push: { branches: [main] }

jobs:
  api-image:
    runs-on: ubuntu-latest
    permissions: { contents: read, packages: write }
    steps:
      - uses: actions/checkout@v4
      - uses: docker/setup-buildx-action@v3
      - uses: docker/login-action@v3
        with:
          registry: ghcr.io
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}
      - uses: docker/build-push-action@v6
        with:
          context: .
          file: infra/docker/api/Dockerfile
          push: true
          tags: |
            ghcr.io/${{ github.repository_owner }}/taqat-api:${{ github.sha }}
            ghcr.io/${{ github.repository_owner }}/taqat-api:latest
          cache-from: type=gha
          cache-to: type=gha,mode=max

  web-image:
    runs-on: ubuntu-latest
    permissions: { contents: read, packages: write }
    steps:
      - uses: actions/checkout@v4
      - uses: docker/setup-buildx-action@v3
      - uses: docker/login-action@v3
        with:
          registry: ghcr.io
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}
      - uses: docker/build-push-action@v6
        with:
          context: .
          file: infra/docker/web/Dockerfile
          push: true
          tags: |
            ghcr.io/${{ github.repository_owner }}/taqat-web:${{ github.sha }}
            ghcr.io/${{ github.repository_owner }}/taqat-web:latest
          cache-from: type=gha
          cache-to: type=gha,mode=max

  deploy:
    needs: [api-image, web-image]
    runs-on: ubuntu-latest
    steps:
      - uses: appleboy/ssh-action@v1
        with:
          host: ${{ secrets.VPS_HOST }}
          username: ${{ secrets.VPS_USER }}
          key: ${{ secrets.VPS_SSH_KEY }}
          script: |
            cd /opt/taqat
            docker compose pull
            docker compose up -d --remove-orphans
            docker compose exec -T api php artisan migrate --force
            docker compose exec -T api php artisan config:cache
            docker compose exec -T api php artisan route:cache
            docker compose exec -T api php artisan event:cache
```

### 12.3 Zero-downtime approach

**Phase 1 (بسيط):**
- `docker compose up -d` مع `restart: unless-stopped` — الحاويات الجديدة تحلّ محل القديمة تدريجياً.
- Nginx يبقى مستمراً — قد تفشل 1-2 request خلال الاستبدال (~2 ثانية).

**Phase 2 (متقدّم):**
- استخدام Traefik بدل Nginx مع rolling update — يوجّه الطلبات إلى instance جديد قبل قتل القديم.
- أو Blue-Green: `api-blue` و `api-green` مع تبديل upstream في Nginx.

### 12.4 Migrations
- تعمل تلقائياً بعد كل نشر (`migrate --force`).
- Migrations كبيرة (index rebuild، column أضخم) تُنفَّذ عبر `Migration Windows` مع صيانة معلنة.
- استخدام `laravel-safeup` أو `spatie/laravel-migration-tester` لاختبار الـ migrations على snapshot قبل الإنتاج.

---

## 13. Health & Monitoring

### 13.1 Health endpoint

`GET /api/health` يعيد:
```json
{
  "status": "ok",
  "checks": {
    "database": "ok",
    "redis": "ok",
    "minio": "ok",
    "reverb": "ok",
    "queue_last_processed_ago_seconds": 12
  },
  "version": "sha-abc123",
  "uptime": 3600
}
```

يستخدمه: Docker healthcheck، Uptime robots (UptimeRobot / Better Stack)، `/api/health` من Next.js middleware للتأكد قبل SSR.

### 13.2 Logging
- Laravel: `LOG_CHANNEL=stack` → stdout (Docker يجمعها).
- Next.js: `pino` مع `pino-http` → stdout.
- Aggregation: `docker compose logs -f` في Phase 1، Loki + Grafana في Phase 2.

### 13.3 Errors
- Laravel: `sentry/sentry-laravel` (اختياري) لتنبيهات real-time.
- Next.js: Sentry SDK.
- بديل مجاني: `spatie/laravel-flare` أو مجرّد إشعارات Slack من ErrorHandler.

---

## 14. Development Environment

### 14.1 First-time setup على Windows/WSL2 أو Linux/Mac

```bash
# 1. Clone
git clone git@github.com:<org>/taqat.git && cd taqat

# 2. Copy envs
cp apps/api/.env.example apps/api/.env
cp apps/web/.env.example apps/web/.env.local

# 3. Bring up
docker compose -f infra/docker/docker-compose.dev.yml up -d

# 4. First-time in api container
docker compose exec api composer install
docker compose exec api php artisan key:generate
docker compose exec api php artisan migrate --seed

# 5. Web
docker compose exec web npm install
```

### 14.2 dev vs prod compose
- `docker-compose.dev.yml` يمرّر source code كـ bind mount لتفعيل hot-reload.
- يستخدم Xdebug + `APP_DEBUG=true`.
- MinIO يُكشف على `localhost:9001` للـ Web console.

---

## 15. مراجع الوثائق الأخرى

- بنية الجداول والعلاقات → [`02-erd.md`](02-erd.md).
- تفصيل خطط Phase 1 → [`03-phase-1-plan.md`](03-phase-1-plan.md).
- خلفية القرارات المعمارية → [`00-overview.md`](00-overview.md).

## 16. تاريخ التعديلات
| الإصدار | التاريخ    | التغيير              |
| ------- | ---------- | -------------------- |
| 1.0     | 2026-09-07 | الإصدار الأول        |
