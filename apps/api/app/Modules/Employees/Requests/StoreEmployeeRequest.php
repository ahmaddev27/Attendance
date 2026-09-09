<?php

declare(strict_types=1);

namespace App\Modules\Employees\Requests;

use App\Shared\Enums\EmployeeStatus;
use App\Shared\Enums\EmploymentType;
use App\Shared\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Note: `employee_number` is deliberately not accepted here — it is
     * always generated server-side by EmployeeService to guarantee
     * uniqueness and sequential, race-safe allocation.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150', 'unique:employees,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'work_schedule_id' => ['nullable', 'integer', 'exists:work_schedules,id'],
            'direct_manager_id' => ['nullable', 'integer', 'exists:employees,id'],
            'employment_type' => ['required', new Enum(EmploymentType::class)],
            'joining_date' => ['required', 'date'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', new Enum(Gender::class)],
            'avatar_path' => ['nullable', 'string'],
            'status' => ['sometimes', new Enum(EmployeeStatus::class)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
