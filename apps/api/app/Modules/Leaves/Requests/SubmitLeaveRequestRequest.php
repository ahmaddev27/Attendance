<?php

declare(strict_types=1);

namespace App\Modules\Leaves\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared by both submission entry points:
 *  - POST /me/leaves (EmployeeLeavesController::store) — employee_id is
 *    ignored there; the target employee is always request()->user()->employee.
 *  - POST /leave-requests (LeaveRequestController::store) — an admin
 *    submitting on behalf of an employee, who additionally requires
 *    employee_id (enforced in the controller, not here, since this
 *    request class has no reliable way to know which route invoked it).
 *
 * attachment_path is a plain string rather than an uploaded file to match
 * the one other file-reference field in this codebase,
 * Employee::avatar_path — file upload handling isn't wired up anywhere
 * yet, so introducing it just for this one field would be inconsistent
 * with the rest of the API.
 */
class SubmitLeaveRequestRequest extends FormRequest
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
            'employee_id' => ['sometimes', 'integer', 'exists:employees,id'],
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'attachment_path' => ['nullable', 'string', 'max:255'],
        ];
    }
}
