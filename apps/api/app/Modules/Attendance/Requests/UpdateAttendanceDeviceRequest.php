<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAttendanceDeviceRequest extends FormRequest
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
            'qr_rotates_every_seconds' => ['sometimes', 'integer', 'min:30', 'max:3600'],
            'allowed_lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'allowed_lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'allowed_radius_meters' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'ip_whitelist' => ['sometimes', 'nullable', 'array'],
            'ip_whitelist.*' => ['string', 'max:64'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
