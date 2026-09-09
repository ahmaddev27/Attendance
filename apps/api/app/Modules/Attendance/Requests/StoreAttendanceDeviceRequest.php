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
            // min:0 — a value of 0 disables QR rotation entirely (see
            // AttendanceDevice::isTokenExpired) for the printed-poster
            // use case. Upper bound stays at 1h for the rotation case.
            'qr_rotates_every_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
            'allowed_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'allowed_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'allowed_radius_meters' => ['nullable', 'integer', 'min:1'],
            'ip_whitelist' => ['nullable', 'array'],
            'ip_whitelist.*' => ['string', 'max:64'],
            'enforce_geo' => ['nullable', 'boolean'],
            'enforce_ip' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
