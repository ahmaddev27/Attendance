<?php

declare(strict_types=1);

namespace App\Modules\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Unauthenticated: the OTP code carried in the body IS the credential.
 * The service verifies it against the hashed cache entry keyed on the
 * resolved user; a wrong or missing code returns the same generic error
 * as an unknown identifier.
 */
class ResetPasswordRequest extends FormRequest
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
            'identifier' => ['required', 'string', 'max:255'],
            // 6-digit numeric OTP. Kept as a string so leading zeros
            // aren't lost by JSON's int coercion, and so a client that
            // sends "012345" hashes to the same value the backend stored.
            'code' => ['required', 'string', 'digits:6'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    public function identifier(): string
    {
        return trim((string) $this->input('identifier'));
    }

    public function code(): string
    {
        return trim((string) $this->input('code'));
    }

    public function password(): string
    {
        return (string) $this->input('password');
    }
}
