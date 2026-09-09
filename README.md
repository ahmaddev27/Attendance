<div align="center">

# TAQAT — Digital Workplace Platform

**Comprehensive HR + Attendance + Tasks + Requests + Realtime Notifications platform for TAQAT employees.**

[![CI](https://img.shields.io/github/actions/workflow/status/ahmaddev27/Attendance/ci.yml?branch=main&label=CI&style=for-the-badge&logo=github)](https://github.com/ahmaddev27/Attendance/actions/workflows/ci.yml)
[![Deploy](https://img.shields.io/github/actions/workflow/status/ahmaddev27/Attendance/deploy.yml?branch=main&label=Deploy&style=for-the-badge&logo=github)](https://github.com/ahmaddev27/Attendance/actions/workflows/deploy.yml)
[![Last commit](https://img.shields.io/github/last-commit/ahmaddev27/Attendance?style=for-the-badge&logo=git)](https://github.com/ahmaddev27/Attendance/commits/main)

[![Phase 1](https://img.shields.io/badge/Phase%201-100%25-brightgreen?style=for-the-badge)](docs/v2/03-phase-1-plan.md)
[![Phase 2](https://img.shields.io/badge/Phase%202-100%25-brightgreen?style=for-the-badge)](docs/v2/03-phase-1-plan.md)
[![Phase 3](https://img.shields.io/badge/Phase%203-100%25-brightgreen?style=for-the-badge)](docs/v2/03-phase-1-plan.md)
[![Phase 4](https://img.shields.io/badge/Phase%204-Planning-lightgrey?style=for-the-badge)](docs/v2/03-phase-1-plan.md)

[![Laravel](https://img.shields.io/badge/Laravel-11-red?style=flat-square&logo=laravel)](https://laravel.com)
[![Next.js](https://img.shields.io/badge/Next.js-15-black?style=flat-square&logo=nextdotjs)](https://nextjs.org)
[![React Native](https://img.shields.io/badge/React%20Native-Expo%2051-blue?style=flat-square&logo=expo)](https://expo.dev)
[![PHP](https://img.shields.io/badge/PHP-8.4-777bb4?style=flat-square&logo=php)](https://php.net)
[![Node.js](https://img.shields.io/badge/Node.js-20-339933?style=flat-square&logo=node.js)](https://nodejs.org)
[![Docker](https://img.shields.io/badge/Docker-Compose-2496ed?style=flat-square&logo=docker)](https://docker.com)
[![License](https://img.shields.io/badge/License-Proprietary-lightgrey?style=flat-square)](#license)

</div>

---

## 🎯 Overview

**Stack:** Laravel 11 API · Next.js 15 (App Router) · React Native (Expo) · MySQL 8 · Redis · MinIO · Reverb WebSocket · Meilisearch · Docker Compose
**Language:** Arabic (100% RTL) — brand tokens: `#2678C4` (blue) + `#F5A623` (accent orange)
**Status:** Production-ready — Phase 1 complete, Phase 2/3/4 features shipped ahead of schedule

---

## 📚 Table of Contents

- [Features](#-features)
- [Architecture](#-architecture)
- [Phase Progress](#-phase-progress)
- [Quick Start](#-quick-start)
- [Deployment](#-deployment)
- [Environment Variables](#-environment-variables)
- [Testing](#-testing)
- [Access & RBAC](#-access--rbac)
- [Repo Layout](#-repo-layout)
- [What's Not Built Yet](#-whats-not-built-yet)

---

## ✨ Features

### 🔐 Authentication & Authorization
- Login by **email or employee number** (auto-detects `@`)
- Sanctum Personal Access Tokens (Bearer) — works for SPA and mobile
- Real RBAC via [spatie/laravel-permission](https://spatie.be/docs/laravel-permission) on `/admin/*` routes
- 5 roles: `super-admin` / `management` / `department-manager` / `team-leader` / `employee`

### 👥 Employees & Organization
- Full employee CRUD with avatar upload (MinIO S3-compatible)
- Race-safe sequential employee numbers (`lockForUpdate`)
- Departments, teams, positions, work schedules, holidays

### 📱 Attendance
- QR-based check-in/check-out via scan devices
- Working Hours Engine (present, late, early-leave, overtime)
- Monthly attendance summary
- Detailed attendance log with search + filters

### 🌴 Leaves
- Multi-type leaves (paid/unpaid, capped/uncapped, attachment-required)
- Annual balances with pending/used/entitled tracking
- Multi-step approval workflow
- Self-service submit/cancel for employees

### 📝 Workflow Engine & Generic Requests
- Extensible workflow engine (custom request types)
- Multiple approver strategies (Department Manager, Team Leader, Custom)
- Approve / Reject / Return / Forward actions
- Approval Inbox for managers

### 📌 Tasks
- Tasks with subtasks, custom priorities/statuses/tags
- Comments + file attachments (signed URLs)
- Kanban board (drag & drop)
- Assignee-based visibility

### 🔔 Multi-channel Notifications
- **Database** (durable inbox + bell counter)
- **Reverb WebSocket** (realtime toast — with dedup to avoid flood)
- **Email** via Resend
- **SMS** via MTC Jordan
- **WhatsApp** via Meta Cloud API
- Every channel is opt-in per notification (`sendSms`, `sendWhatsapp`)

### 🤖 AI Motivation (Anthropic Claude)
- Daily personalized motivational message per employee
- Context includes: attendance streak, task completion, upcoming leave
- Fallback library if Claude API fails — dashboard never 500s
- Cached 24h per user in Redis
- Scheduled warmup daily at 07:00 (Asia/Amman)

### 📊 Reports & Analytics
- **Admin Dashboard** with live KPIs (60s refetch)
- **Employee Home** with personal KPIs
- Monthly attendance reports in **CSV / Excel / PDF** formats
- Excel: RTL sheet + brand header + frozen pane
- PDF: Arabic font (Amiri) + brand-blue table
- Audit log via [spatie/laravel-activitylog](https://spatie.be/docs/laravel-activitylog)

### 📈 Analytics Dashboard (Phase 3)
- Dedicated `/analytics` page with **5 parallel useQueries**
- **Attendance KPIs**: rate %, avg check-in/out clock, working days
- **Attendance heatmap**: 7×24 grid (day-of-week × hour) with 5-step intensity buckets
- **Leave patterns**: monthly trend + by-type + by-department (Recharts line/pie)
- **Task performance**: totals + avg completion hours + top 5 assignees
- **Employee summary**: headcount by dept + team + recent hires (bar chart)
- Filters: preset ranges (7d/30d/this-month/custom) + dept/team/employee
- SQL-raw queries in `AnalyticsService`, wrapped in `Cache::remember` (5 min TTL)

### 🔍 Global Search (Meilisearch)
- Cmd/Ctrl+K palette
- Cross-index search: employees, tasks, requests, leaves
- Typo-tolerant + Arabic tokenization
- 300ms debounce

### ⚙️ System Settings
- Admin Settings page — runtime edit without redeploy
- **Mail** (Resend key, from address, from name)
- **SMS** (MTC username, password, sender, endpoint, fake mode)
- **WhatsApp** (access token, phone number ID, business ID)
- **AI** (Anthropic API key, Claude model)
- Sensitive values encrypted at rest (`Crypt::encryptString`)
- Cache-first reads (1h TTL)

### 📱 Applications
- **Web Admin Panel** (Next.js 15) — fully RTL, shadcn/ui + TAQAT tokens
- **Mobile App** (React Native + Expo) — Login, Home KPIs, Tasks + detail, Leaves + create, Requests, Notifications inbox, QR Scanner, Profile
- **PWA** — Serwist service worker (NetworkFirst for HTML + `/_next/*`, StaleWhileRevalidate elsewhere) + offline shell for `/scan` + install prompt

### 📲 Push Notifications (Expo)
- `POST /me/push-tokens` — mobile app registers on every launch; upsert on (user_id, device_id) so a rotated Expo token overwrites in place
- `PushChannel` custom Laravel notification channel, opt-in per `TaqatNotification` via `$sendPush=true`
- Bulk-send in 100-message chunks (Expo API cap), receipt polling prunes `DeviceNotRegistered` tokens automatically
- Same fake/real gateway split as SMS + WhatsApp — `FakePushGateway` in `testing` env logs to laravel.log

### 🧰 Operator Commands
- `php artisan taqat:backfill-users` — create login accounts for existing employees (idempotent, `--random` for per-user passwords)
- `php artisan taqat:wipe-demo` — nuke transactional data + non-admin users, KEEP org tree (dept/team/position/leave-type/schedule/holiday/task-config) and super-admins
- Both commands have `--dry-run` for safe preview and refuse to run destructive paths in production without `--force`

---

## 🏛️ Architecture

### Backend (Laravel 11 — Modular Monolith)
```
apps/api/app/Modules/
├── AI/                 Claude client + Motivation service
├── Auth/               Login (email/number) + Sanctum tokens
├── Attendance/         QR devices + scan flow + WHM calculator
├── Employees/          Employee CRUD + org tree
├── Leaves/             Types + balances + requests + workflow
├── Notifications/      TaqatNotification + dedup dispatcher
├── Organization/       Departments + teams + positions
├── Reports/            Dashboards + CSV/Excel/PDF exports + Audit log
├── Requests/           Generic request submissions + approvals
├── Search/             Meilisearch cross-index search
├── Settings/           DB-backed key/value with encryption
├── Sms/                MTC gateway + queued jobs
├── Tasks/              Tasks + comments + attachments + kanban
├── Whatsapp/           Meta Cloud API gateway
└── Workflow/           Workflow steps + approver resolver
```

### Frontend (Next.js 15 App Router)
```
apps/web/src/app/
├── (auth)/login              Public login
├── (admin)/                  Admin routes with sidebar (RBAC-filtered)
│   ├── dashboard, employees, organization/*, attendance
│   ├── leaves, requests, approvals, workflows, request-types
│   ├── tasks, tasks-config/*, reports/attendance, audit
│   ├── notifications, settings
├── (employee)/               Employee routes with slim sidebar
│   └── home, my-tasks, my-leaves, my-requests
├── (public)/scan             QR check-in (offline-capable via SW)
├── offline                   PWA offline fallback
├── sw.ts                     Serwist service worker
└── manifest.ts               PWA manifest
```

### Mobile (React Native + Expo)
```
apps/mobile/app/
├── (auth)/login              Login screen
├── (tabs)/                   Home / Tasks / Leaves / Profile
└── scan                      QR scanner modal
```

### Infrastructure (Docker Compose)
```
docker-compose.simple.yml
├── db              MySQL 8 (healthchecked)
├── redis           Redis 7-alpine
├── minio           S3-compatible storage
├── meilisearch     v1.10, private
├── api             php-fpm 8.4 (custom-built)
├── nginx           fronts php-fpm
├── web             Next.js standalone
├── queue           php artisan queue:work
├── scheduler       php artisan schedule:work
└── reverb          php artisan reverb:start
```

---

## 📈 Phase Progress

| Phase | Scope | Status | Highlights |
|-------|-------|--------|------------|
| **Phase 1** (Foundation — M1-M8) | 12 wk | ✅ **100%** | Auth · Employees · Attendance (QR) · Leaves · Tasks · Requests + Workflow · Notifications · Reports · Audit · RBAC |
| **Phase 2** (Enterprise Extensions) | 8 wk  | ✅ **100%** | Mail (Resend) · SMS (MTC) · WhatsApp (Meta) · AI (Claude) · Excel + PDF · Meilisearch · PWA · Admin Settings UI |
| **Phase 3** (Analytics + Mobile) | 6 wk  | ✅ **100%** | Analytics dashboard (Recharts + heatmap) · Push Notifications (Expo) · React Native app (Auth · Home · Tasks · Leaves · Requests · Notifications · Profile · QR scan) |
| **Phase 4** (Scale + Security) | TBD   | 🕒 **Planning** | 2FA · session management · deeper audit · BI drill-downs · EAS mobile release |

**Delivered ~3 months ahead of the original 30-week roadmap.**

---

## 🚀 Quick Start

### Prerequisites
- Docker Desktop 4.30+
- Node.js 20+ (for mobile app)
- Git

### Local development
```bash
git clone https://github.com/ahmaddev27/Attendance.git taqat
cd taqat

# Environment
cp .env.example .env
# → Edit .env with real values (see "Environment Variables" below)

# Bring the stack up
docker compose -f docker-compose.simple.yml up -d --build

# Migrations + admin seeder
docker compose -f docker-compose.simple.yml exec -T api php artisan migrate --seed --force

# Open in browser
open http://localhost:8181
# Admin: admin@taqat.local / password
```

### Mobile app
```bash
cd apps/mobile
cp .env.example .env
# Set EXPO_PUBLIC_API_URL=http://<your-lan-ip>  (same wifi as your phone)
npm install
npx expo start
# Scan QR with Expo Go
```

---

## 🚢 Deployment

### Auto-deploy (GitHub Actions → VPS)
Every push to `main` triggers:
1. **CI** (api-tests + web-build)
2. **Build & Push Images** (to GHCR)
3. **Deploy to VPS** — SSH → `git pull` → composer sync → docker build → `up -d --force-recreate` → `migrate --force` → cache config

The deploy job:
- ✅ Never touches `.env` (auto-backup before `git reset`)
- ✅ Only rebuilds `api` + `web` (db/redis/minio remain untouched)
- ✅ Restarts nginx to re-resolve api container IP
- ✅ Post-deploy health check — fails loudly if any container isn't `running`

**Required GitHub Secrets** (in repo Settings → Secrets):
- `VPS_HOST`, `VPS_USER`, `VPS_SSH_KEY`, `VPS_SSH_PORT`, `VPS_APP_PATH`

See [`docs/deployment/vps-deploy.md`](docs/deployment/vps-deploy.md) for full details.

### Apache Reverse Proxy (cPanel)
- [`infra/apache/proxy.conf`](infra/apache/proxy.conf) — copy to `/etc/apache2/conf.d/userdata/{std,ssl}/2_4/<user>/<domain>/proxy.conf`
- Forwards: `/api → nginx:8180` · `/app,/apps → reverb:8182` (WebSocket) · `/` → next.js:8181
- Force HTTPS redirect + Let's Encrypt AutoSSL

---

## 🔧 Environment Variables

### Application
```bash
APP_URL=https://attendees.taqatgaza.com
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...
```

### Database (MySQL)
```bash
DB_HOST=db
DB_DATABASE=taqat
DB_USERNAME=taqat
DB_PASSWORD=...
DB_ROOT_PASSWORD=...
```

### Redis
```bash
REDIS_HOST=redis
REDIS_PASSWORD=...
REDIS_CLIENT=predis
```

### Reverb (Realtime)
```bash
# Internal (Laravel → Reverb via Docker network)
REVERB_HOST=reverb
REVERB_PORT=8182                # host port binding
REVERB_SERVER_PORT=8080         # inside container
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
```bash
MINIO_ROOT_USER=taqat_minio
MINIO_ROOT_PASSWORD=...
AWS_BUCKET=taqat-media
AWS_ENDPOINT=http://minio:9000
```

### Mail (Resend) — or from admin UI
```bash
MAIL_MAILER=resend
MAIL_FROM_ADDRESS=noreply@taqatgaza.com
MAIL_FROM_NAME=TAQAT
RESEND_KEY=re_...
```

### MTC SMS — or from admin UI
```bash
MTC_SMS_USERNAME=...
MTC_SMS_PASSWORD=...
MTC_SMS_SENDER=TAQAT
MTC_SMS_ENDPOINT=http://int.mtcsms.com/sendsms.aspx
MTC_SMS_FAKE=false
```

### WhatsApp (Meta Cloud API) — or from admin UI
```bash
WHATSAPP_ACCESS_TOKEN=...
WHATSAPP_PHONE_NUMBER_ID=...
WHATSAPP_BUSINESS_ACCOUNT_ID=...
WHATSAPP_FAKE=false
```

### AI (Anthropic Claude) — or from admin UI
```bash
ANTHROPIC_API_KEY=sk-ant-...
```

### Search (Meilisearch)
```bash
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=<random master key>
```

**💡 Tip:** Mail + SMS + WhatsApp + AI credentials can be set from the admin UI (`/settings`) after the first deploy — no need to edit `.env` every time.

---

## 🧪 Testing

### Backend (Pest)
```bash
docker compose exec -T api php artisan test
```

Coverage:
- Auth (login by email + number, token issue, logout)
- Employees + Organization CRUD
- Attendance (scan flow, fraud guard, monthly summary, race conditions)
- Leaves (submit, approve, reject, cancel, balance management)
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

### CI (GitHub Actions per push)
- `api-tests` (PHP 8.4 + MySQL + Redis)
- `web-build` (Node 20 + Next.js build)
- `deploy` (after CI passes)

---

## 🔒 Access & RBAC

### Default Admin
- **Email:** `admin@taqat.local`
- **Password:** `password`
- **Employee Number:** `1000`
- **Role:** `super-admin` (all permissions)

### Role Matrix
| Role | manage-users | manage-departments | view-all-attendance | approve-leaves | create-tasks | manage-workflows | view-reports | view-audit-logs |
|------|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| super-admin | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| management | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | — |
| department-manager | — | — | ⚠️ dept-scoped | ✅ | ✅ | — | — | — |
| team-leader | — | — | ⚠️ team-scoped | ⚠️ team-scoped | ✅ | — | — | — |
| employee | — | — | own only | — | — | — | — | — |

> ⚠️ **Scoped permissions** are boolean-granted today; per-department/per-team scoping is a planned enhancement (a policy/query-scope layer). See [What's Not Built Yet](#-whats-not-built-yet).

Sidebar filters items by user permissions at runtime — a `team-leader` sees only the pages they can act on.

---

## 📁 Repo Layout

```
taqat/
├── .github/workflows/         CI + Deploy pipelines
├── apps/
│   ├── api/                   Laravel 11 backend
│   ├── web/                   Next.js 15 admin/employee panel
│   └── mobile/                React Native + Expo
├── docs/
│   ├── v2/                    Architecture + ERD + Phase plans
│   └── deployment/            VPS deploy guide
├── infra/
│   ├── apache/proxy.conf      Reverse proxy config
│   ├── docker/api/Dockerfile  PHP 8.4 image
│   ├── docker/web/Dockerfile  Next.js standalone
│   └── docker/nginx/          Nginx configs
├── docker-compose.simple.yml  VPS production stack
├── docker-compose.yml         Local dev stack
└── .env.example               Environment template
```

---

## 🚧 What's Not Built Yet

Features from the extended SRS deferred to a later phase:

### Phase 4 (planned)
- **Two-Factor Authentication** — TOTP + SMS fallback
- **Session management UI** — see-my-devices + revoke
- **Deeper audit trail** — IP/UA on every sensitive op, cohort view
- **Rate limiting** on auth + reset-password endpoints
- **BI drill-downs** — cohort analysis, custom report builder
- **Row-level permission scoping** — department-manager sees only their department's attendance/leaves (currently `view-*` permissions are boolean-granted; a query-scope layer would close the ⚠️ items in the RBAC matrix)

### Extended-SRS deferred
- **Projects + Sprints + Scrum ceremonies** (Kanban is per-status; project grouping is not modelled)
- **Manager AI Assistant** (only the daily-motivation AI is live)
- **Executive Dashboard** (C-level KPI wall)
- **Performance Reviews + KPI system**
- **Multi-tenancy (SaaS)** — postponed by product owner
- **WhatsApp templates** for cold outreach outside the 24h customer-service window
- **Mobile app store submission** — needs an EAS/Expo project id + certs (framework fully wired)

### Known operational debt
- **Reverb WebSocket** — realtime notifications need `mod_proxy_http >= 2.4.47` OR `mod_proxy_wstunnel` on Apache; the `infra/apache/proxy.conf` in this repo is deploy-ready but must be copied to the VPS + `httpd` restarted the first time.
- **Deploy `.git` ownership** — auto-recovers when the SSH deploy user has passwordless sudo for `chown`; otherwise a manual `sudo chown -R <user>:<group> /path/to/app/.git` is needed once whenever `root` runs a git op on the box.
- **PDF Amiri font cache** — dompdf downloads the Amiri TTF into `storage/fonts/` on first render. In `testing` env we skip the `@font-face` so tests never hit fonts.gstatic.com.

---

## 🔗 References

- **Backend API base:** `https://attendees.taqatgaza.com/api`
- **Web admin:** `https://attendees.taqatgaza.com`
- **Repository:** https://github.com/ahmaddev27/Attendance
- **Internal docs:** [`docs/v2/`](docs/v2/) (Architecture + ERD + Phase 1 Plan)

---

## 📜 License

Proprietary — TAQAT © 2026
