<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectCandidateApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-candidates') === true;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Reason is required so the rejection is auditable — a blank
            // reason is what the admin would type to hide the decision.
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }
}
