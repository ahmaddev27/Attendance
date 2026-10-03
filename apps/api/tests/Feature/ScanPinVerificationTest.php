<?php

declare(strict_types=1);

use App\Models\Attendance;
use App\Models\AttendanceDevice;
use App\Models\Employee;
use App\Models\User;

/**
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function scanPinPayload(Employee $employee, AttendanceDevice $device, array $extra = []): array
{
    return [
        'employee_number' => $employee->employee_number,
        'qr_token' => $device->qr_token,
        ...$extra,
    ];
}

test('with enforcement off a public scan needs no pin and ignores one if sent', function () {
    $employee = makeEmployeeWithSchedule();
    $device = AttendanceDevice::factory()->create();

    $this->postJson('/api/scan/check-in', scanPinPayload($employee, $device, ['pin' => 'not-a-pin']))
        ->assertOk()
        ->assertJsonPath('attendance.employee_id', $employee->id);
});

test('with enforcement off an unknown employee number keeps the existing response', function () {
    $device = AttendanceDevice::factory()->create();

    $this->postJson('/api/scan/status', ['employee_number' => 999999, 'qr_token' => $device->qr_token])
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Unknown employee number.');
});

test('with enforcement on a check-in without a pin is rejected', function () {
    enableScanPinEnforcement();
    $employee = makeEmployeeWithSchedule();
    issueScanPinFor($employee, '4829');
    $device = AttendanceDevice::factory()->create();

    $this->postJson('/api/scan/check-in', scanPinPayload($employee, $device))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['pin' => 'أدخل رمز الحضور المكوّن من 4 أرقام.']);

    expect(Attendance::query()->where('employee_id', $employee->id)->exists())->toBeFalse();
});

test('with enforcement on a wrong pin is rejected with the generic message', function () {
    enableScanPinEnforcement();
    $employee = makeEmployeeWithSchedule();
    issueScanPinFor($employee, '4829');
    $device = AttendanceDevice::factory()->create();

    $this->postJson('/api/scan/check-in', scanPinPayload($employee, $device, ['pin' => '9164']))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'رمز الحضور غير صحيح.');

    expect(Attendance::query()->where('employee_id', $employee->id)->exists())->toBeFalse();
});

test('with enforcement on an unknown employee number gets the same generic message', function () {
    // Under PIN-only identity (owner's 2026-10-03 call), employee_number is
    // ignored on the kiosk path — the PIN alone identifies the row. An
    // unknown number plus a wrong PIN therefore collapses to "wrong PIN".
    enableScanPinEnforcement();
    $device = AttendanceDevice::factory()->create();

    $this->postJson('/api/scan/check-in', ['employee_number' => 999999, 'qr_token' => $device->qr_token, 'pin' => '4829'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'رمز الحضور غير صحيح.');
});

test('with enforcement on the correct pin checks the employee in', function () {
    enableScanPinEnforcement();
    $employee = makeEmployeeWithSchedule();
    issueScanPinFor($employee, '4829');
    $device = AttendanceDevice::factory()->create();

    $this->postJson('/api/scan/check-in', scanPinPayload($employee, $device, ['pin' => '4829']))
        ->assertOk()
        ->assertJsonPath('attendance.employee_id', $employee->id);

    expect(Attendance::query()->where('employee_id', $employee->id)->exists())->toBeTrue();
});

test('ten wrong pins from the same IP lock the kiosk out even when the next pin is correct', function () {
    // PIN-only identity rate-limits per-IP (we can't key off an employee
    // we haven't resolved yet). Threshold is 10 — wider than the old
    // per-employee bucket because a busy shared kiosk sees legit typos.
    enableScanPinEnforcement();
    $employee = makeEmployeeWithSchedule();
    issueScanPinFor($employee, '4829');
    $device = AttendanceDevice::factory()->create();

    foreach (range(1, 10) as $attempt) {
        $this->postJson('/api/scan/status', scanPinPayload($employee, $device, ['pin' => '9164']))
            ->assertUnprocessable();
    }

    $this->postJson('/api/scan/check-in', scanPinPayload($employee, $device, ['pin' => '4829']))
        ->assertStatus(429)
        ->assertJsonPath('message', 'تم إيقاف المسح لهذا الرقم مؤقتاً بسبب محاولات خاطئة متكررة. حاول بعد 15 دقيقة.');

    expect(Attendance::query()->where('employee_id', $employee->id)->exists())->toBeFalse();
});

test('a correct pin clears the IP failed-attempt counter', function () {
    enableScanPinEnforcement();
    $employee = makeEmployeeWithSchedule();
    issueScanPinFor($employee, '4829');
    $device = AttendanceDevice::factory()->create();

    foreach (range(1, 2) as $round) {
        foreach (range(1, 9) as $attempt) {
            $this->postJson('/api/scan/status', scanPinPayload($employee, $device, ['pin' => '9164']))
                ->assertUnprocessable();
        }

        $this->postJson('/api/scan/status', scanPinPayload($employee, $device, ['pin' => '4829']))
            ->assertOk();
    }
});

test('an unknown PIN just gets the generic invalid-credentials response', function () {
    // Under PIN-only identity, "no PIN issued for this employee" is
    // indistinguishable from "wrong PIN" — the server never had a row to
    // match the typed PIN against, so it returns the generic message.
    // This is a feature: it prevents walking the directory to learn who
    // does/doesn't have a PIN yet.
    enableScanPinEnforcement();
    $employee = makeEmployeeWithSchedule();
    $device = AttendanceDevice::factory()->create();

    $this->postJson('/api/scan/check-in', scanPinPayload($employee, $device, ['pin' => '4829']))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'رمز الحضور غير صحيح.');
});

test('the status endpoint requires the pin too', function () {
    enableScanPinEnforcement();
    $employee = makeEmployeeWithSchedule();
    issueScanPinFor($employee, '4829');
    $device = AttendanceDevice::factory()->create();

    $this->postJson('/api/scan/status', scanPinPayload($employee, $device))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['pin']);

    $this->postJson('/api/scan/status', scanPinPayload($employee, $device, ['pin' => '9164']))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'رمز الحضور غير صحيح.');

    $this->postJson('/api/scan/status', scanPinPayload($employee, $device, ['pin' => '4829']))
        ->assertOk()
        ->assertJsonPath('data.state', 'not_checked_in');
});

test('check-out requires the pin too', function () {
    enableScanPinEnforcement();
    $employee = makeEmployeeWithSchedule();
    issueScanPinFor($employee, '4829');
    $device = AttendanceDevice::factory()->create();

    $this->postJson('/api/scan/check-out', scanPinPayload($employee, $device, ['pin' => '9164']))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'رمز الحضور غير صحيح.');
});

test('scans with an invalid qr token never count against the pin lockout', function () {
    enableScanPinEnforcement();
    $employee = makeEmployeeWithSchedule();
    issueScanPinFor($employee, '4829');
    $device = AttendanceDevice::factory()->create();

    foreach (range(1, 6) as $attempt) {
        $this->postJson('/api/scan/check-in', [
            'employee_number' => $employee->employee_number,
            'qr_token' => str_repeat('x', 64),
            'pin' => '9164',
        ])->assertUnauthorized();
    }

    $this->postJson('/api/scan/status', scanPinPayload($employee, $device, ['pin' => '4829']))
        ->assertOk();
});

test('device info exposes whether a pin is required', function () {
    $device = AttendanceDevice::factory()->create();

    $this->getJson("/api/scan/device/{$device->qr_token}")
        ->assertOk()
        ->assertJsonPath('pin_required', false);

    enableScanPinEnforcement();

    $this->getJson("/api/scan/device/{$device->qr_token}")
        ->assertOk()
        ->assertJsonPath('pin_required', true);
});

test('a valid bearer token scans without a pin while enforcement is on', function () {
    enableScanPinEnforcement();
    $employee = makeEmployeeWithSchedule();
    $user = User::factory()->create(['employee_id' => $employee->id]);
    $device = AttendanceDevice::factory()->create();

    $this->withToken($user->createToken('mobile')->plainTextToken)
        ->postJson('/api/scan/check-in', scanPinPayload($employee, $device))
        ->assertOk()
        ->assertJsonPath('attendance.employee_id', $employee->id);
});

test('a bearer token scan may omit the employee number', function () {
    enableScanPinEnforcement();
    $employee = makeEmployeeWithSchedule();
    $user = User::factory()->create(['employee_id' => $employee->id]);
    $device = AttendanceDevice::factory()->create();

    $this->withToken($user->createToken('mobile')->plainTextToken)
        ->postJson('/api/scan/status', ['qr_token' => $device->qr_token])
        ->assertOk()
        ->assertJsonPath('data.employee.id', $employee->id);
});

test('a bearer token cannot scan for another employee', function () {
    $employee = makeEmployeeWithSchedule();
    $colleague = makeEmployeeWithSchedule();
    $user = User::factory()->create(['employee_id' => $employee->id]);
    $device = AttendanceDevice::factory()->create();

    $this->withToken($user->createToken('mobile')->plainTextToken)
        ->postJson('/api/scan/check-in', scanPinPayload($colleague, $device))
        ->assertForbidden()
        ->assertJsonPath('message', 'لا يمكن تسجيل الحضور عن موظف آخر.');

    expect(Attendance::query()->exists())->toBeFalse();
});

test('a bearer token whose account has no employee profile is rejected', function () {
    $user = User::factory()->create(['employee_id' => null]);
    $device = AttendanceDevice::factory()->create();

    $this->withToken($user->createToken('mobile')->plainTextToken)
        ->postJson('/api/scan/status', ['qr_token' => $device->qr_token])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'حسابك غير مرتبط بملف موظف.');
});

test('an invalid bearer token is rejected instead of falling back to the employee number', function () {
    $employee = makeEmployeeWithSchedule();
    $device = AttendanceDevice::factory()->create();

    $this->withToken('1|not-a-real-token')
        ->postJson('/api/scan/check-in', scanPinPayload($employee, $device))
        ->assertUnauthorized()
        ->assertJsonPath('message', 'جلسة الدخول غير صالحة أو منتهية. سجّل الدخول مجدداً.');

    expect(Attendance::query()->exists())->toBeFalse();
});

test('an expired bearer token is rejected', function () {
    $employee = makeEmployeeWithSchedule();
    $user = User::factory()->create(['employee_id' => $employee->id]);
    $device = AttendanceDevice::factory()->create();
    $token = $user->createToken('mobile')->plainTextToken;

    $this->travel((int) config('sanctum.expiration') + 1)->minutes();

    $this->withToken($token)
        ->postJson('/api/scan/status', ['qr_token' => $device->qr_token])
        ->assertUnauthorized();
});

test('a signed-in web session never stands in for the scanning employee', function () {
    $employee = makeEmployeeWithSchedule();
    $user = User::factory()->create(['employee_id' => $employee->id]);
    $device = AttendanceDevice::factory()->create();

    $this->actingAs($user, 'web');

    $this->withToken('garbage')
        ->postJson('/api/scan/status', ['qr_token' => $device->qr_token])
        ->assertUnauthorized();

    $this->withoutToken()
        ->postJson('/api/scan/status', ['qr_token' => $device->qr_token])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['employee_number']);
});
