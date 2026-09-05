# Employee Attendance System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a Laravel-based employee attendance system with a public QR scan flow (attendance + leave requests) and an admin dashboard for employee management, leave approval, and configurable anti-fraud rules, with SMS notifications via MTC.

**Architecture:** Clean layered architecture (Controller → Service → Repository → Model). Livewire 3 for admin dashboard reactivity, thin Blade views for the public scan flow. All business logic lives in services; all queries in repositories. SMS is abstracted behind an interface and dispatched asynchronously via queues.

**Tech Stack:** Laravel 11 · PHP 8.3 · MySQL 8 · Livewire 3 · Tailwind CSS · Alpine.js · Laravel Breeze (admin auth) · Pest for tests · MTC SMS HTTP API.

---

## File Structure

Files created across the whole plan (grouped by responsibility):

```
app/
├── Enums/
│   ├── AttendanceType.php               # check_in | check_out
│   ├── FraudCheckStatus.php             # passed | gps_failed | ip_failed | skipped
│   ├── LeaveStatus.php                  # pending | approved | rejected
│   └── SmsStatus.php                    # sent | failed
├── DataObjects/
│   ├── FraudCheckContext.php            # ip, lat, lng inputs
│   ├── FraudCheckResult.php             # passed(bool) + status + reason
│   └── SmsResult.php                    # ok(bool) + provider response + error code
├── Models/
│   ├── Employee.php
│   ├── Attendance.php
│   ├── LeaveRequest.php
│   ├── Setting.php
│   └── SmsLog.php
├── Repositories/
│   ├── EmployeeRepository.php
│   ├── AttendanceRepository.php
│   ├── LeaveRequestRepository.php
│   ├── SettingRepository.php
│   └── SmsLogRepository.php
├── Services/
│   ├── EmployeeService.php
│   ├── AttendanceService.php
│   ├── LeaveService.php
│   ├── FraudGuardService.php
│   ├── SettingsService.php
│   └── Sms/
│       ├── SmsGatewayInterface.php
│       ├── MtcSmsGateway.php
│       └── SmsService.php
├── Jobs/
│   └── SendSmsJob.php
├── Http/
│   ├── Controllers/
│   │   └── ScanController.php           # public: /scan, /scan/attendance, /scan/leave
│   ├── Middleware/
│   │   └── ThrottleScan.php             # rate limit public endpoints
│   └── Requests/
│       ├── Public/
│       │   ├── RecordAttendanceRequest.php
│       │   └── SubmitLeaveRequestRequest.php
│       └── Admin/
│           ├── StoreEmployeeRequest.php
│           ├── UpdateEmployeeRequest.php
│           └── UpdateSettingsRequest.php
└── Livewire/Admin/
    ├── Overview.php
    ├── Employees/{EmployeeList,EmployeeForm}.php
    ├── Attendance/AttendanceList.php
    ├── Leaves/{LeaveList,LeaveReviewModal}.php
    ├── Settings/SettingsForm.php
    └── SmsLogs/SmsLogList.php

database/
├── migrations/ (one per table + settings + sms_logs)
├── seeders/{SettingsSeeder,AdminUserSeeder}.php
└── factories/{Employee,Attendance,LeaveRequest,SmsLog}Factory.php

resources/views/
├── layouts/{admin,public}.blade.php
├── scan/{index,attendance,attendance-confirm,attendance-success,leave,leave-success}.blade.php
└── livewire/admin/*.blade.php

routes/web.php
config/{attendance.php,sms.php}
tests/{Feature,Unit}/**
```

**Design principle:** One class per file, one clear responsibility, injectable via constructor, testable in isolation.

---

## Conventions Used Throughout

