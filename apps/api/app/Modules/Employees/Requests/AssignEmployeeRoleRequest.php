<?php

declare(strict_types=1);

namespace App\Modules\Employees\Requests;

use App\Modules\Employees\Services\EmployeeRoleService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignEmployeeRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => [
                'required',
                'string',
                Rule::in([...EmployeeRoleService::ASSIGNABLE_ROLES, EmployeeRoleService::SUPER_ADMIN]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.required' => 'اختر الدور.',
            'role.in' => 'الدور المختار غير معروف.',
        ];
    }
}
