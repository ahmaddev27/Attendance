<?php

declare(strict_types=1);

use App\Models\AttendanceDevice;
use App\Models\Employee;
use App\Models\EmployeeScanPin;
use App\Models\SmsLog;
use App\Models\User;
use App\Modules\Attendance\Services\ScanPinGenerator;
use App\Modules\Attendance\Services\ScanPinService;
use App\Modules\Sms\Contracts\SmsGateway;
use App\Modules\Sms\Contracts\SmsResult;
use App\Modules\Sms\Jobs\SendSmsJob;
use App\Shared\Enums\EmployeeStatus;
use App\Shared\Enums\ScanPinSource;
use App\Shared\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\Feature\Concerns\ActsAsEmployeeUser;

uses(ActsAsEmployeeUser::class);

function actingAsUserWithoutManageUsers(): User
{
    Permission::findOrCreate('manage-users', 'web');

    $user = User::factory()->create();
    Sanctum::actingAs($user);

    return $user;
}

/**
 * JsonResponse escapes "/" by default and bcrypt hashes contain slashes, so
 * a raw substring check on the body could miss a leaked hash.
 */
function unescapedJson(mixed $value): string
{
    return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

test('an admin reset returns a strong 4-digit pin once and stores only its hash', function () {
    Queue::fake();
    $admin = actingAsAdmin();
    $employee = Employee::factory()->create(['phone' => '0791234567']);

    $response = $this->postJson("/api/employees/{$employee->id}/scan-pin")
        ->assertOk()
        ->assertJsonPath('data.sms_queued', true);

    $pin = (string) $response->json('data.pin');

    expect($pin)->toMatch('/^\d{4}$/')
        ->and(app(ScanPinGenerator::class)->isWeak($pin))->toBeFalse()
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');

    $stored = EmployeeScanPin::query()->where('employee_id', $employee->id)->sole();

    expect($stored->pin_hash)->not->toBe($pin)
        ->and(Hash::check($pin, $stored->pin_hash))->toBeTrue()
        ->and($stored->set_via)->toBe(ScanPinSource::AdminReset)
        ->and($stored->set_by_user_id)->toBe($admin->id);

    Queue::assertPushed(
        SendSmsJob::class,
        // SmsService dials the international form of the stored local phone.
        fn (SendSmsJob $job): bool => $job->to === '970791234567' && str_contains($job->body, $pin),
    );

    $audit = DB::table('activity_log')->where('description', 'scan_pin_reset')->sole();

    expect($audit->log_name)->toBe('attendance')
        ->and((int) $audit->subject_id)->toBe($employee->id)
        ->and((int) $audit->causer_id)->toBe($admin->id)
        ->and((string) $audit->properties)->not->toContain($pin)
        ->and((string) $audit->properties)->not->toContain($stored->pin_hash);
});

test('an admin reset reports that no sms was queued when the employee has no phone', function () {
    Queue::fake();
    actingAsAdmin();
    $employee = Employee::factory()->create(['phone' => null]);

    $this->postJson("/api/employees/{$employee->id}/scan-pin")
        ->assertOk()
        ->assertJsonPath('data.sms_queued', false);

    Queue::assertNotPushed(SendSmsJob::class);
    expect(EmployeeScanPin::query()->where('employee_id', $employee->id)->exists())->toBeTrue();
});

test('an sms failure never rolls back a reset', function () {
    actingAsAdmin();
    $employee = Employee::factory()->create(['phone' => '0791234567']);
    app()->instance(SmsGateway::class, new class implements SmsGateway
    {
        public function send(string $to, string $body): SmsResult
        {
            throw new RuntimeException('carrier down');
        }
    });

    $this->postJson("/api/employees/{$employee->id}/scan-pin")
        ->assertOk()
        ->assertJsonPath('data.sms_queued', false);

    expect(EmployeeScanPin::query()->where('employee_id', $employee->id)->exists())->toBeTrue();
});

test('the pin never reaches the sms log', function () {
    actingAsAdmin();
    $employee = Employee::factory()->create(['phone' => '0791234567']);

    $pin = (string) $this->postJson("/api/employees/{$employee->id}/scan-pin")
        ->assertOk()
        ->json('data.pin');

    // The log records the number actually dialled: local phones are sent
    // in international form (default country code 970).
    $log = SmsLog::query()->where('to', '970791234567')->sole();

    expect($log->body)->toContain('[REDACTED]')
        ->and($log->body)->not->toContain($pin);
});

test('scan pin admin endpoints require manage-users', function () {
    actingAsUserWithoutManageUsers();
    $employee = Employee::factory()->create();

    $this->postJson("/api/employees/{$employee->id}/scan-pin")->assertForbidden();
    $this->getJson('/api/admin/attendance/scan-pins')->assertForbidden();
    $this->postJson('/api/admin/attendance/scan-pins/issue-missing')->assertForbidden();
    $this->putJson('/api/admin/attendance/scan-pins/enforcement', ['required' => true, 'force' => true])->assertForbidden();

    expect(EmployeeScanPin::query()->exists())->toBeFalse()
        ->and(app(ScanPinService::class)->isRequired())->toBeFalse();
});

test('issue-missing only issues pins to active employees without one and reports counts', function () {
    Queue::fake();
    actingAsAdmin();

    $withPhone = Employee::factory()->count(2)->create();
    $withoutPhone = Employee::factory()->create(['phone' => null]);
    $alreadyIssued = Employee::factory()->create();
    issueScanPinFor($alreadyIssued, '4829');
    $originalHash = EmployeeScanPin::query()->where('employee_id', $alreadyIssued->id)->value('pin_hash');
    $inactive = Employee::factory()->create(['status' => EmployeeStatus::Inactive]);
    $terminated = Employee::factory()->create(['status' => EmployeeStatus::Terminated]);

    $this->postJson('/api/admin/attendance/scan-pins/issue-missing')
        ->assertOk()
        ->assertJsonPath('data.issued', 3)
        ->assertJsonPath('data.sms_queued', 2)
        ->assertJsonPath('data.without_phone', 1);

    $newlyIssuedIds = [...$withPhone->pluck('id')->all(), $withoutPhone->id];

    expect(EmployeeScanPin::query()->whereIn('employee_id', $newlyIssuedIds)->where('set_via', ScanPinSource::BulkIssue->value)->count())->toBe(3)
        ->and(EmployeeScanPin::query()->where('employee_id', $alreadyIssued->id)->value('pin_hash'))->toBe($originalHash)
        ->and(EmployeeScanPin::query()->whereIn('employee_id', [$inactive->id, $terminated->id])->exists())->toBeFalse();

    Queue::assertPushed(SendSmsJob::class, 2);

    Queue::pushed(SendSmsJob::class)->each(function (SendSmsJob $job): void {
        preg_match('/\d{4}/', $job->body, $matches);
        // Jobs carry the international number; employees keep what was typed.
        $employee = Employee::query()->whereNotNull('phone')->get()
            ->sole(fn (Employee $candidate): bool => PhoneNumber::toInternational((string) $candidate->phone, '970') === $job->to);

        expect(Hash::check($matches[0], (string) EmployeeScanPin::query()->where('employee_id', $employee->id)->value('pin_hash')))->toBeTrue();
    });

    $audit = DB::table('activity_log')->where('description', 'scan_pin_bulk_issued')->sole();

    expect(json_decode((string) $audit->properties, true))
        ->toBe(['issued' => 3, 'sms_queued' => 2, 'without_phone' => 1]);
});

test('the summary reports pin coverage for active employees', function () {
    actingAsAdmin();
    issueScanPinFor(Employee::factory()->create(), '4829');
    Employee::factory()->create();
    Employee::factory()->create(['phone' => null]);
    Employee::factory()->create(['status' => EmployeeStatus::Terminated, 'phone' => null]);

    $this->getJson('/api/admin/attendance/scan-pins')
        ->assertOk()
        ->assertJsonPath('data.required', false)
        ->assertJsonPath('data.active_employees', 3)
        ->assertJsonPath('data.with_pin', 1)
        ->assertJsonPath('data.without_pin', 2)
        ->assertJsonPath('data.without_pin_and_phone', 1);
});

test('enabling enforcement while active employees lack a pin needs an explicit force', function () {
    actingAsAdmin();
    Employee::factory()->count(2)->create();

    $this->putJson('/api/admin/attendance/scan-pins/enforcement', ['required' => true])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['required' => 'يوجد 2 موظف نشط بدون رمز حضور. أرسل الرموز أولاً أو أكّد التفعيل.']);

    expect(app(ScanPinService::class)->isRequired())->toBeFalse();

    $this->putJson('/api/admin/attendance/scan-pins/enforcement', ['required' => true, 'force' => true])
        ->assertOk()
        ->assertJsonPath('data.required', true)
        ->assertJsonPath('data.without_pin', 2);

    $device = AttendanceDevice::factory()->create();
    $this->getJson("/api/scan/device/{$device->qr_token}")->assertJsonPath('pin_required', true);

    $audit = DB::table('activity_log')->where('description', 'scan_pin_enforcement_changed')->sole();

    expect(json_decode((string) $audit->properties, true))->toMatchArray(['required' => true, 'forced' => true]);
});