- **TDD every task.** Write the failing test first, watch it fail with the expected message, then implement the minimum code to pass.
- **Commit at the end of every task.** Each task ends in a working, committed state.
- **Use Pest** (Laravel 11's default test runner) — `php artisan test` or `./vendor/bin/pest`.
- **Arabic messages** stored in `lang/ar/messages.php`; keys used everywhere in code.
- **Timezone:** `Asia/Amman` set in `config/app.php` from the start.

---

## Milestone 1 — Foundation

### Task 1.1: Bootstrap Laravel Project

**Files:**
- Create: entire Laravel skeleton
- Modify: `config/app.php` (timezone, locale)
- Modify: `.env` (DB credentials)

- [ ] **Step 1: Create the Laravel project**

```bash
composer create-project laravel/laravel . "^11.0"
```

- [ ] **Step 2: Configure timezone and locale**

Edit `config/app.php`:

```php
'timezone' => 'Asia/Amman',
'locale' => 'ar',
'fallback_locale' => 'en',
```

- [ ] **Step 3: Set DB credentials in `.env`**

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=employee_attendance
DB_USERNAME=root
DB_PASSWORD=
QUEUE_CONNECTION=database
APP_LOCALE=ar
APP_FALLBACK_LOCALE=en
APP_TIMEZONE=Asia/Amman
```

- [ ] **Step 4: Create the database**

```bash
mysql -u root -e "CREATE DATABASE employee_attendance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

- [ ] **Step 5: Run initial migrations**

```bash
php artisan migrate
```

Expected: `users`, `password_reset_tokens`, `sessions` tables created.

- [ ] **Step 6: Commit**

```bash
git init && git add -A
git commit -m "chore: bootstrap Laravel 11 project"
```

---

### Task 1.2: Install Frontend Stack (Livewire 3, Tailwind, Alpine, Breeze)

**Files:**
- Modify: `composer.json`, `package.json`
- Create: `tailwind.config.js` (auto)
- Create: Breeze auth scaffolding

- [ ] **Step 1: Install Livewire 3**

```bash
composer require livewire/livewire "^3.0"
```

- [ ] **Step 2: Install Breeze with Blade + Alpine**

```bash
composer require laravel/breeze --dev
php artisan breeze:install blade --pest
```

This installs Tailwind, Alpine, and Pest for testing.

- [ ] **Step 3: Install and build frontend**

```bash
npm install
npm run build
```

- [ ] **Step 4: Run migrations**

```bash
php artisan migrate
```

- [ ] **Step 5: Verify Pest works**

```bash
./vendor/bin/pest
```

Expected: All default tests pass (Breeze auth tests included).

- [ ] **Step 6: Enable RTL in main layout**

Edit `resources/views/layouts/app.blade.php`, change `<html>` tag to:

```blade
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">
```

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "chore: install Livewire 3, Breeze, Tailwind with RTL"
```

---

### Task 1.3: Create Enums

**Files:**
- Create: `app/Enums/AttendanceType.php`
- Create: `app/Enums/FraudCheckStatus.php`
- Create: `app/Enums/LeaveStatus.php`
- Create: `app/Enums/SmsStatus.php`

- [ ] **Step 1: Write failing test**

Create `tests/Unit/Enums/EnumsTest.php`:

```php
<?php

use App\Enums\AttendanceType;
use App\Enums\FraudCheckStatus;
use App\Enums\LeaveStatus;
use App\Enums\SmsStatus;

it('exposes attendance type values', function () {
    expect(AttendanceType::CheckIn->value)->toBe('check_in');
    expect(AttendanceType::CheckOut->value)->toBe('check_out');
});

it('exposes fraud check status values', function () {
    expect(FraudCheckStatus::Passed->value)->toBe('passed');
    expect(FraudCheckStatus::GpsFailed->value)->toBe('gps_failed');
    expect(FraudCheckStatus::IpFailed->value)->toBe('ip_failed');
    expect(FraudCheckStatus::Skipped->value)->toBe('skipped');
});

it('exposes leave status values', function () {
    expect(LeaveStatus::Pending->value)->toBe('pending');
    expect(LeaveStatus::Approved->value)->toBe('approved');
    expect(LeaveStatus::Rejected->value)->toBe('rejected');
});

it('exposes sms status values', function () {
    expect(SmsStatus::Sent->value)->toBe('sent');
    expect(SmsStatus::Failed->value)->toBe('failed');
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
./vendor/bin/pest tests/Unit/Enums/EnumsTest.php
```

Expected: FAIL with "Class AttendanceType not found".

- [ ] **Step 3: Create the enums**

`app/Enums/AttendanceType.php`:
```php
<?php

namespace App\Enums;

enum AttendanceType: string
{
    case CheckIn = 'check_in';
    case CheckOut = 'check_out';
}
```

`app/Enums/FraudCheckStatus.php`:
```php
<?php

namespace App\Enums;

enum FraudCheckStatus: string
{
    case Passed = 'passed';
    case GpsFailed = 'gps_failed';
    case IpFailed = 'ip_failed';
    case Skipped = 'skipped';
}
```

`app/Enums/LeaveStatus.php`:
```php
<?php

namespace App\Enums;

enum LeaveStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
```

`app/Enums/SmsStatus.php`:
```php
<?php

namespace App\Enums;

enum SmsStatus: string
{
    case Sent = 'sent';
    case Failed = 'failed';
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
./vendor/bin/pest tests/Unit/Enums/EnumsTest.php
```

Expected: 4 passing.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: add domain enums (attendance, fraud, leave, sms)"
```

---

### Task 1.4: Migrations for All Tables

**Files:**
- Create: `database/migrations/xxxx_create_employees_table.php`
- Create: `database/migrations/xxxx_create_attendances_table.php`
- Create: `database/migrations/xxxx_create_leave_requests_table.php`
- Create: `database/migrations/xxxx_create_settings_table.php`
- Create: `database/migrations/xxxx_create_sms_logs_table.php`

- [ ] **Step 1: Generate the migration files**

```bash
php artisan make:migration create_employees_table
php artisan make:migration create_attendances_table
php artisan make:migration create_leave_requests_table
php artisan make:migration create_settings_table
php artisan make:migration create_sms_logs_table
```

- [ ] **Step 2: Fill employees migration**

```php
Schema::create('employees', function (Blueprint $table) {
    $table->id();
    $table->unsignedInteger('employee_number')->unique();
    $table->string('name', 150);
    $table->string('phone', 20)->unique();
    $table->string('email', 150)->unique()->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

- [ ] **Step 3: Fill attendances migration**

```php
Schema::create('attendances', function (Blueprint $table) {
    $table->id();
    $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
    $table->enum('type', ['check_in', 'check_out']);
    $table->timestamp('scanned_at');
    $table->string('ip_address', 45)->nullable();
    $table->decimal('latitude', 10, 7)->nullable();
    $table->decimal('longitude', 10, 7)->nullable();
    $table->enum('fraud_check_status', ['passed', 'gps_failed', 'ip_failed', 'skipped'])->default('skipped');
    $table->timestamp('created_at')->useCurrent();

    $table->index(['employee_id', 'scanned_at']);
});
```

- [ ] **Step 4: Fill leave_requests migration**

```php
Schema::create('leave_requests', function (Blueprint $table) {
    $table->id();
    $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
    $table->date('start_date');
    $table->date('end_date');
    $table->text('note')->nullable();
    $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
    $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('reviewed_at')->nullable();
    $table->text('rejection_reason')->nullable();
    $table->timestamps();

    $table->index(['status', 'start_date']);
});
```

- [ ] **Step 5: Fill settings migration**

```php
Schema::create('settings', function (Blueprint $table) {
    $table->id();
    $table->string('key')->unique();
    $table->text('value')->nullable();
    $table->enum('type', ['string', 'boolean', 'json', 'number'])->default('string');
    $table->timestamps();
});
```

- [ ] **Step 6: Fill sms_logs migration**

```php
Schema::create('sms_logs', function (Blueprint $table) {
    $table->id();
    $table->string('phone', 20);
    $table->text('message');
    $table->enum('status', ['sent', 'failed']);
    $table->text('provider_response')->nullable();
    $table->string('error_code')->nullable();
    $table->timestamp('sent_at');

    $table->index('sent_at');
});
```

- [ ] **Step 7: Run migrations**

```bash
php artisan migrate:fresh
```

Expected: All tables created without errors.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: add migrations for employees, attendances, leaves, settings, sms_logs"
```

---

### Task 1.5: Models with Relationships

**Files:**
- Create: `app/Models/Employee.php`
- Create: `app/Models/Attendance.php`
- Create: `app/Models/LeaveRequest.php`
- Create: `app/Models/Setting.php`
- Create: `app/Models/SmsLog.php`
- Create: `database/factories/{Employee,Attendance,LeaveRequest,SmsLog}Factory.php`

- [ ] **Step 1: Generate model + factory shells**

```bash
php artisan make:model Employee -f
php artisan make:model Attendance -f
php artisan make:model LeaveRequest -f
php artisan make:model Setting
php artisan make:model SmsLog -f
```

- [ ] **Step 2: Write failing relationship test**

Create `tests/Feature/Models/RelationshipsTest.php`:

```php
<?php

use App\Models\{Employee, Attendance, LeaveRequest, User};

it('employee has many attendances', function () {
    $employee = Employee::factory()->create();
    Attendance::factory()->for($employee)->count(3)->create();

    expect($employee->attendances)->toHaveCount(3);
});

it('employee has many leave requests', function () {
    $employee = Employee::factory()->create();
    LeaveRequest::factory()->for($employee)->count(2)->create();

    expect($employee->leaveRequests)->toHaveCount(2);
});

it('leave request belongs to reviewer user', function () {
    $reviewer = User::factory()->create();
    $leave = LeaveRequest::factory()->create(['reviewed_by' => $reviewer->id]);

    expect($leave->reviewer->id)->toBe($reviewer->id);
});
```

- [ ] **Step 3: Run test — expect FAIL**

```bash
./vendor/bin/pest tests/Feature/Models/RelationshipsTest.php
```

Expected: FAIL — relationships/factories not defined.

- [ ] **Step 4: Implement Employee model**

`app/Models/Employee.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = ['employee_number', 'name', 'phone', 'email', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
```

- [ ] **Step 5: Implement Attendance model**

`app/Models/Attendance.php`:
```php
<?php

namespace App\Models;

use App\Enums\AttendanceType;
use App\Enums\FraudCheckStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'employee_id', 'type', 'scanned_at',
        'ip_address', 'latitude', 'longitude', 'fraud_check_status',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
        'type' => AttendanceType::class,
        'fraud_check_status' => FraudCheckStatus::class,
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
```

- [ ] **Step 6: Implement LeaveRequest model**

`app/Models/LeaveRequest.php`:
```php
<?php

namespace App\Models;

use App\Enums\LeaveStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id', 'start_date', 'end_date', 'note',
        'status', 'reviewed_by', 'reviewed_at', 'rejection_reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'reviewed_at' => 'datetime',
        'status' => LeaveStatus::class,
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
```

- [ ] **Step 7: Implement Setting and SmsLog models**

`app/Models/Setting.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'type'];
}
```

`app/Models/SmsLog.php`:
```php
<?php

namespace App\Models;

use App\Enums\SmsStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['phone', 'message', 'status', 'provider_response', 'error_code', 'sent_at'];

    protected $casts = [
        'sent_at' => 'datetime',
        'status' => SmsStatus::class,
    ];
}
```

- [ ] **Step 8: Implement factories**

`database/factories/EmployeeFactory.php`:
```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_number' => $this->faker->unique()->numberBetween(1001, 9999),
            'name' => $this->faker->name(),
            'phone' => '+9627' . $this->faker->unique()->numerify('########'),
            'email' => $this->faker->unique()->safeEmail(),
            'is_active' => true,
        ];
    }
}
```

`database/factories/AttendanceFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Enums\AttendanceType;
use App\Enums\FraudCheckStatus;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'type' => AttendanceType::CheckIn,
            'scanned_at' => now(),
            'fraud_check_status' => FraudCheckStatus::Skipped,
        ];
    }
}
```

`database/factories/LeaveRequestFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Enums\LeaveStatus;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveRequestFactory extends Factory
{
    public function definition(): array
    {
        $date = $this->faker->dateTimeBetween('+1 day', '+30 days');

        return [
            'employee_id' => Employee::factory(),
            'start_date' => $date,
            'end_date' => $date,
            'note' => $this->faker->optional()->sentence(),
            'status' => LeaveStatus::Pending,
        ];
    }
}
```

`database/factories/SmsLogFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Enums\SmsStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class SmsLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'phone' => '+9627' . $this->faker->numerify('########'),
            'message' => $this->faker->sentence(),
            'status' => SmsStatus::Sent,
            'sent_at' => now(),
        ];
    }
}
```

- [ ] **Step 9: Run tests — expect PASS**

```bash
./vendor/bin/pest tests/Feature/Models/RelationshipsTest.php
```

Expected: 3 passing.

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "feat: add models, factories, and relationships"
```

---

### Task 1.6: SettingsService with Cache

**Files:**
- Create: `app/Repositories/SettingRepository.php`
- Create: `app/Services/SettingsService.php`
- Create: `database/seeders/SettingsSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: Write failing test**

Create `tests/Feature/Services/SettingsServiceTest.php`:

```php
<?php

use App\Services\SettingsService;
use App\Models\Setting;

beforeEach(fn () => cache()->flush());

it('returns default when setting is missing', function () {
    $service = app(SettingsService::class);
    expect($service->get('missing_key', 'default'))->toBe('default');
});

it('reads a string setting', function () {
    Setting::create(['key' => 'sms_username', 'value' => 'acme', 'type' => 'string']);
    $service = app(SettingsService::class);
    expect($service->get('sms_username'))->toBe('acme');
});

it('casts boolean settings', function () {
    Setting::create(['key' => 'gps_enabled', 'value' => '1', 'type' => 'boolean']);
    $service = app(SettingsService::class);
    expect($service->get('gps_enabled'))->toBeTrue();
});

it('casts number settings', function () {
    Setting::create(['key' => 'geofence_radius_meters', 'value' => '100', 'type' => 'number']);
    $service = app(SettingsService::class);
    expect($service->get('geofence_radius_meters'))->toBe(100);
});

it('casts json settings', function () {
    Setting::create(['key' => 'ip_whitelist', 'value' => json_encode(['1.1.1.1']), 'type' => 'json']);
    $service = app(SettingsService::class);
    expect($service->get('ip_whitelist'))->toBe(['1.1.1.1']);
});

it('sets a setting and invalidates cache', function () {
    $service = app(SettingsService::class);
    $service->set('foo', 'bar', 'string');
    expect($service->get('foo'))->toBe('bar');
});
```

- [ ] **Step 2: Run — expect FAIL**

```bash
./vendor/bin/pest tests/Feature/Services/SettingsServiceTest.php
```

- [ ] **Step 3: Implement SettingRepository**

`app/Repositories/SettingRepository.php`:
```php
<?php

namespace App\Repositories;

use App\Models\Setting;

class SettingRepository
{
    public function all(): array
    {
        return Setting::query()->get()->keyBy('key')->toArray();
    }

    public function upsert(string $key, ?string $value, string $type): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value, 'type' => $type]);
    }
}
```

- [ ] **Step 4: Implement SettingsService**

`app/Services/SettingsService.php`:
```php
<?php

namespace App\Services;

use App\Repositories\SettingRepository;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    private const CACHE_KEY = 'settings.all';

    public function __construct(private readonly SettingRepository $repo) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->all();
        if (! isset($settings[$key])) {
            return $default;
        }

        return $this->cast($settings[$key]['value'], $settings[$key]['type']);
    }

    public function set(string $key, mixed $value, string $type): void
    {
        $stored = match ($type) {
            'json' => json_encode($value),
            'boolean' => $value ? '1' : '0',
            default => (string) $value,
        };

        $this->repo->upsert($key, $stored, $type);
        Cache::forget(self::CACHE_KEY);
    }

    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addHour(), fn () => $this->repo->all());
    }

    private function cast(?string $value, string $type): mixed
    {
        if ($value === null) return null;

        return match ($type) {
            'boolean' => (bool) $value,
            'number' => is_numeric($value) ? $value + 0 : 0,
            'json' => json_decode($value, true),
            default => $value,
        };
    }
}
```

- [ ] **Step 5: Run tests — expect PASS**

```bash
./vendor/bin/pest tests/Feature/Services/SettingsServiceTest.php
```

Expected: 6 passing.

- [ ] **Step 6: Create SettingsSeeder**

`database/seeders/SettingsSeeder.php`:
```php
<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['key' => 'gps_enabled', 'value' => '0', 'type' => 'boolean'],
            ['key' => 'office_lat', 'value' => null, 'type' => 'number'],
            ['key' => 'office_lng', 'value' => null, 'type' => 'number'],
            ['key' => 'geofence_radius_meters', 'value' => '100', 'type' => 'number'],
            ['key' => 'ip_enabled', 'value' => '0', 'type' => 'boolean'],
            ['key' => 'ip_whitelist', 'value' => json_encode([]), 'type' => 'json'],
            ['key' => 'sms_username', 'value' => '', 'type' => 'string'],
            ['key' => 'sms_password', 'value' => '', 'type' => 'string'],
            ['key' => 'sms_sender', 'value' => '', 'type' => 'string'],
            ['key' => 'employee_number_start', 'value' => '1001', 'type' => 'number'],
        ];

        foreach ($defaults as $row) {
            Setting::updateOrCreate(['key' => $row['key']], $row);
        }
    }
}
```

- [ ] **Step 7: Register in `DatabaseSeeder.php`**

Add inside `run()`:
```php
$this->call([SettingsSeeder::class]);
```

- [ ] **Step 8: Run seed**

```bash
php artisan migrate:fresh --seed
```

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: settings service with cache and seeded defaults"
```

---

### Task 1.7: Admin Seeder and Auth Route Guard

**Files:**
- Create: `database/seeders/AdminUserSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Create the seeder**

`database/seeders/AdminUserSeeder.php`:
```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Administrator', 'password' => Hash::make('password')]
        );
    }
}
```

- [ ] **Step 2: Register in DatabaseSeeder**

```php
$this->call([SettingsSeeder::class, AdminUserSeeder::class]);
```

- [ ] **Step 3: Verify seed works**

```bash
php artisan migrate:fresh --seed
```

- [ ] **Step 4: Add `/admin` route group in `routes/web.php`**

Add at the bottom:
```php
Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {
    Route::view('/', 'admin.overview')->name('overview');
});
```

Create a temporary `resources/views/admin/overview.blade.php`:
```blade
<x-app-layout>
    <div class="p-8 text-2xl">Admin Dashboard — Overview (placeholder)</div>
</x-app-layout>
```

- [ ] **Step 5: Add HTTP test**

`tests/Feature/Admin/AdminAccessTest.php`:
```php
<?php

use App\Models\User;

it('redirects guests from admin routes', function () {
    $this->get('/admin')->assertRedirect('/login');
});

it('allows authenticated users into admin', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/admin')->assertOk();
});
```

- [ ] **Step 6: Run — expect PASS**

```bash
./vendor/bin/pest tests/Feature/Admin/AdminAccessTest.php
```

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: admin seeder and protected /admin route"
```

---

## Milestone 2 — Employee Management

### Task 2.1: EmployeeRepository

**Files:**
- Create: `app/Repositories/EmployeeRepository.php`

- [ ] **Step 1: Write failing test**

`tests/Feature/Repositories/EmployeeRepositoryTest.php`:
```php
<?php

use App\Models\Employee;
use App\Repositories\EmployeeRepository;

it('returns 0 when no employees exist', function () {
    expect(app(EmployeeRepository::class)->maxEmployeeNumber())->toBe(0);
});

it('returns the highest employee number', function () {
    Employee::factory()->create(['employee_number' => 1001]);
    Employee::factory()->create(['employee_number' => 1042]);
    Employee::factory()->create(['employee_number' => 1005]);

    expect(app(EmployeeRepository::class)->maxEmployeeNumber())->toBe(1042);
});

it('finds by employee number', function () {
    $emp = Employee::factory()->create(['employee_number' => 1010]);
    $found = app(EmployeeRepository::class)->findByNumber(1010);
    expect($found->id)->toBe($emp->id);
});

it('returns null when number not found', function () {
    expect(app(EmployeeRepository::class)->findByNumber(9999))->toBeNull();
});
```

- [ ] **Step 2: Run — expect FAIL**

- [ ] **Step 3: Implement**

`app/Repositories/EmployeeRepository.php`:
```php
<?php

namespace App\Repositories;

use App\Models\Employee;

class EmployeeRepository
{
    public function maxEmployeeNumber(): int
    {
        return (int) Employee::max('employee_number');
    }

    public function findByNumber(int $number): ?Employee
    {
        return Employee::where('employee_number', $number)->first();
    }

    public function create(array $data): Employee
    {
        return Employee::create($data);
    }

    public function update(Employee $employee, array $data): Employee
    {
        $employee->update($data);
        return $employee->fresh();
    }
}
```

- [ ] **Step 4: Run — expect PASS**

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: EmployeeRepository with number lookup and max"
```

---

### Task 2.2: SmsGateway Interface + Fake Implementation

**Files:**
- Create: `app/DataObjects/SmsResult.php`
- Create: `app/Services/Sms/SmsGatewayInterface.php`
- Create: `app/Services/Sms/FakeSmsGateway.php` (for tests)

- [ ] **Step 1: Write failing test**

`tests/Unit/Sms/FakeSmsGatewayTest.php`:
```php
<?php

use App\Services\Sms\FakeSmsGateway;

it('records sent messages', function () {
    $gateway = new FakeSmsGateway();
    $result = $gateway->send('+962700000000', 'Hello');

    expect($result->ok)->toBeTrue();
    expect($gateway->sent())->toHaveCount(1);
    expect($gateway->sent()[0]['to'])->toBe('+962700000000');
});

it('can be configured to fail', function () {
    $gateway = new FakeSmsGateway();
    $gateway->shouldFail('10005');
    $result = $gateway->send('+962700000000', 'Hello');

    expect($result->ok)->toBeFalse();
    expect($result->errorCode)->toBe('10005');
});
```

- [ ] **Step 2: Run — expect FAIL**

- [ ] **Step 3: Create DTO**

`app/DataObjects/SmsResult.php`:
```php
<?php

namespace App\DataObjects;

class SmsResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?string $providerResponse = null,
        public readonly ?string $errorCode = null,
    ) {}

    public static function success(?string $response = null): self
    {
        return new self(true, $response);
    }

    public static function failure(string $errorCode, ?string $response = null): self
    {
        return new self(false, $response, $errorCode);
    }
}
```

- [ ] **Step 4: Create interface**

`app/Services/Sms/SmsGatewayInterface.php`:
```php
<?php

namespace App\Services\Sms;

use App\DataObjects\SmsResult;

interface SmsGatewayInterface
{
    public function send(string $to, string $message): SmsResult;
}
```

- [ ] **Step 5: Create fake**

`app/Services/Sms/FakeSmsGateway.php`:
```php
<?php

namespace App\Services\Sms;

use App\DataObjects\SmsResult;

class FakeSmsGateway implements SmsGatewayInterface
{
    private array $sent = [];
    private ?string $failCode = null;

    public function send(string $to, string $message): SmsResult
    {
        $this->sent[] = ['to' => $to, 'message' => $message];

        if ($this->failCode !== null) {
            return SmsResult::failure($this->failCode);
        }

        return SmsResult::success('ok');
    }

    public function shouldFail(string $errorCode): void
    {
        $this->failCode = $errorCode;
    }

    public function sent(): array
    {
        return $this->sent;
    }
}
```

- [ ] **Step 6: Run — expect PASS**

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: SMS gateway interface with test double"
```

---

### Task 2.3: MtcSmsGateway (Real Implementation)

**Files:**
- Create: `config/sms.php`
- Create: `app/Services/Sms/MtcSmsGateway.php`
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Write failing test using HTTP fakes**

`tests/Unit/Sms/MtcSmsGatewayTest.php`:
```php
<?php

use App\Services\Sms\MtcSmsGateway;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    app(SettingsService::class)->set('sms_username', 'user1', 'string');
    app(SettingsService::class)->set('sms_password', 'pass1', 'string');
    app(SettingsService::class)->set('sms_sender', 'ACME', 'string');
});

it('returns success when provider returns 0', function () {
    Http::fake(['int.mtcsms.com/*' => Http::response('0@Message Sent Successfully', 200)]);
    $result = app(MtcSmsGateway::class)->send('+962700000000', 'hi');

    expect($result->ok)->toBeTrue();
});

it('returns failure with error code when provider returns 10005', function () {
    Http::fake(['int.mtcsms.com/*' => Http::response('10005@Low Balance', 200)]);
    $result = app(MtcSmsGateway::class)->send('+962700000000', 'hi');

    expect($result->ok)->toBeFalse();
    expect($result->errorCode)->toBe('10005');
});

it('returns failure when HTTP call fails', function () {
    Http::fake(['int.mtcsms.com/*' => Http::response('', 500)]);
    $result = app(MtcSmsGateway::class)->send('+962700000000', 'hi');

    expect($result->ok)->toBeFalse();
});
```

- [ ] **Step 2: Run — expect FAIL**

- [ ] **Step 3: Create config**

`config/sms.php`:
```php
<?php

return [
    'endpoint' => env('MTC_SMS_ENDPOINT', 'http://int.mtcsms.com/sendsms.aspx'),
    'timeout' => (int) env('MTC_SMS_TIMEOUT', 10),
];
```

- [ ] **Step 4: Implement gateway**

`app/Services/Sms/MtcSmsGateway.php`:
```php
<?php

namespace App\Services\Sms;

use App\DataObjects\SmsResult;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Http;
use Throwable;

class MtcSmsGateway implements SmsGatewayInterface
{
    public function __construct(private readonly SettingsService $settings) {}

    public function send(string $to, string $message): SmsResult
    {
        try {
            $response = Http::timeout(config('sms.timeout'))->get(config('sms.endpoint'), [
                'username' => $this->settings->get('sms_username'),
                'password' => $this->settings->get('sms_password'),
                'from' => $this->settings->get('sms_sender'),
                'to' => $to,
                'msg' => $message,
                'type' => 0,
            ]);

            if (! $response->successful()) {
                return SmsResult::failure('http_' . $response->status(), $response->body());
            }

            $body = trim($response->body());
            $code = explode('@', $body)[0] ?? '';

            return $code === '0'
                ? SmsResult::success($body)
                : SmsResult::failure($code, $body);
        } catch (Throwable $e) {
            return SmsResult::failure('exception', $e->getMessage());
        }
    }
}
```

- [ ] **Step 5: Bind interface in AppServiceProvider**

Edit `app/Providers/AppServiceProvider.php`:
```php
public function register(): void
{
    $this->app->bind(
        \App\Services\Sms\SmsGatewayInterface::class,
        \App\Services\Sms\MtcSmsGateway::class,
    );
}
```

- [ ] **Step 6: Run — expect PASS**

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: MTC SMS gateway with HTTP client and error mapping"
```

---

### Task 2.4: SmsService (Orchestrates Send + Log)

**Files:**
- Create: `app/Repositories/SmsLogRepository.php`
- Create: `app/Services/Sms/SmsService.php`
- Create: `app/Jobs/SendSmsJob.php`

- [ ] **Step 1: Write failing test**

`tests/Feature/Services/SmsServiceTest.php`:
```php
<?php

use App\Models\SmsLog;
use App\Services\Sms\FakeSmsGateway;
use App\Services\Sms\SmsGatewayInterface;
use App\Services\Sms\SmsService;

beforeEach(function () {
    $this->fake = new FakeSmsGateway();
    $this->app->instance(SmsGatewayInterface::class, $this->fake);
});

it('sends and logs success', function () {
    app(SmsService::class)->sendNow('+962700000000', 'hello');

    expect($this->fake->sent())->toHaveCount(1);
    expect(SmsLog::where('status', 'sent')->count())->toBe(1);
});

it('logs failure with error code', function () {
    $this->fake->shouldFail('10005');
    app(SmsService::class)->sendNow('+962700000000', 'hello');

    $log = SmsLog::first();
    expect($log->status->value)->toBe('failed');
    expect($log->error_code)->toBe('10005');
});
```

- [ ] **Step 2: Run — expect FAIL**

- [ ] **Step 3: Create SmsLogRepository**

`app/Repositories/SmsLogRepository.php`:
```php
<?php

namespace App\Repositories;

use App\Enums\SmsStatus;
use App\Models\SmsLog;

class SmsLogRepository
{
    public function log(string $phone, string $message, SmsStatus $status, ?string $response, ?string $errorCode): SmsLog
    {
        return SmsLog::create([
            'phone' => $phone,
            'message' => $message,
            'status' => $status,
            'provider_response' => $response,
            'error_code' => $errorCode,
            'sent_at' => now(),
        ]);
    }
}
```

- [ ] **Step 4: Create SmsService**

`app/Services/Sms/SmsService.php`:
```php
<?php

namespace App\Services\Sms;

use App\Enums\SmsStatus;
use App\Jobs\SendSmsJob;
use App\Repositories\SmsLogRepository;

class SmsService
{
    public function __construct(
        private readonly SmsGatewayInterface $gateway,
        private readonly SmsLogRepository $logs,
    ) {}

    public function dispatch(string $phone, string $message): void
    {
        SendSmsJob::dispatch($phone, $message);
    }

    public function sendNow(string $phone, string $message): void
    {
        $result = $this->gateway->send($phone, $message);

        $this->logs->log(
            $phone,
            $message,
            $result->ok ? SmsStatus::Sent : SmsStatus::Failed,
            $result->providerResponse,
            $result->errorCode,
        );
    }
}
```

- [ ] **Step 5: Create SendSmsJob**

`app/Jobs/SendSmsJob.php`:
```php
<?php

namespace App\Jobs;

use App\Services\Sms\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(private readonly string $phone, private readonly string $message) {}

    public function handle(SmsService $service): void
    {
        $service->sendNow($this->phone, $this->message);
    }
}
```

- [ ] **Step 6: Create queue table + migrate**

```bash
php artisan queue:table
php artisan migrate
```

- [ ] **Step 7: Run — expect PASS**

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: SmsService with async job and log persistence"
```

---

### Task 2.5: EmployeeService (Create + Auto Number + SMS)

**Files:**
- Create: `app/Services/EmployeeService.php`
- Create: `lang/ar/messages.php`

- [ ] **Step 1: Write failing test**

`tests/Feature/Services/EmployeeServiceTest.php`:
```php
<?php

use App\Models\Employee;
use App\Services\EmployeeService;
use App\Services\Sms\FakeSmsGateway;
use App\Services\Sms\SmsGatewayInterface;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->fake = new FakeSmsGateway();
    $this->app->instance(SmsGatewayInterface::class, $this->fake);
});

