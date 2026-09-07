<?php

declare(strict_types=1);

namespace App\Modules\Leaves\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeaveTypeRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:100'],
            'code' => [
                'sometimes', 'string', 'max:30', 'alpha_dash',
                Rule::unique('leave_types', 'code')->ignore($this->route('leave_type')),
            ],
            'is_paid' => ['sometimes', 'boolean'],
            'is_balance_based' => ['sometimes', 'boolean'],
            'default_annual_entitlement' => ['sometimes', 'numeric', 'min:0', 'max:999.99'],
            'allow_negative_balance' => ['sometimes', 'boolean'],
            'requires_attachment' => ['sometimes', 'boolean'],
            'max_consecutive_days' => ['nullable', 'integer', 'min:1'],
            'min_notice_days' => ['sometimes', 'integer', 'min:0'],
            'color' => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer'],
        ];
    }
}
