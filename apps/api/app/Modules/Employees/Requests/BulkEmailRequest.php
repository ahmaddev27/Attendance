<?php

declare(strict_types=1);

namespace App\Modules\Employees\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Bulk email payload for POST /api/admin/employees/bulk-email. The 500
 * recipient cap is a hard guard against an admin accidentally sending to
 * the whole org — picked to be well above any realistic department size
 * yet low enough that a stolen admin session can't blast tens of thousands
 * in one request.
 */
class BulkEmailRequest extends FormRequest
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
            'subject' => ['required', 'string', 'min:1', 'max:150'],
            'body' => ['required', 'string', 'min:1', 'max:10000'],
        ];
    }
}