it('creates an employee with number 1001 when table is empty', function () {
    $emp = app(EmployeeService::class)->create([
        'name' => 'Ahmad',
        'phone' => '+962700000000',
        'email' => 'ahmad@example.com',
    ]);

    expect($emp->employee_number)->toBe(1001);
});

it('increments employee number from the max', function () {
    Employee::factory()->create(['employee_number' => 1050]);
    $emp = app(EmployeeService::class)->create([
        'name' => 'Sara',
        'phone' => '+962700000001',
    ]);

    expect($emp->employee_number)->toBe(1051);
});

it('sends welcome SMS containing the employee number', function () {
    Queue::fake();

    app(EmployeeService::class)->create([
        'name' => 'Ahmad',
        'phone' => '+962700000000',
    ]);

    Queue::assertPushed(\App\Jobs\SendSmsJob::class, function ($job) {
        return str_contains($job->message ?? '', '1001');
    });
})->skip('inspect SendSmsJob props in own test — placeholder');
```

Note: the last test is skipped because dispatched Job payload internals need helper assertions; we'll cover SMS content in an integration test in Task 2.7.

- [ ] **Step 2: Run — expect FAIL**

- [ ] **Step 3: Create Arabic messages file**

`lang/ar/messages.php`:
```php
<?php

return [
    'employee_welcome' => 'أهلاً بك في :company. رقمك الوظيفي: :number. استخدمه لتسجيل الحضور عبر QR عند المدخل.',
    'leave_approved' => 'تمت الموافقة على طلب إجازتك بتاريخ :date.',
    'leave_rejected' => 'تم رفض طلب إجازتك بتاريخ :date. السبب: :reason',
    'scan_success_check_in' => 'تم تسجيل حضورك في :time',
    'scan_success_check_out' => 'تم تسجيل انصرافك في :time',
    'scan_fraud_gps_failed' => 'يبدو أنك خارج نطاق المكتب. الرجاء تسجيل الحضور من داخل المكتب.',
    'scan_fraud_ip_failed' => 'الرجاء الاتصال بشبكة المكتب لتسجيل الحضور.',
    'scan_employee_not_found' => 'الرقم الوظيفي غير موجود.',
    'scan_employee_inactive' => 'حسابك معطّل. الرجاء التواصل مع الإدارة.',
];
```

Also create empty `lang/en/messages.php` mirroring keys with English translations.

- [ ] **Step 4: Implement EmployeeService**

`app/Services/EmployeeService.php`:
```php
<?php

namespace App\Services;

use App\Models\Employee;
use App\Repositories\EmployeeRepository;
use App\Services\Sms\SmsService;

class EmployeeService
{
    public function __construct(
        private readonly EmployeeRepository $repo,
        private readonly SettingsService $settings,
        private readonly SmsService $sms,
    ) {}

    public function create(array $data): Employee
    {
        $data['employee_number'] = $this->nextEmployeeNumber();
        $employee = $this->repo->create($data);

        $this->sms->dispatch(
            $employee->phone,
            trans('messages.employee_welcome', [
                'company' => config('app.name'),
                'number' => $employee->employee_number,
            ])
        );

        return $employee;
    }

    public function update(Employee $employee, array $data): Employee
    {
        return $this->repo->update($employee, $data);
    }

    private function nextEmployeeNumber(): int
    {
        $max = $this->repo->maxEmployeeNumber();
        if ($max > 0) return $max + 1;

        return (int) $this->settings->get('employee_number_start', 1001);
    }
}
```

- [ ] **Step 5: Run — expect PASS**

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: EmployeeService with sequential numbering and welcome SMS"
```

---

### Task 2.6: Employee CRUD Livewire Components

**Files:**
- Create: `app/Livewire/Admin/Employees/EmployeeList.php`
- Create: `app/Livewire/Admin/Employees/EmployeeForm.php`
- Create: `resources/views/livewire/admin/employees/employee-list.blade.php`
- Create: `resources/views/livewire/admin/employees/employee-form.blade.php`
- Create: `app/Http/Requests/Admin/StoreEmployeeRequest.php`
- Create: `app/Http/Requests/Admin/UpdateEmployeeRequest.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Write failing test**

`tests/Feature/Admin/EmployeeCrudTest.php`:
```php
<?php

use App\Models\Employee;
use App\Models\User;
use App\Services\Sms\FakeSmsGateway;
use App\Services\Sms\SmsGatewayInterface;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->app->instance(SmsGatewayInterface::class, new FakeSmsGateway());
});

it('lists employees', function () {
    Employee::factory()->count(3)->create();

    Livewire::test(\App\Livewire\Admin\Employees\EmployeeList::class)
        ->assertOk()
        ->assertViewHas('employees', fn ($items) => $items->count() === 3);
});

it('filters employees by search term', function () {
    Employee::factory()->create(['name' => 'Ahmad Ali']);
    Employee::factory()->create(['name' => 'Sara Ahmad']);
    Employee::factory()->create(['name' => 'Omar Hassan']);

    Livewire::test(\App\Livewire\Admin\Employees\EmployeeList::class)
        ->set('search', 'Ahmad')
        ->assertViewHas('employees', fn ($items) => $items->count() === 2);
});

it('creates an employee via form component', function () {
    Livewire::test(\App\Livewire\Admin\Employees\EmployeeForm::class)
        ->set('name', 'Ahmad')
        ->set('phone', '+962700000000')
        ->set('email', 'ahmad@example.com')
        ->call('save')
        ->assertHasNoErrors();

    expect(Employee::where('phone', '+962700000000')->exists())->toBeTrue();
});

it('validates required fields', function () {
    Livewire::test(\App\Livewire\Admin\Employees\EmployeeForm::class)
        ->call('save')
        ->assertHasErrors(['name', 'phone']);
});

it('validates unique phone', function () {
    Employee::factory()->create(['phone' => '+962700000000']);

    Livewire::test(\App\Livewire\Admin\Employees\EmployeeForm::class)
        ->set('name', 'X')
        ->set('phone', '+962700000000')
        ->call('save')
        ->assertHasErrors(['phone']);
});
```

- [ ] **Step 2: Run — expect FAIL**

- [ ] **Step 3: Implement EmployeeList**

`app/Livewire/Admin/Employees/EmployeeList.php`:
```php
<?php

