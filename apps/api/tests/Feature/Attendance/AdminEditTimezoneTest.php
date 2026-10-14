<?php

declare(strict_types=1);

use App\Models\Attendance;

function gazaClock(?\DateTimeInterface $at): ?string
{
    return $at ? \Carbon\Carbon::instance($at)->setTimezone('Asia/Gaza')->format('Y-m-d H:i') : null;
}

test('a naive admin-typed check_in_at is interpreted as Asia/Gaza wall-clock', function () {
    actingAsAdmin();
    $employee = makeEmployeeWithSchedule();
    $attendance = Attendance::factory()->create(['employee_id' => $employee->id]);

    $this->patchJson("/api/attendance/{$attendance->id}", ['check_in_at' => '2026-10-14 10:00'])
        ->assertOk();

    expect(gazaClock($attendance->fresh()->check_in_at))->toBe('2026-10-14 10:00');
});

test('an ISO string with an explicit offset still resolves to the same instant', function () {
    actingAsAdmin();
    $employee = makeEmployeeWithSchedule();
    $attendance = Attendance::factory()->create(['employee_id' => $employee->id]);

    $this->patchJson("/api/attendance/{$attendance->id}", ['check_in_at' => '2026-10-14T07:00:00Z'])
        ->assertOk();

    expect($attendance->fresh()->check_in_at->copy()->utc()->format('Y-m-d H:i'))->toBe('2026-10-14 07:00');
});

test('omitting the timestamps does not clear them', function () {
    actingAsAdmin();
    $employee = makeEmployeeWithSchedule();
    $attendance = Attendance::factory()->create(['employee_id' => $employee->id]);
    $before = $attendance->fresh()->check_in_at;

    $this->patchJson("/api/attendance/{$attendance->id}", ['notes' => 'x'])->assertOk();

    expect($attendance->fresh()->check_in_at?->equalTo($before))->toBeTrue();
});
