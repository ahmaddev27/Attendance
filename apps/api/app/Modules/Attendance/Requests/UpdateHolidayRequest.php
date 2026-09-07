<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Requests;

use App\Shared\Enums\HolidayType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHolidayRequest extends FormRequest
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
            'date' => ['sometimes', 'date'],
            'name' => ['sometimes', 'string', 'max:200'],
            'type' => ['sometimes', Rule::enum(HolidayType::class)],
            'is_recurring' => ['sometimes', 'boolean'],
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
