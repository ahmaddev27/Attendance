<?php

declare(strict_types=1);

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use App\Modules\Notifications\Notifications\OpenSessionReminderNotification;
use App\Shared\Enums\AttendanceStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

afterEach(function () {
    Carbon::setTestNow();
});

test('auto-close stamps shift-end on open attendance after shift ended', function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 4, 20, 0, 0, 'Asia/Amman'));

    $schedule = makeWorkSchedule([
        'check_in_time' => '08:00',
        'check_out_time' => '16:00',
        'timezone' => 'Asia/Amman',
    ]);

    $employee = Employee::factory()->create(['work_schedule_id' => $schedule->id]);

    $attendance = Attendance::factory()->create([
        'employee_id' => $employee->id,
        'date' => '2026-10-04',
        'check_in_at' => Carbon::create(2026, 10, 4, 8, 0, 0, 'Asia/Amman'),
        'check_out_at' => null,
        'total_minutes' => null,
        'late_minutes' => 0,
        'early_leave_minutes' => 0,
        'overtime_minutes' => 0,
        'status' => AttendanceStatus::Present,
    ]);

    $this->artisan('taqat:auto-close-attendance')->assertSuccessful();

    $attendance->refresh();
    expect($attendance->check_out_at)->not->toBeNull();
    expect($attendance->check_out_at->format('Y-m-d H:i'))
        ->toBe('2026-10-04 16:00');
    expect($attendance->notes)->toContain('[نظام]');
});

test('auto-close skips sessions whose shift has not ended yet', function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 4, 14, 0, 0, 'Asia/Amman'));

    $schedule = makeWorkSchedule([
        'check_in_time' => '08:00',
        'check_out_time' => '16:00',
        'timezone' => 'Asia/Amman',
    ]);

    $employee = Employee::factory()->create(['work_schedule_id' => $schedule->id]);

    $attendance = Attendance::factory()->create([
        'employee_id' => $employee->id,
        'date' => '2026-10-04',
        'check_in_at' => Carbon::create(2026, 10, 4, 8, 0, 0, 'Asia/Amman'),
        'check_out_at' => null,
        'total_minutes' => null,
        'late_minutes' => 0,
        'early_leave_minutes' => 0,
        'overtime_minutes' => 0,
        'status' => AttendanceStatus::Present,
    ]);

    $this->artisan('taqat:auto-close-attendance')->assertSuccessful();

    expect($attendance->fresh()->check_out_at)->toBeNull();
});

test('reminder fires ~5 minutes before shift-end for open sessions', function () {
    Notification::fake();
    Carbon::setTestNow(Carbon::create(2026, 10, 4, 15, 55, 0, 'Asia/Amman'));

    $user = User::factory()->create();
    $schedule = makeWorkSchedule([
        'check_in_time' => '08:00',
        'check_out_time' => '16:00',
        'timezone' => 'Asia/Amman',
    ]);

    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'work_schedule_id' => $schedule->id,
    ]);

    Attendance::factory()->create([
        'employee_id' => $employee->id,
        'date' => '2026-10-04',
        'check_in_at' => Carbon::create(2026, 10, 4, 8, 0, 0, 'Asia/Amman'),
        'check_out_at' => null,
        'total_minutes' => null,
        'late_minutes' => 0,
        'early_leave_minutes' => 0,
        'overtime_minutes' => 0,
        'status' => AttendanceStatus::Present,
    ]);

    $this->artisan('taqat:notify-open-sessions')->assertSuccessful();

    Notification::assertSentTo($user, OpenSessionReminderNotification::class);
});

test('reminder does not fire outside the 3-8 minute window', function () {
    Notification::fake();
    // 30 minutes before shift-end — outside the new 3-8 window.
    Carbon::setTestNow(Carbon::create(2026, 10, 4, 15, 30, 0, 'Asia/Amman'));

    $user = User::factory()->create();
    $schedule = makeWorkSchedule([
        'check_in_time' => '08:00',
        'check_out_time' => '16:00',
        'timezone' => 'Asia/Amman',
    ]);

    $employee = Employee::factory()->create([
        'user_id' => $user->id,
        'work_schedule_id' => $schedule->id,
    ]);

    Attendance::factory()->create([
        'employee_id' => $employee->id,
        'date' => '2026-10-04',
        'check_in_at' => Carbon::create(2026, 10, 4, 8, 0, 0, 'Asia/Amman'),
        'check_out_at' => null,
        'total_minutes' => null,
        'late_minutes' => 0,
        'early_leave_minutes' => 0,
        'overtime_minutes' => 0,
        'status' => AttendanceStatus::Present,
    ]);

    $this->artisan('taqat:notify-open-sessions')->assertSuccessful();

    Notification::assertNothingSent();
});
