<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Trimmed variant of ScanRequest: the status probe doesn't record
 * anything, so it doesn't need latitude/longitude. Just the QR token
 * (device credential) and the employee number to look up today's row.
 */
class ScanStatusRequest extends FormRequest
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
}
