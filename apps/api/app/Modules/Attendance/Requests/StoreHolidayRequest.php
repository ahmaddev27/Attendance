<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Requests;

use App\Shared\Enums\HolidayType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHolidayRequest extends FormRequest
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
            'date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:200'],
            'type' => ['required', Rule::enum(HolidayType::class)],
            'is_recurring' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string'],
        ];
    }
}
