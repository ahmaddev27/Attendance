<?php

declare(strict_types=1);

namespace App\Modules\Organization\Requests;

use DateTimeZone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:200'],
            'timezone' => ['nullable', 'string', 'max:100', $this->timezoneRule()],
            'settings' => ['nullable', 'array'],
        ];
    }

    /**
     * Guard against free-form strings in `timezone`: only IANA zone names
     * recognised by PHP are accepted, otherwise date math downstream silently
     * falls back to UTC and attendance windows shift by hours.
     */
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
