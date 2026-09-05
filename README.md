# Employee Attendance System

Laravel 11 app for QR-based employee attendance tracking and leave management, with SMS notifications via MTC (Jordan).

## Features

- **Public QR scan flow** — employees check in/out and submit leave requests by scanning a fixed office QR code (no login required)
- **Admin dashboard** — manage employees, review leave requests, view attendance logs, configure anti-fraud rules
- **Configurable fraud protection** — GPS geofence and IP whitelist, each independently toggleable
- **SMS notifications** — employee onboarding + leave approval/rejection decisions via MTC SMS API
- **Auto check-in/out detection** — first scan of the day = check-in, second = check-out
- **Arabic RTL UI**

## Stack

- Laravel 11 · PHP 8.3+
- MySQL 8
- Livewire 3 (admin dashboard)
- Tailwind CSS + Alpine.js
- Laravel Breeze (admin auth)
- Pest (testing)
- MTC SMS HTTP API

## Quick Start

```bash
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan queue:work &
php artisan serve
```

Default admin login: `admin@example.com` / `password`.
Change the password immediately after first login.

## Configuration

All runtime settings live in the `/admin/settings` page — no `.env` edits needed after install:
- GPS check (toggle, office lat/lng, geofence radius)
- IP whitelist (toggle, list of IPs/CIDRs)
- MTC SMS credentials (username, password, sender name)
- Employee number starting value (default 1001)

## Application URLs

| URL | Purpose |
|---|---|
| `/scan` | Public QR landing page (no auth) |
| `/scan/attendance` | Attendance form |
| `/scan/leave` | Leave request form |
| `/admin` | Admin dashboard (requires login) |
| `/admin/employees` | Employee CRUD |
| `/admin/attendance` | Attendance log |
| `/admin/leaves` | Leave request review |
| `/admin/settings` | System configuration |
| `/admin/sms-logs` | SMS delivery audit log |

## Testing

```bash
composer test
```

## Deployment

- Run the queue worker as a supervisor process: `php artisan queue:work --tries=3 --backoff=60`
- Set `Asia/Amman` timezone on the server
- Serve behind HTTPS — browsers require secure context for the geolocation API used by the scan page
- Generate a fresh `APP_KEY` per environment
- Change the seeded admin password before exposing the admin panel
- **Do not reuse the empty `DB_PASSWORD` from local development** in staging or production

## Architecture

Clean layered architecture:

```
Controllers/Livewire → Services → Repositories → Models
```

- **Controllers**: thin, HTTP validation and dispatch only
- **Livewire Components**: reactive admin dashboard state (filters, tables, modals)
- **Services**: all business logic (`EmployeeService`, `AttendanceService`, `LeaveService`, `FraudGuardService`, `SettingsService`, `SmsService`)
- **Repositories**: all DB queries
- **Models**: Eloquent relationships and casts only

SMS is abstracted behind `SmsGatewayInterface` (production: `MtcSmsGateway`, tests: `FakeSmsGateway`).

See `docs/superpowers/specs/` and `docs/superpowers/plans/` for the PRD and implementation plan.
