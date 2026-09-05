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
