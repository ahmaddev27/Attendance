<?php

declare(strict_types=1);

namespace App\Modules\Employees\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Self-service password change — requires knowledge of the current
 * password so an attacker who hijacks an open session cannot silently
 * rotate the victim out of their own account. `different:current_password`
 * blocks the "type the same value in both fields" no-op that would
 * otherwise satisfy the flow without actually rotating anything.
 */
class UpdateMyPasswordRequest extends FormRequest
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
            // Laravel's built-in `current_password` rule verifies the value
            // against the currently-authenticated user's hash — bcrypt
            // check runs server-side, so the plaintext never touches the
            // client-side comparison path.
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ];
    }
}