test('enforcement toggles without force once every active employee has a pin', function () {
    actingAsAdmin();
    issueScanPinFor(Employee::factory()->create(), '4829');
    Employee::factory()->create(['status' => EmployeeStatus::Inactive]);

    $this->putJson('/api/admin/attendance/scan-pins/enforcement', ['required' => true])
        ->assertOk()
        ->assertJsonPath('data.required', true);

    $this->putJson('/api/admin/attendance/scan-pins/enforcement', ['required' => false])
        ->assertOk()
        ->assertJsonPath('data.required', false);
});

test('changing my pin with a wrong current password is rejected', function () {
    $employee = Employee::factory()->create();
    $this->actingAsEmployeeUser($employee);

    $this->putJson('/api/me/scan-pin', ['current_password' => 'wrong-password', 'pin' => '4829', 'pin_confirmation' => '4829'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['current_password' => 'كلمة السر الحالية غير صحيحة.']);

    expect(EmployeeScanPin::query()->exists())->toBeFalse();
});

test('choosing a weak pin is rejected', function (string $weakPin) {
    $employee = Employee::factory()->create();
    $this->actingAsEmployeeUser($employee);

    $this->putJson('/api/me/scan-pin', ['current_password' => 'password', 'pin' => $weakPin, 'pin_confirmation' => $weakPin])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['pin' => 'رمز الحضور سهل التخمين. اختر أرقاماً غير متسلسلة أو مكررة.']);

    expect(EmployeeScanPin::query()->exists())->toBeFalse();
})->with(['0000', '1234', '9876', '2580']);

