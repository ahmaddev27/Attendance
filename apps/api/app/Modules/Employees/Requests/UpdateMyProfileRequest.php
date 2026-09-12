<?php

declare(strict_types=1);

namespace App\Modules\Employees\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Employee self-service profile update — the whitelist here is deliberately
 * narrower than {@see UpdateEmployeeRequest} so a non-admin session cannot
 * grant itself a raise, change teams, promote itself, or overwrite HR-owned
 * fields (first/last name, email, department_id, team_id, position_id,
 * direct_manager_id, work_schedule_id, status, employment_type,
 * joining_date, employee_number, avatar_path). Any of those in the payload
 * are silently dropped by validated() because they simply aren't in the
 * ruleset.
 */
class UpdateMyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route is behind auth:sanctum; the controller further asserts the
        // caller has a linked Employee row before touching anything.
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            // Digits, `+`, `-`, `(`, `)` and spaces only — enough for local
            // and international formats without opening the door to XSS
            // payloads that would land in an SMS body or an admin table
            // cell later.
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^[\d\s\+\-\(\)]+$/'],
        ];
    }
}