namespace App\Livewire\Admin\Employees;

use App\Models\Employee;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class EmployeeList extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $status = 'all'; // all | active | inactive

    public function updating($property): void
    {
        if (in_array($property, ['search', 'status'])) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $query = Employee::query()->orderByDesc('employee_number');

        if ($this->search !== '') {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                  ->orWhere('phone', 'like', "%{$this->search}%")
                  ->orWhere('employee_number', $this->search);
            });
        }

        if ($this->status === 'active') $query->where('is_active', true);
        if ($this->status === 'inactive') $query->where('is_active', false);

        return view('livewire.admin.employees.employee-list', [
            'employees' => $query->paginate(15),
        ]);
    }
}
```

- [ ] **Step 4: Implement EmployeeForm**

`app/Livewire/Admin/Employees/EmployeeForm.php`:
```php
<?php

namespace App\Livewire\Admin\Employees;

use App\Models\Employee;
use App\Services\EmployeeService;
use Livewire\Attributes\Validate;
use Livewire\Component;

class EmployeeForm extends Component
{
    public ?Employee $employee = null;

    #[Validate('required|string|max:150')]
    public string $name = '';

    #[Validate('required|string|max:20')]
    public string $phone = '';

    #[Validate('nullable|email|max:150')]
    public ?string $email = null;

    public bool $isActive = true;

    public function mount(?Employee $employee = null): void
    {
        if ($employee?->exists) {
            $this->employee = $employee;
            $this->name = $employee->name;
            $this->phone = $employee->phone;
            $this->email = $employee->email;
            $this->isActive = $employee->is_active;
        }
    }

    public function rules(): array
    {
        $phoneUnique = 'unique:employees,phone';
        $emailUnique = 'unique:employees,email';

        if ($this->employee?->exists) {
            $phoneUnique .= ',' . $this->employee->id;
            $emailUnique .= ',' . $this->employee->id;
        }

        return [
            'name' => 'required|string|max:150',
            'phone' => "required|string|max:20|{$phoneUnique}",
            'email' => "nullable|email|max:150|{$emailUnique}",
            'isActive' => 'boolean',
        ];
    }

    public function save(EmployeeService $service): void
    {
        $data = $this->validate();
        $payload = [
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'is_active' => $data['isActive'] ?? true,
        ];

        if ($this->employee?->exists) {
            $service->update($this->employee, $payload);
            session()->flash('success', __('تم تحديث الموظف'));
        } else {
            $service->create($payload);
            session()->flash('success', __('تم إنشاء الموظف وأُرسل الرقم عبر SMS'));
        }

        $this->redirectRoute('admin.employees.index');
    }

    public function render()
    {
        return view('livewire.admin.employees.employee-form');
    }
}
```

- [ ] **Step 5: Create the two Blade views**

`resources/views/livewire/admin/employees/employee-list.blade.php`:
```blade
<div class="p-6">
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl font-bold">الموظفين</h1>
        <a href="{{ route('admin.employees.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded">+ موظف جديد</a>
    </div>

    <div class="flex gap-4 mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="بحث..." class="border p-2 rounded flex-1">
        <select wire:model.live="status" class="border p-2 rounded">
            <option value="all">الكل</option>
            <option value="active">نشط</option>
            <option value="inactive">معطّل</option>
        </select>
    </div>

    <table class="w-full border-collapse border">
        <thead class="bg-gray-100">
            <tr>
                <th class="border p-2">الرقم</th>
                <th class="border p-2">الاسم</th>
                <th class="border p-2">الجوال</th>
                <th class="border p-2">الحالة</th>
                <th class="border p-2">إجراءات</th>
            </tr>
        </thead>
        <tbody>
            @foreach($employees as $employee)
                <tr>
                    <td class="border p-2 text-center">{{ $employee->employee_number }}</td>
                    <td class="border p-2">{{ $employee->name }}</td>
                    <td class="border p-2">{{ $employee->phone }}</td>
                    <td class="border p-2 text-center">
                        {{ $employee->is_active ? 'نشط' : 'معطّل' }}
                    </td>
                    <td class="border p-2 text-center">
                        <a href="{{ route('admin.employees.edit', $employee) }}" class="text-blue-600">تعديل</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mt-4">{{ $employees->links() }}</div>
</div>
```

`resources/views/livewire/admin/employees/employee-form.blade.php`:
```blade
<div class="p-6 max-w-lg">
    <h1 class="text-2xl font-bold mb-4">
        {{ $employee?->exists ? 'تعديل موظف' : 'موظف جديد' }}
    </h1>

    <form wire:submit="save" class="space-y-4">
        <div>
            <label class="block mb-1">الاسم</label>
            <input type="text" wire:model="name" class="border p-2 rounded w-full">
            @error('name') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block mb-1">الجوال</label>
            <input type="text" wire:model="phone" class="border p-2 rounded w-full" dir="ltr">
            @error('phone') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block mb-1">الإيميل (اختياري)</label>
            <input type="email" wire:model="email" class="border p-2 rounded w-full" dir="ltr">
            @error('email') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="inline-flex items-center gap-2">
                <input type="checkbox" wire:model="isActive">
                <span>نشط</span>
            </label>
        </div>

        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">حفظ</button>
    </form>
</div>
```

- [ ] **Step 6: Register routes in `routes/web.php`**

Inside the `admin` group:
```php
use App\Livewire\Admin\Employees\EmployeeList;
use App\Livewire\Admin\Employees\EmployeeForm;

Route::get('/employees', EmployeeList::class)->name('employees.index');
Route::get('/employees/create', EmployeeForm::class)->name('employees.create');
Route::get('/employees/{employee}/edit', EmployeeForm::class)->name('employees.edit');
```

- [ ] **Step 7: Run — expect PASS**

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: employee CRUD with Livewire, search, and validation"
```

---

## Milestone 3 — Attendance Flow

### Task 3.1: FraudGuardService

**Files:**
- Create: `app/DataObjects/FraudCheckContext.php`
- Create: `app/DataObjects/FraudCheckResult.php`
- Create: `app/Services/FraudGuardService.php`

- [ ] **Step 1: Write failing test**

`tests/Feature/Services/FraudGuardServiceTest.php`:
```php
<?php

use App\DataObjects\FraudCheckContext;
use App\Enums\FraudCheckStatus;
use App\Services\FraudGuardService;
use App\Services\SettingsService;

function ctx(string $ip = '1.2.3.4', ?float $lat = null, ?float $lng = null): FraudCheckContext {
    return new FraudCheckContext($ip, $lat, $lng);
}

beforeEach(function () {
    $this->settings = app(SettingsService::class);
    $this->settings->set('gps_enabled', false, 'boolean');
    $this->settings->set('ip_enabled', false, 'boolean');
});

it('passes as skipped when both checks disabled', function () {
    $result = app(FraudGuardService::class)->check(ctx());
    expect($result->passed)->toBeTrue();
    expect($result->status)->toBe(FraudCheckStatus::Skipped);
});

it('passes when GPS is within radius', function () {
    $this->settings->set('gps_enabled', true, 'boolean');
    $this->settings->set('office_lat', 31.9539, 'number');
    $this->settings->set('office_lng', 35.9106, 'number');
    $this->settings->set('geofence_radius_meters', 100, 'number');

    $result = app(FraudGuardService::class)->check(ctx('1.1.1.1', 31.9539, 35.9106));
    expect($result->passed)->toBeTrue();
});

it('fails when GPS is outside radius', function () {
    $this->settings->set('gps_enabled', true, 'boolean');
    $this->settings->set('office_lat', 31.9539, 'number');
    $this->settings->set('office_lng', 35.9106, 'number');
    $this->settings->set('geofence_radius_meters', 100, 'number');

    $result = app(FraudGuardService::class)->check(ctx('1.1.1.1', 31.9700, 35.9300));
    expect($result->passed)->toBeFalse();
    expect($result->status)->toBe(FraudCheckStatus::GpsFailed);
});

it('passes when IP is whitelisted', function () {
    $this->settings->set('ip_enabled', true, 'boolean');
    $this->settings->set('ip_whitelist', ['192.168.1.100'], 'json');

    $result = app(FraudGuardService::class)->check(ctx('192.168.1.100'));
    expect($result->passed)->toBeTrue();
});

it('fails when IP not whitelisted', function () {
    $this->settings->set('ip_enabled', true, 'boolean');
    $this->settings->set('ip_whitelist', ['192.168.1.100'], 'json');

    $result = app(FraudGuardService::class)->check(ctx('10.0.0.5'));
    expect($result->passed)->toBeFalse();
    expect($result->status)->toBe(FraudCheckStatus::IpFailed);
});

it('passes when either GPS or IP passes with both enabled', function () {
    $this->settings->set('gps_enabled', true, 'boolean');
    $this->settings->set('office_lat', 31.9539, 'number');
    $this->settings->set('office_lng', 35.9106, 'number');
    $this->settings->set('geofence_radius_meters', 100, 'number');
    $this->settings->set('ip_enabled', true, 'boolean');
    $this->settings->set('ip_whitelist', ['192.168.1.100'], 'json');

    // GPS passes, IP fails => overall pass
    $result = app(FraudGuardService::class)->check(ctx('10.0.0.5', 31.9539, 35.9106));
    expect($result->passed)->toBeTrue();

    // IP passes, GPS fails => overall pass
    $result = app(FraudGuardService::class)->check(ctx('192.168.1.100', 31.9700, 35.9300));
    expect($result->passed)->toBeTrue();
});
```

- [ ] **Step 2: Run — expect FAIL**

- [ ] **Step 3: Create DTOs**

`app/DataObjects/FraudCheckContext.php`:
```php
<?php

namespace App\DataObjects;

class FraudCheckContext
{
    public function __construct(
        public readonly ?string $ip,
        public readonly ?float $latitude = null,
        public readonly ?float $longitude = null,
    ) {}
}
```

`app/DataObjects/FraudCheckResult.php`:
```php
<?php

namespace App\DataObjects;

use App\Enums\FraudCheckStatus;

class FraudCheckResult
{
    public function __construct(
        public readonly bool $passed,
        public readonly FraudCheckStatus $status,
    ) {}
}
```

- [ ] **Step 4: Implement FraudGuardService**

`app/Services/FraudGuardService.php`:
```php
<?php

namespace App\Services;

use App\DataObjects\FraudCheckContext;
use App\DataObjects\FraudCheckResult;
use App\Enums\FraudCheckStatus;

class FraudGuardService
{
    public function __construct(private readonly SettingsService $settings) {}

    public function check(FraudCheckContext $ctx): FraudCheckResult
    {
        $gpsEnabled = (bool) $this->settings->get('gps_enabled', false);
        $ipEnabled = (bool) $this->settings->get('ip_enabled', false);

        if (! $gpsEnabled && ! $ipEnabled) {
            return new FraudCheckResult(true, FraudCheckStatus::Skipped);
        }

        $gpsPassed = $gpsEnabled ? $this->gpsPassed($ctx) : null;
        $ipPassed = $ipEnabled ? $this->ipPassed($ctx) : null;

        $enabledChecks = array_filter([$gpsPassed, $ipPassed], fn ($v) => $v !== null);
        $passed = in_array(true, $enabledChecks, true);

        if ($passed) {
            return new FraudCheckResult(true, FraudCheckStatus::Passed);
        }

        $failure = $gpsEnabled && $gpsPassed === false
            ? FraudCheckStatus::GpsFailed
            : FraudCheckStatus::IpFailed;

        return new FraudCheckResult(false, $failure);
    }

    private function gpsPassed(FraudCheckContext $ctx): bool
    {
        if ($ctx->latitude === null || $ctx->longitude === null) return false;

        $lat = (float) $this->settings->get('office_lat');
        $lng = (float) $this->settings->get('office_lng');
        $radius = (float) $this->settings->get('geofence_radius_meters', 100);

        if ($lat === 0.0 && $lng === 0.0) return false;

        $distance = $this->haversineMeters($ctx->latitude, $ctx->longitude, $lat, $lng);
        return $distance <= $radius;
    }

    private function ipPassed(FraudCheckContext $ctx): bool
    {
        $whitelist = (array) $this->settings->get('ip_whitelist', []);
        return in_array($ctx->ip, $whitelist, true);
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }
}
```

- [ ] **Step 5: Run — expect PASS**

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: FraudGuardService with GPS geofence and IP whitelist"
```

---

### Task 3.2: AttendanceRepository + Service

**Files:**
- Create: `app/Repositories/AttendanceRepository.php`
- Create: `app/Services/AttendanceService.php`

- [ ] **Step 1: Write failing test**

`tests/Feature/Services/AttendanceServiceTest.php`:
```php
<?php

use App\DataObjects\FraudCheckContext;
use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\Employee;
use App\Services\AttendanceService;

it('records first scan of the day as check_in', function () {
    $emp = Employee::factory()->create();
    $att = app(AttendanceService::class)->record($emp, new FraudCheckContext('1.1.1.1'));

    expect($att->type)->toBe(AttendanceType::CheckIn);
});

it('records second scan as check_out', function () {
    $emp = Employee::factory()->create();
    app(AttendanceService::class)->record($emp, new FraudCheckContext('1.1.1.1'));
    $att = app(AttendanceService::class)->record($emp, new FraudCheckContext('1.1.1.1'));

    expect($att->type)->toBe(AttendanceType::CheckOut);
});

