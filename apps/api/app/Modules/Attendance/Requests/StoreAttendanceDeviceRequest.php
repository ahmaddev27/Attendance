<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceDeviceRequest extends FormRequest
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
            'qr_rotates_every_seconds' => ['nullable', 'integer', 'min:30', 'max:3600'],
            'allowed_lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:allowed_lng,allowed_radius_meters'],
            'allowed_lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:allowed_lat,allowed_radius_meters'],
            'allowed_radius_meters' => ['nullable', 'integer', 'min:1'],
            'ip_whitelist' => ['nullable', 'array'],
            'ip_whitelist.*' => ['string', 'max:64'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
