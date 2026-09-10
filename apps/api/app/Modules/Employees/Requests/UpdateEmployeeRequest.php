<?php

declare(strict_types=1);

namespace App\Modules\Employees\Requests;

use App\Shared\Enums\EmployeeStatus;
use App\Shared\Enums\EmploymentType;
use App\Shared\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'email' => [
                'nullable', 'email', 'max:150',
                Rule::unique('employees', 'email')->ignore($this->route('employee')),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            // Partial update: if the caller sends work_schedule_id at all,
            // it must resolve to a real schedule. A null/empty value is
            // rejected — see StoreEmployeeRequest for the "why an employee
            // must always have a schedule" rationale.
            'work_schedule_id' => ['sometimes', 'required', 'integer', 'exists:work_schedules,id'],
            'direct_manager_id' => ['nullable', 'integer', 'exists:employees,id'],
            'employment_type' => ['sometimes', new Enum(EmploymentType::class)],
            'joining_date' => ['sometimes', 'date'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', new Enum(Gender::class)],
            'avatar_path' => ['nullable', 'string'],
            'status' => ['sometimes', new Enum(EmployeeStatus::class)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
