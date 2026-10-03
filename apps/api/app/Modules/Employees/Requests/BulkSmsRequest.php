<?php

declare(strict_types=1);

namespace App\Modules\Employees\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Bulk SMS payload for POST /api/admin/employees/bulk-sms. The 500
 * recipient cap mirrors BulkEmailRequest and is the primary foot-gun
 * guard: SMS is metered through MTC, so an accidentally-selected 10K-row
 * roster could run up the monthly bill in one click.
 *
 * The 500-char body cap covers 3 standard GSM-7 SMS segments (160 chars
 * each, minus concatenation headers). Longer bodies silently split at the
 * carrier anyway, so refusing them here keeps billing predictable.
 */
class BulkSmsRequest extends FormRequest
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
            'employee_ids' => ['required', 'array', 'min:1', 'max:500'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
            'body' => ['required', 'string', 'min:1', 'max:500'],
        ];
    }
}
