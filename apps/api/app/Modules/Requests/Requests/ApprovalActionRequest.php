<?php

declare(strict_types=1);

namespace App\Modules\Requests\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared by the four decision endpoints (approve/reject/return/forward) —
 * which fields are required differs per action, so rules() branches on
 * the controller method the current route actually invokes rather than a
 * client-supplied "action" field.
 */
class ApprovalActionRequest extends FormRequest
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
        $action = $this->route()?->getActionMethod();

        return match ($action) {
            'reject', 'return' => [
                'comment' => ['required', 'string', 'max:2000'],
            ],
            'forward' => [
                'comment' => ['nullable', 'string', 'max:2000'],
                'forwarded_to_id' => ['required', 'integer', 'exists:employees,id'],
            ],
            default => [
                'comment' => ['nullable', 'string', 'max:2000'],
            ],
        };
    }
}