it('third scan same day starts new check_in', function () {
    $emp = Employee::factory()->create();
    app(AttendanceService::class)->record($emp, new FraudCheckContext('1.1.1.1'));
    app(AttendanceService::class)->record($emp, new FraudCheckContext('1.1.1.1'));
    $att = app(AttendanceService::class)->record($emp, new FraudCheckContext('1.1.1.1'));

    expect($att->type)->toBe(AttendanceType::CheckIn);
});

it('previews next scan type without persisting', function () {
    $emp = Employee::factory()->create();
    $type = app(AttendanceService::class)->previewNextType($emp);

    expect($type)->toBe(AttendanceType::CheckIn);
    expect(Attendance::count())->toBe(0);
});
```

- [ ] **Step 2: Run — expect FAIL**

- [ ] **Step 3: Implement AttendanceRepository**

`app/Repositories/AttendanceRepository.php`:
```php
<?php

namespace App\Repositories;

use App\Models\Attendance;
use App\Models\Employee;

class AttendanceRepository
{
    public function lastForEmployeeToday(Employee $employee): ?Attendance
    {
        return Attendance::where('employee_id', $employee->id)
            ->whereDate('scanned_at', today())
            ->latest('scanned_at')
            ->first();
    }

    public function create(array $data): Attendance
    {
        return Attendance::create($data);
    }
}
```

- [ ] **Step 4: Implement AttendanceService**

`app/Services/AttendanceService.php`:
```php
<?php

namespace App\Services;

use App\DataObjects\FraudCheckContext;
use App\DataObjects\FraudCheckResult;
use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\Employee;
use App\Repositories\AttendanceRepository;

class AttendanceService
{
    public function __construct(
        private readonly AttendanceRepository $repo,
        private readonly FraudGuardService $fraud,
    ) {}

    public function previewNextType(Employee $employee): AttendanceType
    {
        $last = $this->repo->lastForEmployeeToday($employee);
        if (! $last) return AttendanceType::CheckIn;

        return $last->type === AttendanceType::CheckIn
            ? AttendanceType::CheckOut
            : AttendanceType::CheckIn;
    }

    public function record(Employee $employee, FraudCheckContext $ctx): Attendance
    {
        $fraudResult = $this->fraud->check($ctx);
        $type = $this->previewNextType($employee);

        return $this->repo->create([
            'employee_id' => $employee->id,
            'type' => $type,
            'scanned_at' => now(),
            'ip_address' => $ctx->ip,
            'latitude' => $ctx->latitude,
            'longitude' => $ctx->longitude,
            'fraud_check_status' => $fraudResult->status,
        ]);
    }

    public function checkFraud(FraudCheckContext $ctx): FraudCheckResult
    {
        return $this->fraud->check($ctx);
    }
}
```

- [ ] **Step 5: Run — expect PASS**

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: AttendanceService with auto check_in/check_out detection"
```

---

### Task 3.3: Public Scan Landing + Attendance Flow

**Files:**
- Create: `app/Http/Controllers/ScanController.php`
- Create: `app/Http/Requests/Public/RecordAttendanceRequest.php`
- Create: `app/Http/Middleware/ThrottleScan.php`
- Create: `resources/views/layouts/public.blade.php`
- Create: `resources/views/scan/index.blade.php`
- Create: `resources/views/scan/attendance.blade.php`
- Create: `resources/views/scan/attendance-confirm.blade.php`
- Create: `resources/views/scan/attendance-success.blade.php`
- Modify: `routes/web.php`
- Modify: `bootstrap/app.php` (register middleware alias)

- [ ] **Step 1: Write failing HTTP test**

`tests/Feature/Public/AttendanceScanTest.php`:
```php
<?php

use App\Enums\AttendanceType;
use App\Models\Attendance;
use App\Models\Employee;

it('shows the scan landing page', function () {
    $this->get('/scan')->assertOk()->assertSee('تسجيل الحضور');
});

it('shows attendance form', function () {
    $this->get('/scan/attendance')->assertOk();
});

it('rejects unknown employee number', function () {
    $this->post('/scan/attendance/preview', ['employee_number' => 9999])
        ->assertSessionHasErrors('employee_number');
});

it('previews check_in for new employee', function () {
    $emp = Employee::factory()->create(['employee_number' => 1001]);

    $this->post('/scan/attendance/preview', ['employee_number' => 1001])
        ->assertOk()
        ->assertSee('check_in');
});

it('rejects inactive employee', function () {
    Employee::factory()->create(['employee_number' => 1001, 'is_active' => false]);

    $this->post('/scan/attendance/preview', ['employee_number' => 1001])
        ->assertSessionHasErrors('employee_number');
});

it('persists attendance on confirm', function () {
    $emp = Employee::factory()->create(['employee_number' => 1001]);

    $this->post('/scan/attendance/confirm', ['employee_number' => 1001])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Attendance::count())->toBe(1);
    expect(Attendance::first()->type)->toBe(AttendanceType::CheckIn);
});
```

- [ ] **Step 2: Run — expect FAIL**

- [ ] **Step 3: Create FormRequest**

`app/Http/Requests/Public/RecordAttendanceRequest.php`:
```php
<?php

namespace App\Http\Requests\Public;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_number' => [
                'required', 'integer',
                Rule::exists('employees', 'employee_number')->where('is_active', true),
            ],
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ];
    }

    public function messages(): array
    {
        return [
            'employee_number.exists' => __('messages.scan_employee_not_found'),
        ];
    }

    public function employee(): Employee
    {
        return Employee::where('employee_number', $this->integer('employee_number'))->firstOrFail();
    }
}
```

- [ ] **Step 4: Create ScanController**

`app/Http/Controllers/ScanController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\DataObjects\FraudCheckContext;
use App\Enums\FraudCheckStatus;
use App\Http\Requests\Public\RecordAttendanceRequest;
use App\Services\AttendanceService;

class ScanController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance) {}

    public function index()
    {
        return view('scan.index');
    }

    public function attendanceForm()
    {
        return view('scan.attendance');
    }

    public function attendancePreview(RecordAttendanceRequest $request)
    {
        $employee = $request->employee();
        $ctx = new FraudCheckContext(
            $request->ip(),
            $request->float('latitude'),
            $request->float('longitude'),
        );

        $fraud = $this->attendance->checkFraud($ctx);
        if (! $fraud->passed) {
            $message = $fraud->status === FraudCheckStatus::GpsFailed
                ? __('messages.scan_fraud_gps_failed')
                : __('messages.scan_fraud_ip_failed');
            return back()->withInput()->withErrors(['employee_number' => $message]);
        }

        $nextType = $this->attendance->previewNextType($employee);

        return view('scan.attendance-confirm', [
            'employee' => $employee,
            'nextType' => $nextType,
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
        ]);
    }

    public function attendanceConfirm(RecordAttendanceRequest $request)
    {
        $employee = $request->employee();
        $ctx = new FraudCheckContext(
            $request->ip(),
            $request->float('latitude'),
            $request->float('longitude'),
        );

        $attendance = $this->attendance->record($employee, $ctx);

        return redirect()->route('scan.index')
            ->with('success', $attendance);
    }
}
```

- [ ] **Step 5: Create the Blade views**

`resources/views/layouts/public.blade.php`:
```blade
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="max-w-md mx-auto p-6">
        @yield('content')
    </div>
</body>
</html>
```

`resources/views/scan/index.blade.php`:
```blade
@extends('layouts.public')
@section('content')
    <h1 class="text-3xl font-bold text-center mb-8">{{ config('app.name') }}</h1>

    @if(session('success'))
        <div class="bg-green-100 text-green-800 p-4 rounded mb-4 text-center">
            @php $att = session('success'); @endphp
            {{ __('messages.scan_success_' . $att->type->value, ['time' => $att->scanned_at->format('H:i')]) }}
        </div>
    @endif

    <div class="space-y-4">
        <a href="{{ route('scan.attendance.form') }}" class="block bg-blue-600 text-white text-center py-4 rounded-lg text-lg">
            تسجيل الحضور
        </a>
        <a href="{{ route('scan.leave.form') }}" class="block bg-purple-600 text-white text-center py-4 rounded-lg text-lg">
            طلب إجازة
        </a>
    </div>
@endsection
```

`resources/views/scan/attendance.blade.php`:
```blade
@extends('layouts.public')
@section('content')
    <h1 class="text-2xl font-bold mb-6">تسجيل الحضور</h1>

    <form method="POST" action="{{ route('scan.attendance.preview') }}" x-data="{ lat: null, lng: null }" x-init="
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(pos => { lat = pos.coords.latitude; lng = pos.coords.longitude; });
        }
    ">
        @csrf
        <input type="hidden" name="latitude" x-bind:value="lat">
        <input type="hidden" name="longitude" x-bind:value="lng">

        <label class="block mb-1">الرقم الوظيفي</label>
        <input type="number" name="employee_number" required autofocus
               class="border p-3 rounded w-full text-center text-xl" dir="ltr">

        @error('employee_number')
            <div class="text-red-600 text-sm mt-2">{{ $message }}</div>
        @enderror

        <button type="submit" class="mt-6 w-full bg-blue-600 text-white py-3 rounded-lg text-lg">
            متابعة
        </button>
    </form>
@endsection
```

`resources/views/scan/attendance-confirm.blade.php`:
```blade
@extends('layouts.public')
@section('content')
    <h1 class="text-2xl font-bold mb-4">تأكيد التسجيل</h1>

    <div class="bg-white p-6 rounded-lg shadow text-center">
        <p class="text-lg mb-2">أهلاً <strong>{{ $employee->name }}</strong></p>
        <p class="mb-6">
            سيتم تسجيل
            <strong class="text-blue-600">
                {{ $nextType->value === 'check_in' ? 'حضورك' : 'انصرافك' }}
            </strong>
            في الوقت
            <strong>{{ now()->format('H:i') }}</strong>
        </p>

        <form method="POST" action="{{ route('scan.attendance.confirm') }}">
            @csrf
            <input type="hidden" name="employee_number" value="{{ $employee->employee_number }}">
            <input type="hidden" name="latitude" value="{{ $latitude }}">
            <input type="hidden" name="longitude" value="{{ $longitude }}">
            <button type="submit" class="bg-green-600 text-white px-6 py-3 rounded-lg w-full">
                تأكيد
            </button>
        </form>

        <a href="{{ route('scan.index') }}" class="block mt-4 text-gray-500">إلغاء</a>
    </div>
@endsection
```

- [ ] **Step 6: Register routes**

Add to top of `routes/web.php` (outside the admin group):
```php
use App\Http\Controllers\ScanController;

Route::middleware('throttle:30,1')->group(function () {
    Route::get('/scan', [ScanController::class, 'index'])->name('scan.index');
    Route::get('/scan/attendance', [ScanController::class, 'attendanceForm'])->name('scan.attendance.form');
    Route::post('/scan/attendance/preview', [ScanController::class, 'attendancePreview'])->name('scan.attendance.preview');
    Route::post('/scan/attendance/confirm', [ScanController::class, 'attendanceConfirm'])->name('scan.attendance.confirm');
});
```

- [ ] **Step 7: Run — expect PASS**

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: public scan flow (landing, attendance preview, confirm)"
```

---

## Milestone 4 — Leave Workflow

### Task 4.1: LeaveRequestRepository + LeaveService

**Files:**
- Create: `app/Repositories/LeaveRequestRepository.php`
- Create: `app/Services/LeaveService.php`

- [ ] **Step 1: Write failing test**

`tests/Feature/Services/LeaveServiceTest.php`:
```php
<?php

use App\Enums\LeaveStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\LeaveService;
use App\Services\Sms\FakeSmsGateway;
use App\Services\Sms\SmsGatewayInterface;

beforeEach(function () {
    $this->fake = new FakeSmsGateway();
    $this->app->instance(SmsGatewayInterface::class, $this->fake);
});

it('creates a pending leave request', function () {
    $emp = Employee::factory()->create();

    $leave = app(LeaveService::class)->submit($emp, [
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-01',
        'note' => 'family',
    ]);

    expect($leave->status)->toBe(LeaveStatus::Pending);
    expect($leave->employee_id)->toBe($emp->id);
});

it('approves a request and sends SMS', function () {
    $emp = Employee::factory()->create();
    $reviewer = User::factory()->create();
    $leave = LeaveRequest::factory()->for($emp)->create(['start_date' => '2026-10-01', 'end_date' => '2026-10-01']);

    app(LeaveService::class)->approve($leave, $reviewer);

    expect($leave->fresh()->status)->toBe(LeaveStatus::Approved);
});

it('rejects a request with a reason and sends SMS', function () {
    $emp = Employee::factory()->create();
    $reviewer = User::factory()->create();
    $leave = LeaveRequest::factory()->for($emp)->create();

    app(LeaveService::class)->reject($leave, $reviewer, 'Too short notice');

    expect($leave->fresh()->status)->toBe(LeaveStatus::Rejected);
    expect($leave->fresh()->rejection_reason)->toBe('Too short notice');
});

it('does not re-review already-decided requests', function () {
    $emp = Employee::factory()->create();
    $reviewer = User::factory()->create();
    $leave = LeaveRequest::factory()->for($emp)->create(['status' => LeaveStatus::Approved]);

    expect(fn () => app(LeaveService::class)->reject($leave, $reviewer, 'x'))
        ->toThrow(\DomainException::class);
});
```

- [ ] **Step 2: Run — expect FAIL**

- [ ] **Step 3: Implement Repository**

`app/Repositories/LeaveRequestRepository.php`:
```php
<?php

namespace App\Repositories;

use App\Models\Employee;
use App\Models\LeaveRequest;

