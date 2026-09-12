<?php

declare(strict_types=1);

namespace App\Modules\Leaves\Requests;

use App\Modules\Leaves\Services\LeaveAttachmentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Storage;

/**
 * Shared by both submission entry points:
 *  - POST /me/leaves (EmployeeLeavesController::store) — employee_id is
 *    ignored there; the target employee is always request()->user()->employee.
 *  - POST /leave-requests (LeaveRequestController::store) — an admin
 *    submitting on behalf of an employee, who additionally requires
 *    employee_id (enforced in the controller, not here, since this
 *    request class has no reliable way to know which route invoked it).
 *
 * `attachment_path` is validated tightly to close an IDOR: an authenticated
 * user could otherwise submit any string and reference another user's
 * (or the system's) file on disk. We require:
 *  - the path lives under the caller's own upload prefix
 *    (leave-attachments/{caller_user_id}/…). Admins acting on behalf of
 *    an employee upload via the same private-disk path scheme rooted at
 *    THEIR own user id, so the same rule applies to both surfaces.
 *  - no path traversal segments ("..").
 *  - the file actually exists on the private disk.
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
            'attachment_path' => [
                'nullable',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || $value === '') {
                        return;
                    }

                    $userId = $this->user()?->id;

                    if ($userId === null) {
                        $fail('غير مسموح — يجب تسجيل الدخول لإرفاق ملف.');

                        return;
                    }

                    // Path traversal — block before any string prefix
                    // comparison so "leave-attachments/{me}/../{other}/x"
                    // can't sneak past.
                    if (str_contains($value, '..')) {
                        $fail('غير مسموح — مسار المرفق غير صالح.');

                        return;
                    }

                    $expectedPrefix = LeaveAttachmentService::directoryFor((int) $userId);

                    if (! str_starts_with($value, $expectedPrefix)) {
                        $fail('غير مسموح — مسار المرفق لا يخصك.');

                        return;
                    }

                    if (! Storage::disk(LeaveAttachmentService::DISK)->exists($value)) {
                        $fail('المرفق غير موجود.');
                    }
                },
            ],
        ];
    }
}
