<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a public kiosk/mobile scan payload. No auth guard applies here
 * (the QR token + employee number together act as the credential) — see
 * FraudGuardService and QrTokenService for the trust checks layered on top.
 */
class ScanRequest extends FormRequest
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
            'employee_number' => ['required', 'integer', 'min:1'],
            'qr_token' => ['required', 'string', 'size:64'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    public function employeeNumber(): int
    {
        return (int) $this->validated('employee_number');
    }

    public function qrToken(): string
    {
        return (string) $this->validated('qr_token');
    }

    public function latitude(): ?float
    {
        $value = $this->validated('latitude');

        return $value === null ? null : (float) $value;
    }

    public function longitude(): ?float
    {
        $value = $this->validated('longitude');

        return $value === null ? null : (float) $value;
    }
}
