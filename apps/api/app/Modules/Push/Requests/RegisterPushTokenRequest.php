<?php

declare(strict_types=1);

namespace App\Modules\Push\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /me/push-tokens` body. Every field is client-supplied except
 * `user_id`, which the controller sets from the authenticated request.
 *
 * `token` looks like `ExponentPushToken[xxxx]` for managed workflow or
 * a raw FCM/APNs string for bare — either shape is passed to Expo's
 * /send verbatim, so no format check here beyond "non-empty".
 */
class RegisterPushTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['required', Rule::in(['ios', 'android', 'web'])],
            'device_id' => ['nullable', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
