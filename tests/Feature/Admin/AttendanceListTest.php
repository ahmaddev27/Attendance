<?php

use App\Enums\AttendanceType;
use App\Livewire\Admin\Attendance\AttendanceList;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
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
