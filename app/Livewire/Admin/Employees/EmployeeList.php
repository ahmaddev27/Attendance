<?php

namespace App\Livewire\Admin\Employees;

use App\Models\Employee;
use App\Services\EmployeeService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class EmployeeList extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $status = 'all'; // all | active | inactive

    // Modal state
    public bool $showModal = false;

    public ?int $editingId = null;

    // Form fields
    public string $name = '';

    public string $phone = '';

    public ?string $email = null;

    public bool $isActive = true;

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $employee = Employee::findOrFail($id);

        $this->editingId = $employee->id;
        $this->name = $employee->name;
        $this->phone = $employee->phone;
        $this->email = $employee->email;
        $this->isActive = $employee->is_active;
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    /**
     * Validation rules are defined here (rather than via #[Validate] attributes)
     * because the uniqueness constraints on phone/email are conditional on
     * whether we're editing an existing employee — that can't be expressed
     * with a static attribute, and Livewire's rule-merging would otherwise
     * let a static attribute rule silently clobber this dynamic one.
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => [
                'required', 'string', 'max:20',
                Rule::unique('employees', 'phone')->ignore($this->editingId),
            ],
            'email' => [
                'nullable', 'email', 'max:150',
                Rule::unique('employees', 'email')->ignore($this->editingId),
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

        if ($this->editingId) {
            $employee = Employee::findOrFail($this->editingId);
            $service->update($employee, $payload);
            $this->dispatch('toast', message: 'تم تحديث بيانات الموظف', type: 'success');
        } else {
            $service->create($payload);
            $this->dispatch('toast', message: 'تم إنشاء الموظف وإرسال رقمه عبر SMS', type: 'success');
        }

        $this->closeModal();
    }

    protected function resetForm(): void
    {
        $this->reset(['name', 'phone', 'email', 'isActive']);
        $this->isActive = true;
        $this->resetErrorBag();
    }

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.admin.employees.employee-list', [
            'employees' => $this->employeesQuery()->paginate(15),
        ]);
    }

    private function employeesQuery()
    {
        return Employee::query()
            ->when($this->search !== '', function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', "%{$this->search}%")
                        ->orWhere('phone', 'like', "%{$this->search}%")
                        ->orWhere('employee_number', $this->search);
                });
            })
            ->when($this->status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($this->status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderByDesc('employee_number');
    }
}
