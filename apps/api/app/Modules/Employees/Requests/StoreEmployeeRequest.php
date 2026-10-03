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

class StoreEmployeeRequest extends FormRequest
{
    /**
     * Digits with the separators people type (+, spaces, dashes, brackets).
     * SmsService turns it into the international form when sending.
     */
    public const PHONE_PATTERN = '/^\+?[\d\s\-().]{7,20}$/';

    public const PHONE_MESSAGE = 'رقم الهاتف غير صالح. اكتبه مثل 0599123456 أو +970599123456.';

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
            'phone' => ['nullable', 'string', 'max:20', 'regex:'.self::PHONE_PATTERN],
            'position_id' => ['nullable', 'integer', 'exists:positions,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            // When omitted, EmployeeService::create() derives company_id
            // from the chosen team's department. An explicit value lets
            // an admin pin an unassigned (team-less) hire to a company.
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            // A schedule is mandatory: the kiosk check-in flow classifies
            // late/early against it, and the monthly summary rolls up
            // expected working days from it. An employee without a
            // schedule silently breaks both — the FE Zod layer already
            // rejects it, mirror that on the server.
            'work_schedule_id' => ['required', 'integer', 'exists:work_schedules,id'],
            'direct_manager_id' => ['nullable', 'integer', 'exists:employees,id'],
            'employment_type' => ['required', new Enum(EmploymentType::class)],
            'joining_date' => ['required', 'date'],
            'birth_date' => ['nullable', 'date'],
            // One physical person = one national ID. Uniqueness lives at the
            // DB too; validate here so the admin sees a clean 422 field
            // error instead of a QueryException bubbling as a 500.
            'national_id' => [
                'nullable', 'string', 'max:50',
                Rule::unique('employees', 'national_id'),
            ],
            // Paths handed back by the upload endpoints — storage keys on
            // the private `local` disk. We refuse any value that isn't under
            // employee-files/, that references a non-existent file, or that
            // contains a path-traversal segment — otherwise an attacker
            // could write any string here and reference an unrelated file.
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
        return ['phone.regex' => self::PHONE_MESSAGE];
    }
}