test('a pin confirmation that does not match is rejected', function () {
    $employee = Employee::factory()->create();
    $this->actingAsEmployeeUser($employee);

    $this->putJson('/api/me/scan-pin', ['current_password' => 'password', 'pin' => '4829', 'pin_confirmation' => '4828'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['pin' => 'تأكيد رمز الحضور لا يطابق الرمز الجديد.']);
});

test('an account without an employee profile cannot set a pin', function () {
    Sanctum::actingAs(User::factory()->create(['employee_id' => null]));

    $this->putJson('/api/me/scan-pin', ['current_password' => 'password', 'pin' => '4829', 'pin_confirmation' => '4829'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['pin' => 'حسابك غير مرتبط بملف موظف.']);
});

test('changing my pin replaces the old one for scanning', function () {
    enableScanPinEnforcement();
    $employee = makeEmployeeWithSchedule();
    issueScanPinFor($employee, '4829');
    $user = $this->actingAsEmployeeUser($employee);
    $device = AttendanceDevice::factory()->create();

    $this->putJson('/api/me/scan-pin', ['current_password' => 'password', 'pin' => '7315', 'pin_confirmation' => '7315'])
        ->assertNoContent();

    $stored = EmployeeScanPin::query()->where('employee_id', $employee->id)->sole();

    expect($stored->set_via)->toBe(ScanPinSource::SelfService)
        ->and($stored->set_by_user_id)->toBe($user->id);

    $payload = ['employee_number' => $employee->employee_number, 'qr_token' => $device->qr_token];

    $this->postJson('/api/scan/status', [...$payload, 'pin' => '4829'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'الرقم الوظيفي أو رمز الحضور غير صحيح.');

    $this->postJson('/api/scan/status', [...$payload, 'pin' => '7315'])
        ->assertOk();

    $this->assertDatabaseHas('activity_log', ['log_name' => 'attendance', 'description' => 'scan_pin_changed']);
});

test('the pin hash never appears in employee payloads or the search index', function () {
    actingAsAdmin();
    $employee = Employee::factory()->create();
    issueScanPinFor($employee, '4829');
    $hash = (string) EmployeeScanPin::query()->where('employee_id', $employee->id)->value('pin_hash');

    foreach (["/api/employees/{$employee->id}", '/api/employees'] as $uri) {
        $body = unescapedJson($this->getJson($uri)->assertOk()->json());

        expect($body)->not->toContain($hash)->not->toContain('pin_hash');
    }

    $employee = $employee->fresh()->load('scanPin');

    expect(unescapedJson($employee->toArray()))->not->toContain($hash)->not->toContain('pin_hash')
        ->and(unescapedJson($employee->toSearchableArray()))->not->toContain($hash);

    $this->actingAsEmployeeUser($employee);

    expect(unescapedJson($this->getJson('/api/me/profile')->assertOk()->json()))
        ->not->toContain($hash)
        ->not->toContain('pin_hash');
});

test('generated pins are always four digits and never weak', function () {
    $generator = app(ScanPinGenerator::class);

    foreach (range(1, 500) as $iteration) {
        $pin = $generator->generate();

        expect($pin)->toMatch('/^\d{4}$/')
            ->and($generator->isWeak($pin))->toBeFalse();
    }
});

test('the weak pin checker flags every documented pattern', function () {
    $generator = app(ScanPinGenerator::class);

    $weak = [
        ...array_map(fn (int $digit): string => str_repeat((string) $digit, 4), range(0, 9)),
        '0123', '1234', '2345', '3456', '4567', '5678', '6789',
        '9876', '8765', '7654', '6543', '5432', '4321', '3210',
        '1212', '2580', '0852', '1004', '2000', '1122',
    ];

    foreach ($weak as $pin) {
        expect($generator->isWeak($pin))->toBeTrue("{$pin} should be weak");
    }

    foreach (['4829', '7315', '1357', '0413', '8024'] as $pin) {
        expect($generator->isWeak($pin))->toBeFalse("{$pin} should be accepted");
    }
});
