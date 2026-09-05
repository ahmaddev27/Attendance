<?php

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\Sms\FakeSmsGateway;
use App\Services\Sms\SmsGatewayInterface;

beforeEach(fn () => $this->app->instance(SmsGatewayInterface::class, new FakeSmsGateway()));

it('shows leave request form', function () {
    $this->get('/scan')
        ->assertOk()
        ->assertSee('تسجيل الحضور')
        ->assertSee('طلب إجازة');
});

it('submits a leave request', function () {
    $emp = Employee::factory()->create(['employee_number' => 1001]);
    $start = now()->addDays(30)->format('Y-m-d');
    $end = now()->addDays(31)->format('Y-m-d');

    $this->post('/scan/leave', [
        'employee_number' => 1001,
        'start_date' => $start,
        'end_date' => $end,
        'note' => 'family',
    ])->assertRedirect();

    expect(LeaveRequest::count())->toBe(1);
});

it('validates dates', function () {
    Employee::factory()->create(['employee_number' => 1001]);
    $start = now()->addDays(30)->format('Y-m-d');
    $end = now()->addDays(20)->format('Y-m-d');

    $this->post('/scan/leave', [
        'employee_number' => 1001,
        'start_date' => $start,
        'end_date' => $end,
    ])->assertSessionHasErrors('end_date');
});
