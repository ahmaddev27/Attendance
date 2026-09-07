<?php

declare(strict_types=1);

namespace App\Modules\Leaves\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdjustBalanceRequest extends FormRequest
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
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            // Positive to grant days, negative to deduct — applied to the
            // balance's `entitlement` figure (see LeaveBalanceService::adjust()).
            'delta' => ['required', 'numeric'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
