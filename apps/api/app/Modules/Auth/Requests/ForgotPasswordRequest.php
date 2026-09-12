<?php

declare(strict_types=1);

namespace App\Modules\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Unauthenticated: anyone may request a reset code — the service layer
 * is what decides whether the identifier resolves to a real user, and
 * the response is intentionally identical either way so the endpoint
 * never reveals whether an account exists.
 */
class ForgotPasswordRequest extends FormRequest
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
            'identifier' => ['required', 'string', 'max:255'],
        ];
    }

    public function identifier(): string
    {
        return trim((string) $this->input('identifier'));
    }
}