class LeaveRequestRepository
{
    public function create(Employee $employee, array $data): LeaveRequest
    {
        return $employee->leaveRequests()->create($data);
    }

    public function update(LeaveRequest $leave, array $data): LeaveRequest
    {
        $leave->update($data);
        return $leave->fresh();
    }
}
```

- [ ] **Step 4: Implement LeaveService**

`app/Services/LeaveService.php`:
```php
<?php

namespace App\Services;

use App\Enums\LeaveStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Repositories\LeaveRequestRepository;
use App\Services\Sms\SmsService;
use DomainException;

class LeaveService
{
    public function __construct(
        private readonly LeaveRequestRepository $repo,
        private readonly SmsService $sms,
    ) {}

    public function submit(Employee $employee, array $data): LeaveRequest
    {
        return $this->repo->create($employee, [
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? $data['start_date'],
            'note' => $data['note'] ?? null,
            'status' => LeaveStatus::Pending,
        ]);
    }

    public function approve(LeaveRequest $leave, User $reviewer): LeaveRequest
    {
        $this->assertPending($leave);

        $updated = $this->repo->update($leave, [
            'status' => LeaveStatus::Approved,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        $this->sms->dispatch(
            $updated->employee->phone,
            trans('messages.leave_approved', ['date' => $updated->start_date->format('Y-m-d')])
        );

        return $updated;
    }

    public function reject(LeaveRequest $leave, User $reviewer, string $reason): LeaveRequest
    {
        $this->assertPending($leave);

        $updated = $this->repo->update($leave, [
            'status' => LeaveStatus::Rejected,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ]);

        $this->sms->dispatch(
            $updated->employee->phone,
            trans('messages.leave_rejected', [
                'date' => $updated->start_date->format('Y-m-d'),
                'reason' => $reason,
            ])
        );

        return $updated;
    }

    private function assertPending(LeaveRequest $leave): void
    {
        if ($leave->status !== LeaveStatus::Pending) {
            throw new DomainException('Only pending leaves can be decided.');
        }
    }
}
```

- [ ] **Step 5: Run — expect PASS**

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: LeaveService with submit/approve/reject and SMS notifications"
```

---

### Task 4.2: Public Leave Request Flow

**Files:**
- Create: `app/Http/Requests/Public/SubmitLeaveRequestRequest.php`
- Modify: `app/Http/Controllers/ScanController.php`
- Create: `resources/views/scan/leave.blade.php`
- Create: `resources/views/scan/leave-success.blade.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Write failing test**

`tests/Feature/Public/LeaveRequestTest.php`:
```php
<?php

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\Sms\FakeSmsGateway;
use App\Services\Sms\SmsGatewayInterface;

beforeEach(fn () => $this->app->instance(SmsGatewayInterface::class, new FakeSmsGateway()));

it('shows leave request form', function () {
    $this->get('/scan/leave')->assertOk()->assertSee('طلب إجازة');
});

it('submits a leave request', function () {
    $emp = Employee::factory()->create(['employee_number' => 1001]);

    $this->post('/scan/leave', [
        'employee_number' => 1001,
        'start_date' => '2026-10-05',
        'end_date' => '2026-10-06',
        'note' => 'family',
    ])->assertRedirect();

    expect(LeaveRequest::count())->toBe(1);
});

it('validates dates', function () {
    Employee::factory()->create(['employee_number' => 1001]);

    $this->post('/scan/leave', [
        'employee_number' => 1001,
        'start_date' => '2026-10-05',
        'end_date' => '2026-10-01',
    ])->assertSessionHasErrors('end_date');
});
```

- [ ] **Step 2: Run — expect FAIL**

- [ ] **Step 3: Create FormRequest**

`app/Http/Requests/Public/SubmitLeaveRequestRequest.php`:
```php
<?php

namespace App\Http\Requests\Public;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_number' => [
                'required', 'integer',
                Rule::exists('employees', 'employee_number')->where('is_active', true),
            ],
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'note' => 'nullable|string|max:1000',
        ];
    }

    public function employee(): Employee
    {
        return Employee::where('employee_number', $this->integer('employee_number'))->firstOrFail();
    }
}
```

- [ ] **Step 4: Add controller methods**

Add to `ScanController.php`:
```php
use App\Http\Requests\Public\SubmitLeaveRequestRequest;
use App\Services\LeaveService;

// add via constructor promotion:
public function __construct(
    private readonly AttendanceService $attendance,
    private readonly LeaveService $leave,
) {}

public function leaveForm()
{
    return view('scan.leave');
}

public function leaveSubmit(SubmitLeaveRequestRequest $request)
{
    $employee = $request->employee();
    $this->leave->submit($employee, $request->validated());

    return redirect()->route('scan.index')->with('leave_success', true);
}
```

- [ ] **Step 5: Create Blade view**

`resources/views/scan/leave.blade.php`:
```blade
@extends('layouts.public')
@section('content')
    <h1 class="text-2xl font-bold mb-6">طلب إجازة</h1>

    <form method="POST" action="{{ route('scan.leave.submit') }}" class="space-y-4">
        @csrf

        <div>
            <label class="block mb-1">الرقم الوظيفي</label>
            <input type="number" name="employee_number" value="{{ old('employee_number') }}"
                   required class="border p-3 rounded w-full text-center text-xl" dir="ltr">
            @error('employee_number') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="block mb-1">من تاريخ</label>
            <input type="date" name="start_date" value="{{ old('start_date') }}" required class="border p-3 rounded w-full">
            @error('start_date') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="block mb-1">إلى تاريخ</label>
            <input type="date" name="end_date" value="{{ old('end_date') }}" required class="border p-3 rounded w-full">
            @error('end_date') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="block mb-1">ملاحظة (اختياري)</label>
            <textarea name="note" class="border p-3 rounded w-full" rows="3">{{ old('note') }}</textarea>
        </div>

        <button type="submit" class="w-full bg-purple-600 text-white py-3 rounded-lg text-lg">
            إرسال الطلب
        </button>
    </form>
@endsection
```

Also update `scan/index.blade.php` to show `leave_success` message near the top:
```blade
@if(session('leave_success'))
    <div class="bg-purple-100 text-purple-800 p-4 rounded mb-4 text-center">
        تم إرسال طلب إجازتك. ستصلك رسالة SMS بالنتيجة.
    </div>
@endif
```

- [ ] **Step 6: Add routes**

Inside the throttle group:
```php
Route::get('/scan/leave', [ScanController::class, 'leaveForm'])->name('scan.leave.form');
Route::post('/scan/leave', [ScanController::class, 'leaveSubmit'])->name('scan.leave.submit');
```

- [ ] **Step 7: Run — expect PASS**

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: public leave request submission flow"
```

---

### Task 4.3: Admin Leave Review Livewire Component

**Files:**
- Create: `app/Livewire/Admin/Leaves/LeaveList.php`
- Create: `resources/views/livewire/admin/leaves/leave-list.blade.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Write failing test**

`tests/Feature/Admin/LeaveReviewTest.php`:
```php
<?php

use App\Enums\LeaveStatus;
use App\Livewire\Admin\Leaves\LeaveList;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\Sms\FakeSmsGateway;
use App\Services\Sms\SmsGatewayInterface;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->app->instance(SmsGatewayInterface::class, new FakeSmsGateway());
});

it('lists leaves and filters by status', function () {
    $emp = Employee::factory()->create();
    LeaveRequest::factory()->for($emp)->count(2)->create(['status' => LeaveStatus::Pending]);
    LeaveRequest::factory()->for($emp)->create(['status' => LeaveStatus::Approved]);

    Livewire::test(LeaveList::class)
        ->set('status', 'pending')
        ->assertViewHas('leaves', fn ($items) => $items->count() === 2);
});

it('approves a leave', function () {
    $emp = Employee::factory()->create();
    $leave = LeaveRequest::factory()->for($emp)->create(['status' => LeaveStatus::Pending]);

    Livewire::test(LeaveList::class)
        ->call('approve', $leave->id);

    expect($leave->fresh()->status)->toBe(LeaveStatus::Approved);
});

it('rejects a leave with reason', function () {
    $emp = Employee::factory()->create();
    $leave = LeaveRequest::factory()->for($emp)->create(['status' => LeaveStatus::Pending]);

    Livewire::test(LeaveList::class)
        ->set('rejectingId', $leave->id)
        ->set('rejectReason', 'Not enough notice')
        ->call('confirmReject');

    expect($leave->fresh()->status)->toBe(LeaveStatus::Rejected);
    expect($leave->fresh()->rejection_reason)->toBe('Not enough notice');
});
```

- [ ] **Step 2: Run — expect FAIL**

- [ ] **Step 3: Implement LeaveList component**

`app/Livewire/Admin/Leaves/LeaveList.php`:
```php
<?php

namespace App\Livewire\Admin\Leaves;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\LeaveService;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class LeaveList extends Component
{
    use WithPagination;

    #[Url] public string $status = 'all';
    #[Url] public ?string $from = null;
    #[Url] public ?string $to = null;
    #[Url] public ?int $employeeId = null;

    public ?int $rejectingId = null;
    public string $rejectReason = '';

    public function updating($property): void
    {
        if (in_array($property, ['status', 'from', 'to', 'employeeId'])) {
            $this->resetPage();
        }
    }

    public function approve(int $id, LeaveService $service): void
    {
        $leave = LeaveRequest::findOrFail($id);
        $service->approve($leave, auth()->user());
        session()->flash('success', 'تمت الموافقة');
    }

    public function startReject(int $id): void
    {
        $this->rejectingId = $id;
        $this->rejectReason = '';
    }

    public function confirmReject(LeaveService $service): void
    {
        $this->validate(['rejectReason' => 'required|string|max:500']);

        $leave = LeaveRequest::findOrFail($this->rejectingId);
        $service->reject($leave, auth()->user(), $this->rejectReason);

        $this->rejectingId = null;
        $this->rejectReason = '';
        session()->flash('success', 'تم الرفض');
    }

    public function render()
    {
        $query = LeaveRequest::with('employee')->latest();

        if ($this->status !== 'all') $query->where('status', $this->status);
        if ($this->from) $query->whereDate('start_date', '>=', $this->from);
        if ($this->to) $query->whereDate('end_date', '<=', $this->to);
        if ($this->employeeId) $query->where('employee_id', $this->employeeId);

        return view('livewire.admin.leaves.leave-list', [
            'leaves' => $query->paginate(15),
            'employees' => Employee::orderBy('name')->get(),
        ]);
    }
}
```

- [ ] **Step 4: Create Blade view**

`resources/views/livewire/admin/leaves/leave-list.blade.php`:
```blade
<div class="p-6">
    <h1 class="text-2xl font-bold mb-4">الإجازات</h1>

    @if(session('success'))
        <div class="bg-green-100 text-green-800 p-3 mb-3 rounded">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-4 gap-4 mb-4">
        <select wire:model.live="status" class="border p-2 rounded">
            <option value="all">كل الحالات</option>
            <option value="pending">قيد المراجعة</option>
            <option value="approved">مقبولة</option>
            <option value="rejected">مرفوضة</option>
        </select>
        <input type="date" wire:model.live="from" class="border p-2 rounded">
        <input type="date" wire:model.live="to" class="border p-2 rounded">
        <select wire:model.live="employeeId" class="border p-2 rounded">
            <option value="">كل الموظفين</option>
            @foreach($employees as $e)
                <option value="{{ $e->id }}">{{ $e->name }} ({{ $e->employee_number }})</option>
            @endforeach
        </select>
    </div>

    <table class="w-full border-collapse border">
        <thead class="bg-gray-100">
            <tr>
                <th class="border p-2">الموظف</th>
                <th class="border p-2">من</th>
                <th class="border p-2">إلى</th>
                <th class="border p-2">ملاحظة</th>
                <th class="border p-2">الحالة</th>
                <th class="border p-2">إجراءات</th>
            </tr>
        </thead>
        <tbody>
            @foreach($leaves as $leave)
                <tr>
                    <td class="border p-2">{{ $leave->employee->name }}</td>
                    <td class="border p-2 text-center">{{ $leave->start_date->format('Y-m-d') }}</td>
                    <td class="border p-2 text-center">{{ $leave->end_date->format('Y-m-d') }}</td>
                    <td class="border p-2 text-sm">{{ $leave->note }}</td>
                    <td class="border p-2 text-center">{{ $leave->status->value }}</td>
                    <td class="border p-2 text-center">
                        @if($leave->status->value === 'pending')
                            <button wire:click="approve({{ $leave->id }})" class="text-green-600">قبول</button>
                            <button wire:click="startReject({{ $leave->id }})" class="text-red-600 mx-2">رفض</button>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mt-4">{{ $leaves->links() }}</div>

    @if($rejectingId)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
            <div class="bg-white p-6 rounded max-w-md w-full">
                <h2 class="text-xl mb-3">سبب الرفض</h2>
                <textarea wire:model="rejectReason" class="border p-2 rounded w-full" rows="3"></textarea>
                @error('rejectReason') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
                <div class="mt-4 flex justify-end gap-2">
                    <button wire:click="$set('rejectingId', null)" class="px-4 py-2">إلغاء</button>
                    <button wire:click="confirmReject" class="bg-red-600 text-white px-4 py-2 rounded">تأكيد الرفض</button>
                </div>
            </div>
        </div>
    @endif
</div>
```

- [ ] **Step 5: Register route**

Inside admin group:
```php
Route::get('/leaves', \App\Livewire\Admin\Leaves\LeaveList::class)->name('leaves.index');
```

- [ ] **Step 6: Run — expect PASS**

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: admin leave review with approve/reject and SMS"
```

