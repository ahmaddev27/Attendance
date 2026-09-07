<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkScheduleRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
            'min_hours_per_day' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'grace_late_minutes' => ['nullable', 'integer', 'min:0'],
            'grace_early_leave_minutes' => ['nullable', 'integer', 'min:0'],
            'workdays' => ['required', 'array', 'min:1'],
            'workdays.*' => ['integer', 'between:0,6'],
            'is_flexible' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
