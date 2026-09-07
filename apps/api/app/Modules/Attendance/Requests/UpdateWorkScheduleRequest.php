<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkScheduleRequest extends FormRequest
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
            'timezone' => ['sometimes', 'string', 'max:64'],
            'check_in_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'check_out_time' => ['sometimes', 'nullable', 'date_format:H:i'],
            'min_hours_per_day' => ['sometimes', 'numeric', 'min:0', 'max:24'],
            'grace_late_minutes' => ['sometimes', 'integer', 'min:0'],
            'grace_early_leave_minutes' => ['sometimes', 'integer', 'min:0'],
            'workdays' => ['sometimes', 'array', 'min:1'],
            'workdays.*' => ['integer', 'between:0,6'],
            'is_flexible' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
