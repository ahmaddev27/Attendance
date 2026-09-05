<?php

namespace App\Livewire\Admin\Employees;

use App\Models\Employee;
use App\Services\EmployeeService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

class EmployeeForm extends Component
{
    public ?Employee $employee = null;

    public string $name = '';

    public string $phone = '';

    public ?string $email = null;

    public bool $isActive = true;

    public function mount(?Employee $employee = null): void
    {
        if ($employee?->exists) {
            $this->employee = $employee;
            $this->name = $employee->name;
            $this->phone = $employee->phone;
            $this->email = $employee->email;
            $this->isActive = $employee->is_active;
        }
    }

    /**
     * Validation rules are defined here (rather than via #[Validate] attributes)
     * because the uniqueness constraints on phone/email are conditional on
     * whether we're editing an existing employee — that can't be expressed
     * with a static attribute, and Livewire's rule-merging would otherwise
     * let a static attribute rule silently clobber this dynamic one.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => [
                'required', 'string', 'max:20',
                Rule::unique('employees', 'phone')->ignore($this->employee?->id),
            ],
            'email' => [
                'nullable', 'email', 'max:150',
                Rule::unique('employees', 'email')->ignore($this->employee?->id),
            ],
            'isActive' => ['boolean'],
        ];
    }

    public function save(EmployeeService $service): void
    {
        $data = $this->validate();

        $payload = [
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?: null,
            'is_active' => $data['isActive'],
        ];

        if ($this->employee?->exists) {
            $service->update($this->employee, $payload);
            session()->flash('success', __('تم تحديث الموظف'));
        } else {
            $service->create($payload);
            session()->flash('success', __('تم إنشاء الموظف وأُرسل الرقم عبر SMS'));
        }

        $this->redirectRoute('admin.employees.index');
    }

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.admin.employees.employee-form');
    }
}
