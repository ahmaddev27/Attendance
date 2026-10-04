<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Requests;

use App\Shared\Enums\AttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin manual correction of an attendance row. Permissions are enforced
 * on the route (`permission:view-all-attendance` plus a stricter inner
 * check inside the service). Every field is optional — the service only
 * touches the ones that arrive and re-runs the hours engine afterwards.
 */
class UpdateAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view-all-attendance') === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // `sometimes|nullable` lets an admin clear check_out_at (reopen
            // the day) while still rejecting an unknown field name.
            'check_in_at' => ['sometimes', 'nullable', 'date'],
            'check_out_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:check_in_at'],
            'status' => ['sometimes', Rule::in(array_column(AttendanceStatus::cases(), 'value'))],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'check_out_at.after_or_equal' => 'لا يمكن أن يسبق وقت الانصراف وقت الحضور.',
        ];
    }
}
