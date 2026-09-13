<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Self-service PIN change. The current password is required so a hijacked,
 * still-open session cannot quietly set a PIN the attacker knows; the
 * service verifies it and rejects weak PINs.
 */
class UpdateMyScanPinRequest extends FormRequest
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
            'current_password' => ['required', 'string'],
            'pin' => ['required', 'digits:4', 'confirmed'],
            'pin_confirmation' => ['required'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.required' => 'الرجاء إدخال كلمة السر الحالية.',
            'pin.required' => 'الرجاء إدخال رمز الحضور الجديد.',
            'pin.digits' => 'رمز الحضور يجب أن يتكون من 4 أرقام.',
            'pin.confirmed' => 'تأكيد رمز الحضور لا يطابق الرمز الجديد.',
            'pin_confirmation.required' => 'الرجاء تأكيد رمز الحضور الجديد.',
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->user()?->employee === null) {
                    $validator->errors()->add('pin', 'حسابك غير مرتبط بملف موظف.');
                }
            },
        ];
    }

    public function currentPassword(): string
    {
        return (string) $this->validated('current_password');
    }

    public function pin(): string
    {
        return (string) $this->validated('pin');
    }
}
