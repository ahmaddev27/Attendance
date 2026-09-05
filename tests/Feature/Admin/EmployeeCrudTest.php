<?php

use App\Livewire\Admin\Employees\EmployeeList;
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

    Livewire::test(EmployeeList::class)
        ->assertOk()
        ->assertViewHas('employees', fn ($items) => $items->count() === 3);
});

it('filters employees by search term', function () {
    Employee::factory()->create(['name' => 'Ahmad Ali']);
    Employee::factory()->create(['name' => 'Sara Ahmad']);
    Employee::factory()->create(['name' => 'Omar Hassan']);

    Livewire::test(EmployeeList::class)
        ->set('search', 'Ahmad')
        ->assertViewHas('employees', fn ($items) => $items->count() === 2);
});

it('opens the create modal', function () {
    Livewire::test(EmployeeList::class)
        ->call('openCreate')
        ->assertSet('showModal', true)
        ->assertSet('editingId', null)
        ->assertSet('name', '');
});

it('creates an employee via modal', function () {
    Livewire::test(EmployeeList::class)
        ->call('openCreate')
        ->set('name', 'Ahmad')
        ->set('phone', '+962700000000')
        ->set('email', 'ahmad@example.com')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showModal', false);

    expect(Employee::where('phone', '+962700000000')->exists())->toBeTrue();
});

it('validates required fields in modal', function () {
    Livewire::test(EmployeeList::class)
        ->call('openCreate')
        ->call('save')
        ->assertHasErrors(['name', 'phone'])
        ->assertSet('showModal', true);
});

it('validates unique phone', function () {
    Employee::factory()->create(['phone' => '+962700000000']);

    Livewire::test(EmployeeList::class)
        ->call('openCreate')
        ->set('name', 'X')
        ->set('phone', '+962700000000')
        ->call('save')
        ->assertHasErrors(['phone']);
});

it('opens the edit modal with existing data', function () {
    $emp = Employee::factory()->create([
        'name' => 'Sara',
        'phone' => '+962700000000',
        'email' => 'sara@example.com',
        'is_active' => true,
    ]);

    Livewire::test(EmployeeList::class)
        ->call('openEdit', $emp->id)
        ->assertSet('showModal', true)
        ->assertSet('editingId', $emp->id)
        ->assertSet('name', 'Sara')
        ->assertSet('phone', '+962700000000');
});

it('updates an employee via modal', function () {
    $emp = Employee::factory()->create(['name' => 'Old Name']);

    Livewire::test(EmployeeList::class)
        ->call('openEdit', $emp->id)
        ->set('name', 'New Name')
        ->call('save')
        ->assertSet('showModal', false);

    expect($emp->fresh()->name)->toBe('New Name');
});

it('allows updating own phone without unique conflict', function () {
    $emp = Employee::factory()->create(['phone' => '+962711111111']);

    Livewire::test(EmployeeList::class)
        ->call('openEdit', $emp->id)
        ->set('name', 'Updated')
        ->call('save')
        ->assertHasNoErrors();
});