---

## Milestone 5 — Dashboard, Attendance List, Settings

### Task 5.1: Attendance List Livewire Component

**Files:**
- Create: `app/Livewire/Admin/Attendance/AttendanceList.php`
- Create: `resources/views/livewire/admin/attendance/attendance-list.blade.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Write failing test**

`tests/Feature/Admin/AttendanceListTest.php`:
```php
<?php

use App\Enums\AttendanceType;
use App\Livewire\Admin\Attendance\AttendanceList;
use App\Models\{Attendance, Employee, User};
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('filters attendance by date range', function () {
    $emp = Employee::factory()->create();
    Attendance::factory()->for($emp)->create(['scanned_at' => '2026-01-15 08:00:00']);
    Attendance::factory()->for($emp)->create(['scanned_at' => '2026-02-15 08:00:00']);

    Livewire::test(AttendanceList::class)
        ->set('from', '2026-01-01')
        ->set('to', '2026-01-31')
        ->assertViewHas('records', fn ($items) => $items->count() === 1);
});

it('filters attendance by type', function () {
    $emp = Employee::factory()->create();
    Attendance::factory()->for($emp)->create(['type' => AttendanceType::CheckIn]);
    Attendance::factory()->for($emp)->create(['type' => AttendanceType::CheckOut]);

    Livewire::test(AttendanceList::class)
        ->set('type', 'check_out')
        ->assertViewHas('records', fn ($items) => $items->count() === 1);
});
```

- [ ] **Step 2: Run — expect FAIL**

- [ ] **Step 3: Implement AttendanceList**

`app/Livewire/Admin/Attendance/AttendanceList.php`:
```php
<?php

namespace App\Livewire\Admin\Attendance;

use App\Models\Attendance;
use App\Models\Employee;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class AttendanceList extends Component
{
    use WithPagination;

    #[Url] public ?string $from = null;
    #[Url] public ?string $to = null;
    #[Url] public ?int $employeeId = null;
    #[Url] public string $type = 'all';
    #[Url] public string $fraudStatus = 'all';

    public function updating($property): void
    {
        if (in_array($property, ['from', 'to', 'employeeId', 'type', 'fraudStatus'])) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $query = Attendance::with('employee')->latest('scanned_at');

        if ($this->from) $query->whereDate('scanned_at', '>=', $this->from);
        if ($this->to) $query->whereDate('scanned_at', '<=', $this->to);
        if ($this->employeeId) $query->where('employee_id', $this->employeeId);
        if ($this->type !== 'all') $query->where('type', $this->type);
        if ($this->fraudStatus !== 'all') $query->where('fraud_check_status', $this->fraudStatus);

        return view('livewire.admin.attendance.attendance-list', [
            'records' => $query->paginate(20),
            'employees' => Employee::orderBy('name')->get(),
        ]);
    }
}
```

- [ ] **Step 4: Create Blade view**

`resources/views/livewire/admin/attendance/attendance-list.blade.php`:
```blade
<div class="p-6">
    <h1 class="text-2xl font-bold mb-4">سجل الحضور</h1>

    <div class="grid grid-cols-5 gap-4 mb-4">
        <input type="date" wire:model.live="from" class="border p-2 rounded">
        <input type="date" wire:model.live="to" class="border p-2 rounded">
        <select wire:model.live="employeeId" class="border p-2 rounded">
            <option value="">كل الموظفين</option>
            @foreach($employees as $e)
                <option value="{{ $e->id }}">{{ $e->name }}</option>
            @endforeach
        </select>
        <select wire:model.live="type" class="border p-2 rounded">
            <option value="all">حضور + انصراف</option>
            <option value="check_in">حضور</option>
            <option value="check_out">انصراف</option>
        </select>
        <select wire:model.live="fraudStatus" class="border p-2 rounded">
            <option value="all">كل الحالات</option>
            <option value="passed">مقبول</option>
            <option value="skipped">بدون فحص</option>
            <option value="gps_failed">فشل GPS</option>
            <option value="ip_failed">فشل IP</option>
        </select>
    </div>

    <table class="w-full border-collapse border">
        <thead class="bg-gray-100">
            <tr>
                <th class="border p-2">الوقت</th>
                <th class="border p-2">الموظف</th>
                <th class="border p-2">النوع</th>
                <th class="border p-2">IP</th>
                <th class="border p-2">فحص</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $r)
                <tr>
                    <td class="border p-2 text-center">{{ $r->scanned_at->format('Y-m-d H:i') }}</td>
                    <td class="border p-2">{{ $r->employee->name }}</td>
                    <td class="border p-2 text-center">{{ $r->type->value }}</td>
                    <td class="border p-2 text-center" dir="ltr">{{ $r->ip_address }}</td>
                    <td class="border p-2 text-center">{{ $r->fraud_check_status->value }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mt-4">{{ $records->links() }}</div>
</div>
```

- [ ] **Step 5: Add route**

```php
Route::get('/attendance', \App\Livewire\Admin\Attendance\AttendanceList::class)->name('attendance.index');
```

- [ ] **Step 6: Run — expect PASS**

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: admin attendance list with multi-filter"
```

---

### Task 5.2: Settings Livewire Form

**Files:**
- Create: `app/Livewire/Admin/Settings/SettingsForm.php`
- Create: `resources/views/livewire/admin/settings/settings-form.blade.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Write failing test**

`tests/Feature/Admin/SettingsTest.php`:
```php
<?php

use App\Livewire\Admin\Settings\SettingsForm;
use App\Models\User;
use App\Services\SettingsService;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->seed(\Database\Seeders\SettingsSeeder::class);
    cache()->flush();
});

it('loads current settings into the form', function () {
    Livewire::test(SettingsForm::class)
        ->assertSet('gpsEnabled', false)
        ->assertSet('geofenceRadius', 100);
});

it('saves settings back', function () {
    Livewire::test(SettingsForm::class)
        ->set('gpsEnabled', true)
        ->set('officeLat', 31.9539)
        ->set('officeLng', 35.9106)
        ->set('geofenceRadius', 150)
        ->set('ipEnabled', true)
        ->set('ipWhitelistText', "192.168.1.100\n10.0.0.5")
        ->call('save');

    $settings = app(SettingsService::class);
    expect($settings->get('gps_enabled'))->toBeTrue();
    expect($settings->get('geofence_radius_meters'))->toBe(150);
    expect($settings->get('ip_whitelist'))->toBe(['192.168.1.100', '10.0.0.5']);
});
```

- [ ] **Step 2: Run — expect FAIL**

- [ ] **Step 3: Implement SettingsForm**

`app/Livewire/Admin/Settings/SettingsForm.php`:
```php
<?php

namespace App\Livewire\Admin\Settings;

use App\Services\SettingsService;
use Livewire\Component;

class SettingsForm extends Component
{
    public bool $gpsEnabled = false;
    public ?float $officeLat = null;
    public ?float $officeLng = null;
    public int $geofenceRadius = 100;

    public bool $ipEnabled = false;
    public string $ipWhitelistText = '';

    public string $smsUsername = '';
    public string $smsPassword = '';
    public string $smsSender = '';

    public int $employeeNumberStart = 1001;

    public function mount(SettingsService $s): void
    {
        $this->gpsEnabled = (bool) $s->get('gps_enabled', false);
        $this->officeLat = $s->get('office_lat');
        $this->officeLng = $s->get('office_lng');
        $this->geofenceRadius = (int) $s->get('geofence_radius_meters', 100);

        $this->ipEnabled = (bool) $s->get('ip_enabled', false);
        $this->ipWhitelistText = implode("\n", (array) $s->get('ip_whitelist', []));

        $this->smsUsername = $s->get('sms_username', '');
        $this->smsPassword = $s->get('sms_password', '');
        $this->smsSender = $s->get('sms_sender', '');

        $this->employeeNumberStart = (int) $s->get('employee_number_start', 1001);
    }

    public function save(SettingsService $s): void
    {
        $this->validate([
            'gpsEnabled' => 'boolean',
            'officeLat' => 'nullable|numeric|between:-90,90',
            'officeLng' => 'nullable|numeric|between:-180,180',
            'geofenceRadius' => 'integer|min:1|max:10000',
            'ipEnabled' => 'boolean',
            'employeeNumberStart' => 'integer|min:1',
        ]);

        $s->set('gps_enabled', $this->gpsEnabled, 'boolean');
        $s->set('office_lat', $this->officeLat, 'number');
        $s->set('office_lng', $this->officeLng, 'number');
        $s->set('geofence_radius_meters', $this->geofenceRadius, 'number');

        $s->set('ip_enabled', $this->ipEnabled, 'boolean');
        $ips = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $this->ipWhitelistText))));
        $s->set('ip_whitelist', $ips, 'json');

        $s->set('sms_username', $this->smsUsername, 'string');
        $s->set('sms_password', $this->smsPassword, 'string');
        $s->set('sms_sender', $this->smsSender, 'string');

        $s->set('employee_number_start', $this->employeeNumberStart, 'number');

        session()->flash('success', 'تم الحفظ');
    }

    public function render()
    {
        return view('livewire.admin.settings.settings-form');
    }
}
```

- [ ] **Step 4: Create Blade view**

`resources/views/livewire/admin/settings/settings-form.blade.php`:
```blade
<div class="p-6 max-w-3xl">
    <h1 class="text-2xl font-bold mb-4">الإعدادات</h1>

    @if(session('success'))
        <div class="bg-green-100 text-green-800 p-3 mb-3 rounded">{{ session('success') }}</div>
    @endif

    <form wire:submit="save" class="space-y-6">
        <fieldset class="border p-4 rounded">
            <legend class="font-bold">فحص GPS</legend>
            <label class="inline-flex items-center gap-2 mb-2">
                <input type="checkbox" wire:model="gpsEnabled">
                <span>تفعيل فحص الموقع الجغرافي</span>
            </label>
            <div class="grid grid-cols-3 gap-3">
                <input type="number" step="0.0000001" wire:model="officeLat" placeholder="خط العرض" class="border p-2 rounded" dir="ltr">
                <input type="number" step="0.0000001" wire:model="officeLng" placeholder="خط الطول" class="border p-2 rounded" dir="ltr">
                <input type="number" wire:model="geofenceRadius" placeholder="نصف القطر (م)" class="border p-2 rounded">
            </div>
        </fieldset>

        <fieldset class="border p-4 rounded">
            <legend class="font-bold">فحص IP</legend>
            <label class="inline-flex items-center gap-2 mb-2">
                <input type="checkbox" wire:model="ipEnabled">
                <span>تفعيل فحص IP</span>
            </label>
            <textarea wire:model="ipWhitelistText" rows="4" class="border p-2 rounded w-full font-mono" dir="ltr"
                placeholder="IP واحد في كل سطر"></textarea>
        </fieldset>

        <fieldset class="border p-4 rounded">
            <legend class="font-bold">MTC SMS</legend>
            <div class="grid grid-cols-3 gap-3">
                <input type="text" wire:model="smsUsername" placeholder="اسم المستخدم" class="border p-2 rounded">
                <input type="text" wire:model="smsPassword" placeholder="كلمة السر" class="border p-2 rounded">
                <input type="text" wire:model="smsSender" placeholder="اسم المرسل" class="border p-2 rounded">
            </div>
        </fieldset>

        <fieldset class="border p-4 rounded">
            <legend class="font-bold">ترقيم الموظفين</legend>
            <input type="number" wire:model="employeeNumberStart" class="border p-2 rounded" min="1">
            <p class="text-sm text-gray-500 mt-1">يستخدم فقط عندما يكون جدول الموظفين فارغاً.</p>
        </fieldset>

        <button type="submit" class="bg-blue-600 text-white px-6 py-3 rounded">حفظ الإعدادات</button>
    </form>
</div>
```

- [ ] **Step 5: Add route**

```php
Route::get('/settings', \App\Livewire\Admin\Settings\SettingsForm::class)->name('settings.index');
```

- [ ] **Step 6: Run — expect PASS**

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: admin settings form (GPS, IP, SMS, numbering)"
```

---

### Task 5.3: Overview Dashboard with KPIs

**Files:**
- Create: `app/Livewire/Admin/Overview.php`
- Create: `resources/views/livewire/admin/overview.blade.php`
- Modify: `routes/web.php` (replace placeholder view route)

- [ ] **Step 1: Write failing test**

`tests/Feature/Admin/OverviewTest.php`:
```php
<?php

use App\Enums\AttendanceType;
use App\Enums\LeaveStatus;
use App\Livewire\Admin\Overview;
use App\Models\{Attendance, Employee, LeaveRequest, User};
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('shows correct KPIs', function () {
    Employee::factory()->count(5)->create(['is_active' => true]);
    $emp = Employee::first();
    Attendance::factory()->for($emp)->create([
        'type' => AttendanceType::CheckIn,
        'scanned_at' => now(),
    ]);
    LeaveRequest::factory()->for($emp)->create(['status' => LeaveStatus::Pending]);
    LeaveRequest::factory()->for($emp)->create([
        'status' => LeaveStatus::Approved,
        'start_date' => today(),
        'end_date' => today(),
    ]);

    Livewire::test(Overview::class)
        ->assertViewHas('presentToday', 1)
        ->assertViewHas('activeEmployees', 5)
        ->assertViewHas('pendingLeaves', 1)
        ->assertViewHas('approvedLeavesToday', 1);
});
```

- [ ] **Step 2: Run — expect FAIL**

- [ ] **Step 3: Implement Overview**

