<?php

declare(strict_types=1);

use App\Models\Attendance;
use App\Models\AttendanceDevice;
use App\Modules\Attendance\Services\ScanPinHasher;
use App\Shared\Enums\AttendanceStatus;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    // PIN-only identity is driven off the enforcement toggle (see memory
    // project-pin-only-scan-decision). The whole file assumes it is on.
    enableScanPinEnforcement();
    RateLimiter::clear('scan-pin:ip:127.0.0.1');
});

test('PIN-only identity: /scan/record resolves the employee from the PIN alone', function () {
    $employee = makeEmployeeWithSchedule();
    issueScanPinFor($employee, '4829');
    $device = AttendanceDevice::factory()->create();

    // No employee_number in the payload at all — PIN is the sole identifier.
    $response = $this->postJson('/api/scan/record', [
        'qr_token' => $device->qr_token,
        'pin' => '4829',
    ])
        ->assertOk()
        ->assertJsonPath('action', 'check-in')
        ->assertJsonPath('attendance.employee_id', $employee->id);

    expect(Attendance::query()->where('employee_id', $employee->id)->count())->toBe(1);
    expect($response->json('message'))->toBe('تم تسجيل حضورك');
});

test('PIN-only identity: a second /scan/record switches the same employee to check-out', function () {
    $employee = makeEmployeeWithSchedule();
    issueScanPinFor($employee, '4829');
    $device = AttendanceDevice::factory()->create();

    $this->postJson('/api/scan/record', [
        'qr_token' => $device->qr_token,
        'pin' => '4829',
    ])->assertOk()->assertJsonPath('action', 'check-in');

    $this->postJson('/api/scan/record', [
        'qr_token' => $device->qr_token,
        'pin' => '4829',
    ])->assertOk()->assertJsonPath('action', 'check-out');

    $row = Attendance::query()->where('employee_id', $employee->id)->first();
    expect($row->check_in_at)->not->toBeNull();
    expect($row->check_out_at)->not->toBeNull();
});

test('PIN-only identity: a third /scan/record after both halves returns 409 done', function () {
    $employee = makeEmployeeWithSchedule();
    issueScanPinFor($employee, '4829');
    $device = AttendanceDevice::factory()->create();

    // Simulate a complete day already recorded so we don't have to call the
    // live services twice — this test is only about the "done" branch.
    Attendance::query()->create([
        'employee_id' => $employee->id,
        'date' => now()->toDateString(),
        'check_in_at' => now()->subHours(8),
        'check_out_at' => now()->subMinutes(5),
        'status' => AttendanceStatus::Present->value,
        'check_in_device_id' => $device->id,
        'check_out_device_id' => $device->id,
    ]);

    $this->postJson('/api/scan/record', [
        'qr_token' => $device->qr_token,
        'pin' => '4829',
    ])
        ->assertStatus(409)
        ->assertJsonPath('action', 'done')
        ->assertJsonPath('message', 'سجّلت حضورك وانصرافك لليوم.');
});

test('PIN-only identity: a wrong PIN gets the generic invalid-credentials message', function () {
    $employee = makeEmployeeWithSchedule();
    issueScanPinFor($employee, '4829');
    $device = AttendanceDevice::factory()->create();

    $this->postJson('/api/scan/record', [
        'qr_token' => $device->qr_token,
        'pin' => '9164',
    ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'رمز الحضور غير صحيح.');

    expect(Attendance::query()->where('employee_id', $employee->id)->exists())->toBeFalse();
});

test('PIN-only identity: /scan/record WITHOUT a PIN gets a validation error', function () {
    $device = AttendanceDevice::factory()->create();

    $this->postJson('/api/scan/record', [
        'qr_token' => $device->qr_token,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['pin' => 'أدخل رمز الحضور المكوّن من 4 أرقام.']);
});

test('PIN-only identity: an IP that misses 25 PINs in a row is rate-limited', function () {
    $employee = makeEmployeeWithSchedule();
    issueScanPinFor($employee, '4829');
    $device = AttendanceDevice::factory()->create();

    for ($i = 0; $i < 25; $i++) {
        $this->postJson('/api/scan/record', [
            'qr_token' => $device->qr_token,
            'pin' => '9164',
        ])->assertStatus(422);
    }

    $this->postJson('/api/scan/record', [
        'qr_token' => $device->qr_token,
        'pin' => '4829',
    ])
        ->assertStatus(429)
        ->assertJsonPath('message', 'تم إيقاف المسح لهذا الرقم مؤقتاً بسبب محاولات خاطئة متكررة. حاول بعد دقيقتين.');
});

test('PIN uniqueness: two employees can never share the same PIN via issueScanPinFor', function () {
    // Direct repo call — mirrors what ScanPinService::reset guarantees via
    // generateUnique() + hasher. The DB UNIQUE constraint on
    // pin_lookup_hash is the backstop that makes PIN-only identity safe.
    $a = makeEmployeeWithSchedule();
    $b = makeEmployeeWithSchedule();

    issueScanPinFor($a, '4829');

    expect(fn () => issueScanPinFor($b, '4829'))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

test('PIN-only identity: the HMAC lookup matches whatever ScanPinHasher produces', function () {
    $employee = makeEmployeeWithSchedule();
    issueScanPinFor($employee, '4829');

    $hasher = app(ScanPinHasher::class);
    $stored = \App\Models\EmployeeScanPin::query()->where('employee_id', $employee->id)->first();

    expect($stored->pin_lookup_hash)->toBe($hasher->hash('4829'));
    expect($hasher->hash('4829'))->not->toBe($hasher->hash('4830'));
});

test('PIN enforcement OFF: /scan/record falls back to the legacy employee_number path', function () {
    // Flip the enforcement off for this one test. The record endpoint is
    // allowed to work with the pre-PIN flow too (so deployments running
    // PIN-off are not broken by shipping the endpoint).
    app(\App\Modules\Settings\Services\SettingsService::class)
        ->set('attendance.scan_pin_required', '0', 'attendance');

    $employee = makeEmployeeWithSchedule();
    $device = AttendanceDevice::factory()->create();

    $this->postJson('/api/scan/record', [
        'qr_token' => $device->qr_token,
        'employee_number' => $employee->employee_number,
    ])->assertOk()->assertJsonPath('action', 'check-in');
});
