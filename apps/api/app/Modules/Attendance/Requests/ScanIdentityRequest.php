<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Requests;

use App\Modules\Attendance\Services\ScanPinService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Identity rules shared by every public scan endpoint. No auth guard applies
 * here: a bearer token (mobile app) identifies the employee on its own, while
 * the kiosk page has to type the employee number — plus the scan PIN once
 * enforcement is on. ScanIdentityService performs the actual verification.
 */
abstract class ScanIdentityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(ScanPinService $scanPins): array
    {
        $viaBearerToken = $this->bearerToken() !== null;

        return [
            'employee_number' => [$viaBearerToken ? 'nullable' : 'required', 'integer', 'min:1'],
            'qr_token' => ['required', 'string', 'size:64'],
            'pin' => ! $viaBearerToken && $scanPins->isRequired()
                ? ['required', 'digits:4']
                : ['exclude'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pin.required' => 'أدخل رمز الحضور المكوّن من 4 أرقام.',
            'pin.digits' => 'رمز الحضور يجب أن يتكون من 4 أرقام.',
        ];
    }

    public function employeeNumber(): ?int
    {
        $value = $this->validated('employee_number');

        return $value === null ? null : (int) $value;
    }

    public function qrToken(): string
    {
        return (string) $this->validated('qr_token');
    }

    public function pin(): ?string
    {
        $value = $this->validated('pin');

        return $value === null ? null : (string) $value;
    }
}