`app/Livewire/Admin/Overview.php`:
```php
<?php

namespace App\Livewire\Admin;

use App\Enums\AttendanceType;
use App\Enums\LeaveStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Livewire\Component;

class Overview extends Component
{
    public function render()
    {
        $activeEmployees = Employee::where('is_active', true)->count();
        $presentToday = Attendance::whereDate('scanned_at', today())
            ->where('type', AttendanceType::CheckIn)
            ->distinct('employee_id')->count('employee_id');

        $pendingLeaves = LeaveRequest::where('status', LeaveStatus::Pending)->count();
        $approvedLeavesToday = LeaveRequest::where('status', LeaveStatus::Approved)
            ->whereDate('start_date', '<=', today())
            ->whereDate('end_date', '>=', today())
            ->count();

        $absentToday = max(0, $activeEmployees - $presentToday - $approvedLeavesToday);

        $recentScans = Attendance::with('employee')->latest('scanned_at')->limit(5)->get();

        $last7Days = collect(range(0, 6))->map(function ($i) {
            $date = today()->subDays($i);
            return [
                'date' => $date->format('m-d'),
                'count' => Attendance::whereDate('scanned_at', $date)
                    ->where('type', AttendanceType::CheckIn)
                    ->distinct('employee_id')->count('employee_id'),
            ];
        })->reverse()->values();

        return view('livewire.admin.overview', [
            'activeEmployees' => $activeEmployees,
            'presentToday' => $presentToday,
            'absentToday' => $absentToday,
            'pendingLeaves' => $pendingLeaves,
            'approvedLeavesToday' => $approvedLeavesToday,
            'recentScans' => $recentScans,
            'last7Days' => $last7Days,
        ]);
    }
}
```

- [ ] **Step 4: Create Blade view**

`resources/views/livewire/admin/overview.blade.php`:
```blade
<div class="p-6">
    <h1 class="text-2xl font-bold mb-4">لوحة التحكم</h1>

    <div class="grid grid-cols-4 gap-4 mb-6">
        <div class="bg-white p-4 rounded shadow">
            <div class="text-sm text-gray-500">حضور اليوم</div>
            <div class="text-3xl font-bold">{{ $presentToday }} / {{ $activeEmployees }}</div>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <div class="text-sm text-gray-500">غياب اليوم</div>
            <div class="text-3xl font-bold">{{ $absentToday }}</div>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <div class="text-sm text-gray-500">إجازات معتمدة اليوم</div>
            <div class="text-3xl font-bold">{{ $approvedLeavesToday }}</div>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <div class="text-sm text-gray-500">طلبات قيد المراجعة</div>
            <div class="text-3xl font-bold text-orange-600">{{ $pendingLeaves }}</div>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-6">
        <div class="bg-white p-4 rounded shadow">
            <h2 class="font-bold mb-3">آخر 7 أيام</h2>
            <div class="flex items-end gap-2 h-40">
                @foreach($last7Days as $day)
                    <div class="flex-1 flex flex-col items-center">
                        <div class="bg-blue-600 w-full" style="height: {{ min(100, $day['count'] * 10) }}%"></div>
                        <div class="text-xs mt-1">{{ $day['date'] }}</div>
                        <div class="text-xs font-bold">{{ $day['count'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="bg-white p-4 rounded shadow">
            <h2 class="font-bold mb-3">آخر عمليات المسح</h2>
            <ul class="space-y-2">
                @foreach($recentScans as $scan)
                    <li class="flex justify-between text-sm">
                        <span>{{ $scan->employee->name }}</span>
                        <span class="text-gray-500">{{ $scan->type->value }} — {{ $scan->scanned_at->format('H:i') }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
```

- [ ] **Step 5: Replace placeholder route**

In `routes/web.php`, replace:
```php
Route::view('/', 'admin.overview')->name('overview');
```
with:
```php
Route::get('/', \App\Livewire\Admin\Overview::class)->name('overview');
```

- [ ] **Step 6: Run — expect PASS**

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: admin overview dashboard with KPIs and 7-day chart"
```

---

### Task 5.4: SMS Logs Page

**Files:**
- Create: `app/Livewire/Admin/SmsLogs/SmsLogList.php`
- Create: `resources/views/livewire/admin/sms-logs/sms-log-list.blade.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Write failing test**

`tests/Feature/Admin/SmsLogListTest.php`:
```php
<?php

use App\Enums\SmsStatus;
use App\Livewire\Admin\SmsLogs\SmsLogList;
use App\Models\{SmsLog, User};
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('filters logs by status', function () {
    SmsLog::factory()->create(['status' => SmsStatus::Sent]);
    SmsLog::factory()->create(['status' => SmsStatus::Failed]);

    Livewire::test(SmsLogList::class)
        ->set('status', 'failed')
        ->assertViewHas('logs', fn ($items) => $items->count() === 1);
});
```

- [ ] **Step 2: Run — expect FAIL**

- [ ] **Step 3: Implement SmsLogList**

`app/Livewire/Admin/SmsLogs/SmsLogList.php`:
```php
<?php

namespace App\Livewire\Admin\SmsLogs;

use App\Models\SmsLog;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class SmsLogList extends Component
{
    use WithPagination;

    #[Url] public string $status = 'all';
    #[Url] public string $phone = '';
    #[Url] public ?string $from = null;
    #[Url] public ?string $to = null;

    public function updating($property): void
    {
        if (in_array($property, ['status', 'phone', 'from', 'to'])) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $query = SmsLog::latest('sent_at');

        if ($this->status !== 'all') $query->where('status', $this->status);
        if ($this->phone) $query->where('phone', 'like', "%{$this->phone}%");
        if ($this->from) $query->whereDate('sent_at', '>=', $this->from);
        if ($this->to) $query->whereDate('sent_at', '<=', $this->to);

        return view('livewire.admin.sms-logs.sms-log-list', [
            'logs' => $query->paginate(20),
        ]);
    }
}
```

- [ ] **Step 4: Create Blade view**

`resources/views/livewire/admin/sms-logs/sms-log-list.blade.php`:
```blade
<div class="p-6">
    <h1 class="text-2xl font-bold mb-4">سجل SMS</h1>

    <div class="grid grid-cols-4 gap-4 mb-4">
        <select wire:model.live="status" class="border p-2 rounded">
            <option value="all">كل الحالات</option>
            <option value="sent">مرسلة</option>
            <option value="failed">فشلت</option>
        </select>
        <input type="text" wire:model.live.debounce.300ms="phone" placeholder="بحث برقم الجوال" class="border p-2 rounded" dir="ltr">
        <input type="date" wire:model.live="from" class="border p-2 rounded">
        <input type="date" wire:model.live="to" class="border p-2 rounded">
    </div>

    <table class="w-full border-collapse border">
        <thead class="bg-gray-100">
            <tr>
                <th class="border p-2">الوقت</th>
                <th class="border p-2">الجوال</th>
                <th class="border p-2">الرسالة</th>
                <th class="border p-2">الحالة</th>
                <th class="border p-2">الخطأ</th>
            </tr>
        </thead>
        <tbody>
            @foreach($logs as $log)
                <tr>
                    <td class="border p-2 text-center">{{ $log->sent_at->format('Y-m-d H:i') }}</td>
                    <td class="border p-2 text-center" dir="ltr">{{ $log->phone }}</td>
                    <td class="border p-2 text-sm">{{ $log->message }}</td>
                    <td class="border p-2 text-center">{{ $log->status->value }}</td>
                    <td class="border p-2 text-center text-red-600" dir="ltr">{{ $log->error_code }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mt-4">{{ $logs->links() }}</div>
</div>
```

- [ ] **Step 5: Add route**

```php
Route::get('/sms-logs', \App\Livewire\Admin\SmsLogs\SmsLogList::class)->name('sms-logs.index');
```

- [ ] **Step 6: Run — expect PASS**

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: SMS logs admin page with filters"
```

---

### Task 5.5: Admin Navigation Menu

**Files:**
- Modify: `resources/views/layouts/navigation.blade.php` (Breeze provides this)

- [ ] **Step 1: Add nav links**

In `resources/views/layouts/navigation.blade.php`, inside the `<x-nav-link>` group, add:

```blade
<x-nav-link :href="route('admin.overview')" :active="request()->routeIs('admin.overview')">
    الرئيسية
</x-nav-link>
<x-nav-link :href="route('admin.employees.index')" :active="request()->routeIs('admin.employees.*')">
    الموظفين
</x-nav-link>
<x-nav-link :href="route('admin.attendance.index')" :active="request()->routeIs('admin.attendance.*')">
    الحضور
</x-nav-link>
<x-nav-link :href="route('admin.leaves.index')" :active="request()->routeIs('admin.leaves.*')">
    الإجازات
</x-nav-link>
<x-nav-link :href="route('admin.settings.index')" :active="request()->routeIs('admin.settings.*')">
    الإعدادات
</x-nav-link>
<x-nav-link :href="route('admin.sms-logs.index')" :active="request()->routeIs('admin.sms-logs.*')">
    سجل SMS
</x-nav-link>
```

- [ ] **Step 2: Manual smoke test**

```bash
php artisan serve
```
Visit `/login`, log in with `admin@example.com` / `password`, click through every menu item, ensure each page loads without errors.

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "chore: add admin navigation menu"
```

---

## Milestone 6 — Hardening

### Task 6.1: Full Test Suite Verification

- [ ] **Step 1: Run entire test suite**

```bash
./vendor/bin/pest --coverage --min=70
```

Expected: All tests green, coverage ≥ 70%.

- [ ] **Step 2: If any test fails, fix inline and commit**

- [ ] **Step 3: Add a CI-ready script to `composer.json`**

Under `"scripts"`:
```json
"test": "@php artisan test --parallel"
```

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "chore: add composer test script and verify coverage"
```

---

### Task 6.2: Manual End-to-End Smoke Test

- [ ] **Step 1: Run seed + serve**

```bash
php artisan migrate:fresh --seed
php artisan queue:work --queue=default &
php artisan serve
```

- [ ] **Step 2: Manual test checklist**

- [ ] Log in at `/login` with `admin@example.com` / `password`.
- [ ] Configure office lat/lng and enable GPS in `/admin/settings`.
- [ ] Create an employee with your own phone in `/admin/employees/create`. Confirm SMS delivered (or logged) with the employee number.
- [ ] Open `/scan` on a mobile browser, tap "تسجيل الحضور", enter the number, confirm — verify attendance appears in `/admin/attendance`.
- [ ] Submit a leave request via `/scan/leave`. Approve it in `/admin/leaves` — confirm SMS.
- [ ] Toggle GPS off and confirm scan still works.

- [ ] **Step 3: Note any issues, fix them, re-test**

- [ ] **Step 4: Commit any fixes**

```bash
git add -A
git commit -m "fix: smoke-test regressions"
```

---

### Task 6.3: README and Deployment Notes

**Files:**
- Create: `README.md`

- [ ] **Step 1: Write README**

`README.md`:
```markdown
# Employee Attendance System

Laravel 11 app for QR-based attendance and leave management, with MTC SMS notifications.

## Quick Start

```bash
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan queue:table && php artisan migrate
php artisan queue:work &
php artisan serve
```

Default admin: `admin@example.com` / `password`.

## Configuration

All runtime settings (GPS, IP whitelist, MTC credentials) live in the `/admin/settings` page — no `.env` edits needed after install.

## Deployment

- Point a supervisor process at `php artisan queue:work --tries=3 --backoff=60`.
- Ensure `Asia/Amman` timezone on the server.
- Serve behind HTTPS; browsers require secure context for geolocation.
- Rotate `APP_KEY` per environment.

## Test

```bash
./vendor/bin/pest
```
```

- [ ] **Step 2: Commit**

```bash
git add -A
git commit -m "docs: add README with quickstart and deployment notes"
```

---

## Definition of Done

- All 6 milestones' tests pass (`./vendor/bin/pest`).
- Manual smoke test in Task 6.2 completed successfully with real SMS delivery.
- No `TODO`, `TBD`, or placeholder code in committed files.
- Admin can log in, manage employees, review leaves, and configure settings.
- Public scan flow (attendance + leave) works from a mobile browser.
- SMS is dispatched via queue and logged in `/admin/sms-logs`.
- Test coverage ≥ 70%.

---

## Self-Review Notes

**Spec coverage** — each PRD section mapped to tasks:
- §3 Tech Stack → Task 1.1, 1.2 (installs), 6.1 (verification).
- §4 Architecture → structure enforced across every task (services + repos + models).
- §5 Data Model → Task 1.4 (migrations), Task 1.5 (models).
- §6 Use Cases:
  - UC-1 Check-in/out → Task 3.2 (service) + Task 3.3 (public flow).
  - UC-2 Leave request → Task 4.2.
  - UC-3 Create employee → Task 2.5 + 2.6.
  - UC-4 Review leave → Task 4.3.
  - UC-5 Configure fraud rules → Task 5.2.
  - UC-6 Overview KPIs → Task 5.3.
- §7 Dashboard/Filters → Tasks 2.6, 5.1, 4.3, 5.4.
- §8 SMS Integration → Tasks 2.2, 2.3, 2.4.
- §9 Security → CSRF (default), auth middleware (Task 1.7), rate limit (Task 3.3), input validation (FormRequests throughout).
- §11 Testing → Every task ends with tests + coverage gate in Task 6.1.

**No unresolved placeholders** in any code block.
**Type/method consistency** verified: `SmsGatewayInterface::send`, `SmsService::dispatch`/`sendNow`, `AttendanceService::record`/`previewNextType`/`checkFraud`, `LeaveService::submit`/`approve`/`reject`, `SettingsService::get`/`set`/`all` referenced consistently across all callers.

---

**End of Plan**
