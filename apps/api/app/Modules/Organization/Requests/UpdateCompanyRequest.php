<?php

declare(strict_types=1);

namespace App\Modules\Organization\Requests;

use DateTimeZone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:100', $this->timezoneRule()],
            'settings' => ['sometimes', 'nullable', 'array'],
        ];
    }

    private function timezoneRule(): ValidationRule
    {
        return new class implements ValidationRule
        {
            public function validate(string $attribute, mixed $value, \Closure $fail): void
            {
                if ($value === null || $value === '') {
                    return;
                }

                if (! in_array($value, DateTimeZone::listIdentifiers(), true)) {
                    $fail('The :attribute must be a valid IANA timezone identifier.');
                }
            }
        };
    }
}
