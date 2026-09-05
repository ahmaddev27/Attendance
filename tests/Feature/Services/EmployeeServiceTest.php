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

it('dispatches welcome SMS containing the employee number', function () {
    Queue::fake();

    app(EmployeeService::class)->create([
        'name' => 'Ahmad',
        'phone' => '+962700000000',
    ]);

    Queue::assertPushed(\App\Jobs\SendSmsJob::class);
});
