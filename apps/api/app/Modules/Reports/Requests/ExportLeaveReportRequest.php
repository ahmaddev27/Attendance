<?php

declare(strict_types=1);

namespace App\Modules\Reports\Requests;

use App\Shared\Enums\LeaveStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportLeaveReportRequest extends FormRequest
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
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'leave_type_id' => ['nullable', 'integer', 'exists:leave_types,id'],
            'status' => ['nullable', Rule::in(array_map(fn (LeaveStatus $status) => $status->value, LeaveStatus::cases()))],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ];
    }
}
