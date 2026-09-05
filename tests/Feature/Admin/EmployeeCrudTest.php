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
