<?php

declare(strict_types=1);

namespace App\Modules\Employees\Requests;

use App\Modules\Employees\Services\EmployeeFileService;
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
            'phone' => ['nullable', 'string', 'max:20', 'regex:'.StoreEmployeeRequest::PHONE_PATTERN],
            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            // Soft Company Scoping: EmployeeService re-derives this from
            // team.department.company whenever team_id changes; passing an
            // explicit value overrides that derivation.
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            // Partial update: if the caller sends work_schedule_id at all,
            // it must resolve to a real schedule. A null/empty value is
            // rejected — see StoreEmployeeRequest for the "why an employee
            // must always have a schedule" rationale.
            'work_schedule_id' => ['sometimes', 'required', 'integer', 'exists:work_schedules,id'],
            'direct_manager_id' => ['nullable', 'integer', 'exists:employees,id'],
            'employment_type' => ['sometimes', new Enum(EmploymentType::class)],
            'joining_date' => ['sometimes', 'date'],
            'birth_date' => ['nullable', 'date'],
            // One physical person = one national ID. On update we ignore the
            // employee's own current row so re-saving an unchanged value is
            // accepted; a collision with any OTHER employee row is rejected.
            'national_id' => [
                'nullable', 'string', 'max:50',
                Rule::unique('employees', 'national_id')->ignore($this->route('employee')),
            ],
            'national_id_image_path' => [
                'nullable', 'string', 'max:255',
                EmployeeFileService::pathValidationRule(),
            ],
            'employment_contract_path' => [
                'nullable', 'string', 'max:255',
                EmployeeFileService::pathValidationRule(),
            ],
            'gender' => ['nullable', new Enum(Gender::class)],
            'avatar_path' => ['nullable', 'string'],
            'status' => ['sometimes', new Enum(EmployeeStatus::class)],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['phone.regex' => StoreEmployeeRequest::PHONE_MESSAGE];
    }
}
