<?php

declare(strict_types=1);

namespace App\Modules\Leaves\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Backs both POST /leave-requests/{leave_request}/approve and .../reject.
 * Which action is being performed is already unambiguous from the route
 * itself, so rejection_reason's requiredness is derived from the current
 * route's action name rather than trusting a client-supplied "action"
 * field that would just duplicate what the URL already says.
 */
class AdminLeaveActionRequest extends FormRequest
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
        $isReject = $this->route()?->getActionMethod() === 'reject';

        return [
            'rejection_reason' => [$isReject ? 'required' : 'nullable', 'string', 'max:1000'],
        ];
    }
}
