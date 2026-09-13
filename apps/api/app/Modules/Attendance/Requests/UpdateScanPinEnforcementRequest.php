<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `force` is the admin's explicit second confirmation for switching PINs on
 * while some active employees still have none — without it the service
 * refuses so nobody is locked out of the kiosk by accident.
 */
class UpdateScanPinEnforcementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'required' => ['required', 'boolean'],
            'force' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required.required' => 'حدّد حالة تفعيل رمز الحضور.',
            'required.boolean' => 'قيمة تفعيل رمز الحضور غير صالحة.',
            'force.boolean' => 'قيمة تأكيد التفعيل غير صالحة.',
        ];
    }

    public function pinRequired(): bool
    {
        return (bool) $this->validated('required');
    }

    public function force(): bool
    {
        return (bool) $this->validated('force', false);
    }
}
